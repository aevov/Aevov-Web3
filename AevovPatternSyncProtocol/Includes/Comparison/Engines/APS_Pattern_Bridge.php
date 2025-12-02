<?php

namespace APS\Comparison\Engines;

class APS_Pattern_Bridge {
    private $bloom_integration;
    private $cache;
    private $metrics;
    
    public function __construct(APS_BLOOM_Integration $bloom_integration, APS_Cache $cache, MetricsDB $metrics) {
        $this->bloom_integration = $bloom_integration;
        $this->cache = $cache;
        $this->metrics = $metrics;
    }

    public function adapt_pattern($pattern_data) {
        $cache_key = 'pattern_' . md5(serialize($pattern_data));
        
        if ($cached = $this->cache->get($cache_key)) {
            return $cached;
        }

        $adapted_pattern = [
            'hash' => $this->generate_pattern_hash($pattern_data),
            'type' => 'adapted_pattern',
            'source' => 'bloom',
            'version' => APS_VERSION,
            'features' => $this->extract_features($pattern_data),
            'structure' => $this->extract_structure($pattern_data),
            'metadata' => [
                'original_confidence' => $pattern_data['confidence'] ?? 0,
                'adaptation_timestamp' => time(),
                'original_type' => $pattern_data['type'] ?? 'unknown'
            ],
            'relationships' => $this->extract_relationships($pattern_data),
            'vectors' => $this->extract_vectors($pattern_data)
        ];

        $this->cache->set($cache_key, $adapted_pattern);
        $this->record_adaptation_metrics($pattern_data, $adapted_pattern);

        return $adapted_pattern;
    }

    public function convert_to_bloom_format($aps_pattern) {
        return [
            'type' => 'bloom_pattern',
            'hash' => $aps_pattern['hash'],
            'data' => $this->convert_pattern_data($aps_pattern),
            'confidence' => $this->calculate_bloom_confidence($aps_pattern),
            'metadata' => [
                'source' => 'aps',
                'version' => APS_VERSION,
                'conversion_timestamp' => time()
            ]
        ];
    }

    public function sync_patterns($patterns) {
        $synced_patterns = [];
        $batch_size = 100;
        
        foreach (array_chunk($patterns, $batch_size) as $batch) {
            foreach ($batch as $pattern) {
                try {
                    $synced_pattern = $this->sync_single_pattern($pattern);
                    if ($synced_pattern) {
                        $synced_patterns[] = $synced_pattern;
                    }
                } catch (Exception $e) {
                    $this->log_sync_error($pattern, $e);
                }
            }
        }

        return $synced_patterns;
    }

    private function sync_single_pattern($pattern) {
        $bloom_pattern = $this->convert_to_bloom_format($pattern);
        $sync_result = $this->bloom_integration->sync_pattern($bloom_pattern);

        if ($sync_result) {
            $this->update_pattern_sync_status($pattern['hash'], 'synced');
            return $bloom_pattern;
        }

        return null;
    }

    private function extract_features($pattern_data) {
        $features = [];

        if (isset($pattern_data['features'])) {
            $features = array_merge($features, $this->normalize_features($pattern_data['features']));
        }

        if (isset($pattern_data['vectors'])) {
            $features['vector_features'] = $this->compute_vector_features($pattern_data['vectors']);
        }

        if (isset($pattern_data['metadata'])) {
            $features['metadata_features'] = $this->extract_metadata_features($pattern_data['metadata']);
        }

        return $features;
    }

    private function extract_structure($pattern_data) {
        $structure = [
            'dimensions' => $this->extract_dimensions($pattern_data),
            'topology' => $this->analyze_topology($pattern_data),
            'hierarchy' => $this->extract_hierarchy($pattern_data),
            'connections' => $this->analyze_connections($pattern_data)
        ];

        return $this->optimize_structure($structure);
    }

    private function extract_relationships($pattern_data) {
        $relationships = [];

        if (isset($pattern_data['relationships'])) {
            foreach ($pattern_data['relationships'] as $rel) {
                $relationships[] = [
                    'type' => $rel['type'],
                    'target' => $rel['target'],
                    'strength' => $rel['strength'],
                    'metadata' => $rel['metadata'] ?? []
                ];
            }
        }

        return $relationships;
    }

    private function extract_vectors($pattern_data) {
        if (!isset($pattern_data['vectors'])) {
            return null;
        }

        return [
            'embeddings' => $this->normalize_vectors($pattern_data['vectors']),
            'dimensions' => count($pattern_data['vectors']),
            'statistics' => $this->compute_vector_statistics($pattern_data['vectors'])
        ];
    }

    private function normalize_features($features) {
        $normalized = [];

        foreach ($features as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = $this->normalize_features($value);
            } else if (is_numeric($value)) {
                $normalized[$key] = $this->normalize_value($value);
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function normalize_value($value) {
        if ($value === 0) return 0;
        return $value / (abs($value) + 1);
    }

    private function normalize_vectors($vectors) {
        $normalized = [];
        $max_val = max(array_map('abs', $vectors));

        if ($max_val == 0) {
            return array_fill(0, count($vectors), 0);
        }

        foreach ($vectors as $vector) {
            $normalized[] = $vector / $max_val;
        }

        return $normalized;
    }

    private function compute_vector_statistics($vectors) {
        return [
            'mean' => array_sum($vectors) / count($vectors),
            'variance' => $this->compute_variance($vectors),
            'range' => [min($vectors), max($vectors)],
            'distribution' => $this->analyze_distribution($vectors)
        ];
    }

    private function compute_variance($values) {
        $mean = array_sum($values) / count($values);
        return array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / count($values);
    }

    private function analyze_distribution($values) {
        sort($values);
        $n = count($values);

        return [
            'quartiles' => [
                'q1' => $values[floor($n * 0.25)],
                'q2' => $values[floor($n * 0.5)],
                'q3' => $values[floor($n * 0.75)]
            ],
            'skewness' => $this->compute_skewness($values),
            'kurtosis' => $this->compute_kurtosis($values)
        ];
    }

    private function generate_pattern_hash($pattern_data) {
        return hash('sha256', serialize($pattern_data));
    }

    private function calculate_bloom_confidence($pattern) {
        $confidence_factors = [
            'feature_quality' => $this->assess_feature_quality($pattern['features']),
            'structure_quality' => $this->assess_structure_quality($pattern['structure']),
            'relationship_quality' => $this->assess_relationship_quality($pattern['relationships'])
        ];

        return array_sum($confidence_factors) / count($confidence_factors);
    }

    private function record_adaptation_metrics($original, $adapted) {
        $this->metrics->record_metric(
            'pattern_adaptation',
            'conversion_time',
            microtime(true) - time(),
            ['pattern_hash' => $adapted['hash']]
        );

        $this->metrics->record_metric(
            'pattern_adaptation',
            'feature_count',
            count($adapted['features']),
            ['pattern_hash' => $adapted['hash']]
        );
    }

    private function log_sync_error($pattern, $error) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'aps_sync_log',
            [
                'sync_type' => 'pattern_sync',
                'sync_data' => json_encode([
                    'pattern_hash' => $pattern['hash'],
                    'error' => $error->getMessage(),
                    'trace' => $error->getTraceAsString()
                ]),
                'status' => 'error',
                'created_at' => current_time('mysql')
            ]
        );
    }
}