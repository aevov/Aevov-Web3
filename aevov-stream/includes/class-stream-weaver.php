<?php
/**
 * Stream Weaver - Pattern-based stream generation and management
 *
 * Provides intelligent stream composition using patterns from the Aevov network.
 *
 * @package AevovStream
 * @since 1.0.0
 */

namespace AevovStream;

if (!defined('ABSPATH')) {
    exit;
}

class StreamWeaver {

    /**
     * Database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Streams table name
     *
     * @var string
     */
    private $streams_table;

    /**
     * Stream patterns table name
     *
     * @var string
     */
    private $patterns_table;

    /**
     * Cache group
     *
     * @var string
     */
    private $cache_group = 'aevov_stream';

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->streams_table = $wpdb->prefix . 'aevov_streams';
        $this->patterns_table = $wpdb->prefix . 'aevov_stream_patterns';

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks() {
        add_action('init', [$this, 'create_tables']);
        add_action('aevov_stream_cleanup', [$this, 'cleanup_expired_streams']);
    }

    /**
     * Create database tables
     *
     * @return void
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql_streams = "CREATE TABLE IF NOT EXISTS {$this->streams_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            stream_id VARCHAR(64) NOT NULL UNIQUE,
            user_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            stream_type VARCHAR(50) NOT NULL DEFAULT 'sequential',
            config LONGTEXT NULL,
            metadata LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            INDEX user_id (user_id),
            INDEX status (status),
            INDEX stream_type (stream_type)
        ) {$charset_collate};";

        $sql_patterns = "CREATE TABLE IF NOT EXISTS {$this->patterns_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            stream_id VARCHAR(64) NOT NULL,
            pattern_id VARCHAR(64) NOT NULL,
            position INT UNSIGNED NOT NULL DEFAULT 0,
            duration INT UNSIGNED NULL,
            transition_type VARCHAR(50) DEFAULT 'fade',
            config LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX stream_id (stream_id),
            INDEX pattern_id (pattern_id),
            INDEX position (position)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_streams);
        dbDelta($sql_patterns);
    }

    /**
     * Create a new stream
     *
     * @param array $params Stream parameters
     * @return string|WP_Error Stream ID or error
     */
    public function create_stream($params) {
        $stream_id = wp_generate_uuid4();

        $result = $this->wpdb->insert(
            $this->streams_table,
            [
                'stream_id' => $stream_id,
                'user_id' => get_current_user_id(),
                'title' => sanitize_text_field($params['title'] ?? 'Untitled Stream'),
                'description' => sanitize_textarea_field($params['description'] ?? ''),
                'status' => 'active',
                'stream_type' => sanitize_key($params['type'] ?? 'sequential'),
                'config' => json_encode($params['config'] ?? []),
                'metadata' => json_encode($params['metadata'] ?? []),
                'expires_at' => isset($params['expires_in'])
                    ? gmdate('Y-m-d H:i:s', time() + intval($params['expires_in']))
                    : null
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return new \WP_Error('stream_creation_failed', 'Failed to create stream');
        }

        do_action('aevov_stream_created', $stream_id, $params);

        return $stream_id;
    }

    /**
     * Get pattern IDs for a stream with intelligent sequencing
     *
     * @param array $params Query parameters
     * @return array Pattern IDs
     */
    public function get_pattern_ids($params) {
        $stream_id = $params['stream_id'] ?? null;

        // If we have a stream ID, get patterns from database
        if ($stream_id) {
            $patterns = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT pattern_id FROM {$this->patterns_table}
                 WHERE stream_id = %s
                 ORDER BY position ASC",
                $stream_id
            ));

            if (!empty($patterns)) {
                return $patterns;
            }
        }

        // Generate pattern sequence based on parameters
        $num_patterns = isset($params['num_patterns']) ? intval($params['num_patterns']) : 5;
        $strategy = $params['strategy'] ?? 'sequential';

