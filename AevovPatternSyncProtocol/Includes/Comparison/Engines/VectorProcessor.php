<?php
/**
 * Feature vector processing and normalization
 * 
 * @package APS
 * @subpackage Comparison\Engines
 */

namespace APS\Comparison\Engines;

class VectorProcessor {
    public function normalize_features($features) {
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

    public function normalize_vectors($vectors) {
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

    public function compute_vector_statistics($vectors) {
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
}