<?php
namespace BLOOM\Processing;

use BLOOM\Models\TensorModel;
use BLOOM\Models\ChunkModel;
use BLOOM\Models\PatternModel;
use BLOOM\Utilities\DataValidator;

/**
 * Tensor Processing Engine for BLOOM Pattern Recognition
 */
class TensorProcessor {
    private $chunk_processor;
    private $tensor_model;
    private $chunk_model;
    private $pattern_model;
    private $validator;
    private $batch_size = 100;
    private $chunk_size = 7 * 1024 * 1024; // 7MB chunks

    public function __construct() {
        $this->tensor_model = new TensorModel();
        $this->chunk_model = new ChunkModel();
        $this->pattern_model = new PatternModel();

        // Conditional instantiation for dependencies
        if (class_exists('BLOOM\Utilities\DataValidator')) {
            $this->validator = new DataValidator();
        }

        $this->chunk_processor = new ChunkProcessor();
    }

    public function process_tensor($tensor_data) {
        try {
            // Validate tensor data
            $this->validate_tensor($tensor_data);

            // Pre-process tensor data
            $preprocessed_data = $this->tensor_model->preprocess($tensor_data);

            // Split into chunks
            $chunks = $this->chunk_processor->split_tensor($preprocessed_data);

            // Process chunks in batches to improve performance
            $processed_chunks = [];
            foreach (array_chunk($chunks, $this->batch_size) as $batch) {
                $batch_results = $this->process_chunk_batch($batch);
                if (!empty($batch_results)) {
                    $processed_chunks = array_merge($processed_chunks, $batch_results);
                }
            }

            // Post-process results
            $final_results = $this->tensor_model->postprocess($processed_chunks);

            // Extract patterns from processed chunks and add to final_results
            $all_patterns = [];
            foreach ($processed_chunks as $chunk) {
                if (isset($chunk['patterns']) && is_array($chunk['patterns'])) {
                    $all_patterns = array_merge($all_patterns, $chunk['patterns']);
                }
            }
            $final_results['patterns'] = $all_patterns;

            // Store results
            return $this->store_processed_tensor($final_results);

        } catch (\Exception $e) {
            $this->handle_processing_error($e);
            throw $e;
        }
    }

    public function process_pattern($pattern_data) {
        try {
            // Validate pattern data
            $validator = $this->get_validator();
            if ($validator) {
                $validator->validate_pattern_data($pattern_data);
            }

            // Extract features from pattern
            $features = $this->extract_features($pattern_data);

            // Calculate confidence score
            $confidence = $this->calculate_confidence($pattern_data, $features);

            // Find similar patterns
            $similar_patterns = $this->pattern_model->find_similar([
                'type' => $pattern_data['type'],
                'features' => $features
            ], 0.75);

            // Detect clustered patterns
            $clustered_patterns = $this->detect_clustered_patterns($features);
            if (!empty($clustered_patterns)) {
                $similar_patterns = array_merge($similar_patterns, $clustered_patterns);
            }

            $result = [
                'pattern_hash' => hash('sha256', json_encode($features)),
                'type' => $pattern_data['type'],
                'features' => $features,
                'confidence' => $confidence,
                'similar_patterns' => $similar_patterns,
                'metadata' => array_merge(
                    $pattern_data['metadata'] ?? [],
                    ['processed_at' => time()]
                )
            ];

            return $result;

        } catch (\Exception $e) {
            $this->handle_processing_error($e);
            throw $e;
        }
    }

    public function store_pattern($pattern_data) {
        return $this->pattern_model->create($pattern_data);
    }

    public function get_pattern($pattern_id) {
        return $this->pattern_model->get($pattern_id);
    }

    public function process_patterns($tensor_data_array) {
        $results = [];

        foreach ($tensor_data_array as $tensor_data) {
            try {
                $result = $this->process_tensor($tensor_data);
                if ($result) {
                    $results[] = $result;
                }
            } catch (\Exception $e) {
                // Log error but continue processing other tensors
                $this->handle_processing_error($e);
            }
        }

        return $results;
    }

    private function validate_tensor($tensor_data) {
        $validator = $this->get_validator();
        if ($validator) {
            return $validator->validate_tensor_data($tensor_data);
        }

        // Basic validation fallback
        return is_array($tensor_data) && isset($tensor_data['values']);
    }

    private function get_validator() {
        if (!$this->validator && class_exists('BLOOM\Utilities\DataValidator')) {
            $this->validator = new DataValidator();
        }
        return $this->validator;
    }

    private function process_chunk_batch($batch) {
        $results = [];

        foreach ($batch as $chunk) {
            try {
                $processed_chunk = $this->process_single_chunk($chunk);
                if ($processed_chunk) {
                    $results[] = $processed_chunk;
                }
            } catch (\Exception $e) {
                $this->handle_processing_error($e);
            }
        }

        return $results;
    }

