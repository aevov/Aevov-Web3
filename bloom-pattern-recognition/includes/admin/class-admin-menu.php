<?php
/**
 * Handles WordPress admin menu and pages integration
 */
namespace BLOOM\Admin;

use BLOOM\Monitoring\SystemMonitor;
use BLOOM\Processing\TensorProcessor;
use BLOOM\Monitoring\MetricsCollector;

class AdminMenu {
    private $plugin_slug = 'bloom-patterns';
    private $capability = 'manage_options';
    private $system_monitor;
    private $tensor_processor;
    private $metrics_collector;

    public function __construct() {
        $this->system_monitor = new SystemMonitor();
        $this->tensor_processor = new TensorProcessor();
        $this->metrics_collector = new MetricsCollector();

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_menu() {
        add_menu_page(
            __('BLOOM Patterns', 'bloom-pattern-system'),
            __('BLOOM Patterns', 'bloom-pattern-system'),
            $this->capability,
            $this->plugin_slug,
            [$this, 'render_dashboard'],
            'dashicons-visibility',
            30
        );

        $this->add_submenu_pages([
            'dashboard' => __('Dashboard', 'bloom-pattern-system'),
            'patterns' => __('Patterns', 'bloom-pattern-system'),
            'monitor' => __('Monitor', 'bloom-pattern-system'),
            'settings' => __('Settings', 'bloom-pattern-system')
        ]);
    }

    private function add_submenu_pages($pages) {
        foreach ($pages as $slug => $title) {
            add_submenu_page(
                $this->plugin_slug,
                $title,
                $title,
                $this->capability,
                "{$this->plugin_slug}-{$slug}",
                [$this, "render_{$slug}_page"]
            );
        }
    }

    public function render_dashboard() {
        $metrics = $this->get_dashboard_metrics();
        include BLOOM_PATH . 'admin/templates/dashboard.php';
    }

    private function get_dashboard_metrics() {
        return [
            'patterns' => $this->tensor_processor->get_pattern_stats(),
            'performance' => $this->metrics_collector->collect_performance_metrics(),
            'network' => $this->system_monitor->get_network_health(),
            'activity' => $this->get_recent_activity()
        ];
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, $this->plugin_slug) === false) {
            return;
        }

        wp_enqueue_style('bloom-admin', BLOOM_URL . 'assets/css/admin.css', [], BLOOM_VERSION);
        wp_enqueue_script('bloom-admin', BLOOM_URL . 'assets/js/admin.js', ['jquery', 'wp-api'], BLOOM_VERSION, true);
        wp_localize_script('bloom-admin', 'bloomAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'wsUrl' => 'ws://localhost:8080',
            'nonce' => wp_create_nonce('bloom_admin')
        ]);
    }
}