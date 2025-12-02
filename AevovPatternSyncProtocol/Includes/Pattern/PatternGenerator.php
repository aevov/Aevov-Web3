<?php

namespace APS\Pattern;

use APS\DB\MetricsDB;
use APS\Core\Logger;

class PatternGenerator {
    private $chunk_processor;
    private $pattern_storage;
    private $validator;
    private $metrics;
    private $logger;

    public function __construct() {
        $this->chunk_processor = new ChunkProcessor();
        $this->pattern_storage = new PatternStorage();
        $this->validator = new PatternValidator();
        $this->metrics = new MetricsDB();
        $this->logger = Logger::get_instance();
    }

    public function generate_patterns($chunk_ids) {
        try {
            if (empty($chunk_ids) || !is_array($chunk_ids)) {
                $this->logger->warning('No chunk IDs provided for pattern generation');
                return [];
            }
            
            // Process chunks in batches
            $chunks_data = $this->chunk_processor->process_chunks($chunk_ids);
            
            if (empty($chunks_data)) {
                $this->logger->warning('No chunk data returned from chunk processor', ['chunk_ids' => $chunk_ids]);
                return [];
            }
            
            // Extract patterns
            $patterns = [];
            foreach ($chunks_data as $chunk_data) {
                $pattern = $this->extract_pattern($chunk_data);
                $validation_result = $this->validator->validate_pattern($pattern);
                
                if (!$validation_result['valid']) {
                    $this->logger->error('Invalid pattern generated from chunk', [
                        'chunk_id' => $chunk_data['id'] ?? 'unknown',
                        'errors' => $validation_result['errors']
                    ]);
                    continue;
                }
                
                // Perform consistency validation
                $consistency_result = $this->validator->validate_pattern_consistency($pattern);
                if (isset($consistency_result['warnings']) && !empty($consistency_result['warnings'])) {
                    $this->logger->warning('Pattern consistency warnings', [
                        'pattern_id' => $pattern['id'],
                        'warnings' => $consistency_result['warnings']
                    ]);
                }
                
                $patterns[] = $pattern;
            }
            
            if (empty($patterns)) {
                $this->logger->warning('No valid patterns generated from provided chunks', ['chunk_ids' => $chunk_ids]);
                return [];
            }
 
            // Store patterns
            $stored_patterns = $this->pattern_storage->store_patterns($patterns);
            
            if (empty($stored_patterns)) {
                $this->logger->error('Failed to store any generated patterns', ['generated_patterns_count' => count($patterns)]);
                return [];
            }
 
            // Record metrics
            $this->metrics->record_metric('pattern_generation', 'batch_processing', count($patterns), [
                'chunks_processed' => count($chunk_ids),
                'patterns_generated' => count($patterns),
                'patterns_stored' => count($stored_patterns)
            ]);
            $this->logger->info('Pattern generation metrics recorded', [
                'chunks_processed' => count($chunk_ids),
                'patterns_generated' => count($patterns)
            ]);
 
            // Trigger pattern distribution
            if (function_exists('do_action')) {
                do_action('aps_patterns_generated', $stored_patterns);
                $this->logger->info('Action "aps_patterns_generated" triggered', ['patterns_count' => count($stored_patterns)]);
            }
 
            return $stored_patterns;
 
        } catch (\Exception $e) {
            $this->logger->error('Pattern generation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chunk_ids' => $chunk_ids
            ]);
            throw $e; // Re-throw the exception after logging
        }
    }

    public function process_input($input) {
        try {
            if (empty($input)) {
                $this->logger->warning('Empty input provided for pattern processing');
                return $this->generate_response(['status' => 'error', 'message' => 'No input data']);
            }
            
            // Process user input through pattern system
            $relevant_patterns = $this->find_relevant_patterns($input);
            
            if (empty($relevant_patterns)) {
                $this->logger->info('No relevant patterns found for input', ['input' => $input]);
                return $this->generate_response(['status' => 'no_match', 'message' => 'No relevant patterns found']);
            }
            
            $analysis = $this->analyze_patterns($relevant_patterns, $input);
            
            $this->logger->info('Input processed successfully', ['input_length' => strlen(json_encode($input))]);
            
            return $this->generate_response($analysis);
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while processing input', [
                'input' => $input,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->generate_response(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function extract_pattern($chunk_data) {
        try {
            // Basic validation for chunk_data
            if (!is_array($chunk_data) || !isset($chunk_data['id']) || !isset($chunk_data['tensor_name']) || !isset($chunk_data['tensor_data'])) {
                $this->logger->error('Invalid chunk data provided for pattern extraction', ['chunk_data' => $chunk_data]);
                throw new \Exception('Invalid chunk data for pattern extraction');
            }
            
            $features = $this->extract_features($chunk_data);
            
            return [
                'id' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('pattern_', true),
                'type' => 'tensor_pattern',
                'source' => 'bloom_chunk',
                'features' => $features,
                'metadata' => [
                    'chunk_id' => $chunk_data['id'],
                    'tensor_name' => $chunk_data['tensor_name'],
                    'generated_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
                ],
                'confidence' => $this->calculate_confidence($chunk_data)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while extracting pattern from chunk', [
                'chunk_id' => $chunk_data['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function extract_features($chunk_data) {
        try {
            if (!isset($chunk_data['tensor_data']) || !is_array($chunk_data['tensor_data']) || !isset($chunk_data['tensor_data']['data'])) {
                $this->logger->error('Missing or invalid tensor_data in chunk for feature extraction', ['chunk_data' => $chunk_data]);
                throw new \Exception('Invalid tensor data for feature extraction');
            }
            
            $tensor_data = $chunk_data['tensor_data'];
            
            // Ensure shape and dtype are set, provide defaults if not
            $shape = $chunk_data['shape'] ?? [];
            $dtype = $chunk_data['dtype'] ?? 'unknown';
            
            $statistics = $this->calculate_statistics($tensor_data['data']);
            $embeddings = $this->generate_embeddings($tensor_data['data']);
            
            $this->logger->info('Features extracted successfully from chunk', [
                'chunk_id' => $chunk_data['id'] ?? 'unknown',
                'shape' => $shape,
                'dtype' => $dtype
            ]);
            
            return [
                'shape' => $shape,
                'dtype' => $dtype,
                'statistics' => $statistics,
                'embeddings' => $embeddings
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while extracting features from chunk', [
                'chunk_id' => $chunk_data['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function calculate_statistics($data) {
        try {
            if (!is_array($data) || empty($data)) {
                $this->logger->warning('Empty or invalid data provided for statistics calculation');
                return [
                    'mean' => 0,
                    'variance' => 0,
                    'distribution' => []
                ];
            }
            
            $mean = array_sum($data) / count($data);
            $variance = $this->calculate_variance($data);
            $distribution = $this->analyze_distribution($data);
            
            $this->logger->debug('Statistics calculated', [
                'mean' => $mean,
                'variance' => $variance
            ]);
            
            return [
                'mean' => $mean,
                'variance' => $variance,
                'distribution' => $distribution
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while calculating statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'mean' => 0,
                'variance' => 0,
                'distribution' => []
            ];
        }
    }

    private function calculate_variance($data) {
        try {
            if (!is_array($data) || count($data) < 2) {
                $this->logger->warning('Insufficient data for variance calculation', ['data_count' => count($data)]);
                return 0;
            }
            
            $mean = array_sum($data) / count($data);
            $variance = 0;
            foreach ($data as $value) {
                $variance += pow($value - $mean, 2);
            }
            
            $result = $variance / count($data);
            $this->logger->debug('Variance calculated', ['variance' => $result]);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while calculating variance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    private function analyze_distribution($data) {
        try {
            if (!is_array($data) || empty($data)) {
                $this->logger->warning('Empty or invalid data provided for distribution analysis');
                return [];
            }
            
            sort($data);
            $count = count($data);
            
            $min = $data[0];
            $max = $data[$count - 1];
            $median = $this->calculate_median($data);
            $quartiles = $this->calculate_quartiles($data);
            
            $this->logger->debug('Distribution analyzed', [
                'min' => $min,
                'max' => $max,
                'median' => $median
            ]);
            
            return [
                'min' => $min,
                'max' => $max,
                'median' => $median,
                'quartiles' => $quartiles
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while analyzing distribution', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    private function calculate_median($sorted_data) {
        try {
            if (!is_array($sorted_data) || empty($sorted_data)) {
                $this->logger->warning('Empty or invalid data provided for median calculation');
                return 0;
            }
            
            $count = count($sorted_data);
            $mid = floor($count / 2);
            
            $median = ($count % 2 === 0)
                ? ($sorted_data[$mid - 1] + $sorted_data[$mid]) / 2
                : $sorted_data[$mid];
            
            $this->logger->debug('Median calculated', ['median' => $median]);
            return $median;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while calculating median', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    private function calculate_quartiles($sorted_data) {
        try {
            if (!is_array($sorted_data) || empty($sorted_data)) {
                $this->logger->warning('Empty or invalid data provided for quartile calculation');
                return [];
            }
            
            $count = count($sorted_data);
            
            $q1 = $sorted_data[floor($count * 0.25)];
            $q2 = $this->calculate_median($sorted_data);
            $q3 = $sorted_data[floor($count * 0.75)];
            
            $this->logger->debug('Quartiles calculated', [
                'q1' => $q1,
                'q2' => $q2,
                'q3' => $q3
            ]);
            
            return [
                'q1' => $q1,
                'q2' => $q2,
                'q3' => $q3
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while calculating quartiles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    private function generate_embeddings($data) {
        try {
            if (!is_array($data) || empty($data)) {
                $this->logger->warning('Empty or invalid data provided for embedding generation');
                return ['vector' => [], 'dimensions' => 0];
            }
            
            // Generate normalized feature vector
            $features = $this->generate_feature_vector($data);
            
            $this->logger->debug('Embeddings generated', ['dimensions' => count($features)]);
            
            return [
                'vector' => $features,
                'dimensions' => count($features)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while generating embeddings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['vector' => [], 'dimensions' => 0];
        }
    }

    private function generate_feature_vector($data) {
        try {
            if (!is_array($data) || empty($data)) {
                $this->logger->warning('Empty or invalid data provided for feature vector generation');
                return [];
            }
            
            $features = [];
            
            // Basic statistics
            $count = count($data);
            if ($count > 0) {
                $features[] = array_sum($data) / $count; // mean
                $features[] = $this->calculate_variance($data); // variance
                $features[] = max($data); // max value
                $features[] = min($data); // min value
            } else {
                $features = [0, 0, 0, 0]; // Default values for empty data
            }
            
            // Normalize feature vector
            $magnitude = sqrt(array_sum(array_map(function($x) {
                return $x * $x;
            }, $features)));
            
            if ($magnitude > 0) {
                $features = array_map(function($x) use ($magnitude) {
                    return $x / $magnitude;
                }, $features);
            }
            
            $this->logger->debug('Feature vector generated', ['vector_size' => count($features)]);
            
            return $features;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while generating feature vector', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    private function calculate_confidence($chunk_data) {
        try {
            // Implement your confidence calculation logic here
            // For now, returning a default high confidence
            $confidence = 0.95;
            
            // Example: Adjust confidence based on chunk data quality or size
            if (isset($chunk_data['size']) && $chunk_data['size'] < 100) {
                $confidence -= 0.1; // Reduce confidence for small chunks
            }
            
            $this->logger->debug('Confidence calculated', ['confidence' => $confidence, 'chunk_id' => $chunk_data['id'] ?? 'unknown']);
            
            return max(0, min(1, $confidence)); // Ensure confidence is between 0 and 1
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while calculating confidence', [
                'chunk_id' => $chunk_data['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0; // Return 0 confidence on error
        }
    }

    private function find_relevant_patterns($input) {
        try {
            global $wpdb;
            
            // Basic input validation
            if (empty($input)) {
                $this->logger->warning('Empty input provided for relevant pattern search');
                return [];
            }
            
            // Implement more sophisticated pattern search logic here
            // For now, a simple query for recent patterns
            $patterns = $this->pattern_storage->get_patterns_by_type('tensor_pattern', 10); // Using PatternStorage
            
            if ($patterns === false) {
                $this->logger->error('Failed to retrieve relevant patterns from database', [
                    'error' => $this->wpdb->last_error ?? 'unknown'
                ]);
                return [];
            }
            
            $this->logger->info('Relevant patterns found', ['count' => count($patterns)]);
            
            return $patterns;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while finding relevant patterns', [
                'input' => $input,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    private function analyze_patterns($patterns, $input) {
        try {
            if (!is_array($patterns) || empty($patterns)) {
                $this->logger->warning('No patterns provided for analysis');
                return ['patterns' => [], 'input' => $input, 'timestamp' => current_time('mysql')];
            }
            
            // Implement more sophisticated pattern analysis logic here
            // For now, just return the patterns and input
            $analysis_result = [
                'patterns' => $patterns,
                'input' => $input,
                'timestamp' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
            ];
            
            $this->logger->info('Patterns analyzed successfully', [
                'patterns_count' => count($patterns),
                'input_length' => strlen(json_encode($input))
            ]);
            
            return $analysis_result;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while analyzing patterns', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['patterns' => [], 'input' => $input, 'timestamp' => current_time('mysql'), 'error' => $e->getMessage()];
        }
    }

    private function generate_response($analysis) {
        try {
            if (!is_array($analysis) || empty($analysis)) {
                $this->logger->error('Empty or invalid analysis data provided for response generation');
                return ['status' => 'error', 'message' => 'Invalid analysis data'];
            }
            
            // Implement more sophisticated response generation logic here
            $status = $analysis['status'] ?? 'success';
            $message = $analysis['message'] ?? 'Pattern analysis complete';
            $timestamp = $analysis['timestamp'] ?? (function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'));
            
            $response = [
                'status' => $status,
                'response' => $message,
                'timestamp' => $timestamp,
                'analysis_details' => $analysis // Include full analysis for debugging/transparency
            ];
            
            $this->logger->info('Response generated', ['status' => $status, 'message' => $message]);
            
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while generating response', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}