    private function process_single_chunk($chunk) {
        // Extract features from chunk data
        $features = $this->extract_features_from_chunk($chunk);

        // Extract patterns from chunk data
        $patterns = $this->extract_patterns_from_chunk($chunk);

        return [
            'chunk_id' => $chunk['id'] ?? uniqid(),
            'features' => $features,
            'patterns' => $patterns,
            'values' => $chunk['values'] ?? [],
            'metadata' => $chunk['metadata'] ?? []
        ];
    }

    private function extract_features_from_chunk($chunk) {
        $features = [];

        if (!isset($chunk['values']) || !is_array($chunk['values'])) {
            return $features;
        }

        $values = $chunk['values'];
        $count = count($values);

        if ($count > 0) {
            $features['mean'] = array_sum($values) / $count;
            $features['max'] = max($values);
            $features['min'] = min($values);
            $features['variance'] = $this->calculate_variance($values, $features['mean']);
        }

        return $features;
    }

    private function extract_patterns_from_chunk($chunk) {
        $patterns = [];

        if (!isset($chunk['values']) || !is_array($chunk['values'])) {
            return $patterns;
        }

        // Sequential pattern detection
        $sequential = $this->detect_sequential_patterns($chunk['values']);
        if (!empty($sequential)) {
            $patterns['sequential'] = $sequential;
        }

        // Statistical pattern detection
        $statistical = $this->detect_statistical_patterns($chunk['values']);
        if (!empty($statistical)) {
            $patterns['statistical'] = $statistical;
        }

        return $patterns;
    }

    private function detect_sequential_patterns($values) {
        $patterns = [];
        $sequence_length = min(10, count($values)); // Look for sequences up to 10 elements

        for ($len = 3; $len <= $sequence_length; $len++) {
            for ($i = 0; $i <= count($values) - $len; $i++) {
                $sequence = array_slice($values, $i, $len);
                $pattern_strength = $this->calculate_sequence_strength($sequence);

                if ($pattern_strength > 0.7) {
                    $patterns[] = [
                        'type' => 'sequence',
                        'values' => $sequence,
                        'strength' => $pattern_strength,
                        'position' => $i
                    ];
                }
            }
        }

        return $patterns;
    }

    private function detect_statistical_patterns($values) {
        $patterns = [];

        if (count($values) < 3) {
            return $patterns;
        }

        $mean = array_sum($values) / count($values);
        $variance = $this->calculate_variance($values, $mean);
        $std_dev = sqrt($variance);

        // Detect outliers
        $outliers = [];
        foreach ($values as $i => $value) {
            if (abs($value - $mean) > 2 * $std_dev) {
                $outliers[] = ['index' => $i, 'value' => $value];
            }
        }

        if (!empty($outliers)) {
            $patterns[] = [
                'type' => 'outliers',
                'outliers' => $outliers,
                'mean' => $mean,
                'std_dev' => $std_dev
            ];
        }

        return $patterns;
    }

    private function detect_clustered_patterns($features, $num_clusters = 3) {
        $patterns = [];
        if (empty($features) || count($features) < $num_clusters) {
            return $patterns; // Not enough data to form clusters
        }

        // For simplicity, let's assume features are numerical and can be treated as points in 1D space
        // In a real scenario, this would involve more complex distance metrics and clustering algorithms (e.g., K-Means, DBSCAN)
        // For now, a very basic grouping based on value proximity

        $values = array_values($features); // Convert associative array to indexed array of values
        sort($values); // Sort values to make grouping easier

        $cluster_size = floor(count($values) / $num_clusters);
        for ($i = 0; $i < $num_clusters; $i++) {
            $start_index = $i * $cluster_size;
            $end_index = ($i == $num_clusters - 1) ? count($values) : ($start_index + $cluster_size);
            $cluster_values = array_slice($values, $start_index, $end_index - $start_index);

            if (!empty($cluster_values)) {
                $patterns[] = [
                    'type' => 'cluster',
                    'centroid' => array_sum($cluster_values) / count($cluster_values),
                    'size' => count($cluster_values),
                    'values' => $cluster_values
                ];
            }
        }
        return $patterns;
    }

    private function calculate_sequence_strength($sequence) {
        if (count($sequence) < 3) {
            return 0;
        }

        $differences = [];
        for ($i = 1; $i < count($sequence); $i++) {
            $differences[] = $sequence[$i] - $sequence[$i-1];
        }

        // Check for arithmetic progression
        $first_diff = $differences[0];
        $is_arithmetic = true;
        foreach ($differences as $diff) {
            if (abs($diff - $first_diff) > 0.01) { // Allow small floating point errors
                $is_arithmetic = false;
                break;
            }
        }

        return $is_arithmetic ? 1.0 : 0.0;
    }

