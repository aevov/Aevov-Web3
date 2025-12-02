<?php

namespace APS\Analysis;

class PatternAnalyzer {
    private $patterns = [];
    private $confidence_threshold;
    private $feature_weights = [
        'structural' => 0.4,
        'semantic' => 0.3,
        'relational' => 0.3
    ];
    
    public function __construct($confidence_threshold = null, $feature_weights = null) {
        $this->confidence_threshold = $confidence_threshold ?? get_option('aps_confidence_threshold', 0.75);
        
        if ($feature_weights !== null) {
            $this->feature_weights = $feature_weights;
        }
    }

    public function analyze_pattern($data) {
        $features = $this->extract_features($data);
        $metrics = $this->calculate_metrics($features);
        $signature = $this->generate_signature($features);
        
        $pattern = [
            'id' => wp_generate_uuid4(),
            'features' => $features,
            'metrics' => $metrics,
            'pattern_hash' => $signature,
            'confidence' => $this->calculate_confidence($metrics),
            'timestamp' => current_time('mysql', true)
        ];
        
        $this->store_pattern($pattern);
        return $pattern;
    }

    private function generate_signature($features) {
        $feature_string = implode('|', array_map('json_encode', $features));
        return sha256($feature_string);
    }

    private function extract_features($data) {
        $structural_features = $this->analyze_structure($data);
        $semantic_features = $this->analyze_semantics($data);
        $relational_features = $this->analyze_relationships($data);
        
        return [
            'structural' => $structural_features,
            'semantic' => $semantic_features,
            'relational' => $relational_features,
            'vector' => $this->generate_feature_vector([
                $structural_features,
                $semantic_features,
                $relational_features
            ])
        ];
    }

    private function analyze_structure($data) {
        $structure = [];
        
        if (is_array($data)) {
            $structure['depth'] = $this->calculate_array_depth($data);
            $structure['breadth'] = $this->calculate_array_breadth($data);
            $structure['density'] = $this->calculate_density($data);
            $structure['symmetry'] = $this->measure_symmetry($data);
            $structure['entropy'] = $this->calculate_entropy($data);
            $structure['type_distribution'] = $this->analyze_type_distribution($data);
        }
        
        return $structure;
    }

    private function analyze_semantics($data) {
        $semantics = [];
        
        if (is_array($data)) {
            $semantics['key_frequency'] = $this->analyze_key_frequency($data);
            $semantics['value_patterns'] = $this->detect_value_patterns($data);
            $semantics['naming_consistency'] = $this->measure_naming_consistency($data);
            $semantics['contextual_relevance'] = $this->calculate_contextual_relevance($data);
        }
        
        return $semantics;
    }

    private function analyze_relationships($data) {
        $relationships = [];
        
        if (is_array($data)) {
            $relationships['dependencies'] = $this->find_dependencies($data);
            $relationships['correlations'] = $this->calculate_correlations($data);
            $relationships['hierarchy'] = $this->analyze_hierarchy($data);
            $relationships['clusters'] = $this->detect_clusters($data);
        }
        
        return $relationships;
    }

    private function calculate_metrics($features) {
        return [
            'complexity' => $this->calculate_complexity($features),
            'coherence' => $this->calculate_coherence($features),
            'stability' => $this->calculate_stability($features),
            'modularity' => $this->calculate_modularity($features),
            'maintainability' => $this->calculate_maintainability($features)
        ];
    }

    private function calculate_complexity($features) {
        $structural_complexity = $features['structural']['depth'] * $features['structural']['breadth'];
        $semantic_complexity = count($features['semantic']['key_frequency']);
        $relational_complexity = count($features['relational']['dependencies']);
        
        return ($structural_complexity * 0.4 + $semantic_complexity * 0.3 + $relational_complexity * 0.3) / 100;
    }

