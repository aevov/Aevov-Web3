<?php
namespace BLOOM\Integration;

use BLOOM\Models\PatternModel;
use BLOOM\Processing\TensorProcessor;
use BLOOM\Models\ChunkModel;
use BLOOM\Models\TensorModel;
use BLOOM\Utilities\DataValidator;
use BLOOM\Utilities\ErrorHandler;

class APSIntegration {
    private $pattern_model;
    private $tensor_processor;
    private $chunk_model;
    private $tensor_model;
    private $validator;
    private $error_handler;
    private $pattern_cache = [];
    private $tensor_cache = [];
    private $comparison_threshold = 0.75;
    private $current_sku = 1;

    public function __construct() {
        $this->pattern_model = new PatternModel();
        $this->tensor_processor = new TensorProcessor();
        $this->chunk_model = new ChunkModel();
        $this->tensor_model = new TensorModel();
        $this->validator = new DataValidator();
        $this->error_handler = new ErrorHandler();

        // Only setup hooks if WordPress functions are available
        if (function_exists('add_filter') && function_exists('add_action')) {
            add_filter('aps_pattern_analysis', [$this, 'process_aps_pattern']);
            add_filter('aps_before_comparison', [$this, 'prepare_pattern_data']);
            add_action('aps_pattern_distributed', [$this, 'handle_pattern_distribution']);
            add_action('aps_comparison_complete', [$this, 'handle_comparison_result']);
            add_filter('aps_product_fields', [$this, 'add_tensor_fields']);
            add_action('aps_product_import', [$this, 'handle_tensor_import'], 10, 2);
        }
    }

    private function generate_sku() {
        $sku = sprintf("BLOOM-%04d", $this->current_sku);
        $this->current_sku++;
        return $sku;
    }

    public function handle_tensor_import($product_id, $data) {
        if (!empty($data['tensor_chunk'])) {
            try {
                $chunk_data = json_decode($data['tensor_chunk'], true);
                if (!$chunk_data || !isset($chunk_data['sku'])) {
                    throw new Exception('Invalid tensor chunk data');
                }

                $this->chunk_model->store_chunk([
                    'tensor_sku' => $chunk_data['sku'],
                    'chunk_data' => $chunk_data['data'],
                    'dtype' => $chunk_data['dtype'],
                    'shape' => json_encode($chunk_data['shape'])
                ]);

                update_post_meta($product_id, 'tensor_sku', $chunk_data['sku']);
                update_post_meta($product_id, 'tensor_dtype', $chunk_data['dtype']);
                update_post_meta($product_id, 'tensor_shape', json_encode($chunk_data['shape']));

            } catch (Exception $e) {
                $this->handle_processing_error($e, [
                    'product_id' => $product_id,
                    'action' => 'tensor_import'
                ]);
            }
        }
    }

    public function prepare_pattern_data($data) {
        if (!isset($data['patterns'])) {
            return $data;
        }

        foreach ($data['patterns'] as $key => $pattern) {
            $bloom_pattern = $this->get_bloom_pattern($pattern['id']);
            if ($bloom_pattern) {
                $data['patterns'][$key]['bloom_data'] = $this->prepare_bloom_comparison_data($bloom_pattern);
            }
            
            $tensor_data = $this->get_tensor_data($pattern['tensor_sku'] ?? null);
            if ($tensor_data) {
                $data['patterns'][$key]['tensor_data'] = $tensor_data;
            }
        }

        return $data;
    }

    private function get_tensor_data($sku) {
        if (!$sku) return null;

        if (!isset($this->tensor_cache[$sku])) {
            $chunk = $this->chunk_model->get_chunk($sku);
            
            if ($chunk) {
                $this->tensor_cache[$sku] = [
                    'data' => base64_decode($chunk['chunk_data']),
                    'shape' => json_decode($chunk['shape'], true),
                    'dtype' => $chunk['dtype']
                ];
            }
        }

        return $this->tensor_cache[$sku] ?? null;
    }

    public function process_aps_pattern($pattern_data) {
        try {
            if (isset($pattern_data['tensor_data']) && strlen($pattern_data['tensor_data']) > 50 * 1024 * 1024) {
                return $this->handle_large_pattern($pattern_data);
            }

            $bloom_pattern = $this->convert_to_bloom_format($pattern_data);
            $result = $this->tensor_processor->process_pattern($bloom_pattern);
            
            if ($result['confidence'] >= $this->comparison_threshold) {
                $this->store_patterns([$result]);
            }
            
            return $this->convert_to_aps_format($result);

        } catch (Exception $e) {
            $this->handle_processing_error($e, $pattern_data);
            throw $e;
        }
    }

