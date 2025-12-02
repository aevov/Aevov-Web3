<?php
/**
 * Task scheduling and recurring job management
 * 
 * @package APS
 * @subpackage Queue
 */

namespace APS\Queue;

use APS\DB\MetricsDB;
use APS\Monitoring\AlertManager;

class TaskScheduler {
    private $metrics;
    private $alert_manager;
    private $queue_manager;
    private $schedules = [];
    private $default_intervals = [
        'minutely' => 60,
        'five_minutes' => 300,
        'hourly' => 3600,
        'daily' => 86400,
        'weekly' => 604800
    ];

    public function __construct() {
        $this->metrics = new MetricsDB();
        $this->alert_manager = new AlertManager();
        $this->queue_manager = new QueueManager();
        $this->init_hooks();
        $this->register_default_tasks();
    }

    private function init_hooks() {
        add_action('init', [$this, 'schedule_recurring_tasks']);
        add_action('aps_run_scheduled_task', [$this, 'execute_task'], 10, 2);
        add_filter('cron_schedules', [$this, 'add_custom_schedules']);
        
        // Handle task scheduling events
        add_action('aps_schedule_task', [$this, 'schedule_task'], 10, 3);
        add_action('aps_unschedule_task', [$this, 'unschedule_task']);
        
        // Monitoring hooks
        add_action('aps_check_scheduled_tasks', [$this, 'monitor_scheduled_tasks']);
    }

    private function register_default_tasks() {
        // System maintenance tasks
        $this->register_task('pattern_cleanup', [
            'schedule' => 'daily',
            'callback' => [$this, 'cleanup_old_patterns'],
            'args' => ['days' => 30]
        ]);

        $this->register_task('metrics_aggregation', [
            'schedule' => 'hourly',
            'callback' => [$this, 'aggregate_metrics'],
            'args' => ['interval' => 'hour']
        ]);

        // Pattern processing tasks
        $this->register_task('pattern_distribution', [
            'schedule' => 'five_minutes',
            'callback' => [$this, 'distribute_patterns'],
            'args' => ['batch_size' => 100]
        ]);

        // Sync tasks
        $this->register_task('bloom_sync', [
            'schedule' => 'five_minutes',
            'callback' => [$this, 'sync_with_bloom'],
            'args' => ['full_sync' => false]
        ]);

        // Monitoring tasks
        $this->register_task('health_check', [
            'schedule' => 'minutely',
            'callback' => [$this, 'perform_health_check'],
            'args' => []
        ]);
    }

    public function register_task($name, $config) {
        if (!isset($config['schedule'], $config['callback'])) {
            throw new \Exception("Invalid task configuration for {$name}");
        }

        $this->schedules[$name] = [
            'name' => $name,
            'schedule' => $config['schedule'],
            'callback' => $config['callback'],
            'args' => $config['args'] ?? [],
            'next_run' => time() + $this->get_interval($config['schedule'])
        ];
    }

    public function schedule_task($name, $timestamp = null, $args = []) {
        if (!isset($this->schedules[$name])) {
            throw new \Exception("Unknown task: {$name}");
        }

        $timestamp = $timestamp ?? time();
        $task = $this->schedules[$name];

        wp_schedule_single_event(
            $timestamp,
            'aps_run_scheduled_task',
            [$name, $args]
        );

        $this->metrics->record_metric('task_scheduled', 1, [
            'task' => $name,
            'scheduled_time' => $timestamp
        ]);
    }

    public function execute_task($name, $args = []) {
        if (!isset($this->schedules[$name])) {
            throw new \Exception("Unknown task: {$name}");
        }

        $task = $this->schedules[$name];
        $start_time = microtime(true);

        try {
            // Execute task callback
            $result = call_user_func($task['callback'], $args);

            // Record success metrics
            $this->record_task_success($name, $start_time);

            // Schedule next run for recurring tasks
            if ($task['schedule']) {
                $this->schedule_next_run($name);
            }

            return $result;

        } catch (\Exception $e) {
            $this->handle_task_failure($name, $e, $start_time);
            throw $e;
        }
    }

    public function unschedule_task($name) {
        $timestamp = wp_next_scheduled('aps_run_scheduled_task', [$name]);
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'aps_run_scheduled_task', [$name]);
            
            $this->metrics->record_metric('task_unscheduled', 1, [
                'task' => $name
            ]);
        }
    }

    public function add_custom_schedules($schedules) {
        // Add minutely schedule
        $schedules['minutely'] = [
            'interval' => 60,
            'display' => __('Every Minute', 'aps')
        ];

        // Add five minute schedule
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => __('Every Five Minutes', 'aps')
        ];

        return $schedules;
    }

    public function monitor_scheduled_tasks() {
        foreach ($this->schedules as $name => $task) {
            $next_run = wp_next_scheduled('aps_run_scheduled_task', [$name]);
            
            if (!$next_run) {
                $this->alert_manager->trigger_alert('missed_scheduled_task', [
                    'task' => $name,
                    'last_run' => $task['last_run'] ?? null
                ]);

                // Attempt to reschedule
                $this->schedule_task($name);
            }
        }
    }

    private function schedule_next_run($name) {
        $task = $this->schedules[$name];
        $interval = $this->get_interval($task['schedule']);
        $next_run = time() + $interval;

        $this->schedule_task($name, $next_run, $task['args']);
        $this->schedules[$name]['next_run'] = $next_run;
    }

    private function get_interval($schedule) {
        return $this->default_intervals[$schedule] ?? 3600;
    }

    private function record_task_success($name, $start_time) {
        $duration = microtime(true) - $start_time;

        $this->metrics->record_metric('task_execution', $duration, [
            'task' => $name,
            'status' => 'success'
        ]);

        $this->schedules[$name]['last_run'] = time();
        $this->schedules[$name]['last_duration'] = $duration;
    }

    private function handle_task_failure($name, $error, $start_time) {
        $duration = microtime(true) - $start_time;

        $this->metrics->record_metric('task_execution', $duration, [
            'task' => $name,
            'status' => 'failed',
            'error' => $error->getMessage()
        ]);

        $this->alert_manager->trigger_alert('task_execution_failed', [
            'task' => $name,
            'error' => $error->getMessage(),
            'duration' => $duration
        ]);
    }

    public function get_task_schedule($name) {
        return $this->schedules[$name] ?? null;
    }

    public function get_all_schedules() {
        return $this->schedules;
    }

    public function get_task_metrics($name) {
        return [
            'last_run' => $this->schedules[$name]['last_run'] ?? null,
            'last_duration' => $this->schedules[$name]['last_duration'] ?? null,
            'next_run' => $this->schedules[$name]['next_run'] ?? null,
            'average_duration' => $this->metrics->get_metric_average(
                'task_execution',
                ['task' => $name]
            ),
            'failure_rate' => $this->calculate_failure_rate($name)
        ];
    }

    private function calculate_failure_rate($name) {
        $total = $this->metrics->get_metric_count(
            'task_execution',
            ['task' => $name]
        );

        if (!$total) {
            return 0;
        }

        $failures = $this->metrics->get_metric_count(
            'task_execution',
            ['task' => $name, 'status' => 'failed']
        );

        return ($failures / $total) * 100;
    }
}