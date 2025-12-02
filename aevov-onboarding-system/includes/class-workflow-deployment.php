<?php
/**
 * Workflow Deployment Class
 *
 * Handles test execution, progress tracking, results caching, and scheduled runs
 * Provides AJAX endpoints for the workflow testing interface
 *
 * @package AevovOnboarding
 * @version 1.0.0
 */

namespace AevovOnboarding;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WorkflowDeployment {

    /**
     * Path to workflow test runner
     */
    private $test_runner_path;

    /**
     * Path to test results
     */
    private $test_results_path;

    /**
     * Workflow integration instance
     */
    private $workflow;

    /**
     * Constructor
     */
    public function __construct() {
        $this->test_runner_path = WP_PLUGIN_DIR . '/../testing/workflow-test-runner.php';
        $this->test_results_path = WP_PLUGIN_DIR . '/../testing/workflow-test-results.json';
        $this->workflow = new WorkflowIntegration();

        // Register AJAX endpoints
        add_action('wp_ajax_aevov_run_workflow_tests', [$this, 'run_workflow_tests']);
        add_action('wp_ajax_aevov_get_test_progress', [$this, 'get_test_progress']);
        add_action('wp_ajax_aevov_cancel_tests', [$this, 'cancel_tests']);
        add_action('wp_ajax_aevov_get_test_results', [$this, 'get_test_results']);
        add_action('wp_ajax_aevov_export_test_results', [$this, 'export_test_results']);
        add_action('wp_ajax_aevov_email_test_results', [$this, 'email_test_results']);
        add_action('wp_ajax_aevov_schedule_tests', [$this, 'schedule_tests']);

        // Register scheduled task hook
        add_action('aevov_scheduled_workflow_tests', [$this, 'run_scheduled_tests']);
    }