    private function handle_large_pattern($pattern_data) {
        $chunk_size = BLOOM_CHUNK_SIZE; // Use defined constant
        $tensor_bytes = $pattern_data['tensor_data'];
        $total_size = strlen($tensor_bytes);
        $num_chunks = ceil($total_size / $chunk_size);
        $tensor_sku = $this->generate_sku(); // Generate a single SKU for the entire large pattern

        $stored_chunks_info = [];

        for ($i = 0; $i < $num_chunks; $i++) {
            $chunk_data_raw = substr($tensor_bytes, $i * $chunk_size, $chunk_size);
            $chunk_to_store = [
                'tensor_sku' => $tensor_sku,
                'chunk_index' => $i,
                'data' => base64_encode($chunk_data_raw),
                'original_pattern_id' => $pattern_data['id'] ?? null, // Pass original pattern ID
                'status' => 'partial' // Mark as partial until all chunks are received
            ];

            $insert_id = $this->chunk_model->store_chunk($chunk_to_store);
            if ($insert_id) {
                $stored_chunks_info[] = [
                    'chunk_id' => $insert_id,
                    'tensor_sku' => $tensor_sku,
                    'chunk_index' => $i,
                    'original_pattern_id' => $pattern_data['id'] ?? null
                ];
            } else {
                $this->error_handler->log_error(new \Exception('Failed to store chunk'), [
                    'chunk_index' => $i,
                    'original_pattern_id' => $pattern_data['id'] ?? null
                ]);
                // Decide how to handle failure: throw exception, return false, etc.
                // For now, we'll continue but log the error.
            }
        }

        // Return information about the stored chunks, not the raw chunks
        return [
            'status' => 'chunked_and_stored',
            'tensor_sku' => $tensor_sku,
            'total_parts' => $num_chunks,
            'original_pattern_id' => $pattern_data['id'] ?? null,
            'chunks_info' => $stored_chunks_info
        ];
    }

    private function convert_to_bloom_format($aps_pattern) {
        return [
            'sku' => $this->generate_sku(),
            'type' => $aps_pattern['type'],
            'features' => $aps_pattern['features'],
            'metadata' => array_merge(
                $aps_pattern['metadata'] ?? [],
                ['source' => 'aps']
            ),
            'tensor_data' => [
                'data' => isset($aps_pattern['tensor_data']) ? base64_encode($aps_pattern['tensor_data']) : null,
                'dtype' => $aps_pattern['tensor_dtype'] ?? 'float32',
                'shape' => $aps_pattern['tensor_shape'] ?? []
            ],
            'confidence' => $aps_pattern['confidence'] ?? 0
        ];
    }

    private function convert_to_aps_format($bloom_pattern) {
        return [
            'type' => $bloom_pattern['type'],
            'pattern_hash' => $bloom_pattern['pattern_hash'],
            'features' => $bloom_pattern['features'],
            'confidence' => $bloom_pattern['confidence'],
            'metadata' => array_merge(
                $bloom_pattern['metadata'] ?? [],
                ['bloom_processed' => true]
            )
        ];
    }

    public function handle_pattern_distribution($distribution_data) {
        foreach ($distribution_data['target_sites'] as $site_id) {
            switch_to_blog($site_id);
            
            try {
                $pattern = $this->get_bloom_pattern($distribution_data['pattern_id']);
                if ($pattern) {
                    $this->distribute_bloom_pattern($pattern, $site_id);
                }
            } catch (Exception $e) {
                $this->handle_processing_error($e, [
                    'pattern_id' => $distribution_data['pattern_id'],
                    'site_id' => $site_id
                ]);
            }

            restore_current_blog();
        }
    }

    private function distribute_bloom_pattern($pattern, $site_id) {
        $message = [
            'type' => 'pattern_distribution',
            'data' => [
                'pattern' => $pattern,
                'source_site' => get_current_blog_id(),
                'target_site' => $site_id,
                'timestamp' => time()
            ]
        ];

        $queue = new \BLOOM\Network\MessageQueue();
        $queue->enqueue_message($message);
    }

    public function handle_comparison_result($result, $products, $comparison_data) {
        if (empty($comparison_data['tensor_data'])) {
            return;
        }

        try {
            $patterns = $this->tensor_processor->process_batch($comparison_data['tensor_data']);

            if (!empty($patterns)) {
                $this->store_patterns($patterns);
                $this->analyze_pattern_relationships($patterns, $products);
                $this->update_product_similarities($products, $patterns);
            }

        } catch (\Exception $e) {
            $this->handle_processing_error($e, $comparison_data);
        }
    }

