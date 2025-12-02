<?php
/**
 * Plugin Name: Aevov Demo System
 * Plugin URI: https://aevov.com/demo-system
 * Description: Comprehensive semi-automated demo system for Aevov neurosymbolic architecture with SLM integration, automated workflows, and Typebot API configuration
 * Version: 1.0.0
 * Author: Aevov Systems
 * License: GPL v2 or later
 * Network: true
 * Text Domain: aevov-demo-system
 * Requires PHP: 7.4
 */

namespace AevovDemo;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__FILE__) . '/../aevov-chat-ui/aevov-chat-ui.php';

// Define plugin constants
define('ADS_VERSION', '1.0.0');
define('ADS_PLUGIN_FILE', __FILE__);
define('ADS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ADS_PLUGIN_BASENAME', plugin_basename(__FILE__));

class AevovDemoSystem {
    private static $instance = null;
    
    private $plugin_dependencies = [
        'aps-tools/aps-tools.php' => 'APS Tools',
        'bloom-pattern-recognition/bloom-pattern-system.php' => 'BLOOM Pattern Recognition',
        'AevovPatternSyncProtocol/aevov-pattern-sync-protocol.php' => 'Aevov Pattern Sync Protocol',
        'aevov-onboarding-system/aevov-onboarding.php' => 'Aevov Onboarding System',
        'aevov-diagnostic-network/aevov-diagnostic-network.php' => 'Aevov Diagnostic Network'
    ];
    
    private $available_models = [
        'phi-3-mini' => [
            'name' => 'Phi-3 Mini (3.8B)',
            'description' => 'Microsoft\'s lightweight language model optimized for mobile and edge devices',
            'size' => '2.4GB',
            'files' => [
                'https://huggingface.co/microsoft/Phi-3-mini-4k-instruct/resolve/main/config.json',
                'https://huggingface.co/microsoft/Phi-3-mini-4k-instruct/resolve/main/tokenizer.json',
                'https://huggingface.co/microsoft/Phi-3-mini-4k-instruct/resolve/main/pytorch_model.bin'
            ],
            'chunk_compatible' => true,
            'context_length' => 4096
        ],
        'tinyllama' => [
            'name' => 'TinyLlama 1.1B',
            'description' => 'Compact Llama-based model for efficient inference',
            'size' => '637MB',
            'files' => [
                'https://huggingface.co/TinyLlama/TinyLlama-1.1B-Chat-v1.0/resolve/main/config.json',
                'https://huggingface.co/TinyLlama/TinyLlama-1.1B-Chat-v1.0/resolve/main/tokenizer.json',
                'https://huggingface.co/TinyLlama/TinyLlama-1.1B-Chat-v1.0/resolve/main/pytorch_model.bin'
            ],
            'chunk_compatible' => true,
            'context_length' => 2048
        ],
        'gemma-2b' => [
            'name' => 'Gemma 2B',
            'description' => 'Google\'s efficient small language model',
            'size' => '1.4GB',
            'files' => [
                'https://huggingface.co/google/gemma-2b/resolve/main/config.json',
                'https://huggingface.co/google/gemma-2b/resolve/main/tokenizer.json',
                'https://huggingface.co/google/gemma-2b/resolve/main/pytorch_model.bin'
            ],
            'chunk_compatible' => true,
            'context_length' => 8192
        ],
        'qwen-1.8b' => [
            'name' => 'Qwen 1.8B Chat',
            'description' => 'Alibaba\'s multilingual chat model',
            'size' => '1.1GB',
            'files' => [
                'https://huggingface.co/Qwen/Qwen-1_8B-Chat/resolve/main/config.json',
                'https://huggingface.co/Qwen/Qwen-1_8B-Chat/resolve/main/tokenizer.json',
                'https://huggingface.co/Qwen/Qwen-1_8B-Chat/resolve/main/pytorch_model.bin'
            ],
            'chunk_compatible' => true,
            'context_length' => 32768
        ],
        'stablelm-2-1.6b' => [
            'name' => 'StableLM 2 1.6B',
            'description' => 'Stability AI\'s efficient language model',
            'size' => '950MB',
            'files' => [
                'https://huggingface.co/stabilityai/stablelm-2-1_6b/resolve/main/config.json',
                'https://huggingface.co/stabilityai/stablelm-2-1_6b/resolve/main/tokenizer.json',
                'https://huggingface.co/stabilityai/stablelm-2-1_6b/resolve/main/pytorch_model.bin'
            ],
            'chunk_compatible' => true,
            'context_length' => 4096
        ]
    ];
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('init', [$this, 'load_textdomain']);
        
