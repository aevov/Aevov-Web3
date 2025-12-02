<?php
/**
 * Debug Profiler
 *
 * Performance profiling and metrics
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Debug;

/**
 * Debug Profiler Class
 */
class DebugProfiler
{
    /**
     * Debug engine
     *
     * @var DebugEngine
     */
    private DebugEngine $debug_engine;

    /**
     * Active profiles
     *
     * @var array
     */
    private array $active_profiles = [];

    /**
     * Completed profiles
     *
     * @var array
     */
    private array $completed_profiles = [];

    /**
     * Constructor
     *
     * @param DebugEngine $debug_engine Debug engine
     */
    public function __construct(DebugEngine $debug_engine)
    {
        $this->debug_engine = $debug_engine;
    }

    /**
     * Start profiling
     *
     * @param string $profile_name Profile name
     * @param array $metadata Profile metadata
     * @return void
     */
    public function start(string $profile_name, array $metadata = []): void
    {
        $this->active_profiles[$profile_name] = [
            'name' => $profile_name,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'metadata' => $metadata
        ];
    }

    /**
     * Stop profiling
     *
     * @param string $profile_name Profile name
     * @return array Profile results
     */
    public function stop(string $profile_name): array
    {
        if (!isset($this->active_profiles[$profile_name])) {
            return [];
        }

        $profile = $this->active_profiles[$profile_name];
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);

        $result = [
            'name' => $profile_name,
            'duration' => $end_time - $profile['start_time'],
            'duration_ms' => (int) (($end_time - $profile['start_time']) * 1000),
            'memory_usage' => $end_memory - $profile['start_memory'],
            'peak_memory' => memory_get_peak_usage(true),
            'metadata' => $profile['metadata'],
            'start_time' => $profile['start_time'],
            'end_time' => $end_time
        ];

        unset($this->active_profiles[$profile_name]);
        $this->completed_profiles[] = $result;

        // Keep only last 100 completed profiles
        if (count($this->completed_profiles) > 100) {
            $this->completed_profiles = array_slice($this->completed_profiles, -100);
        }

        // Save to database
        $this->save_profile($result);

        return $result;
    }

    /**
     * Save profile to database
     *
     * @param array $profile Profile data
     * @return void
     */
    private function save_profile(array $profile): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_performance';

        $wpdb->insert($table, [
            'operation' => $profile['name'],
            'duration_ms' => $profile['duration_ms'],
            'memory_used' => $profile['memory_usage'],
            'peak_memory' => $profile['peak_memory'],
            'metadata' => wp_json_encode($profile['metadata']),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Get active profiles
     *
     * @return array
     */
    public function get_active_profiles(): array
    {
        return array_values($this->active_profiles);
    }

    /**
     * Get results
     *
     * @param string|null $profile_name Specific profile name
     * @return array
     */
    public function get_results(?string $profile_name = null): array
    {
        if ($profile_name) {
            return array_filter($this->completed_profiles, function ($profile) use ($profile_name) {
                return $profile['name'] === $profile_name;
            });
        }

        return $this->completed_profiles;
    }

    /**
     * Get statistics
     *
     * @param string $profile_name Profile name
     * @param int $limit Number of recent profiles to analyze
     * @return array
     */
    public function get_statistics(string $profile_name, int $limit = 100): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_performance';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT duration_ms, memory_used, peak_memory
             FROM {$table}
             WHERE operation = %s
             ORDER BY created_at DESC
             LIMIT %d",
            $profile_name,
            $limit
        ), ARRAY_A);

        if (empty($results)) {
            return [];
        }

        $durations = array_column($results, 'duration_ms');
        $memory = array_column($results, 'memory_used');

        return [
            'operation' => $profile_name,
            'count' => count($results),
            'duration' => [
                'min' => min($durations),
                'max' => max($durations),
                'avg' => array_sum($durations) / count($durations),
                'median' => $this->calculate_median($durations)
            ],
            'memory' => [
                'min' => min($memory),
                'max' => max($memory),
                'avg' => array_sum($memory) / count($memory),
                'median' => $this->calculate_median($memory)
            ]
        ];
    }

    /**
     * Calculate median
     *
     * @param array $values Values
     * @return float
     */
    private function calculate_median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Get slow operations
     *
     * @param int $threshold_ms Threshold in milliseconds
     * @param int $limit Limit
     * @return array
     */
    public function get_slow_operations(int $threshold_ms = 1000, int $limit = 20): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_performance';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$table}
             WHERE duration_ms > %d
             ORDER BY duration_ms DESC
             LIMIT %d",
            $threshold_ms,
            $limit
        ), ARRAY_A);
    }
}