    private function calculate_coherence($features) {
        $naming_coherence = $features['semantic']['naming_consistency'];
        $structural_coherence = $features['structural']['symmetry'];
        $relational_coherence = $this->calculate_cluster_coherence($features['relational']['clusters']);
        
        return ($naming_coherence * 0.3 + $structural_coherence * 0.3 + $relational_coherence * 0.4);
    }

    private function calculate_stability($features) {
        $dependency_stability = $this->calculate_dependency_stability($features['relational']['dependencies']);
        $structural_stability = 1 - ($features['structural']['entropy'] / 10);
        $semantic_stability = $features['semantic']['contextual_relevance'];
        
        return ($dependency_stability * 0.4 + $structural_stability * 0.3 + $semantic_stability * 0.3);
    }

    private function calculate_array_depth($array, $depth = 0) {
        $max_depth = $depth;
        
        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->calculate_array_depth($value, $depth + 1);
                $max_depth = max($max_depth, $depth);
            }
        }
        
        return $max_depth;
    }

    private function calculate_array_breadth($array) {
        $breadth = count($array);
        $child_breadth = 0;
        
        foreach ($array as $value) {
            if (is_array($value)) {
                $child_breadth = max($child_breadth, $this->calculate_array_breadth($value));
            }
        }
        
        return max($breadth, $child_breadth);
    }

    private function calculate_density($array) {
        $total_elements = $this->count_elements($array);
        $max_possible = pow(2, $this->calculate_array_depth($array) + 1) - 1;
        
        return $total_elements / $max_possible;
    }

    private function measure_symmetry($array) {
        $structure_hash = $this->hash_structure($array);
        $reversed = array_reverse($array, true);
        $reversed_hash = $this->hash_structure($reversed);
        
        return similar_text($structure_hash, $reversed_hash) / strlen($structure_hash);
    }

    private function calculate_entropy($array) {
        $frequencies = array_count_values($this->flatten_array($array));
        $total = array_sum($frequencies);
        $entropy = 0;
        
        foreach ($frequencies as $frequency) {
            $probability = $frequency / $total;
            $entropy -= $probability * log($probability, 2);
        }
        
        return $entropy;
    }

    private function analyze_type_distribution($array) {
        $types = [];
        
        foreach ($this->flatten_array($array) as $value) {
            $type = gettype($value);
            $types[$type] = ($types[$type] ?? 0) + 1;
        }
        
        return $types;
    }

    private function analyze_key_frequency($array) {
        $frequencies = [];
        
        foreach ($array as $key => $value) {
            $frequencies[$key] = ($frequencies[$key] ?? 0) + 1;
            
            if (is_array($value)) {
                $sub_frequencies = $this->analyze_key_frequency($value);
                foreach ($sub_frequencies as $sub_key => $count) {
                    $frequencies[$sub_key] = ($frequencies[$sub_key] ?? 0) + $count;
                }
            }
        }
        
        return $frequencies;
    }

    private function detect_value_patterns($array) {
        $patterns = [];
        $flattened = $this->flatten_array($array);
        
        $patterns['numeric_sequences'] = $this->find_numeric_sequences($flattened);
        $patterns['string_patterns'] = $this->find_string_patterns($flattened);
        $patterns['repeating_values'] = $this->find_repeating_values($flattened);
        
        return $patterns;
    }

    private function measure_naming_consistency($array) {
        $keys = array_keys($this->flatten_array_with_keys($array));
        $conventions = [
            'camel_case' => 0,
            'snake_case' => 0,
            'kebab_case' => 0
        ];
        
        foreach ($keys as $key) {
            if (preg_match('/^[a-z]+(?:[A-Z][a-z]*)*$/', $key)) {
                $conventions['camel_case']++;
            } elseif (preg_match('/^[a-z]+(?:_[a-z]+)*$/', $key)) {
                $conventions['snake_case']++;
            } elseif (preg_match('/^[a-z]+(?:-[a-z]+)*$/', $key)) {
                $conventions['kebab_case']++;
            }
        }
        
        $total = array_sum($conventions);
        $max_convention = max($conventions);
        
        return $total > 0 ? $max_convention / $total : 1;
    }

    private function calculate_contextual_relevance($array) {
        $keys = array_keys($this->flatten_array_with_keys($array));
        $common_contexts = ['id', 'name', 'type', 'value', 'data', 'meta', 'config', 'settings'];
        $relevance_score = 0;
        
        foreach ($keys as $key) {
            foreach ($common_contexts as $context) {
                if (stripos($key, $context) !== false) {
                    $relevance_score++;
                    break;
                }
            }
        }
        
        return $keys ? $relevance_score / count($keys) : 0;
    }

    private function find_dependencies($array) {
        $dependencies = [];
        $flattened = $this->flatten_array_with_keys($array);
        
        foreach ($flattened as $key => $value) {
            if (is_string($value)) {
                foreach ($flattened as $other_key => $other_value) {
                    if ($key !== $other_key && strpos($value, $other_key) !== false) {
                        $dependencies[$key][] = $other_key;
                    }
                }
            }
        }
        
        return $dependencies;
    }

    private function calculate_correlations($array) {
        $correlations = [];
        
        // Group values into arrays based on their keys 
        $grouped_values = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $grouped_values[$key] = $value; 
            }
        }

        // Calculate correlations for unique pairs of arrays
        $keys = array_keys($grouped_values);
        $num_keys = count($keys);

        $cache = [];

        for ($i = 0; $i < $num_keys; $i++)  {
            for ($j = $i + 1; $j < $num_keys; $j++) {
                $key1 = $keys[$i];
                $key2 = $keys[$j];

                if (!isset($cache[$key1][$key2])) {
                    $cache[$key1][$key2] = $this->calculate_spearman_correlation($grouped_values[$key1], $grouped_values[$key2]);
                    if ($cache[$key1][$key2] > 0.8) {
                        $correlations[$key1][$key2] = $cache[$key1][$key2];
                    }                    
                    $cache[$key2][$key1] = $cache[$key1][$key2];
                }
            }
        }
        
        return $correlations;
    }

    private function calculate_spearman_correlation($array1, $array2) {
        if (!is_array($array1) || !is_array($array2) || count($array1) !== count($array2) || count($array1) < 2) {
            return 0; // Handle invalid input
        }

        // Rank the values in the arrays
        $rank1 = $this->rank_array($array1);
        $rank2 = $this->rank_array($array2);

        // Calculate the sum of squared differences
        $n = count($array1);
        $d_squared_sum = 0;
        for ($i = 0; $i < $n; $i++) {
            $d_squared_sum += pow($rank1[$i] - $rank2[$i], 2);
        }

        // Calculate Spearman's correlation coefficient
        $numerator = 1 - (6 * $d_squared_sum) / ($n * ($n * $n - 1)); 
        return $numerator;
    }

    private function rank_array($array) {
        // Rank the array in one pass
        $sorted_array = $array;
        asort($sorted_array);
        $ranks = array_flip($sorted_array);
        return $ranks;
    }

    private function detect_clusters($array) {
        $clusters = [];
        $flattened = $this->flatten_array_with_keys($array);
        
        foreach ($flattened as $key => $value) {
            $cluster_key = $this->determine_cluster_key($key, $value);
            $clusters[$cluster_key][] = $key;
        }
        
        return $clusters;
    }

    private function calculate_confidence($metrics) {
        $weighted_score = 
            $metrics['complexity'] * 0.2 +
            $metrics['coherence'] * 0.3 +
            $metrics['stability'] * 0.2 +
            $metrics['modularity'] * 0.15 +
            $metrics['maintainability'] * 0.15;
            
        return min(1, max(0, $weighted_score));
    }

    private function generate_feature_vector($feature_sets) {
        $vector = [];
        
        foreach ($feature_sets as $features) {
            $vector = array_merge($vector, $this->flatten_array($features));
        }
        
        return array_map('floatval', $vector);
    }

    private function calculate_similarity($features_a, $features_b) {
        $vector_a = $features_a['vector'];
        $vector_b = $features_b['vector'];
        
        $dot_product = 0;
        $magnitude_a = 0;
        $magnitude_b = 0;
        
        foreach ($vector_a as $i => $value) {
            $dot_product += $value * ($vector_b[$i] ?? 0);
            $magnitude_a += $value * $value;
            $magnitude_b += ($vector_b[$i] ?? 0) * ($vector_b[$i] ?? 0);
        }
        
        $magnitude_a = sqrt($magnitude_a);
        $magnitude_b = sqrt($magnitude_b);
        
        return $magnitude_a && $magnitude_b ? $dot_product / ($magnitude_a * $magnitude_b) : 0;
    }

    private function find_matches($pattern_a, $pattern_b) {
        $matches = [];
        
        foreach ($pattern_a['features'] as $type => $features) {
            if (isset($pattern_b['features'][$type])) {
                $matches[$type] = $this->compare_feature_sets(
                    $features,
                    $pattern_b['features'][$type]
                );
            }
        }
        
        return $matches;
    }

    private function find_differences($pattern_a, $pattern_b) {
        $differences = [];
        
        foreach ($pattern_a['features'] as $type => $features) {
            if (isset($pattern_b['features'][$type])) {
                $differences[$type] = $this->compare_feature_differences(
                    $features,
                    $pattern_b['features'][$type]
                );
            } else {
                $differences[$type] = ['missing' => $features];
            }
        }
        
        return $differences;
    }

    private function compare_feature_sets($features_a, $features_b) {
        $matches = [];
        
        foreach ($features_a as $key => $value) {
            if (isset($features_b[$key]) && $features_b[$key] === $value) {
                $matches[$key] = $value;
            }
        }
        
        return $matches;
    }

    private function compare_feature_differences($features_a, $features_b) {
        $differences = [];
        
        foreach ($features_a as $key => $value) {
            if (!isset($features_b[$key])) {
                $differences['removed'][$key] = $value;
            } elseif ($features_b[$key] !== $value) {
                $differences['modified'][$key] = [
                    'from' => $value,
                    'to' => $features_b[$key]
                ];
            }
        }
        
        foreach ($features_b as $key => $value) {
            if (!isset($features_a[$key])) {
                $differences['added'][$key] = $value;
            }
        }
        
        return $differences;
    }

    private function flatten_array($array) {
        $result = [];
        
        array_walk_recursive($array, function($value) use (&$result) {
            $result[] = $value;
        });
        
        return $return;
    }

    private function flatten_array_with_keys($array, $prefix = '') {
        $result = [];
        
        foreach ($array as $key => $value) {
            $new_key = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten_array_with_keys($value, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }
        
        return $result;
    }

    private function hash_structure($array) {
        $structure = [];
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $structure[$key] = $this->hash_structure($value);
            } else {
                $structure[$key] = gettype($value);
            }
        }
        
        return md5(serialize($structure));
    }

    private function count_elements($array) {
        $count = 0;
        
        foreach ($array as $value) {
            $count++;
            if (is_array($value)) {
                $count += $this->count_elements($value);
            }
        }
        
        return $count;
    }

    private function find_numeric_sequences($values) {
        $sequences = [];
        $current_sequence = [];
        
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                continue;
            }
            
            if (empty($current_sequence) || end($current_sequence) + 1 == $value) {
                $current_sequence[] = $value;
            } else {
                if (count($current_sequence) > 2) {
                    $sequences[] = $current_sequence;
                }
                $current_sequence = [$value];
            }
        }
        
        if (count($current_sequence) > 2) {
            $sequences[] = $current_sequence;
        }
        
        return $sequences;
    }

    private function find_string_patterns($values) {
        $patterns = [];
        
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            
            if (preg_match('/^[A-Z]{2,}$/', $value)) {
                $patterns['uppercase_words'][] = $value;
            }
            
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $patterns['dates'][] = $value;
            }
            
            if (preg_match('/^[a-f0-9]{32}$/', $value)) {
                $patterns['hashes'][] = $value;
            }
            
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $patterns['emails'][] = $value;
            }
            
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                $patterns['urls'][] = $value;
            }
        }
        
        return $patterns;
    }

    private function find_repeating_values($values) {
        $counts = array_count_values($values);
        $repeating = [];
        
        foreach ($counts as $value => $count) {
            if ($count > 1) {
                $repeating[$value] = $count;
            }
        }
        
        return $repeating;
    }

    private function calculate_dependency_stability($dependencies) {
        if (empty($dependencies)) {
            return 1.0;
        }
        
        $total_deps = 0;
        $cyclic_deps = 0;
        
        foreach ($dependencies as $key => $deps) {
            $total_deps += count($deps);
            foreach ($deps as $dep) {
                if (isset($dependencies[$dep]) && in_array($key, $dependencies[$dep])) {
                    $cyclic_deps++;
                }
            }
        }
        
        return $total_deps ? 1 - ($cyclic_deps / (2 * $total_deps)) : 1;
    }

    private function calculate_cluster_coherence($clusters) {
        if (empty($clusters)) {
            return 1.0;
        }
        
        $coherence_sum = 0;
        foreach ($clusters as $cluster) {
            $coherence_sum += $this->calculate_cluster_internal_similarity($cluster);
        }
        
        return $coherence_sum / count($clusters);
    }

    private function calculate_cluster_internal_similarity($cluster) {
        if (count($cluster) <= 1) {
            return 1.0;
        }
        
        $similarity_sum = 0;
        $comparisons = 0;
        
        for ($i = 0; $i < count($cluster); $i++) {
            for ($j = $i + 1; $j < count($cluster); $j++) {
                $similarity_sum += $this->calculate_key_similarity($cluster[$i], $cluster[$j]);
                $comparisons++;
            }
        }
        
        return $comparisons ? $similarity_sum / $comparisons : 1;
    }

    private function calculate_key_similarity($key1, $key2) {
        return similar_text($key1, $key2) / max(strlen($key1), strlen($key2));
    }

    private function determine_cluster_key($key, $value) {
        if (is_numeric($value)) {
            return 'numeric';
        }
        
        if (is_bool($value)) {
            return 'boolean';
        }
        
        if (is_string($value)) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'email';
            }
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return 'url';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return 'date';
            }
        }
        
        $key_parts = explode('_', strtolower($key));
        return end($key_parts);
    }

    private function calculate_modularity($features) {
        $structural_modularity = 1 - ($features['structural']['depth'] / 10);
        $dependency_modularity = $this->calculate_dependency_modularity($features['relational']['dependencies']);
        $semantic_modularity = $this->calculate_semantic_modularity($features['semantic']);
        
        return ($structural_modularity * 0.3 + $dependency_modularity * 0.4 + $semantic_modularity * 0.3);
    }

    private function calculate_dependency_modularity($dependencies) {
        if (empty($dependencies)) {
            return 1.0;
        }
        
        $total_possible = count($dependencies) * (count($dependencies) - 1);
        $actual_deps = 0;
        
        foreach ($dependencies as $deps) {
            $actual_deps += count($deps);
        }
        
        return $total_possible ? 1 - ($actual_deps / $total_possible) : 1;
    }

    private function calculate_semantic_modularity($semantic_features) {
        $naming_consistency = $semantic_features['naming_consistency'];
        $contextual_relevance = $semantic_features['contextual_relevance'];
        
        return ($naming_consistency * 0.6 + $contextual_relevance * 0.4);
    }

    private function calculate_maintainability($features) {
        $complexity_factor = 1 - ($features['structural']['depth'] * $features['structural']['breadth'] / 100);
        $coupling_factor = $this->calculate_dependency_modularity($features['relational']['dependencies']);
        $cohesion_factor = $this->calculate_cluster_coherence($features['relational']['clusters']);
        
        return ($complexity_factor * 0.3 + $coupling_factor * 0.3 + $cohesion_factor * 0.4);
    }
}