        // AJAX handlers
        add_action('wp_ajax_ads_download_model', [$this, 'handle_model_download']);
        add_action('wp_ajax_ads_reset_system', [$this, 'handle_system_reset']);
        add_action('wp_ajax_ads_start_workflow', [$this, 'handle_workflow_start']);
        add_action('wp_ajax_ads_get_workflow_status', [$this, 'get_workflow_status']);
        add_action('wp_ajax_ads_generate_typebot_config', [$this, 'generate_typebot_config']);
        add_action('wp_ajax_ads_monitor_api_calls', [$this, 'monitor_api_calls']);
        add_action('wp_ajax_ads_test_chatbot', [$this, 'test_chatbot']);
        add_action('wp_ajax_ads_send_chat_message', [$this, 'handle_chat_message']);
        add_action('wp_ajax_ads_get_api_activity', [$this, 'get_api_activity']);
        add_action('wp_ajax_ads_generate_api_key', [$this, 'generate_api_key']);
        add_action('wp_ajax_ads_test_typebot_webhook', [$this, 'test_typebot_webhook']);
        add_action('wp_ajax_ads_get_api_config', [$this, 'get_api_config']);
        add_action('wp_ajax_ads_test_api_request', [$this, 'test_api_request']);
        add_action('wp_ajax_ads_get_system_status', [$this, 'get_system_status']);
        add_action('wp_ajax_ads_get_recent_activity', [$this, 'get_recent_activity']);
        
        // Integration with onboarding system
        add_action('admin_bar_menu', [$this, 'add_admin_bar_integration'], 101);
        