    private function calculate_variance($values, $mean) {
        $sum_squares = 0;
        foreach ($values as $value) {
            $sum_squares += pow($value - $mean, 2);
        }
        return $sum_squares / count($values);
    }

    private function extract_features($pattern_data) {
        $features = [];

        if (isset($pattern_data['tensor_data'])) {
            $tensor_features = $this->extract_tensor_features($pattern_data['tensor_data']);
            $features = array_merge($features, $tensor_features);
        }

        if (isset($pattern_data['features'])) {
            $features = array_merge($features, $pattern_data['features']);
        }

        return $features;
    }

    private function extract_tensor_features($tensor_data) {
        $features = [];

        if (isset($tensor_data['data'])) {
            $data = is_string($tensor_data['data']) ?
                   json_decode($tensor_data['data'], true) :
                   $tensor_data['data'];

            if (is_array($data)) {
                $features['mean'] = array_sum($data) / count($data);
                $features['max'] = max($data);
                $features['min'] = min($data);
                $features['variance'] = $this->calculate_variance($data, $features['mean']);
            }
        }

        if (isset($tensor_data['shape'])) {
            $features['shape'] = $tensor_data['shape'];
        }

        if (isset($tensor_data['dtype'])) {
            $features['dtype'] = $tensor_data['dtype'];
        }

        return $features;
    }

    private function calculate_confidence($pattern_data, $features) {
        $base_confidence = 0.5;

        // Increase confidence based on feature completeness
        if (!empty($features)) {
            $base_confidence += 0.2;
        }

        // Increase confidence if tensor data is present
        if (isset($pattern_data['tensor_data'])) {
            $base_confidence += 0.2;
        }

        // Increase confidence based on pattern type
        if (isset($pattern_data['type'])) {
            $base_confidence += 0.1;
        }

        return min(1.0, $base_confidence);
    }

    private function store_processed_tensor($tensor_results) {
        try {
            $tensor_sku = $this->tensor_model->create($tensor_results);

            if ($tensor_sku) {
                // Store any patterns found
                if (isset($tensor_results['patterns']) && is_array($tensor_results['patterns'])) {
                    foreach ($tensor_results['patterns'] as $pattern) {
                        // Ensure required fields for PatternModel::create are present
                        // Ensure required fields for PatternModel::create are present and correctly formatted
                        $pattern_to_store = [
                            'type' => $pattern['type'] ?? 'unknown',
                            'features' => $pattern, // Pass the entire pattern array as features, PatternModel will json_encode it
                            'confidence' => $pattern['strength'] ?? ($pattern['confidence'] ?? 0.0), // Use strength or confidence
                            'metadata' => $pattern['metadata'] ?? [],
                            'tensor_sku' => $tensor_sku,
                        ];
                        $this->pattern_model->create($pattern_to_store);
                    }
                }

                return [
                    'tensor_sku' => $tensor_sku,
                    'status' => 'processed',
                    'patterns_found' => count($tensor_results['patterns'] ?? [])
                ];
            }

            return false;

        } catch (\Exception $e) {
            $this->handle_processing_error($e);
            return false;
        }
    }

    private function handle_processing_error($error) {
        if (class_exists('\BLOOM\Utilities\ErrorHandler')) {
            $error_handler = new \BLOOM\Utilities\ErrorHandler();
            $error_handler->log_error($error, [
                'component' => 'tensor_processor',
                'context' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
            ]);
        } else {
            error_log('BLOOM TensorProcessor Error: ' . $error->getMessage());
        }
    }
}

/**
 * Chunk Processor for splitting tensors into manageable pieces
 */
class ChunkProcessor {
    private $chunk_size;

    public function __construct($chunk_size = null) {
        $this->chunk_size = $chunk_size ?? (7 * 1024 * 1024); // 7MB default
    }

    public function split_tensor($tensor_data) {
        $chunks = [];

        if (!isset($tensor_data['values']) || !is_array($tensor_data['values'])) {
            throw new \InvalidArgumentException('Tensor data must contain values array');
        }

        $values = $tensor_data['values'];
        $total_size = count($values);
        $chunk_count = ceil($total_size / $this->chunk_size);

        for ($i = 0; $i < $chunk_count; $i++) {
            $start = $i * $this->chunk_size;
            $chunk_values = array_slice($values, $start, $this->chunk_size);

            $chunks[] = [
                'id' => uniqid('chunk_'),
                'index' => $i,
                'values' => $chunk_values,
                'metadata' => array_merge(
                    $tensor_data['metadata'] ?? [],
                    [
                        'chunk_index' => $i,
                        'total_chunks' => $chunk_count,
                        'chunk_size' => count($chunk_values)
                    ]
                )
            ];
        }

        return $chunks;
    }
}
