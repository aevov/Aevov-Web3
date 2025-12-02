<?php
/**
 * Analyzes BLOOM patterns and their relationships across tensor chunks
 */
namespace BLOOM\Reports;

class BLOOM_Pattern_Analytics {
    private $pattern_model;
    private $tensor_model;
    private $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->pattern_model = new BLOOM_Pattern_Model();
        $this->tensor_model = new BLOOM_Tensor_Model();
    }

    public function analyze_pattern_distribution() {
        return [
            'pattern_types' => $this->get_pattern_type_distribution(),
            'pattern_confidence' => $this->analyze_confidence_distribution(),
            'pattern_relationships' => $this->analyze_pattern_relationships(),
            'tensor_coverage' => $this->analyze_tensor_coverage()
        ];
    }

    private function get_pattern_type_distribution() {
        return $this->db->get_results(
            "SELECT pattern_type, COUNT(*) as count, 
                    AVG(confidence) as avg_confidence,
                    MAX(confidence) as max_confidence
             FROM {$this->db->prefix}bloom_patterns 
             WHERE status = 'active'
             GROUP BY pattern_type",
            ARRAY_A
        );
    }

    private function analyze_confidence_distribution() {
        $ranges = [
            '0.95-1.00' => ['min' => 0.95, 'max' => 1.00],
            '0.85-0.95' => ['min' => 0.85, 'max' => 0.95],
            '0.75-0.85' => ['min' => 0.75, 'max' => 0.85],
            '0.00-0.75' => ['min' => 0.00, 'max' => 0.75]
        ];

        $distribution = [];
        foreach ($ranges as $label => $range) {
            $distribution[$label] = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) 
                 FROM {$this->db->prefix}bloom_patterns 
                 WHERE confidence >= %f AND confidence < %f",
                $range['min'],
                $range['max']
            ));
        }

        return $distribution;
    }

    private function analyze_tensor_coverage() {
        $tensors = $this->tensor_model->get_all();
        $coverage = [];

        foreach ($tensors as $tensor) {
            $coverage[$tensor['tensor_sku']] = [
                'total_chunks' => $tensor['total_chunks'],
                'processed_chunks' => $this->count_processed_chunks($tensor['tensor_sku']),
                'pattern_count' => $this->count_patterns_for_tensor($tensor['tensor_sku']),
                'average_confidence' => $this->get_tensor_pattern_confidence($tensor['tensor_sku'])
            ];
        }

        return $coverage;
    }

    private function analyze_pattern_relationships() {
        // Analyze how patterns relate across different tensor chunks
        $relationships = [];
        $patterns = $this->pattern_model->get_top_patterns(100);

        foreach ($patterns as $pattern) {
            $relationships[$pattern['pattern_hash']] = [
                'co_occurrences' => $this->find_pattern_co_occurrences($pattern['pattern_hash']),
                'tensor_distribution' => $this->get_pattern_tensor_distribution($pattern['pattern_hash']),
                'similarity_clusters' => $this->find_similar_patterns($pattern)
            ];
        }

        return $relationships;
    }

    private function find_pattern_co_occurrences($pattern_hash) {
        return $this->db->get_results($this->db->prepare(
            "SELECT p2.pattern_hash, COUNT(*) as count
             FROM {$this->db->prefix}bloom_patterns p1
             JOIN {$this->db->prefix}bloom_patterns p2 
             ON p1.tensor_sku = p2.tensor_sku
             WHERE p1.pattern_hash = %s 
             AND p2.pattern_hash != %s
             GROUP BY p2.pattern_hash
             HAVING count > 1
             ORDER BY count DESC
             LIMIT 10",
            $pattern_hash,
            $pattern_hash
        ), ARRAY_A);
    }

    private function find_similar_patterns($pattern) {
        $pattern_data = json_decode($pattern['pattern_data'], true);
        return $this->pattern_model->find_similar_patterns(
            $pattern_data,
            0.85
        );
    }

    private function count_processed_chunks($tensor_sku) {
        return $this->db->get_var($this->db->prepare(
            "SELECT COUNT(DISTINCT chunk_index) 
             FROM {$this->db->prefix}bloom_chunks 
             WHERE tensor_sku = %s AND status = 'processed'",
            $tensor_sku
        ));
    }

    private function get_tensor_pattern_confidence($tensor_sku) {
        return $this->db->get_var($this->db->prepare(
            "SELECT AVG(confidence) 
             FROM {$this->db->prefix}bloom_patterns 
             WHERE tensor_sku = %s",
            $tensor_sku
        ));
    }
}