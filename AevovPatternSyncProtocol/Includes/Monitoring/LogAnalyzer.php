<?php
/**
 * Advanced log analysis and reporting
 * 
 * @package APS
 * @subpackage Monitoring
 */

namespace APS\Monitoring;

use APS\Core\Logger;
use APS\DB\MetricsDB;
use APS\Monitoring\AlertManager;

class LogAnalyzer {
    private $logger;
    private $metrics;
    private $alert_manager;

    public function __construct(Logger $logger, MetricsDB $metrics, AlertManager $alert_manager) {
        $this->logger = $logger;
        $this->metrics = $metrics;
        $this->alert_manager = $alert_manager;
    }

    public function analyze_logs() {
        $logs = $this->logger->get_logs();
        $this->analyze_error_logs($logs);
        $this->analyze_performance_logs($logs);
        $this->analyze_system_logs($logs);
    }

    private function analyze_error_logs($logs) {
        $error_count = 0;
        $critical_errors = [];

        foreach ($logs as $log) {
            if ($log['log_level'] === 'error') {
                $error_count++;
                if ($log['log_level'] === 'critical') {
                    $critical_errors[] = $log;
                }
            }
        }

        $this->metrics->record_metric('logs', 'errors', $error_count);
        $this->metrics->record_metric('logs', 'critical_errors', count($critical_errors));

        if (count($critical_errors) > 0) {
            $this->alert_manager->trigger_alert('critical_errors', [
                'count' => count($critical_errors),
                'errors' => $critical_errors
            ]);
        }
    }

    private function analyze_performance_logs($logs) {
        $processing_times = [];
        $queue_delays = [];

        foreach ($logs as $log) {
            if ($log['log_type'] === 'pattern_processing') {
                $processing_times[] = $log['log_context']['processing_time'];
            } elseif ($log['log_type'] === 'queue_delay') {
                $queue_delays[] = $log['log_context']['delay'];
            }
        }

        if (!empty($processing_times)) {
            $this->metrics->record_metric('logs', 'pattern_processing_time', array_sum($processing_times) / count($processing_times));
        }

        if (!empty($queue_delays)) {
            $this->metrics->record_metric('logs', 'queue_delay', array_sum($queue_delays) / count($queue_delays));
        }
    }

    private function analyze_system_logs($logs) {
        $system_events = [];

        foreach ($logs as $log) {
            if ($log['log_type'] === 'system_event') {
                $system_events[] = $log;
            }
        }

        $this->metrics->record_metric('logs', 'system_events', count($system_events));

        if (count($system_events) > 0) {
            $this->alert_manager->trigger_alert('system_events', [
                'count' => count($system_events),
                'events' => $system_events
            ]);
        }
    }

    public function generate_log_report($type = 'all', $start_date = null, $end_date = null) {
        $logs = $this->logger->get_logs($type, null, null, 100, 0);
        $report = [
            'timestamp' => current_time('mysql'),
            'log_type' => $type,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'logs' => $logs
        ];

        return $report;
    }
}