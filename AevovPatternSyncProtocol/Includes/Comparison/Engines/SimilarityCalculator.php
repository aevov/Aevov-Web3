<?php
/**
 * Pattern similarity calculation and comparison
 * 
 * @package APS
 * @subpackage Comparison\Engines
 */

namespace APS\Comparison\Engines;

use APS\Comparison\Engines\VectorProcessor;

class SimilarityCalculator {
    private $vector_processor;

    public function __construct(VectorProcessor $vector_processor) {
        $this->vector_processor = $vector_processor;
    }

    public function calculate_feature_similarity($features_a, $features_b) {
        $vec_a = $features_a['features'];
        $vec_b = $features_b['features'];
        
        $dot_product = 0;
        $norm_a = 0;
        $norm_b = 0;

        for ($i = 0; $i < count($vec_a); $i++) {
            $dot_product += $vec_a[$i] * $vec_b[$i];
            $norm_a += $vec_a[$i] * $vec_a[$i];
            $norm_b += $vec_b[$i] * $vec_b[$i];
        }

        $norm_a = sqrt($norm_a);
        $norm_b = sqrt($norm_b);

        if ($norm_a == 0 || $norm_b == 0) return 0;

        return $dot_product / ($norm_a * $norm_b);
    }

    public function calculate_structural_similarity($features_a, $features_b) {
        $structure_a = $features_a['structure'];
        $structure_b = $features_b['structure'];

        $similarities = [
            $this->compare_statistics($structure_a['statistics'], $structure_b['statistics']),
            $this->compare_eigenvalues($structure_a['eigenvalues'], $structure_b['eigenvalues']),
            $this->compare_svd($structure_a['svd'], $structure_b['svd'])
        ];

        return array_sum($similarities) / count($similarities);
    }

    private function compare_statistics($stats_a, $stats_b) {
        $similarity = 0;
        $similarity += 1 - abs($stats_a['mean'] - $stats_b['mean']) / max(abs($stats_a['mean']), abs($stats_b['mean']));
        $similarity += 1 - abs($stats_a['variance'] - $stats_b['variance']) / max(abs($stats_a['variance']), abs($stats_b['variance']));
        $similarity += 1 - abs($stats_a['skewness'] - $stats_b['skewness']) / max(abs($stats_a['skewness']), abs($stats_b['skewness']));
        $similarity += 1 - abs($stats_a['kurtosis'] - $stats_b['kurtosis']) / max(abs($stats_a['kurtosis']), abs($stats_b['kurtosis']));
        return $similarity / 4;
    }

    private function compare_eigenvalues($ev_a, $ev_b) {
        $similarity = 0;
        for ($i = 0; $i < min(count($ev_a), count($ev_b)); $i++) {
            $similarity += 1 - abs($ev_a[$i] - $ev_b[$i]) / max(abs($ev_a[$i]), abs($ev_b[$i]));
        }
        return $similarity / count($ev_a);
    }

    private function compare_svd($svd_a, $svd_b) {
        $similarity = 0;
        $similarity += 1 - abs(count($svd_a['U']) - count($svd_b['U'])) / max(count($svd_a['U']), count($svd_b['U']));
        $similarity += 1 - abs(count($svd_a['S']) - count($svd_b['S'])) / max(count($svd_a['S']), count($svd_b['S']));
        $similarity += 1 - abs(count($svd_a['V']) - count($svd_b['V'])) / max(count($svd_a['V']), count($svd_b['V']));
        return $similarity / 3;
    }
}