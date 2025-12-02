<?php
/**
 * includes/comparison/class-aps-comparator.php
 */

namespace APS\Comparison;

use APS\Analysis\SymbolicPatternAnalyzer;
use BLOOM\Processing\TensorProcessor;
use AevovChunkRegistry\ChunkRegistry;
use AevovChunkRegistry\AevovChunk;
use APS\Models\ComposedModel;
use APS\Reasoning\ReasoningEngine; // New import
use APS\Reasoning\AnalogyReasoningEngine; // New import
use APS\Reasoning\HRMReasoningEngine; // New import
use AevovNeuroArchitect\Core\NeuralPatternCatalog;

require_once dirname(__FILE__) . '/../../../../aevov-chunk-registry/includes/class-chunk-registry.php';
require_once dirname(__FILE__) . '/../../../../aevov-chunk-registry/includes/class-aevov-chunk.php';
require_once dirname(__FILE__) . '/../Reasoning/AnalogyReasoningEngine.php';
require_once dirname(__FILE__) . '/../Reasoning/HRMReasoningEngine.php';
require_once dirname(__FILE__) . '/../../../aevov-neuro-architect/includes/class-neural-pattern-catalog.php';



class APS_Comparator {
    private $symbolic_analyzer;
    private $tensor_processor;
    private $rule_engine;
    private $filters;
    private $bloom_integration;
    private $pattern_catalog;

    public function __construct() {
        $this->pattern_catalog = new NeuralPatternCatalog();
    }

    

    public function compose_model( $blueprint ) {
        try {
            $this->validate_blueprint($blueprint);

            $patterns = $this->select_patterns( $blueprint );
            $model = $this->assemble_model( $patterns, $blueprint );
            if ( isset( $blueprint['memory'] ) && $blueprint['memory']['enabled'] ) {
                $model->set_memory_system( $this->compose_memory_system( $blueprint['memory'] ) );
            }
            if ( isset( $blueprint['reasoning'] ) && $blueprint['reasoning']['enabled'] ) {
                $model->set_reasoning_engine( $this->compose_reasoning_engine( $blueprint['reasoning'] ) );
            }
            if ( isset( $blueprint['training'] ) ) {
                // $model->set_training_config( $this->configure_training( $blueprint['training'] ) );
            }

            return $model;
        } catch (\Exception $e) {
            // Log the error and re-throw or return a WP_Error
            error_log('Blueprint composition failed: ' . $e->getMessage());
            throw new \Exception('Blueprint composition failed: ' . $e->getMessage());
        }
    }

    private function compose_memory_system( $memory_blueprint ) {
        $memory_manager = new \AevovMemoryCore\MemoryManager();
        $memory_system_id = 'memory-system-' . uniqid();

        $components = [];
        if (isset($memory_blueprint['components'])) {
            foreach ($memory_blueprint['components'] as $index => $component_blueprint) {
                $component_address = $memory_system_id . '-component-' . $index;
                $memory_manager->write_to_memory( $component_address, $component_blueprint, $memory_system_id );
                $components[] = $component_address;
            }
        }

        $memory_system = [
            'id' => $memory_system_id,
            'blueprint' => $memory_blueprint,
            'components' => $components,
        ];

        return $memory_system;
    }

    private function select_patterns( $blueprint ) {
        $selected_patterns = [];

        foreach ( $blueprint['layers'] as $layer ) {
            $query_args = [
                'pattern_type' => $layer['pattern_type'],
                'metadata_contains' => $layer['options'] ?? [],
            ];

            $candidate_patterns = $this->pattern_catalog->query_patterns( $query_args );
            $best_patterns = $this->find_best_patterns( $candidate_patterns, $layer['count'] );

            $selected_patterns = array_merge( $selected_patterns, $best_patterns );
        }
        return $selected_patterns;
    }