        switch ($strategy) {
            case 'random':
                return $this->get_random_patterns($num_patterns, $params);

            case 'similar':
                return $this->get_similar_patterns($params['seed_pattern'] ?? null, $num_patterns);

            case 'trending':
                return $this->get_trending_patterns($num_patterns);

            case 'personalized':
                return $this->get_personalized_patterns(get_current_user_id(), $num_patterns);

            case 'sequential':
            default:
                return $this->get_sequential_patterns($params);
        }
    }

    /**
     * Get random patterns
     *
     * @param int $count Number of patterns
     * @param array $params Filter parameters
     * @return array Pattern IDs
     */
    private function get_random_patterns($count, $params = []) {
        $aps_table = $this->wpdb->prefix . 'aps_patterns';

        $sql = "SELECT pattern_id FROM {$aps_table} WHERE status = 'active'";
        $prepare_args = [];

        if (!empty($params['category'])) {
            $sql .= " AND category = %s";
            $prepare_args[] = $params['category'];
        }

        $sql .= " ORDER BY RAND() LIMIT %d";
        $prepare_args[] = $count;

        if (!empty($prepare_args)) {
            $sql = $this->wpdb->prepare($sql, $prepare_args);
        }

        $patterns = $this->wpdb->get_col($sql);

        return !empty($patterns) ? $patterns : $this->generate_fallback_patterns($count);
    }

    /**
     * Get similar patterns based on a seed pattern
     *
     * @param string|null $seed_pattern Seed pattern ID
     * @param int $count Number of patterns
     * @return array Pattern IDs
     */
    private function get_similar_patterns($seed_pattern, $count) {
        if (!$seed_pattern) {
            return $this->get_random_patterns($count);
        }

        // Get seed pattern's embedding
        $bloom_tensors_table = $this->wpdb->prefix . 'aps_bloom_tensors';

        $seed_embedding = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT embedding FROM {$bloom_tensors_table} WHERE pattern_id = %s",
            $seed_pattern
        ));

        if (!$seed_embedding) {
            return $this->get_random_patterns($count);
        }

        // Find patterns with similar embeddings (cosine similarity)
        // This is a simplified version - in production, use vector database
        $similar = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT pattern_id FROM {$bloom_tensors_table}
             WHERE pattern_id != %s
             ORDER BY ABS(LENGTH(embedding) - LENGTH(%s))
             LIMIT %d",
            $seed_pattern,
            $seed_embedding,
            $count
        ));

        return !empty($similar) ? $similar : $this->get_random_patterns($count);
    }

    /**
     * Get trending patterns
     *
     * @param int $count Number of patterns
     * @return array Pattern IDs
     */
    private function get_trending_patterns($count) {
        $aps_table = $this->wpdb->prefix . 'aps_patterns';

        $patterns = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT pattern_id FROM {$aps_table}
             WHERE status = 'active'
             AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY usage_count DESC
             LIMIT %d",
            $count
        ));

        return !empty($patterns) ? $patterns : $this->get_random_patterns($count);
    }

    /**
     * Get personalized patterns for a user
     *
     * @param int $user_id User ID
     * @param int $count Number of patterns
     * @return array Pattern IDs
     */
    private function get_personalized_patterns($user_id, $count) {
        if (!$user_id) {
            return $this->get_trending_patterns($count);
        }

        // Get user's recently viewed/used patterns
        $history_table = $this->wpdb->prefix . 'aevov_user_pattern_history';

        $user_patterns = $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT DISTINCT pattern_id FROM {$history_table}
             WHERE user_id = %d
             ORDER BY viewed_at DESC
             LIMIT 10",
            $user_id
        ));

        // Get similar patterns to user's history
        $recommendations = [];
        foreach ($user_patterns as $pattern) {
            $similar = $this->get_similar_patterns($pattern, 2);
            $recommendations = array_merge($recommendations, $similar);
        }

        // Remove duplicates and limit
        $recommendations = array_unique($recommendations);
        $recommendations = array_slice($recommendations, 0, $count);

        return !empty($recommendations) ? $recommendations : $this->get_trending_patterns($count);
    }

    /**
     * Get sequential patterns
     *
     * @param array $params Parameters
     * @return array Pattern IDs
     */
    private function get_sequential_patterns($params) {
        $num_patterns = isset($params['num_patterns']) ? intval($params['num_patterns']) : 5;
        $start_id = isset($params['start_id']) ? intval($params['start_id']) : 1;
        $step = isset($params['step']) ? intval($params['step']) : 1;

        $pattern_ids = [];
        for ($i = 0; $i < $num_patterns; $i++) {
            $pattern_ids[] = 'pattern_' . ($start_id + ($i * $step));
        }

        return $pattern_ids;
    }

    /**
     * Generate fallback patterns when database is empty
     *
     * @param int $count Number of patterns
     * @return array Pattern IDs
     */
    private function generate_fallback_patterns($count) {
        $patterns = [];
        for ($i = 1; $i <= $count; $i++) {
            $patterns[] = 'fallback_pattern_' . $i;
        }
        return $patterns;
    }

    /**
     * Add patterns to a stream
     *
     * @param string $stream_id Stream ID
     * @param array $patterns Pattern configurations
     * @return bool Success status
     */
    public function add_patterns_to_stream($stream_id, $patterns) {
        $position = 0;

        foreach ($patterns as $pattern) {
            $this->wpdb->insert(
                $this->patterns_table,
                [
                    'stream_id' => $stream_id,
                    'pattern_id' => $pattern['id'],
                    'position' => $position++,
                    'duration' => $pattern['duration'] ?? null,
                    'transition_type' => $pattern['transition'] ?? 'fade',
                    'config' => json_encode($pattern['config'] ?? [])
                ],
                ['%s', '%s', '%d', '%d', '%s', '%s']
            );
        }

        wp_cache_delete($stream_id, $this->cache_group);

        return true;
    }

    /**
     * Get stream by ID
     *
     * @param string $stream_id Stream ID
     * @return object|null Stream object
     */
    public function get_stream($stream_id) {
        $cached = wp_cache_get($stream_id, $this->cache_group);
        if ($cached !== false) {
            return $cached;
        }

        $stream = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->streams_table} WHERE stream_id = %s",
            $stream_id
        ));

        if ($stream) {
            $stream->config = json_decode($stream->config, true);
            $stream->metadata = json_decode($stream->metadata, true);
            $stream->patterns = $this->get_stream_patterns($stream_id);

            wp_cache_set($stream_id, $stream, $this->cache_group, 300);
        }

        return $stream;
    }

    /**
     * Get patterns for a stream
     *
     * @param string $stream_id Stream ID
     * @return array Patterns
     */
    public function get_stream_patterns($stream_id) {
        $patterns = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->patterns_table}
             WHERE stream_id = %s
             ORDER BY position ASC",
            $stream_id
        ), ARRAY_A);

        foreach ($patterns as &$pattern) {
            $pattern['config'] = json_decode($pattern['config'], true);
        }

        return $patterns;
    }

    /**
     * Update stream status
     *
     * @param string $stream_id Stream ID
     * @param string $status New status
     * @return bool Success status
     */
    public function update_stream_status($stream_id, $status) {
        $result = $this->wpdb->update(
            $this->streams_table,
            ['status' => sanitize_key($status)],
            ['stream_id' => $stream_id],
            ['%s'],
            ['%s']
        );

        wp_cache_delete($stream_id, $this->cache_group);

        return $result !== false;
    }

    /**
     * Clean up expired streams
     *
     * @return int Number of streams cleaned up
     */
    public function cleanup_expired_streams() {
        $expired = $this->wpdb->get_col(
            "SELECT stream_id FROM {$this->streams_table}
             WHERE expires_at IS NOT NULL AND expires_at < NOW()"
        );

        foreach ($expired as $stream_id) {
            $this->delete_stream($stream_id);
        }

        return count($expired);
    }

    /**
     * Delete a stream
     *
     * @param string $stream_id Stream ID
     * @return bool Success status
     */
    public function delete_stream($stream_id) {
        $this->wpdb->delete($this->patterns_table, ['stream_id' => $stream_id], ['%s']);
        $this->wpdb->delete($this->streams_table, ['stream_id' => $stream_id], ['%s']);

        wp_cache_delete($stream_id, $this->cache_group);

        do_action('aevov_stream_deleted', $stream_id);

        return true;
    }

    /**
     * Get user's streams
     *
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array Streams
     */
    public function get_user_streams($user_id, $args = []) {
        $defaults = [
            'status' => null,
            'limit' => 20,
            'offset' => 0
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM {$this->streams_table} WHERE user_id = %d";
        $prepare_args = [$user_id];

        if ($args['status']) {
            $sql .= " AND status = %s";
            $prepare_args[] = $args['status'];
        }

        $sql .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $prepare_args[] = $args['limit'];
        $prepare_args[] = $args['offset'];

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $prepare_args)
        );
    }
}