    public function add_tensor_fields($fields) {
        $fields['bloom_tensor_fields'] = [
            'tensor_sku' => [
                'type' => 'text',
                'label' => __('BLOOM Tensor SKU', 'aps'),
                'description' => __('BLOOM tensor chunk identifier', 'aps')
            ],
            'tensor_chunk' => [
                'type' => 'textarea',
                'label' => __('Tensor Chunk Data', 'aps'),
                'description' => __('JSON data from BLOOM tensor chunk', 'aps')
            ],
            'pattern_matches' => [
                'type' => 'number',
                'label' => __('Pattern Matches', 'aps'),
                'description' => __('Number of BLOOM patterns found', 'aps')
            ]
        ];

        return $fields;
    }

    private function get_bloom_pattern($pattern_id) {
        if (!isset($this->pattern_cache[$pattern_id])) {
            $this->pattern_cache[$pattern_id] = $this->pattern_model->get($pattern_id);
        }
        return $this->pattern_cache[$pattern_id];
    }

    private function update_product_similarities($products, $patterns) {
        foreach ($products as $product_a) {
            foreach ($products as $product_b) {
                if ($product_a->ID === $product_b->ID) continue;

                $similarity = $this->calculate_pattern_similarity(
                    $patterns[$product_a->ID] ?? [],
                    $patterns[$product_b->ID] ?? []
                );

                if ($similarity >= $this->comparison_threshold) {
                    update_post_meta(
                        $product_a->ID,
                        'aps_similarity_' . $product_b->ID,
                        $similarity
                    );
                }
            }
        }
    }

    private function calculate_pattern_similarity($patterns_a, $patterns_b) {
        if (empty($patterns_a) || empty($patterns_b)) {
            return 0;
        }

        $matches = 0;
        $total = 0;

        foreach ($patterns_a as $pattern_a) {
            foreach ($patterns_b as $pattern_b) {
                if ($pattern_a['pattern_hash'] === $pattern_b['pattern_hash']) {
                    $matches++;
                }
                $total++;
            }
        }

        return $total > 0 ? $matches / $total : 0;
    }

    private function handle_processing_error($error, $context) {
        $this->error_handler->log_error($error, [
            'component' => 'aps_integration',
            'context' => $context
        ]);
    }

    private function store_patterns($patterns) {
        foreach ($patterns as $pattern) {
            $this->pattern_model->create($pattern);
        }
    }

    private function analyze_pattern_relationships($patterns, $products) {
        // Analyze relationships between patterns and products
        foreach ($patterns as $pattern) {
            $similar_patterns = $this->pattern_model->find_similar_patterns(
                $pattern['pattern_hash'],
                $this->comparison_threshold
            );
            
            if (!empty($similar_patterns)) {
                $this->update_pattern_relationships($pattern, $similar_patterns);
            }
        }
    }

    private function update_pattern_relationships($pattern, $similar_patterns) {
        foreach ($similar_patterns as $similar_pattern) {
            // Update pattern relationship metadata
            $relationship_data = [
                'pattern_a' => $pattern['id'],
                'pattern_b' => $similar_pattern['id'],
                'similarity_score' => $this->calculate_similarity_score($pattern, $similar_pattern),
                'relationship_type' => 'similar',
                'created_at' => current_time('mysql')
            ];
            
            // Store relationship data (could be in a separate relationships table)
            update_option('bloom_pattern_relationship_' . $pattern['id'] . '_' . $similar_pattern['id'], $relationship_data);
        }
    }

    private function calculate_similarity_score($pattern_a, $pattern_b) {
        // Simple similarity calculation based on pattern hash and features
        if ($pattern_a['pattern_hash'] === $pattern_b['pattern_hash']) {
            return 1.0;
        }
        
        // Compare features if available
        if (isset($pattern_a['features']) && isset($pattern_b['features'])) {
            $features_a = is_array($pattern_a['features']) ? $pattern_a['features'] : json_decode($pattern_a['features'], true);
            $features_b = is_array($pattern_b['features']) ? $pattern_b['features'] : json_decode($pattern_b['features'], true);
            
            if ($features_a && $features_b) {
                $intersection = array_intersect_assoc($features_a, $features_b);
                $union = array_merge($features_a, $features_b);
                return count($intersection) / count($union);
            }
        }
        
        return 0.0;
    }

    private function prepare_bloom_comparison_data($bloom_pattern) {
        return [
            'pattern_hash' => $bloom_pattern['pattern_hash'],
            'features' => $bloom_pattern['features'],
            'confidence' => $bloom_pattern['confidence'],
            'metadata' => $bloom_pattern['metadata'] ?? []
        ];
    }
}