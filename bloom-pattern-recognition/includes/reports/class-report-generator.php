<?php
/**
 * Generates comprehensive reports for BLOOM pattern analysis
 */

namespace BLOOM\Reports;

use BLOOM\Monitoring\BLOOM_Metrics_Collector;

class BLOOM_Report_Generator {
    private $pattern_analytics;
    private $metrics_collector;
    private $exporters = [];
    
    public function __construct() {
        $this->pattern_analytics = new BLOOM_Pattern_Analytics();
        $this->metrics_collector = new BLOOM_Metrics_Collector();
        
        // Register exporters
        $this->register_exporters();
        
        add_action('admin_post_generate_bloom_report', [$this, 'handle_report_generation']);
    }

    public function generate_report($type = 'full', $format = 'json') {
        $report_data = [
            'timestamp' => current_time('mysql'),
            'report_type' => $type,
            'system_stats' => $this->get_system_stats(),
            'pattern_analysis' => $this->get_pattern_analysis(),
            'network_status' => $this->get_network_status()
        ];

        if ($this->exporters[$format]) {
            return $this->exporters[$format]->export($report_data);
        }

        return $report_data;
    }

    private function register_exporters() {
        $this->exporters = [
            'json' => new BLOOM_JSON_Exporter(),
            'csv' => new BLOOM_CSV_Exporter(),
            'pdf' => new BLOOM_PDF_Exporter()
        ];
    }

    private function get_system_stats() {
        return [
            'performance' => $this->metrics_collector->collect_performance_metrics(),
            'resources' => $this->metrics_collector->collect_resource_metrics(),
            'processing' => [
                'total_patterns' => $this->count_total_patterns(),
                'active_patterns' => $this->count_active_patterns(),
                'pattern_distribution' => $this->get_pattern_distribution(),
                'processing_rate' => $this->calculate_processing_rate()
            ]
        ];
    }

    private function get_pattern_analysis() {
        return $this->pattern_analytics->analyze_pattern_distribution();
    }

    private function get_network_status() {
        $network_manager = new BLOOM_Network_Manager();
        return [
            'active_sites' => $network_manager->get_active_sites(),
            'distribution' => $network_manager->get_pattern_distribution(),
            'sync_status' => $network_manager->get_sync_status(),
            'load_balance' => $network_manager->get_load_distribution()
        ];
    }

    public function handle_report_generation() {
        check_admin_referer('generate_bloom_report');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'bloom-pattern-system'));
        }

        $type = sanitize_text_field($_POST['report_type'] ?? 'full');
        $format = sanitize_text_field($_POST['report_format'] ?? 'json');

        try {
            $report = $this->generate_report($type, $format);
            $this->send_report($report, $format);
        } catch (Exception $e) {
            wp_die($e->getMessage());
        }
    }

    private function send_report($report, $format) {
        $filename = 'bloom_report_' . date('Y-m-d_H-i-s');
        
        switch ($format) {
            case 'json':
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '.json"');
                echo json_encode($report, JSON_PRETTY_PRINT);
                break;
                
            case 'csv':
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
                $this->exporters['csv']->output($report);
                break;
                
            case 'pdf':
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                $this->exporters['pdf']->output($report);
                break;
        }
        
        exit;
    }
}