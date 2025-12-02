<?php

namespace APS\Comparison\Engines;

use APS\Comparison\Engines\PatternEngine;
use APS\Comparison\Engines\TensorEngine;
use APS\Comparison\Engines\SymbolicPatternEngine;
use APS\Analysis\EmbeddingGenerator;
use APS\DB\APS_Cache;
use APS\Rules\APS_Rules;

class APS_Hybrid_Engine {
    private $pattern_engine;
    private $tensor_engine;
    private $symbolic_engine;
    private $embedding_generator;
    private $cache;
    private $rules_engine;
    
    public function __construct(
        PatternEngine $pattern_engine,
        TensorEngine $tensor_engine,
        SymbolicPatternEngine $symbolic_engine,
        EmbeddingGenerator $embedding_generator,
        APS_Cache $cache,
        APS_Rules $rules_engine
    ) {
        $this->pattern_engine = $pattern_engine;
        $this->tensor_engine = $tensor_engine;
        $this->symbolic_engine = $symbolic_engine;
        $this->embedding_generator = $embedding_generator;
        $this->cache = $cache;
        $this->rules_engine = $rules_engine;
    }

    public function compare($items, $options = []) {
        list($pattern_items, $tensor_items, $symbolic_items) = $this->separate_items($items);
        
        $pattern_results = !empty($pattern_items) ? 
            $this->pattern_engine->compare($pattern_items, $options) : null;
        
        $tensor_results = !empty($tensor_items) ? 
            $this->tensor_engine->compare($tensor_items, $options) : null;
        
        $symbolic_results = !empty($symbolic_items) ?
            $this->symbolic_engine->compare($symbolic_items, $options) : null;
        
        return $this->combine_results(
            $pattern_results,
            $tensor_results,
            $symbolic_results,
            $items,
            $options
        );
    }

    private function separate_items($items) {
        $patterns = [];
        $tensors = [];
        $symbolic_patterns = [];

        foreach ($items as $item) {
            if (isset($item['type']) && $item['type'] === 'symbolic_pattern') {
                $symbolic_patterns[] = $item;
            } else if ($this->is_tensor($item)) {
                $tensors[] = $item;
            } else {
                $patterns[] = $item;
            }
        }

        return [$patterns, $tensors, $symbolic_patterns];
    }

    private function combine_results($pattern_results, $tensor_results, $symbolic_results, $original_items, $options) { 
        $combined_matrix = $this->build_combined_matrix(
            $pattern_results,
            $tensor_results,
            count($original_items)
        );
    
        $structural_analysis = $this->combine_structural_analysis(
            $pattern_results,
            $tensor_results
        );
    
        $embeddings = $this->compute_hybrid_embeddings(
            $pattern_results,
            $tensor_results
        );
    
        $confidence_scores = $this->compute_confidence_scores(
            $combined_matrix,
            $structural_analysis
        );
    
        $combined_results = [
            'type' => 'hybrid',
            'similarity_matrix' => $combined_matrix,
            'structural_analysis' => $structural_analysis,
            'embeddings' => $embeddings,
            'confidence_scores' => $confidence_scores,
            'overall_score' => $this->calculate_overall_score($combined_matrix),
            'pattern_contribution' => $pattern_results ? $this->calculate_contribution($pattern_results) : 0,
            'tensor_contribution' => $tensor_results ? $this->calculate_contribution($tensor_results) : 0
        ];
        
        if ($symbolic_results) {
            $combined_results['symbolic_analysis'] = $symbolic_results;
            
            // Integrate symbolic analysis with other results
            if ($tensor_results || $pattern_results) {
                $combined_results['neuro_symbolic_integration'] = $this->integrate_symbolic_with_neural(
                    $symbolic_results,
                    $tensor_results,
                    $pattern_results
                );
            }
        }
    
        return $combined_results;
    }