    private function find_best_patterns( $patterns, $count ) {
        if ( empty( $patterns ) ) {
            return [];
        }

        $scored_patterns = [];
        foreach ( $patterns as $pattern ) {
            $scored_patterns[] = [
                'pattern' => $pattern,
                'score' => $this->calculate_pattern_score( $pattern ),
            ];
        }

        usort($scored_patterns, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice( array_column( $scored_patterns, 'pattern' ), 0, $count );
    }

    private function calculate_pattern_score( $pattern ) {
        $performance_score = $this->calculate_performance_score( $pattern->performance );
        $dependency_score = $this->calculate_dependency_score( $pattern->dependencies );
        $compatibility_score = $this->calculate_compatibility_score( $pattern );

        // These weights can be adjusted to prioritize different factors
        $weights = ['performance' => 0.5, 'dependency' => 0.3, 'compatibility' => 0.2];

        return ($performance_score * $weights['performance']) +
               ($dependency_score * $weights['dependency']) +
               ($compatibility_score * $weights['compatibility']);
    }

    private function calculate_performance_score( $performance ) {
        if ( empty( $performance ) ) {
            return 0.5; // Default score for patterns with no performance data
        }

        // This is a simplified calculation. A real implementation would be more sophisticated.
        $accuracy = $performance['accuracy'] ?? 0;
        $speed = $performance['speed'] ?? 0;

        return ($accuracy * 0.7) + ($speed * 0.3);
    }

    private function calculate_dependency_score( $dependencies ) {
        // This is a simplified calculation. A real implementation would be more sophisticated.
        return 1 / (1 + count( $dependencies ));
    }

    private function calculate_compatibility_score( $pattern ) {
        // This is a placeholder for a more sophisticated compatibility scoring mechanism.
        // A real implementation would analyze the pattern's inputs and outputs to determine
        // how well it fits with other patterns.
        return 0.5;
    }

    private function assemble_model( $patterns, $blueprint ) {
        $model_id = 'composed-model-' . uniqid();
        $composed_model = new ComposedModel( $model_id, $blueprint['name'], $blueprint['description'] ?? '' );

        $layers = [];
        foreach($blueprint['layers'] as $layer_blueprint) {
            $layer_patterns = [];
            foreach($patterns as $pattern) {
                if($pattern->pattern_type === $layer_blueprint['pattern_type']) {
                    $layer_patterns[] = $pattern;
                }
            }
            $layers[] = $layer_patterns;
        }

        foreach($layers as $layer) {
            $composed_model->add_layer($layer);
        }

        return $composed_model;
    }

    public function find_analogous_patterns( $pattern, $top_n = 5 ) {
        $similar_patterns = $this->query_chunk_registry( $pattern, $top_n );
        return $similar_patterns;
    }

    private function query_chunk_registry( $pattern, $top_n = 5 ) {
        $chunk_registry = new ChunkRegistry();
        $chunk = new AevovChunk($pattern['id'], $pattern['type'], '', $pattern['features']);
        return $chunk_registry->find_similar_chunks($chunk, $top_n);
    }

    public function store_comparison_result($result) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'aps_comparisons',
            [
                'comparison_uuid' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('comp_', true),
                'comparison_type' => $result['type'],
                'items_data' => json_encode($result['items']),
                'settings' => json_encode($result['settings']),
                'status' => 'completed'
            ]
        );

        $comparison_id = $wpdb->insert_id;

        $wpdb->insert(
            $wpdb->prefix . 'aps_results',
            [
                'comparison_id' => $comparison_id,
                'result_data' => json_encode($result['results']),
                'match_score' => $result['score'],
                'pattern_data' => json_encode($result['patterns'])
            ]
        );

