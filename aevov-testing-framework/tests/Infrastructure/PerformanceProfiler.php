<?php
/**
 * Performance Profiler
 * Tracks and analyzes performance metrics for tests
 */

namespace AevovTesting\Infrastructure;

class PerformanceProfiler {

    private static $instance = null;
    private $profiles = [];
    private $current_profile = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Start profiling a named operation
     */
    public function start($name) {
        $this->current_profile = $name;
        $this->profiles[$name] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_peak_memory' => memory_get_peak_usage(true),
            'queries_start' => $this->getQueryCount(),
        ];
    }

    /**
     * End profiling current operation
     */
    public function end($name = null) {
        $name = $name ?? $this->current_profile;

        if (!isset($this->profiles[$name])) {
            return null;
        }

        $profile = &$this->profiles[$name];
        $profile['end_time'] = microtime(true);
        $profile['end_memory'] = memory_get_usage(true);
        $profile['end_peak_memory'] = memory_get_peak_usage(true);
        $profile['queries_end'] = $this->getQueryCount();

        // Calculate metrics
        $profile['duration'] = $profile['end_time'] - $profile['start_time'];
        $profile['memory_used'] = $profile['end_memory'] - $profile['start_memory'];
        $profile['peak_memory'] = $profile['end_peak_memory'] - $profile['start_peak_memory'];
        $profile['query_count'] = $profile['queries_end'] - $profile['queries_start'];

        $this->current_profile = null;

        return $profile;
    }

    /**
     * Get current database query count
     */
    private function getQueryCount() {
        global $wpdb;
        return isset($wpdb->num_queries) ? $wpdb->num_queries : 0;
    }

    /**
     * Get profile results
     */
    public function getProfile($name) {
        return $this->profiles[$name] ?? null;
    }

    /**
     * Get all profiles
     */
    public function getAllProfiles() {
        return $this->profiles;
    }

    /**
     * Clear all profiles
     */
    public function clear() {
        $this->profiles = [];
        $this->current_profile = null;
    }

    /**
     * Generate performance report
     */
    public function generateReport() {
        $report = [
            'total_profiles' => count($this->profiles),
            'profiles' => [],
            'summary' => [
                'total_duration' => 0,
                'total_memory' => 0,
                'total_queries' => 0,
                'slowest_operation' => null,
                'most_memory_intensive' => null,
                'most_queries' => null,
            ],
        ];

        $slowest_time = 0;
        $most_memory = 0;
        $most_queries = 0;

        foreach ($this->profiles as $name => $profile) {
            $report['profiles'][$name] = [
                'duration_ms' => round($profile['duration'] * 1000, 2),
                'memory_mb' => round($profile['memory_used'] / 1024 / 1024, 2),
                'peak_memory_mb' => round($profile['peak_memory'] / 1024 / 1024, 2),
                'query_count' => $profile['query_count'],
            ];

            $report['summary']['total_duration'] += $profile['duration'];
            $report['summary']['total_memory'] += $profile['memory_used'];
            $report['summary']['total_queries'] += $profile['query_count'];

            if ($profile['duration'] > $slowest_time) {
                $slowest_time = $profile['duration'];
                $report['summary']['slowest_operation'] = $name;
            }

            if ($profile['memory_used'] > $most_memory) {
                $most_memory = $profile['memory_used'];
                $report['summary']['most_memory_intensive'] = $name;
            }

            if ($profile['query_count'] > $most_queries) {
                $most_queries = $profile['query_count'];
                $report['summary']['most_queries'] = $name;
            }
        }

        $report['summary']['total_duration_ms'] = round($report['summary']['total_duration'] * 1000, 2);
        $report['summary']['total_memory_mb'] = round($report['summary']['total_memory'] / 1024 / 1024, 2);

        return $report;
    }

    /**
     * Benchmark a callable
     */
    public function benchmark($callable, $iterations = 100, $name = null) {
        $name = $name ?? 'benchmark_' . uniqid();

        $times = [];
        $memories = [];
        $queries_counts = [];

        for ($i = 0; $i < $iterations; $i++) {
            $this->start($name . '_' . $i);
            call_user_func($callable);
            $profile = $this->end($name . '_' . $i);

            $times[] = $profile['duration'];
            $memories[] = $profile['memory_used'];
            $queries_counts[] = $profile['query_count'];
        }

        return [
            'iterations' => $iterations,
            'time' => [
                'min' => min($times) * 1000,
                'max' => max($times) * 1000,
                'avg' => array_sum($times) / count($times) * 1000,
                'median' => $this->median($times) * 1000,
                'stddev' => $this->stddev($times) * 1000,
            ],
            'memory' => [
                'min' => min($memories) / 1024,
                'max' => max($memories) / 1024,
                'avg' => array_sum($memories) / count($memories) / 1024,
            ],
            'queries' => [
                'min' => min($queries_counts),
                'max' => max($queries_counts),
                'avg' => array_sum($queries_counts) / count($queries_counts),
            ],
        ];
    }

    /**
     * Calculate median
     */
    private function median($values) {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2) {
            return $values[$middle];
        } else {
            return ($values[$middle - 1] + $values[$middle]) / 2.0;
        }
    }

    /**
     * Calculate standard deviation
     */
    private function stddev($values) {
        $avg = array_sum($values) / count($values);
        $variance = 0.0;

        foreach ($values as $value) {
            $variance += pow($value - $avg, 2);
        }

        $variance /= count($values);
        return sqrt($variance);
    }

    /**
     * Compare two benchmarks
     */
    public function compare($benchmark1, $benchmark2) {
        $time_diff_pct = (($benchmark2['time']['avg'] - $benchmark1['time']['avg']) / $benchmark1['time']['avg']) * 100;
        $memory_diff_pct = (($benchmark2['memory']['avg'] - $benchmark1['memory']['avg']) / $benchmark1['memory']['avg']) * 100;

        return [
            'time_difference_pct' => round($time_diff_pct, 2),
            'memory_difference_pct' => round($memory_diff_pct, 2),
            'faster' => $time_diff_pct < 0,
            'more_efficient' => $memory_diff_pct < 0,
        ];
    }
}
