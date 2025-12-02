<?php
/**
 * includes/comparison/class-aps-filters.php
 */
class APS_Filters {
    private $pre_filters = [];
    private $post_filters = [];

    public function __construct(array $pre_filters = [], array $post_filters = []) {
        $this->pre_filters = $pre_filters;
        $this->post_filters = $post_filters;

        if (empty($pre_filters) && empty($post_filters)) {
            $this->register_default_filters();
        }
    }

    private function register_default_filters() {
        $this->add_pre_filter('normalize_data', [$this, 'normalize_comparison_data']);
        $this->add_pre_filter('validate_structure', [$this, 'validate_data_structure']);
        $this->add_post_filter('clean_results', [$this, 'clean_comparison_results']);
        $this->add_post_filter('optimize_output', [$this, 'optimize_result_output']);
    }

    public function add_pre_filter($name, $callback) {
        $this->pre_filters[$name] = $callback;
    }

    public function add_post_filter($name, $callback) {
        $this->post_filters[$name] = $callback;
    }

    public function apply_pre_comparison_filters($data) {
        foreach ($this->pre_filters as $filter) {
            $data = call_user_func($filter, $data);
        }
        return $data;
    }

    public function apply_post_comparison_filters($results) {
        foreach ($this->post_filters as $filter) {
            $results = call_user_func($filter, $results);
        }
        return $results;
    }

    public function normalize_comparison_data($data) {
        if (!is_array($data)) {
            return $data;
        }

        $normalized = [];
        foreach ($data as $key => $value) {
            if (is_numeric($value)) {
                $normalized[$key] = self::normalize_numeric_value($value);
            } elseif (is_array($value)) {
                $normalized[$key] = $this->normalize_comparison_data($value);
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private static function normalize_numeric_value($value) {
        if ($value === 0) return 0;
        return ($value - min($value, 0)) / (max(abs($value), 1));
    }

    public function validate_data_structure($data) {
        // Data structure validation implementation
        return $data;
    }

    public function clean_comparison_results($results) {
        // Results cleaning implementation
        return $results;
    }

    public function optimize_result_output($results) {
        // Output optimization implementation
        return $results;
    }
}