    private function build_combined_matrix($pattern_results, $tensor_results, $total_items) {
        $matrix = array_fill(0, $total_items, array_fill(0, $total_items, 0));
        $pattern_indices = [];
        $tensor_indices = [];
        $current_index = 0;

        if ($pattern_results) {
            foreach ($pattern_results['similarity_matrix'] as $i => $row) {
                $pattern_indices[] = $current_index;
                $current_index++;
            }
        }

        if ($tensor_results) {
            foreach ($tensor_results['similarity_matrix'] as $i => $row) {
                $tensor_indices[] = $current_index;
                $current_index++;
            }
        }

        for ($i = 0; $i < $total_items; $i++) {
            for ($j = 0; $j < $total_items; $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = 1.0;
                    continue;
                }

                $matrix[$i][$j] = $this->determine_similarity(
                    $i, $j,
                    $pattern_results,
                    $tensor_results,
                    $pattern_indices,
                    $tensor_indices
                );
            }
        }

        return $matrix;
    }

    private function determine_similarity($i, $j, $pattern_results, $tensor_results, $pattern_indices, $tensor_indices) {
        $both_patterns = in_array($i, $pattern_indices) && in_array($j, $pattern_indices);
        $both_tensors = in_array($i, $tensor_indices) && in_array($j, $tensor_indices);

        if ($both_patterns) {
            $pi = array_search($i, $pattern_indices);
            $pj = array_search($j, $pattern_indices);
            return $pattern_results['similarity_matrix'][$pi][$pj];
        }

        if ($both_tensors) {
            $ti = array_search($i, $tensor_indices);
            $tj = array_search($j, $tensor_indices);
            return $tensor_results['similarity_matrix'][$ti][$tj];
        }

        return $this->compute_cross_type_similarity(
            $i,
            $j,
            $pattern_results,
            $tensor_results,
            $pattern_indices,
            $tensor_indices
        );
    }

    private function compute_cross_type_similarity($i, $j, $pattern_results, $tensor_results, $pattern_indices, $tensor_indices) {
        $features_i = $this->extract_features($i, $pattern_results, $tensor_results, $pattern_indices, $tensor_indices);
        $features_j = $this->extract_features($j, $pattern_results, $tensor_results, $pattern_indices, $tensor_indices);
        
        $similarity = $this->compute_feature_similarity($features_i, $features_j);
        $structural_similarity = $this->compute_structural_similarity($features_i, $features_j);
        
        return ($similarity * 0.7 + $structural_similarity * 0.3);
    }

    private function extract_features($index, $pattern_results, $tensor_results, $pattern_indices, $tensor_indices) {
        if (in_array($index, $pattern_indices)) {
            $pi = array_search($index, $pattern_indices);
            return [
                'type' => 'pattern',
                'features' => $pattern_results['embeddings'][$pi],
                'structure' => $pattern_results['structural_analysis'][$pi]
            ];
        }

        if (in_array($index, $tensor_indices)) {
            $ti = array_search($index, $tensor_indices);
            return [
                'type' => 'tensor',
                'features' => $tensor_results['embeddings'][$ti],
                'structure' => $tensor_results['structural_analysis'][$ti]
            ];
        }

        return null;
    }

    private function compute_feature_similarity($features_a, $features_b) {
        if (!$features_a || !$features_b) return 0;
        
        $vec_a = $features_a['features'];
        $vec_b = $features_b['features'];
        
        // Use the embedding generator to normalize vectors
        $normalized_vec_a = $this->embedding_generator->normalize_vector($vec_a);
        $normalized_vec_b = $this->embedding_generator->normalize_vector($vec_b);
        
        $dot_product = 0;
        $length = min(count($normalized_vec_a), count($normalized_vec_b));
        
        for ($i = 0; $i < $length; $i++) {
            $dot_product += $normalized_vec_a[$i] * $normalized_vec_b[$i];
        }
        
        return $dot_product;
    }

    private function compute_structural_similarity($features_a, $features_b) {
        if (!$features_a || !$features_b) return 0;

        $structure_a = $features_a['structure'];
        $structure_b = $features_b['structure'];

        $similarities = [
            $this->compare_statistics($structure_a['statistics'], $structure_b['statistics']),
            $this->compare_eigenvalues($structure_a['eigenvalues'], $structure_b['eigenvalues']),
            $this->compare_svd($structure_a['svd'], $structure_b['svd'])
        ];

        return array_sum($similarities) / count($similarities);
    }

    private function calculate_overall_score($matrix) {
        $n = count($matrix);
        $sum = 0;
        $count = 0;

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $sum += $matrix[$i][$j];
                $count++;
            }
        }

        return $count > 0 ? $sum / $count : 0;
    }

    private function is_tensor($item) {
        return isset($item['data']) && isset($item['shape']) && isset($item['dtype']);
    }

    private function calculate_contribution($results) {
        return isset($results['overall_score']) ? $results['overall_score'] : 0;
    }

    /**
    * Integrates symbolic analysis results with neural/tensor results
    * 
    * @param array $symbolic_results Results from symbolic pattern analysis
    * @param array $tensor_results Results from tensor analysis (can be null)
    * @param array $pattern_results Results from pattern analysis (can be null)
    * @return array Integrated analysis results
    */
    private function integrate_symbolic_with_neural($symbolic_results, $tensor_results, $pattern_results) {
        // Initialize the integration result structure
        $integration = [
            'integrated_matrix' => [],
            'confidence_weights' => [],
            'feature_alignment' => [],
            'cross_domain_mappings' => []
        ];
        
        // 1. Align symbolic and neural representations
        $aligned_features = $this->align_symbolic_neural_features(
            $symbolic_results,
            $tensor_results,
            $pattern_results
        );
        
        // 2. Create a weighted similarity matrix that considers both domains
        $integration['integrated_matrix'] = $this->create_integrated_similarity_matrix(
            $symbolic_results,
            $tensor_results,
            $pattern_results,
            $aligned_features
        );
        
        // 3. Calculate confidence weights for each domain based on data quality
        $integration['confidence_weights'] = $this->calculate_domain_confidence_weights(
            $symbolic_results,
            $tensor_results,
            $pattern_results
        );
        
        // 4. Generate cross-domain feature mappings
        $integration['cross_domain_mappings'] = $this->generate_cross_domain_mappings(
            $symbolic_results,
            $tensor_results,
            $pattern_results,
            $aligned_features
        );
    
        // 5. Store the feature alignment information
        $integration['feature_alignment'] = $aligned_features;
        
        return $integration;
    }

    /**
     * Aligns symbolic patterns with neural/tensor patterns
     */
    private function align_symbolic_neural_features($symbolic_results, $tensor_results, $pattern_results) {
        $aligned_features = [];
        
        // Get symbolic patterns
        $symbolic_patterns = isset($symbolic_results['matches']) ? $symbolic_results['matches'] : [];
        
        // Get neural patterns (from tensor or pattern results)
        $neural_patterns = [];
        if ($tensor_results) {
            $neural_patterns = array_merge($neural_patterns, $this->extract_neural_patterns($tensor_results));
        }
        if ($pattern_results) {
            $neural_patterns = array_merge($neural_patterns, $this->extract_neural_patterns($pattern_results));
        }
        
        // For each symbolic pattern, find the best matching neural pattern
        foreach ($symbolic_patterns as $s_idx => $symbolic_pattern) {
            $best_match = null;
            $best_score = 0;
            
            foreach ($neural_patterns as $n_idx => $neural_pattern) {
                // Calculate semantic similarity between symbolic and neural patterns
                $similarity = $this->calculate_cross_domain_similarity(
                    $symbolic_pattern,
                    $neural_pattern
                );
                
                if ($similarity > $best_score) {
                    $best_score = $similarity;
                    $best_match = [
                        'neural_idx' => $n_idx,
                        'similarity' => $similarity,
                        'neural_pattern' => $neural_pattern
                    ];
                }
            }
            
            if ($best_match && $best_score > 0.5) { // Threshold for meaningful alignment
                $aligned_features[$s_idx] = $best_match;
            }
        }
        
        return $aligned_features;
    }

    /**
     * Extracts neural patterns from results
     */
    private function extract_neural_patterns($results) {
        $patterns = [];
        
        if (isset($results['embeddings'])) {
            foreach ($results['embeddings'] as $idx => $embedding) {
                $pattern = [
                    'embedding' => $embedding,
                    'structure' => isset($results['structural_analysis'][$idx]) ? 
                        $results['structural_analysis'][$idx] : [],
                    'tensor_data' => ['features' => $embedding]
                ];
                
                // Use the rules engine to determine pattern type
                $type = $this->rules_engine->get_pattern_type($pattern);
                
                if ($type === 'neural' || $type === 'tensor') {
                    $patterns[] = $pattern;
                }
            }
        }
        
        return $patterns;
    }

    /**
     * Calculates similarity between symbolic and neural patterns
     */
    private function calculate_cross_domain_similarity($symbolic_pattern, $neural_pattern) {
        // Extract features from symbolic pattern
        $symbolic_features = isset($symbolic_pattern['components']) ? 
            $symbolic_pattern['components'] : [];
        
        // Extract features from neural pattern
        $neural_features = isset($neural_pattern['embedding']) ? 
            $neural_pattern['embedding'] : [];
        
        // If we have relations in the symbolic pattern, convert them to vectors
        if (isset($symbolic_features['relations'])) {
            $relation_vector = $this->convert_relations_to_vector($symbolic_features['relations']);
            
            // Use the embedding generator to combine embeddings with appropriate confidence
            if (!empty($relation_vector) && !empty($neural_features)) {
                $neural_confidence = 0.6; // Default confidence for neural patterns
                $combined_embedding = $this->embedding_generator->combine_embeddings(
                    $neural_features,
                    $relation_vector,
                    $neural_confidence
                );
                
                return $this->compute_feature_similarity(
                    ['features' => $combined_embedding],
                    ['features' => $neural_features]
                );
            }
        }
        
        // If we have rules in the symbolic pattern, compare with structure
        if (isset($symbolic_features['rules']) && isset($neural_pattern['structure'])) {
            $rules_count = count($symbolic_features['rules']);
            $structure_complexity = isset($neural_pattern['structure']['eigenvalues']) ? 
                count($neural_pattern['structure']['eigenvalues']) : 0;
            
            // More complex structures should align with more rules
            $complexity_ratio = min($rules_count, $structure_complexity) / 
                max(1, max($rules_count, $structure_complexity));
            
            return 0.3 + (0.7 * $complexity_ratio);
        }
        
        return 0.5; // Default similarity if no specific comparison is possible
    }

    /**
     * Creates an integrated similarity matrix that combines symbolic and neural results
     */
    private function create_integrated_similarity_matrix($symbolic_results, $tensor_results, $pattern_results, $aligned_features) {
        // Get the symbolic comparison matrix
        $symbolic_matrix = isset($symbolic_results['comparison_matrix']) ? 
            $symbolic_results['comparison_matrix'] : [];
        
        // First, build the neural matrix using the existing method
        $neural_matrix = $this->build_combined_matrix(
            $pattern_results,
            $tensor_results,
            count($symbolic_matrix)
        );
        
        // Now create an integrated matrix that combines symbolic and neural information
        $integrated_matrix = [];
        
        foreach ($symbolic_matrix as $key => $data) {
            list($i, $j) = explode('-', $key);
            $i = (int)$i;
            $j = (int)$j;
            
            $symbolic_score = $data['score'];
            $neural_score = isset($neural_matrix[$i][$j]) ? $neural_matrix[$i][$j] : 0.5;
            
            // Calculate alignment boost - if both items have aligned neural patterns, boost their similarity
            $alignment_boost = 0;
            if (isset($aligned_features[$i]) && isset($aligned_features[$j])) {
                $alignment_boost = 0.1; // Small boost for aligned features
            }
            
            // Weighted combination of symbolic and neural scores with alignment boost
            $weights = $this->calculate_domain_confidence_weights(
                $symbolic_results,
                $tensor_results,
                $pattern_results
            );
            
            $integrated_score = ($symbolic_score * $weights['symbolic']) + 
                            ($neural_score * $weights['neural']) +
                            $alignment_boost;
            
            // Cap at 1.0
            $integrated_score = min(1.0, $integrated_score);
            
            $integrated_matrix[$key] = [
                'score' => $integrated_score,
                'symbolic_score' => $symbolic_score,
                'neural_score' => $neural_score,
                'alignment_boost' => $alignment_boost
            ];
        }
        
        return $integrated_matrix;
    }

    /**
     * Gets neural similarity between two items
     */
    private function get_neural_similarity($i, $j, $neural_matrices, $aligned_features) {
        // Check if both items have aligned neural patterns
        if (isset($aligned_features[$i]) && isset($aligned_features[$j])) {
            $neural_i = $aligned_features[$i]['neural_idx'];
            $neural_j = $aligned_features[$j]['neural_idx'];
            
            // Find which matrix contains these indices
            foreach ($neural_matrices as $matrix) {
                if (isset($matrix[$neural_i]) && isset($matrix[$neural_i][$neural_j])) {
                    return $matrix[$neural_i][$neural_j];
                }
            }
        }
        
        return 0.5; // Default similarity if no alignment exists
    }

    /**
     * Calculates confidence weights for each domain
     */
    private function calculate_domain_confidence_weights($symbolic_results, $tensor_results, $pattern_results) {
        $weights = [
            'symbolic' => 0.5,
            'neural' => 0.5
        ];
        
        // Adjust weights based on result quality
        if ($symbolic_results && isset($symbolic_results['overall_score'])) {
            $weights['symbolic'] = min(0.8, max(0.2, $symbolic_results['overall_score']));
        }
        
        $neural_score = 0;
        $count = 0;
        
        if ($tensor_results && isset($tensor_results['score'])) {
            $neural_score += $tensor_results['score'];
            $count++;
        }
        
        if ($pattern_results && isset($pattern_results['overall_score'])) {
            $neural_score += $pattern_results['overall_score'];
            $count++;
        }
        
        if ($count > 0) {
            $weights['neural'] = min(0.8, max(0.2, $neural_score / $count));
        }
        
        // Normalize weights
        $total = $weights['symbolic'] + $weights['neural'];
        $weights['symbolic'] /= $total;
        $weights['neural'] /= $total;
        
        return $weights;
    }

    /**
     * Generates mappings between symbolic and neural features
     */
    private function generate_cross_domain_mappings($symbolic_results, $tensor_results, $pattern_results, $aligned_features) {
        $mappings = [];
        
        foreach ($aligned_features as $symbolic_idx => $alignment) {
            $neural_idx = $alignment['neural_idx'];
            $similarity = $alignment['similarity'];
            
            // Get symbolic pattern components
            $symbolic_pattern = isset($symbolic_results['matches'][$symbolic_idx]) ? 
                $symbolic_results['matches'][$symbolic_idx] : [];
            
            // Get neural pattern features
            $neural_pattern = $alignment['neural_pattern'];
            
            // Create mapping between symbolic components and neural features
            $mapping = [
                'symbolic_idx' => $symbolic_idx,
                'neural_idx' => $neural_idx,
                'confidence' => $similarity,
                'feature_mappings' => $this->map_features($symbolic_pattern, $neural_pattern)
            ];
            
            $mappings[] = $mapping;
        }
        
        return $mappings;
    }

    /**
     * Maps features between symbolic and neural patterns
     */
    private function map_features($symbolic_pattern, $neural_pattern) {
        $mappings = [];
        
        // Extract components from symbolic pattern
        $components = isset($symbolic_pattern['components']) ? 
            $symbolic_pattern['components'] : [];
        
        // Extract features from neural pattern
        $neural_features = isset($neural_pattern['embedding']) ? 
            $neural_pattern['embedding'] : [];
        
        // Map symbols to neural features
        if (isset($components['symbols']) && !empty($neural_features)) {
            $symbols = $components['symbols'];
            $feature_count = min(count($symbols), count($neural_features));
            
            for ($i = 0; $i < $feature_count; $i++) {
                $mappings[] = [
                    'symbolic' => $symbols[$i],
                    'neural' => $neural_features[$i],
                    'type' => 'symbol_to_feature'
                ];
            }
        }
        
        return $mappings;
    }

    /**
     * Converts relations to a numerical vector
     */
    private function convert_relations_to_vector($relations) {
        // Simple conversion of relations to a numerical vector
        $vector = [];
        
        foreach ($relations as $relation) {
            // Convert relation to a numerical value (hash-based approach)
            $hash = crc32(is_string($relation) ? $relation : json_encode($relation));
            $normalized = ($hash % 1000) / 1000; // Normalize to [0,1]
            $vector[] = $normalized;
        }
        
        // Pad to ensure minimum length
        while (count($vector) < 5) {
            $vector[] = 0;
        }
        
        return $this->embedding_generator->normalize_vector($vector);
    }


}