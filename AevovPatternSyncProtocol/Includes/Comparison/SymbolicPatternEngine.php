<?php

class SymbolicPatternEngine {
    protected $db;
    protected $cache;
    protected $weights = [
        'symbols' => 0.4,
        'relations' => 0.3,
        'rules' => 0.2,
        'features' => 0.1
    ];

    protected $similarityThreshold = 0.75;

    public function __construct(PatternDB $db, APS_Cache $cache) {
        $this->db = $db;
        $this->cache = $cache;
    }

    public function compare($patterns, $options = []) {
        $matrix = $this->build_comparison_matrix($patterns);
        return [
            'matches' => $this->identify_pattern_matches($matrix),
            'overall_score' => $this->calculate_overall_score($matrix),
            'comparison_matrix' => $matrix
        ];
    }

    private function build_comparison_matrix($patterns) {
        $count = count($patterns);
        $matrix = [];
        $component_scores = []; // Store component scores
        
        for ($i = 0; $i < $count; $i++) {
            $matrix[$i] = [];
            $component_scores[$i] = [];
            for ($j = 0; $j < $count; $j++) {
                $component_scores[$i][$j] = [];
                if ($i == $j) {
                    $matrix[$i][$j] = 1.0; // Self-comparison is always 1.0
                    $component_scores[$i][$j] = [
                        'symbols' => 1.0,
                        'relations' => 1.0,
                        'rules' => 1.0,
                        'features' => 1.0
                    ];
                } else if ($j > $i) { // Only calculate for j > i (upper triangle)
                    // Check cache first
                    $cache_key = $this->generate_cache_key($patterns[$i], $patterns[$j]);
                    $cached_result = $this->cache->get($cache_key);
                    
                    if ($cached_result !== false) {
                        $matrix[$i][$j] = $cached_result['score'];
                        $component_scores[$i][$j] = $cached_result['components'];
                        // Mirror the result for the lower triangle
                        $matrix[$j][$i] = $matrix[$i][$j];
                        $component_scores[$j][$i] = $component_scores[$i][$j];
                        continue;
                    }
                    
                    // Calculate and store component scores
                    $symbols = $this->compare_symbols($patterns[$i]['symbols'] ?? [], $patterns[$j]['symbols'] ?? []);
                    $relations = $this->compare_relations($patterns[$i]['relations'] ?? [], $patterns[$j]['relations'] ?? []);
                    $rules = $this->compare_rules($patterns[$i]['rules'] ?? [], $patterns[$j]['rules'] ?? []);
                    $features = $this->compare_features($patterns[$i]['features'] ?? [], $patterns[$j]['features'] ?? []);
                    
                    $component_scores[$i][$j] = [
                        'symbols' => $symbols,
                        'relations' => $relations,
                        'rules' => $rules,
                        'features' => $features
                    ];
                    
                    // Calculate overall similarity
                    $weights = [
                        'symbols' => 0.3,
                        'relations' => 0.3,
                        'rules' => 0.2,
                        'features' => 0.2
                    ];
                    
                    $matrix[$i][$j] = 
                        $symbols * $weights['symbols'] +
                        $relations * $weights['relations'] +
                        $rules * $weights['rules'] +
                        $features * $weights['features'];
                    
                    // Cache the result
                    $this->cache->set($cache_key, [
                        'score' => $matrix[$i][$j],
                        'components' => $component_scores[$i][$j]
                    ]);
                    
                    // Mirror the result for the lower triangle
                    $matrix[$j][$i] = $matrix[$i][$j];
                    $component_scores[$j][$i] = $component_scores[$i][$j];
                }
            }
        }
        
        return ['matrix' => $matrix, 'component_scores' => $component_scores];
    }
    
    private function generate_cache_key($pattern1, $pattern2) {
        // Extract identifiers or hash the patterns
        $id1 = $pattern1['id'] ?? md5(serialize($pattern1));
        $id2 = $pattern2['id'] ?? md5(serialize($pattern2));
        
        // Sort to ensure the same key regardless of order
        $ids = [$id1, $id2];
        sort($ids);
        
        return 'symbolic_comparison_' . implode('_', $ids);
    }
    
        private function compare_symbolic_patterns($pattern1, $pattern2) {
            $key = $this->generate_cache_key($pattern1, $pattern2);
            if ($cached = $this->cache->get($key)) return $cached;
    
            $components = ['symbols', 'relations', 'rules', 'features'];
            $scores = [];
    
            foreach ($components as $component) {
                $scores[$component] = $this->compare_component($pattern1[$component] ?? [], $pattern2[$component] ?? []);
            }
    
            $totalScore = array_sum(array_map(fn($comp) => $scores[$comp] * $this->weights[$comp], array_keys($scores)));
    
            $result = ['score' => $totalScore, 'components' => $scores];
            $this->cache->set($key, $result);
            return $result;
        }
    
        private function compare_component($set1, $set2) {
            if (empty($set1) && empty($set2)) return 1.0;
            if (empty($set1) || empty($set2)) return 0.0;
    
            $matches = count(array_intersect($set1, $set2));
            return $matches / max(count($set1), count($set2));
        }
    
        private function identify_pattern_matches($matrix) {
            $matches = [];
            foreach ($matrix as $key => $data) {
                if ($data['score'] >= $this->similarityThreshold) {
                    $matches[] = ['pair' => $key, 'score' => $data['score']];
                }
            }
            return $matches;
        }
    
        private function calculate_overall_score($matrix) {
            $total = array_sum(array_column($matrix, 'score'));
            return count($matrix) ? $total / count($matrix) : 0;
        }
    
        private function extract_key_data($pattern) {
            return [
                'symbols' => array_values(array_unique($pattern['symbols'] ?? [])),
                'relations' => array_values(array_unique($pattern['relations'] ?? [])),
                'rules' => array_values(array_unique($pattern['rules'] ?? []))
            ];
        }
    }
    