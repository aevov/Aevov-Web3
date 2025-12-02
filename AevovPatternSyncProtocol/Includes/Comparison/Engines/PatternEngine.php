<?php
/**
 * Pattern matching and comparison engine
 * 
 * @package APS
 * @subpackage Comparison\Engines
 */

namespace APS\Comparison\Engines;

use APS\DB\PatternDB;
use APS\Integration\BloomIntegration;
use APS\Monitoring\AlertManager;
use APS\DB\APS_Cache;

class PatternEngine {
    private $pattern_db;
    private $bloom_integration;
    private $alert_manager;
    private $cache;

    public function __construct(PatternDB $pattern_db, BloomIntegration $bloom_integration, AlertManager $alert_manager, APS_Cache $cache) {
        $this->pattern_db = $pattern_db;
        $this->bloom_integration = $bloom_integration;
        $this->alert_manager = $alert_manager;
        $this->cache = $cache;
    }
    
    private function extract_patterns($items) {
        $patterns = [];
        
        foreach ($items as $item) {
            $cache_key = 'pattern_' . md5(serialize($item));
            if ($cached = $this->cache->get($cache_key)) {
                $patterns[] = $cached;
                continue;
            }

            $extracted = $this->bloom_integration->analyze_pattern($item);
            $this->cache->set($cache_key, $extracted);
            $patterns[] = $extracted;
        }

        return $patterns;
    }

    private function build_comparison_matrix($patterns) {
        $matrix = [];
        $component_scores = []; 
        $count = count($patterns);
    
        for ($i = 0; $i < $count; $i++) {
            $matrix[$i] = [];
            $component_scores[$i] = [];
            
            for ($j = 0; $j < $count; $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = 1.0; // Self-comparison is always 1.0
                    $component_scores[$i][$j] = ['features' => 1.0, 'structure' => 1.0, 'confidence' => 1.0];
                } else if ($j > $i) { // Only calculate for upper triangle
                    $cache_key = $this->generate_comparison_cache_key($patterns[$i], $patterns[$j]);
                    if ($cached = $this->cache->get($cache_key)) {
                        $matrix[$i][$j] = $cached['score'];
                        $component_scores[$i][$j] = $cached['components'];
                    } else {
                        $result = $this->compare_pattern_pair($patterns[$i], $patterns[$j]);
                        $matrix[$i][$j] = $result['score'];
                        $component_scores[$i][$j] = $result['components'];
                        $this->cache->set($cache_key, $result);
                    }
                    // Mirror results for lower triangle
                    $matrix[$j][$i] = $matrix[$i][$j];
                    $component_scores[$j][$i] = $component_scores[$i][$j];
                }
            }
        }

        return ['matrix' => $matrix, 'component_scores' => $component_scores];
    }

    private function compare_pattern_pair($pattern1, $pattern2) {
        $similarities = [];
        $feature_scores = [];
        $structure_scores = [];
        $confidence_scores = [];

        foreach ($pattern1['features'] as $p1) {
            $best_match = 0;
            $best_feature_score = 0;
            $best_structure_score = 0;
            $best_confidence_score = 0;
            
            foreach ($pattern2['features'] as $p2) {
                $similarity = $this->calculate_feature_similarity($p1, $p2);
                if ($similarity > $best_match) {
                    $best_match = $similarity;
                    $best_feature_score = $this->compare_component($p1['features'], $p2['features']);
                    $best_structure_score = $this->compare_component($p1['structure'], $p2['structure']);
                    $best_confidence_score = min($p1['confidence'], $p2['confidence']);
                }
            }

            $similarities[] = $best_match;
            $feature_scores[] = $best_feature_score;
            $structure_scores[] = $best_structure_score;
            $confidence_scores[] = $best_confidence_score;
        }

        return [
            'score' => array_sum($similarities) / max(1, count($similarities)), // Prevent division by zero
            'components' => [
                'features' => array_sum($feature_scores) / max(1, count($feature_scores)),
                'structure' => array_sum($structure_scores) / max(1, count($structure_scores)),
                'confidence' => array_sum($confidence_scores) / max(1, count($confidence_scores))
            ]
        ];
    }

    private function calculate_feature_similarity($f1, $f2) {
        return ($this->compare_component($f1['features'], $f2['features']) * 0.4 +
                $this->compare_component($f1['structure'], $f2['structure']) * 0.4 +
                min($f1['confidence'], $f2['confidence']) * 0.2);
    }

    private function compare_component($set1, $set2) {
        if (empty($set1) && empty($set2)) return 1.0;
        if (empty($set1) || empty($set2)) return 0.0;
        return count(array_intersect($set1, $set2)) / max(count($set1), count($set2));
    }

    private function generate_comparison_cache_key($pattern1, $pattern2) {
        $id1 = $pattern1['id'] ?? md5(json_encode($pattern1));
        $id2 = $pattern2['id'] ?? md5(json_encode($pattern2));
        return 'pattern_comparison_' . implode('_', array_sort([$id1, $id2]));
    }

    public function compare($items) {
        $patterns = $this->extract_patterns($items);
        $comparison_data = $this->build_comparison_matrix($patterns);
        
        return [
            'type' => 'pattern',
            'matrix' => $comparison_data['matrix'],
            'component_scores' => $comparison_data['component_scores'],
            'score' => $this->calculate_overall_score($comparison_data['matrix']),
            'matches' => $this->identify_pattern_matches($comparison_data['matrix'])
        ];
    }

    private function identify_pattern_matches($matrix) {
        $threshold = get_option('aps_pattern_match_threshold') ?? 0.75; // Default to 0.75
        $matches = [];

        foreach ($matrix as $i => $row) {
            foreach ($row as $j => $score) {
                if ($i < $j && $score >= $threshold) {
                    $matches[] = ['pair' => [$i, $j], 'score' => $score];
                }
            }
        }
        return $matches;
    }

    private function calculate_overall_score($matrix) {
        $sum = 0;
        $count = 0;

        foreach ($matrix as $i => $row) {
            foreach ($row as $j => $score) {
                if ($i < $j) {
                    $sum += $score;
                    $count++;
                }
            }
        }

        return $count > 0 ? $sum / $count : 0;
    }
}
