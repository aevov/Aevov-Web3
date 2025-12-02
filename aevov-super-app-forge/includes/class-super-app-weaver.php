<?php
/**
 * Super App Weaver - Intelligent App Generation from UAD
 *
 * Converts Universal App Definitions (UAD) into platform-agnostic applications
 * using pattern matching and generation from the Aevov network.
 *
 * @package AevovSuperAppForge
 * @since 1.0.0
 */

namespace AevovSuperAppForge;

use AevovChunkRegistry\ChunkRegistry;
use AevovChunkRegistry\AevovChunk;
use AevovEmbeddingEngine\EmbeddingManager;

class SuperAppWeaver {

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
     * Components table name
     *
     * @var string
     */
    private $components_table;

    /**
     * Cache group
     *
     * @var string
     */
    private $cache_group = 'aevov_super_app';

    /**
     * Embedding manager
     *
     * @var EmbeddingManager|null
     */
    private $embedding_manager;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->patterns_table = $wpdb->prefix . 'aevov_app_patterns';
        $this->components_table = $wpdb->prefix . 'aevov_app_components';

        $this->init_embedding_manager();
        add_action('init', [$this, 'create_tables'], 5);
    }

    /**
     * Initialize the embedding manager
     */
    private function init_embedding_manager() {
        $embedding_path = dirname(__FILE__) . '/../../../aevov-embedding-engine/includes/class-embedding-manager.php';
        if (file_exists($embedding_path)) {
            require_once $embedding_path;
            $this->embedding_manager = new EmbeddingManager();
        }
    }

    /**
     * Create database tables
     *
     * @return void
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql_patterns = "CREATE TABLE IF NOT EXISTS {$this->patterns_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pattern_id VARCHAR(64) NOT NULL UNIQUE,
            pattern_type VARCHAR(50) NOT NULL,
            component_type VARCHAR(100) NULL,
            platform VARCHAR(50) DEFAULT 'universal',
            embedding LONGTEXT NULL,
            template LONGTEXT NULL,
            properties LONGTEXT NULL,
            dependencies LONGTEXT NULL,
            usage_count INT UNSIGNED DEFAULT 0,
            quality_score DECIMAL(5,4) DEFAULT 0.5000,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX pattern_type (pattern_type),
            INDEX component_type (component_type),
            INDEX platform (platform),
            INDEX status (status)
        ) {$charset_collate};";

        $sql_components = "CREATE TABLE IF NOT EXISTS {$this->components_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            component_id VARCHAR(64) NOT NULL UNIQUE,
            app_id VARCHAR(64) NOT NULL,
            component_type VARCHAR(100) NOT NULL,
            pattern_id VARCHAR(64) NULL,
            config LONGTEXT NULL,
            position INT UNSIGNED DEFAULT 0,
            parent_id VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX app_id (app_id),
            INDEX component_type (component_type),
            INDEX parent_id (parent_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_patterns);
        dbDelta($sql_components);
    }

    /**
     * Weave an application from a Universal App Definition
     *
     * @param array $uad Universal App Definition
     * @return array Woven application structure
     */
    public function weave_app($uad) {
        $app_id = wp_generate_uuid4();

        // Match UAD components to existing patterns
        $patterns = $this->pattern_matching($uad);

        // Generate new patterns for unmatched components
        $new_patterns = $this->pattern_generation($uad, $patterns);

        // Merge all patterns
        $all_patterns = array_merge($patterns, $new_patterns);

        // Transpile patterns into platform-agnostic app structure
        $app = $this->cross_platform_transpilation($all_patterns);

        // Store the generated components
        $this->store_app_components($app_id, $all_patterns);

        // Add app metadata
        $app['app_id'] = $app_id;
        $app['metadata'] = [
            'created_at' => current_time('mysql'),
            'pattern_count' => count($all_patterns),
            'matched_patterns' => count($patterns),
            'generated_patterns' => count($new_patterns)
        ];

        do_action('aevov_app_woven', $app_id, $app, $uad);

        return $app;
    }

    /**
     * Match UAD components to existing patterns in the database
     *
     * @param array $uad Universal App Definition
     * @return array Matched patterns
     */
    private function pattern_matching($uad) {
        $patterns = [];

        if (!isset($uad['components']) || !is_array($uad['components'])) {
            return $patterns;
        }

        foreach ($uad['components'] as $component) {
            $component_type = $component['type'] ?? 'unknown';

            // Try to find matching pattern in database
            $matched_pattern = $this->find_pattern_for_component($component);

            if ($matched_pattern) {
                $patterns[] = [
                    'id' => $matched_pattern->pattern_id,
                    'type' => $matched_pattern->pattern_type,
                    'component' => $component,
                    'template' => json_decode($matched_pattern->template, true),
                    'properties' => json_decode($matched_pattern->properties, true),
                    'source' => 'database'
                ];

                // Update usage count
                $this->increment_pattern_usage($matched_pattern->pattern_id);
            } else {
                // Try chunk registry for pattern matching
                $chunk_pattern = $this->find_pattern_from_chunks($component);
                if ($chunk_pattern) {
                    $patterns[] = $chunk_pattern;
                }
            }
        }

        // Also match logic rules if present
        if (isset($uad['logic']) && is_array($uad['logic'])) {
            foreach ($uad['logic'] as $rule) {
                $logic_pattern = $this->find_logic_pattern($rule);
                if ($logic_pattern) {
                    $patterns[] = $logic_pattern;
                }
            }
        }

        return $patterns;
    }

    /**
     * Find a pattern for a component
     *
     * @param array $component Component definition
     * @return object|null Pattern record
     */
    private function find_pattern_for_component($component) {
        $component_type = $component['type'] ?? '';

        // First try exact match by component type
        $pattern = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->patterns_table}
             WHERE component_type = %s AND status = 'active'
             ORDER BY quality_score DESC, usage_count DESC
             LIMIT 1",
            $component_type
        ));

        if ($pattern) {
            return $pattern;
        }

        // Try semantic matching if we have embeddings
        if ($this->embedding_manager && !empty($component['description'])) {
            return $this->find_pattern_by_similarity($component);
        }

        return null;
    }

    /**
     * Find pattern by semantic similarity
     *
     * @param array $component Component definition
     * @return object|null Pattern record
     */
    private function find_pattern_by_similarity($component) {
        $description = $component['description'] ?? $component['type'] ?? '';

        try {
            $embedding_result = $this->embedding_manager->embed($description);
            $query_embedding = $embedding_result['metadata']['vector'] ?? null;

            if (!$query_embedding) {
                return null;
            }

            // Get all patterns with embeddings
            $patterns = $this->wpdb->get_results(
                "SELECT * FROM {$this->patterns_table}
                 WHERE status = 'active' AND embedding IS NOT NULL
                 LIMIT 100"
            );

            $best_match = null;
            $best_score = 0;

            foreach ($patterns as $pattern) {
                $pattern_embedding = json_decode($pattern->embedding, true);
                if (!$pattern_embedding) {
                    continue;
                }

                $score = $this->cosine_similarity($query_embedding, $pattern_embedding);
                if ($score > $best_score && $score > 0.7) {
                    $best_score = $score;
                    $best_match = $pattern;
                }
            }

            return $best_match;
        } catch (\Exception $e) {
            error_log('[SuperAppWeaver] Similarity search error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find pattern from chunk registry
     *
     * @param array $component Component definition
     * @return array|null Pattern data
     */
    private function find_pattern_from_chunks($component) {
        $chunk_registry_path = dirname(__FILE__) . '/../../../aevov-chunk-registry/includes/class-chunk-registry.php';

        if (!file_exists($chunk_registry_path)) {
            return null;
        }

        require_once $chunk_registry_path;

        try {
            $chunk_registry = new ChunkRegistry();

            // Create a query chunk
            $query_chunk = new AevovChunk(
                'query_' . uniqid(),
                'ui_component',
                '',
                [
                    'type' => $component['type'] ?? 'unknown',
                    'description' => $component['description'] ?? '',
                    'properties' => $component['properties'] ?? []
                ],
                []
            );

            $similar = $chunk_registry->find_similar_chunks($query_chunk, 1);

            if (!empty($similar) && $similar[0]['score'] > 0.6) {
                $chunk = $similar[0]['chunk'];
                return [
                    'id' => $chunk->id,
                    'type' => 'ui',
                    'component' => $component,
                    'template' => $chunk->metadata['template'] ?? null,
                    'properties' => $chunk->metadata['properties'] ?? [],
                    'source' => 'chunk_registry'
                ];
            }
        } catch (\Exception $e) {
            error_log('[SuperAppWeaver] Chunk registry error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Find a logic pattern
     *
     * @param array $rule Logic rule
     * @return array|null Pattern data
     */
    private function find_logic_pattern($rule) {
        $rule_type = $rule['type'] ?? '';

        $pattern = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->patterns_table}
             WHERE pattern_type = 'logic' AND component_type = %s AND status = 'active'
             ORDER BY quality_score DESC
             LIMIT 1",
            $rule_type
        ));

        if ($pattern) {
            return [
                'id' => $pattern->pattern_id,
                'type' => 'logic',
                'rule' => $rule,
                'template' => json_decode($pattern->template, true),
                'source' => 'database'
            ];
        }

        return null;
    }

    /**
     * Generate new patterns for unmatched components
     *
     * @param array $uad Universal App Definition
     * @param array $existing_patterns Already matched patterns
     * @return array Generated patterns
     */
    private function pattern_generation($uad, $existing_patterns) {
        $new_patterns = [];
        $matched_components = array_map(function($p) {
            return $p['component'] ?? null;
        }, $existing_patterns);
        $matched_components = array_filter($matched_components);

        if (!isset($uad['components'])) {
            return $new_patterns;
        }

        foreach ($uad['components'] as $component) {
            // Check if component was already matched
            $is_matched = false;
            foreach ($matched_components as $matched) {
                if ($matched === $component || (
                    isset($matched['type'], $component['type']) &&
                    $matched['type'] === $component['type']
                )) {
                    $is_matched = true;
                    break;
                }
            }

            if (!$is_matched) {
                $generated = $this->generate_pattern_for_component($component);
                if ($generated) {
                    $new_patterns[] = $generated;
                }
            }
        }

        // Generate patterns for unmatched logic rules
        if (isset($uad['logic'])) {
            $matched_rules = array_filter(array_map(function($p) {
                return $p['rule'] ?? null;
            }, $existing_patterns));

            foreach ($uad['logic'] as $rule) {
                if (!in_array($rule, $matched_rules)) {
                    $generated = $this->generate_logic_pattern($rule);
                    if ($generated) {
                        $new_patterns[] = $generated;
                    }
                }
            }
        }

        return $new_patterns;
    }

    /**
     * Generate a pattern for a component
     *
     * @param array $component Component definition
     * @return array Generated pattern
     */
    private function generate_pattern_for_component($component) {
        $pattern_id = 'gen_' . wp_generate_uuid4();
        $component_type = $component['type'] ?? 'custom';

        // Generate template based on component type
        $template = $this->generate_component_template($component);

        // Create embedding if possible
        $embedding = null;
        if ($this->embedding_manager) {
            $description = $component['description'] ?? $component_type;
            try {
                $result = $this->embedding_manager->embed($description);
                $embedding = $result['metadata']['vector'] ?? null;
            } catch (\Exception $e) {
                // Continue without embedding
            }
        }

        // Store the generated pattern
        $this->wpdb->insert(
            $this->patterns_table,
            [
                'pattern_id' => $pattern_id,
                'pattern_type' => 'ui',
                'component_type' => $component_type,
                'platform' => 'universal',
                'embedding' => $embedding ? json_encode($embedding) : null,
                'template' => json_encode($template),
                'properties' => json_encode($component['properties'] ?? []),
                'dependencies' => json_encode($component['dependencies'] ?? []),
                'status' => 'active'
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return [
            'id' => $pattern_id,
            'type' => 'ui',
            'component' => $component,
            'template' => $template,
            'properties' => $component['properties'] ?? [],
            'source' => 'generated'
        ];
    }

    /**
     * Generate a component template
     *
     * @param array $component Component definition
     * @return array Template structure
     */
    private function generate_component_template($component) {
        $type = $component['type'] ?? 'div';
        $properties = $component['properties'] ?? [];

        // Base templates for common component types
        $base_templates = [
            'button' => [
                'element' => 'button',
                'class' => 'aevov-button',
                'attributes' => ['type' => 'button'],
                'slots' => ['default' => ['text' => $properties['label'] ?? 'Button']]
            ],
            'text' => [
                'element' => 'p',
                'class' => 'aevov-text',
                'slots' => ['default' => ['text' => $properties['content'] ?? '']]
            ],
            'input' => [
                'element' => 'input',
                'class' => 'aevov-input',
                'attributes' => [
                    'type' => $properties['inputType'] ?? 'text',
                    'placeholder' => $properties['placeholder'] ?? ''
                ]
            ],
            'image' => [
                'element' => 'img',
                'class' => 'aevov-image',
                'attributes' => [
                    'src' => $properties['src'] ?? '',
                    'alt' => $properties['alt'] ?? ''
                ]
            ],
            'container' => [
                'element' => 'div',
                'class' => 'aevov-container',
                'children' => []
            ],
            'list' => [
                'element' => 'ul',
                'class' => 'aevov-list',
                'children' => []
            ],
            'form' => [
                'element' => 'form',
                'class' => 'aevov-form',
                'attributes' => ['method' => 'post'],
                'children' => []
            ],
            'card' => [
                'element' => 'div',
                'class' => 'aevov-card',
                'slots' => [
                    'header' => [],
                    'body' => [],
                    'footer' => []
                ]
            ],
            'modal' => [
                'element' => 'div',
                'class' => 'aevov-modal',
                'attributes' => ['role' => 'dialog'],
                'slots' => [
                    'header' => [],
                    'body' => [],
                    'footer' => []
                ]
            ]
        ];

        if (isset($base_templates[$type])) {
            $template = $base_templates[$type];
            // Merge custom properties
            if (!empty($properties['class'])) {
                $template['class'] .= ' ' . $properties['class'];
            }
            return $template;
        }

        // Generic template for unknown types
        return [
            'element' => 'div',
            'class' => 'aevov-' . sanitize_key($type),
            'attributes' => $properties['attributes'] ?? [],
            'slots' => ['default' => []]
        ];
    }

    /**
     * Generate a logic pattern
     *
     * @param array $rule Logic rule
     * @return array Generated pattern
     */
    private function generate_logic_pattern($rule) {
        $pattern_id = 'logic_' . wp_generate_uuid4();
        $rule_type = $rule['type'] ?? 'custom';

        $template = [
            'type' => $rule_type,
            'conditions' => $rule['conditions'] ?? [],
            'actions' => $rule['actions'] ?? [],
            'trigger' => $rule['trigger'] ?? 'manual'
        ];

        // Store the generated pattern
        $this->wpdb->insert(
            $this->patterns_table,
            [
                'pattern_id' => $pattern_id,
                'pattern_type' => 'logic',
                'component_type' => $rule_type,
                'platform' => 'universal',
                'template' => json_encode($template),
                'properties' => json_encode($rule),
                'status' => 'active'
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return [
            'id' => $pattern_id,
            'type' => 'logic',
            'rule' => $rule,
            'template' => $template,
            'source' => 'generated'
        ];
    }

    /**
     * Transpile patterns into platform-agnostic app structure
     *
     * @param array $patterns All patterns
     * @return array Application structure
     */
    private function cross_platform_transpilation($patterns) {
        $app = [
            'ui' => ['components' => [], 'layout' => []],
            'logic' => ['rules' => [], 'state' => []],
            'data' => ['models' => [], 'bindings' => []],
            'styles' => ['theme' => [], 'components' => []]
        ];

        foreach ($patterns as $pattern) {
            $type = $pattern['type'] ?? 'ui';

            switch ($type) {
                case 'ui':
                    $component = $this->transpile_ui_component($pattern);
                    $app['ui']['components'][] = $component;

                    // Extract styles
                    if (!empty($pattern['template']['class'])) {
                        $app['styles']['components'][$component['id']] = [
                            'class' => $pattern['template']['class']
                        ];
                    }
                    break;

                case 'logic':
                    $rule = $this->transpile_logic_rule($pattern);
                    $app['logic']['rules'][] = $rule;

                    // Track state requirements
                    if (!empty($rule['state_deps'])) {
                        foreach ($rule['state_deps'] as $dep) {
                            if (!isset($app['logic']['state'][$dep])) {
                                $app['logic']['state'][$dep] = null;
                            }
                        }
                    }
                    break;

                case 'data':
                    $model = $this->transpile_data_model($pattern);
                    $app['data']['models'][] = $model;
                    break;
            }
        }

        // Build layout from components
        $app['ui']['layout'] = $this->build_layout($app['ui']['components']);

        return $app;
    }

    /**
     * Transpile a UI component
     *
     * @param array $pattern Pattern data
     * @return array Component structure
     */
    private function transpile_ui_component($pattern) {
        $component = $pattern['component'] ?? [];
        $template = $pattern['template'] ?? [];

        return [
            'id' => $pattern['id'],
            'type' => $component['type'] ?? 'div',
            'element' => $template['element'] ?? 'div',
            'class' => $template['class'] ?? '',
            'attributes' => $template['attributes'] ?? [],
            'slots' => $template['slots'] ?? [],
            'children' => $template['children'] ?? [],
            'props' => $component['properties'] ?? [],
            'events' => $component['events'] ?? []
        ];
    }

    /**
     * Transpile a logic rule
     *
     * @param array $pattern Pattern data
     * @return array Rule structure
     */
    private function transpile_logic_rule($pattern) {
        $rule = $pattern['rule'] ?? [];
        $template = $pattern['template'] ?? [];

        return [
            'id' => $pattern['id'],
            'type' => $rule['type'] ?? 'custom',
            'trigger' => $template['trigger'] ?? 'manual',
            'conditions' => $template['conditions'] ?? [],
            'actions' => $template['actions'] ?? [],
            'state_deps' => $this->extract_state_dependencies($template)
        ];
    }

    /**
     * Transpile a data model
     *
     * @param array $pattern Pattern data
     * @return array Model structure
     */
    private function transpile_data_model($pattern) {
        return [
            'id' => $pattern['id'],
            'fields' => $pattern['fields'] ?? [],
            'validations' => $pattern['validations'] ?? [],
            'relations' => $pattern['relations'] ?? []
        ];
    }

    /**
     * Extract state dependencies from a template
     *
     * @param array $template Template data
     * @return array State dependencies
     */
    private function extract_state_dependencies($template) {
        $deps = [];

        if (!empty($template['conditions'])) {
            foreach ($template['conditions'] as $condition) {
                if (isset($condition['state'])) {
                    $deps[] = $condition['state'];
                }
            }
        }

        if (!empty($template['actions'])) {
            foreach ($template['actions'] as $action) {
                if (isset($action['target']) && strpos($action['target'], 'state.') === 0) {
                    $deps[] = substr($action['target'], 6);
                }
            }
        }

        return array_unique($deps);
    }

    /**
     * Build layout from components
     *
     * @param array $components Component list
     * @return array Layout structure
     */
    private function build_layout($components) {
        $layout = [
            'type' => 'root',
            'children' => []
        ];

        foreach ($components as $component) {
            $layout['children'][] = [
                'component_id' => $component['id'],
                'position' => count($layout['children'])
            ];
        }

        return $layout;
    }

    /**
     * Store app components in database
     *
     * @param string $app_id Application ID
     * @param array $patterns Patterns to store
     * @return void
     */
    private function store_app_components($app_id, $patterns) {
        $position = 0;

        foreach ($patterns as $pattern) {
            $this->wpdb->insert(
                $this->components_table,
                [
                    'component_id' => $pattern['id'],
                    'app_id' => $app_id,
                    'component_type' => $pattern['type'] ?? 'ui',
                    'pattern_id' => $pattern['id'],
                    'config' => json_encode($pattern),
                    'position' => $position++
                ],
                ['%s', '%s', '%s', '%s', '%s', '%d']
            );
        }
    }

    /**
     * Increment pattern usage count
     *
     * @param string $pattern_id Pattern ID
     * @return void
     */
    private function increment_pattern_usage($pattern_id) {
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->patterns_table}
             SET usage_count = usage_count + 1
             WHERE pattern_id = %s",
            $pattern_id
        ));
    }

    /**
     * Calculate cosine similarity
     *
     * @param array $vec1 First vector
     * @param array $vec2 Second vector
     * @return float Similarity score
     */
    private function cosine_similarity(array $vec1, array $vec2): float {
        $dot = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;
        $count = min(count($vec1), count($vec2));

        if ($count === 0) {
            return 0.0;
        }

        for ($i = 0; $i < $count; $i++) {
            $dot += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        if ($norm1 == 0.0 || $norm2 == 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($norm1) * sqrt($norm2));
    }

    /**
     * Simulate the generation process
     *
     * @param array $uad Universal App Definition
     * @return array Simulation ticks
     */
    public function simulate_generation($uad) {
        $ticks = [];
        $timestamp = 0;

        // Phase 1: Component analysis
        if (isset($uad['components'])) {
            foreach ($uad['components'] as $index => $component) {
                $ticks[] = [
                    'timestamp' => $timestamp += 100,
                    'phase' => 'analysis',
                    'action' => 'analyze_component',
                    'component' => $component,
                    'index' => $index
                ];
            }
        }

        // Phase 2: Pattern matching
        $ticks[] = [
            'timestamp' => $timestamp += 200,
            'phase' => 'matching',
            'action' => 'start_pattern_matching',
            'total_components' => count($uad['components'] ?? [])
        ];

        // Phase 3: Pattern generation for unmatched
        $ticks[] = [
            'timestamp' => $timestamp += 300,
            'phase' => 'generation',
            'action' => 'generate_patterns'
        ];

        // Phase 4: Transpilation
        $ticks[] = [
            'timestamp' => $timestamp += 200,
            'phase' => 'transpilation',
            'action' => 'start_transpilation'
        ];

        // Phase 5: Logic processing
        if (isset($uad['logic'])) {
            foreach ($uad['logic'] as $index => $rule) {
                $ticks[] = [
                    'timestamp' => $timestamp += 50,
                    'phase' => 'logic',
                    'action' => 'process_rule',
                    'rule' => $rule,
                    'index' => $index
                ];
            }
        }

        // Phase 6: Completion
        $ticks[] = [
            'timestamp' => $timestamp += 100,
            'phase' => 'complete',
            'action' => 'app_ready'
        ];

        return $ticks;
    }

    /**
     * Get app by ID
     *
     * @param string $app_id Application ID
     * @return array|null App data
     */
    public function get_app($app_id) {
        $components = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->components_table}
             WHERE app_id = %s ORDER BY position ASC",
            $app_id
        ), ARRAY_A);

        if (empty($components)) {
            return null;
        }

        foreach ($components as &$comp) {
            $comp['config'] = json_decode($comp['config'], true);
        }

        return [
            'app_id' => $app_id,
            'components' => $components
        ];
    }
}