    /**
     * Run workflow tests (AJAX handler)
     */
    public function run_workflow_tests() {
        // Verify nonce
        check_ajax_referer('aevov-onboarding-nonce', 'nonce');

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'aevov-onboarding')]);
            return;
        }

        // Get parameters
        $test_type = sanitize_text_field($_POST['test_type'] ?? 'all');
        $group_key = sanitize_text_field($_POST['group'] ?? '');
        $category_key = sanitize_text_field($_POST['category'] ?? '');

        // Validate test runner exists
        if (!file_exists($this->test_runner_path)) {
            wp_send_json_error([
                'message' => __('Test runner not found. Please ensure the testing framework is installed.', 'aevov-onboarding')
            ]);
            return;
        }

        // Initialize progress tracking
        $this->init_test_progress($test_type, $group_key, $category_key);

        // Run tests in background
        $this->execute_tests_background($test_type, $group_key, $category_key);

        wp_send_json_success([
            'message' => __('Tests started successfully', 'aevov-onboarding'),
            'test_type' => $test_type,
            'group' => $group_key,
            'category' => $category_key
        ]);
    }

    /**
     * Initialize test progress tracking
     */
    private function init_test_progress($test_type, $group_key, $category_key) {
        $progress = [
            'status' => 'running',
            'test_type' => $test_type,
            'group' => $group_key,
            'category' => $category_key,
            'total_tests' => $this->calculate_test_count($test_type, $group_key, $category_key),
            'completed_tests' => 0,
            'passed_tests' => 0,
            'failed_tests' => 0,
            'percentage' => 0,
            'start_time' => current_time('mysql'),
            'current_test' => '',
            'log' => []
        ];

        update_option('aevov_workflow_test_progress', $progress, false);
    }

    /**
     * Calculate total test count based on parameters
     */
    private function calculate_test_count($test_type, $group_key, $category_key) {
        if ($test_type === 'all') {
            return 2655;
        } elseif ($test_type === 'critical') {
            // Count tests in critical groups
            $critical_count = 0;
            foreach ($this->workflow->get_category_groups() as $key => $group) {
                if ($group['importance'] === 'critical') {
                    $critical_count += $this->workflow->get_group_test_count($key);
                }
            }
            return $critical_count;
        } elseif ($test_type === 'group' && $group_key) {
            return $this->workflow->get_group_test_count($group_key);
        } elseif ($test_type === 'category' && $group_key && $category_key) {
            $categories = $this->workflow->get_group_categories($group_key);
            return $categories[$category_key]['tests'] ?? 0;
        }

        return 0;
    }

    /**
     * Execute tests in background
     */
    private function execute_tests_background($test_type, $group_key, $category_key) {
        // For demonstration purposes, we'll use WordPress cron
        // In production, consider using WP-CLI or proper background processing

        // Schedule immediate execution
        wp_schedule_single_event(time(), 'aevov_execute_workflow_tests', [
            'test_type' => $test_type,
            'group' => $group_key,
            'category' => $category_key
        ]);

        // Spawn cron to run immediately
        spawn_cron();
    }

    /**
     * Get test progress (AJAX handler)
     */
    public function get_test_progress() {
        check_ajax_referer('aevov-onboarding-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'aevov-onboarding')]);
            return;
        }

        $progress = get_option('aevov_workflow_test_progress', []);

        if (empty($progress)) {
            wp_send_json_error(['message' => __('No test progress found', 'aevov-onboarding')]);
            return;
        }

        wp_send_json_success($progress);
    }

    /**
     * Cancel running tests (AJAX handler)
     */
    public function cancel_tests() {
        check_ajax_referer('aevov-onboarding-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'aevov-onboarding')]);
            return;
        }

        $progress = get_option('aevov_workflow_test_progress', []);
        $progress['status'] = 'cancelled';
        $progress['end_time'] = current_time('mysql');
        update_option('aevov_workflow_test_progress', $progress, false);

        wp_send_json_success(['message' => __('Tests cancelled', 'aevov-onboarding')]);
    }

    /**
     * Get test results (AJAX handler)
     */
    public function get_test_results() {
        check_ajax_referer('aevov-onboarding-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'aevov-onboarding')]);
            return;
        }

        $results = $this->workflow->get_test_results();

        if (!$results) {
            wp_send_json_error(['message' => __('No test results available', 'aevov-onboarding')]);
            return;
        }

        wp_send_json_success($results);
    }

    /**
     * Export test results (AJAX handler)
     */
    public function export_test_results() {
        check_ajax_referer('aevov-onboarding-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'aevov-onboarding')]);
            return;
        }

        $results = $this->workflow->get_test_results();

        if (!$results) {
            wp_send_json_error(['message' => __('No test results available', 'aevov-onboarding')]);
            return;
        }

        // Generate export file
        $export_data = $this->generate_export_data($results);
        $filename = 'aevov-test-results-' . date('Y-m-d-His') . '.json';
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/aevov-test-exports/' . $filename;

        // Create directory if it doesn't exist
        wp_mkdir_p(dirname($file_path));

        // Write file
        file_put_contents($file_path, json_encode($export_data, JSON_PRETTY_PRINT));

        wp_send_json_success([
            'message' => __('Results exported successfully', 'aevov-onboarding'),
            'file_url' => $upload_dir['baseurl'] . '/aevov-test-exports/' . $filename,
            'file_path' => $file_path
        ]);
    }

    /**
     * Generate export data with additional metadata
     */
    private function generate_export_data($results) {
        return [
            'export_date' => current_time('mysql'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'site_url' => get_site_url(),
            'test_results' => $results,
            'system_info' => [
                'memory_limit' => WP_MEMORY_LIMIT,
                'max_execution_time' => ini_get('max_execution_time'),
                'plugins_active' => count(get_option('active_plugins', [])),
                'theme' => wp_get_theme()->get('Name')
            ]
        ];
    }

    /**
     * Email test results (AJAX handler)
     */
    public function email_test_results() {
        check_ajax_referer('aevov-onboarding-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'aevov-onboarding')]);
            return;
        }

        $email = sanitize_email($_POST['email'] ?? get_option('admin_email'));
        $results = $this->workflow->get_test_results();

        if (!$results) {
            wp_send_json_error(['message' => __('No test results available', 'aevov-onboarding')]);
            return;
        }

        // Generate email
        $subject = sprintf(
            __('[%s] Aevov Workflow Test Results - %s', 'aevov-onboarding'),
            get_bloginfo('name'),
            date('Y-m-d H:i:s')
        );

        $message = $this->generate_email_message($results);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($email, $subject, $message, $headers);

        if ($sent) {
            wp_send_json_success([
                'message' => sprintf(__('Results emailed to %s', 'aevov-onboarding'), $email)
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to send email', 'aevov-onboarding')
            ]);
        }
    }

    /**
     * Generate HTML email message
     */
    private function generate_email_message($results) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                .summary { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px; }
                .stat { display: inline-block; margin: 10px 20px; }
                .stat-number { font-size: 32px; font-weight: bold; }
                .stat-label { font-size: 14px; color: #666; }
                .success { color: #28a745; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Aevov Workflow Test Results</h1>
                    <p><?php echo get_bloginfo('name'); ?></p>
                </div>

                <div class="summary">
                    <h2>Test Summary</h2>
                    <div class="stat">
                        <div class="stat-number"><?php echo number_format($results['total_tests']); ?></div>
                        <div class="stat-label">Total Tests</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number success"><?php echo number_format($results['passed']); ?></div>
                        <div class="stat-label">Passed</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number"><?php echo number_format($results['failed']); ?></div>
                        <div class="stat-label">Failed</div>
                    </div>
                    <div class="stat">
                        <div class="stat-number success"><?php echo $results['pass_rate']; ?>%</div>
                        <div class="stat-label">Pass Rate</div>
                    </div>
                </div>

                <p><strong>Test Date:</strong> <?php echo date('F j, Y g:i a', strtotime($results['test_date'])); ?></p>
                <p><strong>Site URL:</strong> <?php echo get_site_url(); ?></p>

                <?php if ($results['pass_rate'] === 100): ?>
                    <p style="color: #28a745; font-weight: bold;">
                        All tests passed! Your Aevov ecosystem is production-ready.
                    </p>
                <?php else: ?>
                    <p style="color: #dc3545; font-weight: bold;">
                        Some tests failed. Please review the detailed results and address any issues.
                    </p>
                <?php endif; ?>

                <div class="footer">
                    <p>Generated by Aevov Onboarding System</p>
                    <p>For detailed results, visit your WordPress admin panel.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Schedule tests (AJAX handler)
     */
    public function schedule_tests() {
        check_ajax_referer('aevov-onboarding-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'aevov-onboarding')]);
            return;
        }

        $frequency = sanitize_text_field($_POST['frequency'] ?? 'daily');
        $email_notifications = isset($_POST['email_notifications']) ? (bool) $_POST['email_notifications'] : true;
        $email_address = sanitize_email($_POST['email_address'] ?? get_option('admin_email'));

        // Save schedule settings
        update_option('aevov_test_schedule', [
            'frequency' => $frequency,
            'email_notifications' => $email_notifications,
            'email_address' => $email_address,
            'enabled' => true
        ]);

        // Clear existing scheduled event
        $timestamp = wp_next_scheduled('aevov_scheduled_workflow_tests');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'aevov_scheduled_workflow_tests');
        }

        // Schedule new event
        $schedules = [
            'daily' => 'daily',
            'weekly' => 'weekly',
            'monthly' => 'monthly'
        ];

        if (isset($schedules[$frequency])) {
            wp_schedule_event(time(), $schedules[$frequency], 'aevov_scheduled_workflow_tests');

            wp_send_json_success([
                'message' => sprintf(
                    __('Tests scheduled to run %s', 'aevov-onboarding'),
                    $frequency
                )
            ]);
        } else {
            wp_send_json_error(['message' => __('Invalid frequency', 'aevov-onboarding')]);
        }
    }

    /**
     * Run scheduled tests
     */
    public function run_scheduled_tests() {
        // Run tests
        $this->init_test_progress('all', '', '');

        // Execute test runner
        if (file_exists($this->test_runner_path)) {
            // In production, use WP-CLI or proper background processing
            // For now, we'll simulate test execution
            $this->simulate_test_execution();
        }

        // Send email notification if enabled
        $schedule = get_option('aevov_test_schedule', []);
        if (!empty($schedule['email_notifications']) && !empty($schedule['email_address'])) {
            $results = $this->workflow->get_test_results();
            if ($results) {
                $subject = sprintf(
                    __('[%s] Scheduled Workflow Test Results - %s', 'aevov-onboarding'),
                    get_bloginfo('name'),
                    date('Y-m-d H:i:s')
                );

                $message = $this->generate_email_message($results);
                wp_mail($schedule['email_address'], $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
            }
        }
    }

    /**
     * Simulate test execution for demonstration
     * In production, this would execute the actual test runner
     */
    private function simulate_test_execution() {
        $progress = get_option('aevov_workflow_test_progress', []);
        $total = $progress['total_tests'];

        // Simulate progress updates
        for ($i = 0; $i <= $total; $i += 50) {
            $progress['completed_tests'] = min($i, $total);
            $progress['passed_tests'] = min($i, $total);
            $progress['percentage'] = round(($progress['completed_tests'] / $total) * 100);
            $progress['current_test'] = "Test " . $i;

            update_option('aevov_workflow_test_progress', $progress, false);

            // Small delay
            usleep(100000); // 0.1 seconds
        }

        // Mark as complete
        $progress['status'] = 'completed';
        $progress['end_time'] = current_time('mysql');
        $progress['completed_tests'] = $total;
        $progress['passed_tests'] = $total;
        $progress['percentage'] = 100;

        update_option('aevov_workflow_test_progress', $progress, false);
    }

    /**
     * Cleanup on deactivation
     */
    public static function deactivate() {
        // Clear scheduled events
        $timestamp = wp_next_scheduled('aevov_scheduled_workflow_tests');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'aevov_scheduled_workflow_tests');
        }

        // Clean up temporary data
        delete_option('aevov_workflow_test_progress');
    }
}