        // Create upload directory for models
        add_action('wp_loaded', [$this, 'create_model_directory']);
    }
    
    public function init() {
        // Initialize demo system
        $this->check_dependencies();
        $this->setup_database_tables();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('aevov-demo-system', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Aevov Demo System', 'aevov-demo-system'),
            __('Demo System', 'aevov-demo-system'),
            'manage_options',
            'aevov-demo-system',
            [$this, 'render_demo_dashboard'],
            'dashicons-desktop',
            3
        );
        
        add_submenu_page(
            'aevov-demo-system',
            __('Model Management', 'aevov-demo-system'),
            __('Model Management', 'aevov-demo-system'),
            'manage_options',
            'ads-model-management',
            [$this, 'render_model_management']
        );
        
        add_submenu_page(
            'aevov-demo-system',
            __('Workflow Automation', 'aevov-demo-system'),
            __('Workflow Automation', 'aevov-demo-system'),
            'manage_options',
            'ads-workflow-automation',
            [$this, 'render_workflow_automation']
        );
        
        add_submenu_page(
            'aevov-demo-system',
            __('API Configuration', 'aevov-demo-system'),
            __('API Configuration', 'aevov-demo-system'),
            'manage_options',
            'ads-api-configuration',
            [$this, 'render_api_configuration']
        );
        
        add_submenu_page(
            'aevov-demo-system',
            __('Live Testing', 'aevov-demo-system'),
            __('Live Testing', 'aevov-demo-system'),
            'manage_options',
            'ads-live-testing',
            [$this, 'render_live_testing']
        );

        add_submenu_page(
            'aevov-demo-system',
            __('Documentation', 'aevov-demo-system'),
            __('Documentation', 'aevov-demo-system'),
            'manage_options',
            'ads-documentation',
            [$this, 'render_documentation_page']
        );

        add_submenu_page(
            'aevov-demo-system',
            __('Chat', 'aevov-demo-system'),
            __('Chat', 'aevov-demo-system'),
            'manage_options',
            'ads-chat',
            [$this, 'render_chat_page']
        );
    }
    
    public function add_admin_bar_integration($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add demo system link to onboarding menu if it exists
        if ($wp_admin_bar->get_node('aevov-onboarding')) {
            $wp_admin_bar->add_node([
                'parent' => 'aevov-onboarding',
                'id' => 'aevov-demo',
                'title' => __('🚀 Demo System', 'aevov-demo-system'),
                'href' => admin_url('admin.php?page=aevov-demo-system')
            ]);
        }
        
        // Add standalone demo system menu
        $wp_admin_bar->add_node([
            'id' => 'aevov-demo-standalone',
            'title' => '🚀 ' . __('Aevov Demo', 'aevov-demo-system'),
            'href' => admin_url('admin.php?page=aevov-demo-system'),
            'meta' => [
                'title' => __('Access Aevov Demo System', 'aevov-demo-system')
            ]
        ]);
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'aevov-demo') === false && strpos($hook, 'ads-') === false) {
            return;
        }

        if ($hook === 'aevov-demo-system_page_ads-chat') {
            if (class_exists('AevovChatUI')) {
                \AevovChatUI::enqueue_scripts();
            }
        }
        
        wp_enqueue_style(
            'ads-demo-style',
            ADS_PLUGIN_URL . 'assets/css/demo-system.css',
            [],
            ADS_VERSION
        );

        // Enqueue style for Markdown documentation
        wp_enqueue_style(
            'ads-markdown-style',
            ADS_PLUGIN_URL . 'assets/css/markdown.css',
            [],
            ADS_VERSION
        );
        
        wp_enqueue_script(
            'ads-demo-script',
            ADS_PLUGIN_URL . 'assets/js/demo-system.js',
            ['jquery', 'wp-util'],
            ADS_VERSION,
            true
        );
        
        wp_localize_script('ads-demo-script', 'adsDemo', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ads-demo-nonce'),
            'models' => $this->available_models,
            'i18n' => [
                'downloading' => __('Downloading model...', 'aevov-demo-system'),
                'processing' => __('Processing workflow...', 'aevov-demo-system'),
                'success' => __('Operation completed successfully!', 'aevov-demo-system'),
                'error' => __('An error occurred', 'aevov-demo-system'),
                'confirm_reset' => __('Are you sure you want to reset the entire system? This cannot be undone.', 'aevov-demo-system')
            ]
        ]);
        
        // Add Chart.js for API monitoring
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            '3.9.1',
            true
        );
        
        // Add D3.js for advanced visualizations
        wp_enqueue_script(
            'd3js',
            'https://d3js.org/d3.v7.min.js',
            [],
            '7.8.5',
            true
        );
    }
    
    public function create_model_directory() {
        $upload_dir = wp_upload_dir();
        $model_dir = $upload_dir['basedir'] . '/aevov-models';
        
        if (!file_exists($model_dir)) {
            wp_mkdir_p($model_dir);
            
            // Create .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "deny from all\n";
            file_put_contents($model_dir . '/.htaccess', $htaccess_content);
        }
    }
    
    private function check_dependencies() {
        $missing_plugins = [];
        
        foreach ($this->plugin_dependencies as $plugin_file => $plugin_name) {
            if (!is_plugin_active($plugin_file)) {
                $missing_plugins[] = $plugin_name;
            }
        }
        
        if (!empty($missing_plugins)) {
            add_action('admin_notices', function() use ($missing_plugins) {
                echo '<div class="notice notice-warning">';
                echo '<p><strong>' . __('Aevov Demo System:', 'aevov-demo-system') . '</strong> ';
                echo sprintf(
                    __('The following plugins are required for full functionality: %s', 'aevov-demo-system'),
                    implode(', ', $missing_plugins)
                );
                echo '</p></div>';
            });
        }
    }
    
    private function setup_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Model downloads table
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            model_key varchar(100) NOT NULL,
            model_name varchar(255) NOT NULL,
            download_status varchar(50) DEFAULT 'pending',
            status varchar(50) DEFAULT 'pending',
            file_path text,
            download_progress int DEFAULT 0,
            chunk_status varchar(50) DEFAULT 'pending',
            processing_status varchar(50) DEFAULT 'pending',
            configuration longtext,
            model_config longtext,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY model_key (model_key),
            KEY download_status (download_status),
            KEY status (status),
            KEY chunk_status (chunk_status),
            KEY processing_status (processing_status)
        ) $charset_collate;";
        
        // Workflow executions table
        $table_workflow = $wpdb->prefix . 'ads_workflow_executions';
        $sql_workflow = "CREATE TABLE $table_workflow (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            execution_id varchar(100) NOT NULL,
            model_key varchar(100) NOT NULL,
            workflow_step varchar(100) NOT NULL,
            step_status varchar(50) DEFAULT 'pending',
            step_data longtext,
            error_message text,
            started_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY execution_id (execution_id),
            KEY model_key (model_key),
            KEY workflow_step (workflow_step),
            KEY step_status (step_status)
        ) $charset_collate;";
        
        // API monitoring table
        $table_api = $wpdb->prefix . 'ads_api_monitoring';
        $sql_api = "CREATE TABLE $table_api (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            request_id varchar(100) NOT NULL,
            model_key varchar(100),
            endpoint varchar(255) NOT NULL,
            api_endpoint varchar(255),
            api_key varchar(255),
            method varchar(10) NOT NULL,
            request_data longtext,
            response_data longtext,
            response_code int,
            response_time float,
            configuration longtext,
            status varchar(50) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY endpoint (endpoint),
            KEY model_key (model_key),
            KEY api_key (api_key),
            KEY method (method),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql_workflow);
        dbDelta($sql_api);
    }
    
    public function render_demo_dashboard() {
        $active_models = $this->get_active_models();
        $recent_workflows = $this->get_recent_workflows();
        $system_status = $this->get_system_status_private();
        
        ?>
        <div class="ads-demo-container">
            <div class="ads-header">
                <h1><?php _e('Aevov Demo System', 'aevov-demo-system'); ?></h1>
                <p><?php _e('The Web\'s Neural Network - Comprehensive deAI Demo Environment', 'aevov-demo-system'); ?></p>
                <div class="ads-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=aevov-onboarding'); ?>" class="button button-secondary">
                        ← <?php _e('Back to Onboarding', 'aevov-demo-system'); ?>
                    </a>
                    <button class="button button-primary" onclick="adsDemo.startQuickDemo()">
                        <?php _e('🚀 Start Quick Demo', 'aevov-demo-system'); ?>
                    </button>
                </div>
            </div>
            
            <div class="ads-dashboard-grid">
                <!-- System Status Card -->
                <div class="ads-card ads-system-status">
                    <h3><?php _e('System Status', 'aevov-demo-system'); ?></h3>
                    <div class="ads-status-indicators">
                        <?php foreach ($system_status as $component => $status): ?>
                            <div class="ads-status-item <?php echo $status['status']; ?>">
                                <span class="ads-status-icon"><?php echo $status['icon']; ?></span>
                                <span class="ads-status-label"><?php echo esc_html($status['label']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Active Models Card -->
                <div class="ads-card ads-active-models">
                    <h3><?php _e('Active Models', 'aevov-demo-system'); ?></h3>
                    <?php if (empty($active_models)): ?>
                        <p class="ads-empty-state">
                            <?php _e('No models downloaded yet.', 'aevov-demo-system'); ?>
                            <a href="<?php echo admin_url('admin.php?page=ads-model-management'); ?>">
                                <?php _e('Download your first model', 'aevov-demo-system'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <div class="ads-model-list">
                            <?php foreach ($active_models as $model): ?>
                                <div class="ads-model-item">
                                    <strong><?php echo esc_html($model['name']); ?></strong>
                                    <span class="ads-model-status <?php echo $model['status']; ?>">
                                        <?php echo esc_html($model['status_label']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions Card -->
                <div class="ads-card ads-quick-actions">
                    <h3><?php _e('Quick Actions', 'aevov-demo-system'); ?></h3>
                    <div class="ads-action-buttons">
                        <button class="ads-action-btn" onclick="adsDemo.downloadModel()">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Download Model', 'aevov-demo-system'); ?>
                        </button>
                        <button class="ads-action-btn" onclick="adsDemo.startWorkflow()">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php _e('Start Workflow', 'aevov-demo-system'); ?>
                        </button>
                        <button class="ads-action-btn" onclick="adsDemo.configureAPI()">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('Configure API', 'aevov-demo-system'); ?>
                        </button>
                        <button class="ads-action-btn ads-danger" onclick="adsDemo.resetSystem()">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Reset System', 'aevov-demo-system'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Recent Activity Card -->
                <div class="ads-card ads-recent-activity">
                    <h3><?php _e('Recent Workflow Activity', 'aevov-demo-system'); ?></h3>
                    <?php if (empty($recent_workflows)): ?>
                        <p class="ads-empty-state">
                            <?php _e('No recent workflow activity.', 'aevov-demo-system'); ?>
                        </p>
                    <?php else: ?>
                        <div class="ads-activity-list">
                            <?php foreach ($recent_workflows as $workflow): ?>
                                <div class="ads-activity-item">
                                    <div class="ads-activity-info">
                                        <strong><?php echo esc_html($workflow['model_name']); ?></strong>
                                        <span class="ads-activity-step"><?php echo esc_html($workflow['current_step']); ?></span>
                                    </div>
                                    <div class="ads-activity-status <?php echo $workflow['status']; ?>">
                                        <?php echo esc_html($workflow['status_label']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Real-time Monitoring Section -->
            <div class="ads-monitoring-section">
                <h2><?php _e('Real-time System Monitoring', 'aevov-demo-system'); ?></h2>
                <div class="ads-monitoring-grid">
                    <div class="ads-monitoring-card">
                        <h4><?php _e('API Call Activity', 'aevov-demo-system'); ?></h4>
                        <canvas id="ads-api-chart" width="400" height="200"></canvas>
                    </div>
                    <div class="ads-monitoring-card">
                        <h4><?php _e('Processing Pipeline', 'aevov-demo-system'); ?></h4>
                        <div id="ads-pipeline-visualization"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize dashboard
            adsDemo.initDashboard();
            
            // Start real-time monitoring
            adsDemo.startMonitoring();
        });
        </script>
        <?php
    }
    
    private function get_active_models() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE download_status = 'completed' ORDER BY updated_at DESC LIMIT 5"
        );
        
        $models = [];
        foreach ($results as $result) {
            $models[] = [
                'name' => $result->model_name,
                'status' => $result->processing_status,
                'status_label' => ucfirst($result->processing_status)
            ];
        }
        
        return $models;
    }
    
    private function get_recent_workflows() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        $results = $wpdb->get_results(
            "SELECT DISTINCT execution_id, model_key, 
                    (SELECT model_name FROM {$wpdb->prefix}ads_model_downloads WHERE model_key = w.model_key) as model_name,
                    MAX(started_at) as last_activity,
                    (SELECT workflow_step FROM $table_name WHERE execution_id = w.execution_id ORDER BY started_at DESC LIMIT 1) as current_step,
                    (SELECT step_status FROM $table_name WHERE execution_id = w.execution_id ORDER BY started_at DESC LIMIT 1) as status
             FROM $table_name w 
             GROUP BY execution_id 
             ORDER BY last_activity DESC 
             LIMIT 5"
        );
        
        $workflows = [];
        foreach ($results as $result) {
            $workflows[] = [
                'model_name' => $result->model_name ?: 'Unknown Model',
                'current_step' => ucfirst(str_replace('_', ' ', $result->current_step)),
                'status' => $result->status,
                'status_label' => ucfirst($result->status)
            ];
        }
        
        return $workflows;
    }
    
    private function get_system_status_private() {
        $status = [];
        
        // Check plugin dependencies
        foreach ($this->plugin_dependencies as $plugin_file => $plugin_name) {
            $is_active = is_plugin_active($plugin_file);
            $status[$plugin_file] = [
                'label' => $plugin_name,
                'status' => $is_active ? 'active' : 'inactive',
                'icon' => $is_active ? '✅' : '❌'
            ];
        }
        
        // Check model directory
        $upload_dir = wp_upload_dir();
        $model_dir = $upload_dir['basedir'] . '/aevov-models';
        $status['model_directory'] = [
            'label' => 'Model Directory',
            'status' => is_writable($model_dir) ? 'active' : 'inactive',
            'icon' => is_writable($model_dir) ? '✅' : '❌'
        ];
        
        return $status;
    }
    
    // AJAX Handlers will be implemented in the next part...
    
    public function handle_model_download() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $model_key = sanitize_text_field($_POST['model_key'] ?? '');
        
        if (!isset($this->available_models[$model_key])) {
            wp_send_json_error('Invalid model selected');
            return;
        }
        
        // Start model download process
        $this->start_model_download($model_key);
        
        wp_send_json_success(['message' => 'Model download started']);
    }
    
    private function start_model_download($model_key) {
        global $wpdb;
        
        $model = $this->available_models[$model_key];
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        
        // Insert or update model record
        $wpdb->replace($table_name, [
            'model_key' => $model_key,
            'model_name' => $model['name'],
            'download_status' => 'downloading',
            'download_progress' => 0
        ]);
        
        // Schedule background download
        wp_schedule_single_event(time(), 'ads_download_model_files', [$model_key]);
    }
    
    public function handle_system_reset() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $this->reset_entire_system();
        
        wp_send_json_success(['message' => 'System reset completed']);
    }
    
    private function reset_entire_system() {
        global $wpdb;
        
        // Clear database tables
        $tables = [
            $wpdb->prefix . 'ads_model_downloads',
            $wpdb->prefix . 'ads_workflow_executions',
            $wpdb->prefix . 'ads_api_monitoring'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE $table");
        }
        
        // Clear model files
        $upload_dir = wp_upload_dir();
        $model_dir = $upload_dir['basedir'] . '/aevov-models';
        
        if (is_dir($model_dir)) {
            $files = glob($model_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== '.htaccess') {
                    unlink($file);
                }
            }
        }
        
        // Clear WordPress options
        $options_to_clear = [
            'ads_current_workflow',
            'ads_typebot_config',
            'ads_api_endpoints'
        ];
        
        foreach ($options_to_clear as $option) {
            delete_option($option);
        }
    }
    
    public function handle_workflow_start() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $model_key = sanitize_text_field($_POST['model_key'] ?? '');
        
        if (empty($model_key)) {
            wp_send_json_error('Model key is required');
            return;
        }
        
        // Initialize workflow automation
        require_once ADS_PLUGIN_DIR . 'includes/class-workflow-automation.php';
        $workflow = new \AevovDemo\WorkflowAutomation();
        
        $execution_id = $workflow->start_workflow($model_key);
        
        wp_send_json_success([
            'execution_id' => $execution_id,
            'message' => 'Workflow started successfully'
        ]);
    }
    
    public function get_workflow_status() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $execution_id = sanitize_text_field($_POST['execution_id'] ?? '');
        
        if (empty($execution_id)) {
            wp_send_json_error('Execution ID is required');
            return;
        }
        
        require_once ADS_PLUGIN_DIR . 'includes/class-workflow-automation.php';
        $workflow = new \AevovDemo\WorkflowAutomation();
        
        $status = $workflow->get_workflow_status($execution_id);
        
        wp_send_json_success($status);
    }
    
    public function generate_typebot_config() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $model_key = sanitize_text_field($_POST['model_key'] ?? '');
        
        if (empty($model_key)) {
            wp_send_json_error('Model key is required');
            return;
        }
        
        require_once ADS_PLUGIN_DIR . 'includes/class-api-configuration.php';
        $api_config = new \AevovDemo\APIConfiguration();
        
        $config = $api_config->generate_typebot_configuration($model_key);
        
        wp_send_json_success($config);
    }
    
    public function monitor_api_calls() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        require_once ADS_PLUGIN_DIR . 'includes/class-api-configuration.php';
        $api_config = new \AevovDemo\APIConfiguration();
        
        $activity = $api_config->get_recent_api_activity();
        
        wp_send_json_success($activity);
    }
    
    public function test_chatbot() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $model_key = sanitize_text_field($_POST['model_key'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (empty($model_key) || empty($message)) {
            wp_send_json_error('Model key and message are required');
            return;
        }
        
        require_once ADS_PLUGIN_DIR . 'includes/class-testing-environment.php';
        $testing = new \AevovDemo\TestingEnvironment();
        
        $response = $testing->handle_chat_message($model_key, $message, 0.7, 150);
        
        wp_send_json_success($response);
    }
    
    public function handle_chat_message() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $model_key = sanitize_text_field($_POST['model_key'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $temperature = floatval($_POST['temperature'] ?? 0.7);
        $max_tokens = intval($_POST['max_tokens'] ?? 150);
        
        if (empty($model_key) || empty($message)) {
            wp_send_json_error('Model key and message are required');
            return;
        }
        
        require_once ADS_PLUGIN_DIR . 'includes/class-testing-environment.php';
        $testing = new \AevovDemo\TestingEnvironment();
        
        $response = $testing->handle_chat_message($model_key, $message, $temperature, $max_tokens);
        
        wp_send_json($response);
    }
    
    public function get_api_activity() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        require_once ADS_PLUGIN_DIR . 'includes/class-api-configuration.php';
        $api_config = new \AevovDemo\APIConfiguration();
        
        $activity = $api_config->get_api_statistics();
        
        wp_send_json_success($activity);
    }
    
    public function generate_api_key() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $api_key = 'ads_' . wp_generate_password(32, false);
        
        // Store API key in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'ads_api_monitoring';
        
        $wpdb->insert($table_name, [
            'request_id' => wp_generate_uuid4(),
            'endpoint' => home_url('/wp-json/aevov-demo/v1/chat'),
            'method' => 'POST',
            'request_data' => json_encode([
                'api_key' => $api_key,
                'typebot_webhook' => home_url('/wp-json/aevov-demo/v1/typebot-webhook'),
                'status' => 'active',
                'created_at' => current_time('mysql')
            ]),
            'response_data' => json_encode(['status' => 'API key generated']),
            'response_code' => 200,
            'response_time' => 0.1
        ]);
        
        wp_send_json_success([
            'api_key' => $api_key,
            'endpoint' => home_url('/wp-json/aevov-demo/v1/chat'),
            'webhook' => home_url('/wp-json/aevov-demo/v1/typebot-webhook'),
            'message' => 'API key generated successfully'
        ]);
    }
    
    public function test_typebot_webhook() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $api_id = sanitize_text_field($_POST['api_id'] ?? '');
        
        if (empty($api_id)) {
            wp_send_json_error('API ID is required');
            return;
        }
        
        require_once ADS_PLUGIN_DIR . 'includes/class-testing-environment.php';
        $testing = new \AevovDemo\TestingEnvironment();
        
        $result = $testing->test_typebot_webhook($api_id);
        
        wp_send_json($result);
    }
    
    public function get_api_config() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $api_id = sanitize_text_field($_POST['api_id'] ?? '');
        
        if (empty($api_id)) {
            wp_send_json_error('API ID is required');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ads_api_monitoring';
        
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE request_id = %s OR endpoint LIKE %s ORDER BY created_at DESC LIMIT 1",
            $api_id, '%' . $api_id . '%'
        ));
        
        if (!$config) {
            wp_send_json_error('API configuration not found');
            return;
        }
        
        $request_data = json_decode($config->request_data, true);
        
        wp_send_json_success([
            'api_key' => $request_data['api_key'] ?? 'Not found',
            'api_endpoint' => $config->endpoint,
            'typebot_webhook' => $request_data['typebot_webhook'] ?? 'Not configured'
        ]);
    }
    
    public function test_api_request() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $api_id = sanitize_text_field($_POST['api_id'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $max_tokens = intval($_POST['max_tokens'] ?? 150);
        $temperature = floatval($_POST['temperature'] ?? 0.7);
        
        if (empty($api_id) || empty($message)) {
            wp_send_json_error('API ID and message are required');
            return;
        }
        
        // Simulate API request processing with Aevov deAI network
        $start_time = microtime(true);
        
        $response_text = "Hello! I'm processing your test message through the Aevov deAI network - 'The Web's Neural Network'. This response demonstrates the decentralized AI processing capabilities of our neurosymbolic architecture. Your message: \"" . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '') . "\" has been processed through our distributed pattern recognition system.";
        
        $tokens_used = rand(20, min(80, $max_tokens));
        $processing_time = microtime(true) - $start_time + (rand(500, 2000) / 1000); // Add simulated processing time
        
        // Log the API request
        global $wpdb;
        $table_name = $wpdb->prefix . 'ads_api_monitoring';
        
        $wpdb->insert($table_name, [
            'request_id' => wp_generate_uuid4(),
            'endpoint' => '/wp-json/aevov-demo/v1/chat',
            'method' => 'POST',
            'request_data' => json_encode([
                'message' => $message,
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
                'api_id' => $api_id
            ]),
            'response_data' => json_encode([
                'response' => $response_text,
                'tokens_used' => $tokens_used
            ]),
            'response_code' => 200,
            'response_time' => $processing_time
        ]);
        
        wp_send_json_success([
            'response' => $response_text,
            'model_used' => $api_id,
            'processing_time' => round($processing_time, 3),
            'tokens_used' => $tokens_used,
            'deai_network' => 'Aevov - The Web\'s Neural Network'
        ]);
    }
    
    public function get_system_status() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        // Get system status from the dashboard method
        $active_models = $this->get_active_models();
        $recent_workflows = $this->get_recent_workflows();
        $system_status = $this->get_system_status_private();
        
        wp_send_json_success([
            'system_status' => $system_status,
            'active_models' => $active_models,
            'recent_workflows' => $recent_workflows
        ]);
    }
    
    public function get_recent_activity() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ads-demo-nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        $activity = $this->get_recent_workflows();
        wp_send_json_success($activity);
    }
    
    // Render methods for admin pages
    public function render_model_management() {
        try {
            $class_file = ADS_PLUGIN_DIR . 'includes/class-model-management.php';
            if (!file_exists($class_file)) {
                throw new \Exception('Model Management class file not found');
            }
            
            require_once $class_file;
            
            if (!class_exists('\AevovDemo\ModelManagement')) {
                throw new \Exception('ModelManagement class not found');
            }
            
            $model_management = new \AevovDemo\ModelManagement();
            
            if (!method_exists($model_management, 'render_model_management_page')) {
                throw new \Exception('render_model_management_page method not found');
            }
            
            $model_management->render_model_management_page();
            
        } catch (\Exception $e) {
            echo '<div class="ads-demo-container">';
            echo '<div class="notice notice-error"><p><strong>Model Management Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
            echo '<p>Please check the plugin installation and try again.</p>';
            echo '</div>';
        }
    }

    public function render_workflow_automation() {
        try {
            $class_file = ADS_PLUGIN_DIR . 'includes/class-workflow-automation.php';
            if (!file_exists($class_file)) {
                throw new \Exception('Workflow Automation class file not found');
            }
            
            require_once $class_file;
            
            if (!class_exists('\AevovDemo\WorkflowAutomation')) {
                throw new \Exception('WorkflowAutomation class not found');
            }
            
            $workflow = new \AevovDemo\WorkflowAutomation();
            
            if (!method_exists($workflow, 'render_workflow_page')) {
                throw new \Exception('render_workflow_page method not found');
            }
            
            $workflow->render_workflow_page();
            
        } catch (\Exception $e) {
            echo '<div class="ads-demo-container">';
            echo '<div class="notice notice-error"><p><strong>Workflow Automation Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
            echo '<p>Please check the plugin installation and try again.</p>';
            echo '</div>';
        }
    }

    public function render_api_configuration() {
        try {
            $class_file = ADS_PLUGIN_DIR . 'includes/class-api-configuration.php';
            if (!file_exists($class_file)) {
                throw new \Exception('API Configuration class file not found');
            }
            
            require_once $class_file;
            
            if (!class_exists('\AevovDemo\APIConfiguration')) {
                throw new \Exception('APIConfiguration class not found');
            }
            
            $api_config = new \AevovDemo\APIConfiguration();
            
            if (!method_exists($api_config, 'render_api_configuration_page')) {
                throw new \Exception('render_api_configuration_page method not found');
            }
            
            $api_config->render_api_configuration_page();
            
        } catch (\Exception $e) {
            echo '<div class="ads-demo-container">';
            echo '<div class="notice notice-error"><p><strong>API Configuration Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
            echo '<p>Please check the plugin installation and try again.</p>';
            echo '</div>';
        }
    }

    public function render_live_testing() {
        try {
            $class_file = ADS_PLUGIN_DIR . 'includes/class-testing-environment.php';
            if (!file_exists($class_file)) {
                throw new \Exception('Testing Environment class file not found');
            }
            
            require_once $class_file;
            
            if (!class_exists('\AevovDemo\TestingEnvironment')) {
                throw new \Exception('TestingEnvironment class not found');
            }
            
            $testing = new \AevovDemo\TestingEnvironment();
            
            if (!method_exists($testing, 'render_testing_page')) {
                throw new \Exception('render_testing_page method not found');
            }
            
            $testing->render_testing_page();
            
        } catch (\Exception $e) {
            echo '<div class="ads-demo-container">';
            echo '<div class="notice notice-error"><p><strong>Live Testing Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
            echo '<p>Please check the plugin installation and try again.</p>';
            echo '</div>';
        }
    }

    public function render_documentation_page() {
        // Ensure Parsedown is loaded
        require_once ADS_PLUGIN_DIR . 'includes/Parsedown.php';
        $parsedown = new \Parsedown();

        $doc_path = ADS_PLUGIN_DIR . 'docs/documentation/';
        $requested_doc = sanitize_text_field($_GET['doc'] ?? 'README.md');
        $file_path = realpath($doc_path . $requested_doc);

        // Basic security check to prevent directory traversal
        if (strpos($file_path, realpath($doc_path)) === false || !file_exists($file_path)) {
            $file_path = realpath($doc_path . 'README.md'); // Fallback to main README
        }

        $markdown_content = file_get_contents($file_path);
        $html_content = $parsedown->text($markdown_content);

        ?>
        <div class="wrap">
            <h1><?php _e('Aevov System Documentation', 'aevov-demo-system'); ?></h1>
            <div class="ads-documentation-container">
                <div class="ads-doc-sidebar">
                    <h3><?php _e('Table of Contents', 'aevov-demo-system'); ?></h3>
                    <ul>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=README.md'); ?>">Introduction</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/README.md'); ?>">API Reference</a></li>
                        <!-- Add more links dynamically or manually as needed -->
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/admin-endpoints.md'); ?>">Admin Endpoints</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/consensus-mechanism-endpoints.md'); ?>">Consensus Mechanism Endpoints</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/distributed-ledger-endpoints.md'); ?>">Distributed Ledger Endpoints</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/metrics-endpoints.md'); ?>">Metrics Endpoints</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/network-endpoints.md'); ?>">Network Endpoints</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/pattern-analysis-endpoints.md'); ?>">Pattern Analysis Endpoints</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/pattern-comparison-endpoints.md'); ?>">Pattern Comparison Endpoints</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/pattern-distribution-endpoints.md'); ?>">Pattern Distribution Endpoints</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/pattern-endpoints.md'); ?>">Pattern Endpoints</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/proof-of-contribution-endpoints.md'); ?>">Proof of Contribution Endpoints</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=ads-documentation&doc=api-reference/system-status-endpoints.md'); ?>">System Status Endpoints</a></li>
                    </ul>
                </div>
                <div class="ads-doc-content">
                    <?php echo $html_content; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_chat_page() {
        if (class_exists('AevovChatUI')) {
            \AevovChatUI::render();
        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    AevovDemoSystem::instance();
});

// Register activation hook
register_activation_hook(__FILE__, function() {
    // Create database tables and directories
    $demo_system = AevovDemoSystem::instance();
    $demo_system->create_model_directory();
});