        return [
            'id' => $comparison_id,
            'uuid' => $result['uuid'],
            'score' => $result['score'],
            'timestamp' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
        ];
    }

    private function log_comparison_error(Exception $e, $context) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'aps_sync_log',
            [
                'sync_type' => 'comparison_error',
                'sync_data' => json_encode([
                    'error' => $e->getMessage(),
                    'context' => $context
                ]),
                'status' => 'error'
            ]
        );
    }

    private function validate_and_prepare_item($item) {
        // Sanitize and validate each field in chunk
        $prepared_item = [];
        foreach ($item as $key => $value) {
            switch ($key) {
                case 'chunk_id':
                    $prepared_item['chunk_id'] = intval($value);
                    break;
                case 'sequence':
                    if (!is_array($value)) {
                        throw new Exception("Sequence must be an array of tokens");
                    }
                    $prepared_item['sequence'] = array_map('intval', $value);
                    break;
                case 'attention_mask':
                    if (!is_array($value)) {
                        throw new Exception("Attention mask must be an array");
                    }
                    $prepared_item['attention_mask'] = array_map('intval', $value);
                    break;
                case 'position_ids':
                    if (!is_array($value)) {
                        throw new Exception("Position IDs must be an array");
                    }
                    $prepared_item['position_ids'] = array_map('intval', $value);
                    break;
                case 'chunk_size':
                    $prepared_item['chunk_size'] = intval($value);
                    break;
                case 'overlap':
                    $prepared_item['overlap'] = intval($value);
                    break;
                default:
                    throw new Exception("Unknown attribute: {$key}");
            }
        }
        // Validate required fields are present
        $required_fields = ['chunk_id', 'sequence', 'attention_mask', 'position_ids'];
        foreach ($required_fields as $field) {
            if (!isset($prepared_item[$field])) {
                throw new Exception("Missing required field {$field}");
            }
        }
        return $prepared_item;
    }

    private function load_item_by_identifier($identifier) {
        global $wpdb;

        $pattern = $this->get_pattern_by_identifier($identifier);
        if (!$pattern) {
            throw new Exception("Pattern not identified for identifier: {$identifier}");
        }

        $chunks = $this->get_chunk_by_pattern($identifier);
        if (!$chunks) {
            throw new Exception("No chunks found for identifier: {$identifier}");
        }

        return $this->process_chunks($chunks);
    }

    private function get_pattern_by_identifier($identifier) {
        global $wpdb;

        $pattern = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aps_patterns WHERE pattern_id = %s OR pattern_uuid = %s",
                $identifier,
                $identifier
            ),
            ARRAY_A
        );
        return $pattern;
    }

    private function get_chunk_by_pattern($pattern_id) {
        global $wpdb;

        $chunks = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    chunk_id,
                    sequence,
                    attention_mask,
                    position_ids,
                    chunk_size,
                    overlap
                FROM {$wpdb->prefix}aps_pattern_chunks
                WHERE pattern_id = %d
                ORDER BY chunk_id ASC",
                $pattern_id
            ),
            ARRAY_A
        );
        return $chunks;
    }

    private function process_chunks($chunks) {
        foreach ($chunks as &$chunk) {
            // Convert JSON stored arrays back to PHP arrays
            $chunk['sequence'] = json_decode($chunk['sequence'], true);
            $chunk['attention_mask'] = json_decode($chunk['attention_mask'], true);
            $chunk['position_ids'] = json_decode($chunk['position_ids'], true);

            // Convert numerical fields to integers
            $chunk['chunk_id'] = intval($chunk['chunk_id']);
            $chunk['chunk_size'] = intval($chunk['chunk_size']);
            $chunk['overlap'] = intval($chunk['overlap']);

            // Validate the decoded data
            if (!$chunk['sequence'] || !$chunk['attention_mask'] || !$chunk['position_ids']) {
                throw new Exception("Invalid chunk data format for chunk_id: {$chunk['chunk_id']}");
            }
        }

        return $chunks;
    }
    private function validate_blueprint($blueprint) {
        if (!isset($blueprint['name']) || !is_string($blueprint['name'])) {
            throw new \InvalidArgumentException('Blueprint must have a "name" (string).');
        }
        if (!isset($blueprint['layers']) || !is_array($blueprint['layers'])) {
            throw new \InvalidArgumentException('Blueprint must have "layers" (array).');
        }

        foreach ($blueprint['layers'] as $index => $layer) {
            if (!isset($layer['type']) || !is_string($layer['type'])) {
                throw new \InvalidArgumentException("Layer {$index} must have a 'type' (string).");
            }
            if (!isset($layer['pattern_type']) || !is_string($layer['pattern_type'])) {
                throw new \InvalidArgumentException("Layer {$index} must have a 'pattern_type' (string).");
            }
            if (!isset($layer['count']) || !is_int($layer['count']) || $layer['count'] <= 0) {
                throw new \InvalidArgumentException("Layer {$index} must have a positive 'count' (integer).");
            }
            // Options can be any object, no specific validation for now
        }

        if (isset($blueprint['memory'])) {
            if (!is_array($blueprint['memory']) || !isset($blueprint['memory']['enabled']) || !is_bool($blueprint['memory']['enabled'])) {
                throw new \InvalidArgumentException('Memory section must be an object with an "enabled" (boolean) property.');
            }
            if ($blueprint['memory']['enabled'] && (!isset($blueprint['memory']['components']) || !is_array($blueprint['memory']['components']))) {
                throw new \InvalidArgumentException('Enabled memory blueprint must have "components" (array).');
            }
            if ($blueprint['memory']['enabled']) {
                foreach ($blueprint['memory']['components'] as $index => $component) {
                    if (!isset($component['type']) || !is_string($component['type'])) {
                        throw new \InvalidArgumentException("Memory component {$index} must have a 'type' (string).");
                    }
                    if (!isset($component['capacity']) || !is_int($component['capacity']) || $component['capacity'] <= 0) {
                        throw new \InvalidArgumentException("Memory component {$index} must have a positive 'capacity' (integer).");
                    }
                    if (!isset($component['decay_rate']) || !is_numeric($component['decay_rate']) || $component['decay_rate'] < 0 || $component['decay_rate'] > 1) {
                        throw new \InvalidArgumentException("Memory component {$index} must have a 'decay_rate' (number between 0 and 1).");
                    }
                    // Connections and options can be any object/array, no specific validation for now
                }
            }
        }

        if (isset($blueprint['reasoning'])) {
            if (!is_array($blueprint['reasoning']) || !isset($blueprint['reasoning']['enabled']) || !is_bool($blueprint['reasoning']['enabled'])) {
                throw new \InvalidArgumentException('Reasoning section must be an object with an "enabled" (boolean) property.');
            }
            if ($blueprint['reasoning']['enabled'] && (!isset($blueprint['reasoning']['type']) || !is_string($blueprint['reasoning']['type']))) {
                throw new \InvalidArgumentException('Enabled reasoning blueprint must have a "type" (string).');
            }
            // Parameters and connections can be any object/array, no specific validation for now
        }

        if (isset($blueprint['training'])) {
            if (!is_array($blueprint['training']) || !isset($blueprint['training']['epochs']) || !is_int($blueprint['training']['epochs']) || $blueprint['training']['epochs'] <= 0) {
                throw new \InvalidArgumentException('Training section must be an object with positive "epochs" (integer).');
            }
            if (!isset($blueprint['training']['learning_rate']) || !is_numeric($blueprint['training']['learning_rate']) || $blueprint['training']['learning_rate'] <= 0) {
                throw new \InvalidArgumentException('Training section must be an object with positive "learning_rate" (number).');
            }
            if (!isset($blueprint['training']['dataset_id']) || !is_string($blueprint['training']['dataset_id'])) {
                throw new \InvalidArgumentException('Training section must be an object with a "dataset_id" (string).');
            }
            // Optimizer and loss_function can be any string, no specific validation for now
        }
    }
    protected function compose_reasoning_engine($reasoning_blueprint) {
        $type = $reasoning_blueprint['type'];
        $parameters = $reasoning_blueprint['parameters'] ?? [];
        $connections = $reasoning_blueprint['connections'] ?? [];

        switch ($type) {
            case 'analogy':
                return new AnalogyReasoningEngine($parameters, $connections);
            case 'hrm':
                return new HRMReasoningEngine($parameters, $connections);
            default:
                throw new \InvalidArgumentException("Unknown reasoning engine type: {$type}");
        }
    }

    public function get_comparison($comparison_id) {
        global $wpdb;

        $comparison = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aps_comparisons WHERE id = %d",
                $comparison_id
            ),
            ARRAY_A
        );

        if ($comparison) {
            $results = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}aps_results WHERE comparison_id = %d",
                    $comparison_id
                ),
                ARRAY_A
            );
            if ($results) {
                $comparison['results'] = $results;
            }
        }

        return $comparison;
    }
}
