<?php
/**
 * Image Weaver - Pattern-based Image Generation
 *
 * Provides intelligent image composition using patterns from the Aevov network.
 * Uses vector similarity search to find relevant patterns for image generation.
 *
 * @package AevovImageEngine
 * @since 1.0.0
 */

namespace AevovImageEngine;

use AevovEmbeddingEngine\EmbeddingManager;
use AevovChunkRegistry\ChunkRegistry;
use AevovChunkRegistry\AevovChunk;

require_once dirname(__FILE__) . '/../../../aevov-embedding-engine/includes/class-embedding-manager.php';
require_once dirname(__FILE__) . '/../../../aevov-chunk-registry/includes/class-chunk-registry.php';

class ImageWeaver {

    /**
     * Database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Patterns table name
     *
     * @var string
     */
    private $patterns_table;

    /**
     * Cache group
     *
     * @var string
     */
    private $cache_group = 'aevov_image_weaver';

    /**
     * Embedding manager instance
     *
     * @var EmbeddingManager
     */
    private $embedding_manager;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->patterns_table = $wpdb->prefix . 'aevov_image_patterns';
        $this->embedding_manager = new EmbeddingManager();

        add_action('init', [$this, 'create_tables'], 5);
    }

    /**
     * Create database tables for image patterns
     *
     * @return void
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->patterns_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pattern_id VARCHAR(64) NOT NULL UNIQUE,
            pattern_type VARCHAR(50) NOT NULL DEFAULT 'image',
            category VARCHAR(100) NULL,
            tags TEXT NULL,
            embedding LONGTEXT NULL,
            style_embedding LONGTEXT NULL,
            resolution VARCHAR(50) NULL,
            aspect_ratio VARCHAR(20) NULL,
            color_palette TEXT NULL,
            cubbit_key VARCHAR(255) NULL,
            metadata LONGTEXT NULL,
            usage_count INT UNSIGNED DEFAULT 0,
            quality_score DECIMAL(5,4) DEFAULT 0.5000,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX pattern_type (pattern_type),
            INDEX category (category),
            INDEX status (status),
            INDEX quality_score (quality_score),
            INDEX usage_count (usage_count)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get pattern IDs based on input parameters
     *
     * @param array $params Query parameters including prompt
     * @return array Pattern IDs
     */
    public function get_pattern_ids($params) {
        $prompt = $params['prompt'] ?? '';
        $num_patterns = isset($params['num_patterns']) ? intval($params['num_patterns']) : 5;
        $category = $params['category'] ?? null;
        $style = $params['style'] ?? null;
        $resolution = $params['resolution'] ?? null;

        // Check cache first
        $cache_key = 'patterns_' . md5(serialize($params));
        $cached = wp_cache_get($cache_key, $this->cache_group);
        if ($cached !== false) {
            return $cached;
        }

        // Get embedding for the prompt
        $embedding = $this->get_embedding($prompt);

        // Find similar patterns using vector similarity
        $patterns = $this->find_similar_patterns($embedding, $num_patterns, [
            'category' => $category,
            'style' => $style,
            'resolution' => $resolution
        ]);

        // Extract pattern IDs
        $pattern_ids = wp_list_pluck($patterns, 'pattern_id');

        // If we don't have enough patterns, try the chunk registry
        if (count($pattern_ids) < $num_patterns) {
            $chunk_patterns = $this->find_patterns_from_chunks($prompt, $num_patterns - count($pattern_ids));
            $pattern_ids = array_merge($pattern_ids, $chunk_patterns);
        }

        // Still not enough? Generate procedural patterns
        if (count($pattern_ids) < $num_patterns) {
            $procedural_count = $num_patterns - count($pattern_ids);
            $procedural_patterns = $this->generate_procedural_patterns($params, $procedural_count);
            $pattern_ids = array_merge($pattern_ids, $procedural_patterns);
        }

        // Cache the result
        wp_cache_set($cache_key, $pattern_ids, $this->cache_group, 300);

        // Update usage statistics
        $this->update_pattern_usage($pattern_ids);

        return $pattern_ids;
    }

    /**
     * Get embedding vector for a prompt
     *
     * @param string $prompt Text prompt
     * @return array Embedding vector
     */
    private function get_embedding($prompt) {
        try {
            $result = $this->embedding_manager->embed($prompt);
            if (isset($result['metadata']['vector'])) {
                return $result['metadata']['vector'];
            }
            return $this->generate_fallback_embedding($prompt);
        } catch (\Exception $e) {
            error_log('[ImageWeaver] Embedding error: ' . $e->getMessage());
            return $this->generate_fallback_embedding($prompt);
        }
    }

    /**
     * Generate a fallback embedding when the embedding engine is unavailable
     *
     * @param string $text Input text
     * @return array Simple hash-based embedding
     */
    private function generate_fallback_embedding($text) {
        $embedding = [];
        $normalized = strtolower(trim($text));
        $words = preg_split('/\s+/', $normalized);

        // Create a 128-dimensional embedding based on character and word features
        for ($i = 0; $i < 128; $i++) {
            $value = 0;
            foreach ($words as $word) {
                $hash = crc32($word . $i);
                $value += (($hash % 1000) / 1000.0) - 0.5;
            }
            $embedding[] = $value / max(count($words), 1);
        }

        // Normalize the embedding
        $magnitude = sqrt(array_sum(array_map(function($v) { return $v * $v; }, $embedding)));
        if ($magnitude > 0) {
            $embedding = array_map(function($v) use ($magnitude) { return $v / $magnitude; }, $embedding);
        }

        return $embedding;
    }

    /**
     * Find similar patterns using vector similarity search
     *
     * @param array $query_embedding Query embedding vector
     * @param int $limit Maximum number of results
     * @param array $filters Additional filters
     * @return array Similar patterns with scores
     */
    private function find_similar_patterns($query_embedding, $limit, $filters = []) {
        $sql = "SELECT * FROM {$this->patterns_table} WHERE status = 'active'";
        $prepare_args = [];

        // Apply filters
        if (!empty($filters['category'])) {
            $sql .= " AND category = %s";
            $prepare_args[] = $filters['category'];
        }

        if (!empty($filters['resolution'])) {
            $sql .= " AND resolution = %s";
            $prepare_args[] = $filters['resolution'];
        }

        // Limit to patterns with embeddings
        $sql .= " AND embedding IS NOT NULL LIMIT 500";

        if (!empty($prepare_args)) {
            $query = $this->wpdb->prepare($sql, $prepare_args);
        } else {
            $query = $sql;
        }

        $patterns = $this->wpdb->get_results($query);

        if (empty($patterns)) {
            return [];
        }

        // Calculate cosine similarity for each pattern
        $scored_patterns = [];
        foreach ($patterns as $pattern) {
            $pattern_embedding = json_decode($pattern->embedding, true);
            if (!is_array($pattern_embedding) || empty($pattern_embedding)) {
                continue;
            }

            $similarity = $this->cosine_similarity($query_embedding, $pattern_embedding);

            // Apply style bonus if style filter is set
            if (!empty($filters['style']) && !empty($pattern->style_embedding)) {
                $style_embedding = json_decode($pattern->style_embedding, true);
                $style_query = $this->get_embedding($filters['style']);
                $style_similarity = $this->cosine_similarity($style_query, $style_embedding);
                $similarity = ($similarity * 0.7) + ($style_similarity * 0.3);
            }

            // Boost by quality score
            $similarity *= (1 + ($pattern->quality_score * 0.2));

            $scored_patterns[] = [
                'pattern_id' => $pattern->pattern_id,
                'score' => $similarity,
                'category' => $pattern->category,
                'metadata' => json_decode($pattern->metadata, true)
            ];
        }

        // Sort by similarity score
        usort($scored_patterns, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($scored_patterns, 0, $limit);
    }

    /**
     * Find patterns from the chunk registry
     *
     * @param string $prompt Query prompt
     * @param int $limit Maximum number of results
     * @return array Pattern IDs
     */
    private function find_patterns_from_chunks($prompt, $limit) {
        try {
            $chunk_registry = new ChunkRegistry();

            // Create a temporary chunk-like object for similarity search
            $query_chunk = new AevovChunk(
                'query_' . uniqid(),
                'image',
                '',
                ['description' => $prompt, 'type' => 'image_query'],
                []
            );

            $similar_chunks = $chunk_registry->find_similar_chunks($query_chunk, $limit);

            $pattern_ids = [];
            foreach ($similar_chunks as $result) {
                if ($result['chunk'] && $result['chunk']->type === 'image') {
                    $pattern_ids[] = $result['chunk']->id;
                }
            }

            return $pattern_ids;
        } catch (\Exception $e) {
            error_log('[ImageWeaver] Chunk registry error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate procedural patterns when not enough stored patterns exist
     *
     * @param array $params Generation parameters
     * @param int $count Number of patterns to generate
     * @return array Generated pattern IDs
     */
    private function generate_procedural_patterns($params, $count) {
        $patterns = [];
        $prompt = $params['prompt'] ?? 'abstract';
        $style = $params['style'] ?? 'default';

        // Generate pattern IDs based on prompt characteristics
        $seed = crc32($prompt . $style);

        for ($i = 0; $i < $count; $i++) {
            $pattern_seed = $seed + $i;
            $pattern_id = sprintf(
                'proc_%s_%s_%08x',
                $this->extract_category($prompt),
                $style,
                $pattern_seed
            );

            $patterns[] = $pattern_id;

            // Register the procedural pattern for future use
            $this->register_procedural_pattern($pattern_id, $params, $pattern_seed);
        }

        return $patterns;
    }

    /**
     * Extract a category from the prompt
     *
     * @param string $prompt Input prompt
     * @return string Category identifier
     */
    private function extract_category($prompt) {
        $categories = [
            'landscape' => ['landscape', 'mountain', 'ocean', 'forest', 'sky', 'nature'],
            'portrait' => ['face', 'person', 'portrait', 'character', 'human'],
            'abstract' => ['abstract', 'pattern', 'geometric', 'design', 'art'],
            'architecture' => ['building', 'city', 'structure', 'architecture', 'interior'],
            'object' => ['product', 'object', 'item', 'thing', 'device']
        ];

        $prompt_lower = strtolower($prompt);

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($prompt_lower, $keyword) !== false) {
                    return $category;
                }
            }
        }

        return 'general';
    }

    /**
     * Register a procedurally generated pattern
     *
     * @param string $pattern_id Pattern ID
     * @param array $params Generation parameters
     * @param int $seed Random seed used
     * @return void
     */
    private function register_procedural_pattern($pattern_id, $params, $seed) {
        $embedding = $this->get_embedding($params['prompt'] ?? '');

        $this->wpdb->replace(
            $this->patterns_table,
            [
                'pattern_id' => $pattern_id,
                'pattern_type' => 'procedural',
                'category' => $this->extract_category($params['prompt'] ?? ''),
                'tags' => json_encode(explode(' ', strtolower($params['prompt'] ?? ''))),
                'embedding' => json_encode($embedding),
                'resolution' => $params['resolution'] ?? '1024x1024',
                'metadata' => json_encode([
                    'prompt' => $params['prompt'] ?? '',
                    'style' => $params['style'] ?? 'default',
                    'seed' => $seed,
                    'generated_at' => current_time('mysql')
                ]),
                'status' => 'active'
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @param array $vec1 First vector
     * @param array $vec2 Second vector
     * @return float Similarity score (0-1)
     */
    private function cosine_similarity(array $vec1, array $vec2): float {
        $dot_product = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;

        // Handle dimension mismatch by using minimum length
        $count = min(count($vec1), count($vec2));
        if ($count === 0) {
            return 0.0;
        }

        for ($i = 0; $i < $count; $i++) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        if ($norm1 == 0.0 || $norm2 == 0.0) {
            return 0.0;
        }

        return $dot_product / (sqrt($norm1) * sqrt($norm2));
    }

    /**
     * Update usage statistics for patterns
     *
     * @param array $pattern_ids Pattern IDs to update
     * @return void
     */
    private function update_pattern_usage($pattern_ids) {
        if (empty($pattern_ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($pattern_ids), '%s'));
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->patterns_table}
             SET usage_count = usage_count + 1, updated_at = NOW()
             WHERE pattern_id IN ($placeholders)",
            $pattern_ids
        ));
    }

    /**
     * Register a new pattern
     *
     * @param array $data Pattern data
     * @return string|WP_Error Pattern ID or error
     */
    public function register_pattern($data) {
        $pattern_id = $data['pattern_id'] ?? wp_generate_uuid4();

        // Generate embeddings if not provided
        if (empty($data['embedding']) && !empty($data['description'])) {
            $data['embedding'] = json_encode($this->get_embedding($data['description']));
        }

        $result = $this->wpdb->insert(
            $this->patterns_table,
            [
                'pattern_id' => $pattern_id,
                'pattern_type' => sanitize_key($data['type'] ?? 'image'),
                'category' => sanitize_text_field($data['category'] ?? ''),
                'tags' => json_encode($data['tags'] ?? []),
                'embedding' => $data['embedding'] ?? null,
                'style_embedding' => $data['style_embedding'] ?? null,
                'resolution' => sanitize_text_field($data['resolution'] ?? ''),
                'aspect_ratio' => sanitize_text_field($data['aspect_ratio'] ?? ''),
                'color_palette' => json_encode($data['color_palette'] ?? []),
                'cubbit_key' => sanitize_text_field($data['cubbit_key'] ?? ''),
                'metadata' => json_encode($data['metadata'] ?? []),
                'quality_score' => floatval($data['quality_score'] ?? 0.5),
                'status' => 'active'
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s']
        );

        if ($result === false) {
            return new \WP_Error('pattern_registration_failed', 'Failed to register pattern');
        }

        do_action('aevov_image_pattern_registered', $pattern_id, $data);

        return $pattern_id;
    }

    /**
     * Get pattern by ID
     *
     * @param string $pattern_id Pattern ID
     * @return object|null Pattern object
     */
    public function get_pattern($pattern_id) {
        $pattern = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->patterns_table} WHERE pattern_id = %s",
            $pattern_id
        ));

        if ($pattern) {
            $pattern->tags = json_decode($pattern->tags, true);
            $pattern->embedding = json_decode($pattern->embedding, true);
            $pattern->color_palette = json_decode($pattern->color_palette, true);
            $pattern->metadata = json_decode($pattern->metadata, true);
        }

        return $pattern;
    }

    /**
     * Update pattern quality score based on feedback
     *
     * @param string $pattern_id Pattern ID
     * @param float $feedback Feedback score (-1 to 1)
     * @return bool Success status
     */
    public function update_quality_score($pattern_id, $feedback) {
        $pattern = $this->get_pattern($pattern_id);
        if (!$pattern) {
            return false;
        }

        // Exponential moving average for quality score
        $alpha = 0.1;
        $new_score = ($pattern->quality_score * (1 - $alpha)) + (($feedback + 1) / 2 * $alpha);
        $new_score = max(0, min(1, $new_score)); // Clamp to 0-1

        $result = $this->wpdb->update(
            $this->patterns_table,
            ['quality_score' => $new_score],
            ['pattern_id' => $pattern_id],
            ['%f'],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Get statistics about stored patterns
     *
     * @return array Statistics
     */
    public function get_statistics() {
        $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->patterns_table}");
        $active = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->patterns_table} WHERE status = 'active'");
        $procedural = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->patterns_table} WHERE pattern_type = 'procedural'");

        $categories = $this->wpdb->get_results(
            "SELECT category, COUNT(*) as count FROM {$this->patterns_table}
             WHERE status = 'active' GROUP BY category ORDER BY count DESC",
            ARRAY_A
        );

        $avg_quality = $this->wpdb->get_var(
            "SELECT AVG(quality_score) FROM {$this->patterns_table} WHERE status = 'active'"
        );

        return [
            'total_patterns' => intval($total),
            'active_patterns' => intval($active),
            'procedural_patterns' => intval($procedural),
            'categories' => $categories,
            'average_quality_score' => floatval($avg_quality)
        ];
    }
}
