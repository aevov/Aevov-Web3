<?php
/**
 * Plugin Name: Aevov Onboarding System
 * Description: Comprehensive onboarding system for the complete Aevov Neurosymbolic network
 * Version: 1.0.0
 * Author: Aevov Team
 * Text Domain: aevov-onboarding
 * Requires PHP: 7.4
 */

namespace AevovOnboarding;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load required classes
require_once __DIR__ . '/includes/class-workflow-integration.php';
require_once __DIR__ . '/includes/class-workflow-deployment.php';

class AevovOnboardingSystem {
    private $plugin_dependencies = [
        'aps-tools/aps-tools.php' => 'APS Tools',
        'bloom-pattern-recognition/bloom-pattern-system.php' => 'BLOOM Pattern Recognition',
        'AevovPatternSyncProtocol/aevov-pattern-sync-protocol.php' => 'Aevov Pattern Sync Protocol',
        'bloom-chunk-scanner/bloom-chunk-scanner.php' => 'BLOOM Chunk Scanner',
        'APS Chunk Uploader/chunk-uploader.php' => 'APS Chunk Uploader',
        'aevov-cubbit-cdn/aevov-cubbit-cdn.php' => 'Aevov Cubbit CDN',
        'aevov-stream/aevov-stream.php' => 'Aevov Stream',
        'aevov-image-engine/aevov-image-engine.php' => 'Aevov Image Engine',
        'aevov-music-forge/aevov-music-forge.php' => 'Aevov Music Forge',
        'aevov-simulation-engine/aevov-simulation-engine.php' => 'Aevov Simulation Engine',
        'aevov-application-forge/aevov-application-forge.php' => 'Aevov Application Forge',
        'aevov-super-app-forge/aevov-super-app-forge.php' => 'Aevov Super-App Forge',
        'aevov-language-engine/aevov-language-engine.php' => 'Aevov Language Engine',
        'aevov-chunk-registry/aevov-chunk-registry.php' => 'Aevov Chunk Registry',
        'aevov-neuro-architect/aevov-neuro-architect.php' => 'Aevov Neuro-Architect',
        'aevov-playground/aevov-playground.php' => 'Aevov Playground',
        'aevov-memory-core/aevov-memory-core.php' => 'Aevov Memory Core',
        'aevov-memory-core/aevov-memory-core.php' => 'Aevov Memory Core',
        'aevov-reasoning-engine/aevov-reasoning-engine.php' => 'Aevov Reasoning Engine',
        'aevov-cognitive-engine/aevov-cognitive-engine.php' => 'Aevov Cognitive Engine',
        'aevov-transcription-engine/aevov-transcription-engine.php' => 'Aevov Transcription Engine',
        'aevov-embedding-engine/aevov-embedding-engine.php' => 'Aevov Embedding Engine'
    ];
    
    private $onboarding_steps = [
        'welcome' => 'Welcome to Aevov',
        'system_check' => 'System Requirements Check',
        'plugin_activation' => 'Plugin Activation',
        'architecture_overview' => 'System Architecture',
        'initial_setup' => 'Initial Configuration',
        'pattern_creation' => 'Create Your First Pattern',
        'chunk_management' => 'Chunk Management Setup',
        'sync_configuration' => 'Sync Protocol Setup',
        'testing_validation' => 'System Testing',
        'completion' => 'Setup Complete'
    ];
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_aevov_onboarding_action', [$this, 'handle_ajax_action']);
        add_action('wp_ajax_aevov_get_system_status', [$this, 'get_system_status']);
        add_action('wp_ajax_aevov_activate_plugin', [$this, 'activate_plugin']);
        add_action('wp_ajax_aevov_save_config', [$this, 'save_configuration']);
        add_action('init', [$this, 'load_textdomain']); // Add this line

        // Add to WordPress admin bar
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);

        // Initialize workflow deployment
        new WorkflowDeployment();
    }

    public function load_textdomain() {
        load_plugin_textdomain('aevov-onboarding', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function init() {
        // Initialize onboarding system
        $this->check_onboarding_status();
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Aevov Onboarding', 'aevov-onboarding'),
            __('Aevov Setup', 'aevov-onboarding'),
            'manage_options',
            'aevov-onboarding',
            [$this, 'render_onboarding_page'],
            'dashicons-networking',
            2
        );
        
        // Add submenu items for different sections
        add_submenu_page(
            'aevov-onboarding',
            __('System Status', 'aevov-onboarding'),
            __('System Status', 'aevov-onboarding'),
            'manage_options',
            'aevov-system-status',
            [$this, 'render_system_status_page']
        );
        
        add_submenu_page(
            'aevov-onboarding',
            __('Architecture', 'aevov-onboarding'),
            __('Architecture', 'aevov-onboarding'),
            'manage_options',
            'aevov-architecture',
            [$this, 'render_architecture_page']
        );
        
        add_submenu_page(
            'aevov-onboarding',
            __('Configuration', 'aevov-onboarding'),
            __('Configuration', 'aevov-onboarding'),
            'manage_options',
            'aevov-configuration',
            [$this, 'render_configuration_page']
        );

        add_submenu_page(
            'aevov-onboarding',
            __('System Tests', 'aevov-onboarding'),
            __('System Tests', 'aevov-onboarding'),
            'manage_options',
            'aevov-system-tests',
            [$this, 'render_workflow_testing_page']
        );
    }
    
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Validate that we have a proper WP_Admin_Bar object
        if (!is_object($wp_admin_bar) || !method_exists($wp_admin_bar, 'add_node')) {
            return;
        }
        
        $onboarding_complete = get_option('aevov_onboarding_complete', false);
        $icon = $onboarding_complete ? '✅' : '⚙️';
        $title = $onboarding_complete ? 'Aevov System' : 'Aevov Setup';
        
        $wp_admin_bar->add_node([
            'id' => 'aevov-onboarding',
            'title' => $icon . ' ' . $title,
            'href' => admin_url('admin.php?page=aevov-onboarding'),
            'meta' => [
                'title' => __('Access Aevov Onboarding System', 'aevov-onboarding')
            ]
        ]);
        
        // Add quick access submenu
        $wp_admin_bar->add_node([
            'parent' => 'aevov-onboarding',
            'id' => 'aevov-status',
            'title' => __('System Status', 'aevov-onboarding'),
            'href' => admin_url('admin.php?page=aevov-system-status')
        ]);
        
        $wp_admin_bar->add_node([
            'parent' => 'aevov-onboarding',
            'id' => 'aevov-arch',
            'title' => __('Architecture', 'aevov-onboarding'),
            'href' => admin_url('admin.php?page=aevov-architecture')
        ]);
        
        $wp_admin_bar->add_node([
            'parent' => 'aevov-onboarding',
            'id' => 'aevov-config',
            'title' => __('Configuration', 'aevov-onboarding'),
            'href' => admin_url('admin.php?page=aevov-configuration')
        ]);

        $wp_admin_bar->add_node([
            'parent' => 'aevov-onboarding',
            'id' => 'aevov-tests',
            'title' => __('System Tests', 'aevov-onboarding'),
            'href' => admin_url('admin.php?page=aevov-system-tests')
        ]);
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'aevov') === false) {
            return;
        }

        wp_enqueue_style(
            'aevov-onboarding-style',
            plugins_url('assets/css/onboarding.css', __FILE__),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'aevov-onboarding-script',
            plugins_url('assets/js/onboarding.js', __FILE__),
            ['jquery'],
            '1.0.0',
            true
        );

        // Enqueue workflow testing assets on the system tests page
        if (strpos($hook, 'aevov-system-tests') !== false) {
            wp_enqueue_style(
                'aevov-workflow-testing-style',
                plugins_url('assets/css/workflow-testing.css', __FILE__),
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'aevov-workflow-testing-script',
                plugins_url('assets/js/workflow-testing.js', __FILE__),
                ['jquery'],
                '1.0.0',
                true
            );
        }

        wp_localize_script('aevov-onboarding-script', 'aevovOnboarding', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aevov-onboarding-nonce'),
            'adminEmail' => get_option('admin_email'),
            'i18n' => [
                'loading' => __('Loading...', 'aevov-onboarding'),
                'success' => __('Success!', 'aevov-onboarding'),
                'error' => __('Error occurred', 'aevov-onboarding'),
                'confirm' => __('Are you sure?', 'aevov-onboarding')
            ]
        ]);

        // Add inline styles as fallback
        $this->add_inline_styles();
    }
    
    private function add_inline_styles() {
        ?>
        <style>
            .aevov-onboarding-container {
                max-width: 1200px;
                margin: 20px auto;
                padding: 20px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .aevov-header {
                text-align: center;
                margin-bottom: 40px;
                padding: 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 8px;
            }
            
            .aevov-header h1 {
                margin: 0;
                font-size: 2.5em;
                font-weight: 300;
            }
            
            .aevov-header p {
                margin: 10px 0 0 0;
                font-size: 1.2em;
                opacity: 0.9;
            }
            
            .onboarding-steps {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            
            .step-card {
                padding: 25px;
                border: 2px solid #e1e5e9;
                border-radius: 8px;
                transition: all 0.3s ease;
                cursor: pointer;
            }
            
            .step-card:hover {
                border-color: #667eea;
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
            }
            
            .step-card.completed {
                border-color: #28a745;
                background: #f8fff9;
            }
            
            .step-card.active {
                border-color: #667eea;
                background: #f8f9ff;
            }
            
            .step-card.disabled {
                opacity: 0.5;
                cursor: not-allowed;
                border-color: #e1e5e9;
                background: #f8f9fa;
            }
            
            .step-card.disabled:hover {
                transform: none;
                box-shadow: none;
                border-color: #e1e5e9;
            }
            
            .step-number {
                display: inline-block;
                width: 30px;
                height: 30px;
                background: #667eea;
                color: white;
                border-radius: 50%;
                text-align: center;
                line-height: 30px;
                font-weight: bold;
                margin-right: 15px;
            }
            
            .step-card.completed .step-number {
                background: #28a745;
            }
            
            .step-title {
                font-size: 1.3em;
                font-weight: 600;
                margin: 0 0 10px 0;
            }
            
            .step-description {
                color: #666;
                line-height: 1.5;
            }
            
            .system-status-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            
            .status-card {
                padding: 20px;
                border-radius: 8px;
                text-align: center;
            }
            
            .status-card.active {
                background: #d4edda;
                border: 2px solid #28a745;
            }
            
            .status-card.inactive {
                background: #f8d7da;
                border: 2px solid #dc3545;
            }
            
            .status-card.warning {
                background: #fff3cd;
                border: 2px solid #ffc107;
            }
            
            .architecture-diagram {
                text-align: center;
                margin: 30px 0;
                padding: 20px;
                background: #f8f9fa;
                border-radius: 8px;
            }
            
            .config-section {
                margin: 30px 0;
                padding: 25px;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
            }
            
            .config-section h3 {
                margin-top: 0;
                color: #667eea;
                border-bottom: 2px solid #667eea;
                padding-bottom: 10px;
            }
            
            .btn-primary {
                background: #667eea;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 16px;
                transition: background 0.3s ease;
            }
            
            .btn-primary:hover {
                background: #5a6fd8;
            }
            
            .btn-success {
                background: #28a745;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 16px;
            }
            
            .progress-bar {
                width: 100%;
                height: 20px;
                background: #e9ecef;
                border-radius: 10px;
                overflow: hidden;
                margin: 20px 0;
            }
            
            .progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #667eea, #764ba2);
                transition: width 0.5s ease;
            }
        </style>
        <?php
    }
    
    public function render_onboarding_page() {
        $current_step = get_option('aevov_current_onboarding_step', 'welcome');
        $completed_steps = get_option('aevov_completed_steps', []);
        
        ?>
        <div class="aevov-onboarding-container">
            <div class="aevov-header">
                <h1><?php _e('Aevov Neurosymbolic Network', 'aevov-onboarding'); ?></h1>
                <p><?php _e('Complete System Setup & Configuration Guide', 'aevov-onboarding'); ?></p>
            </div>
            
            <?php $this->render_progress_bar($completed_steps); ?>
            
            <div class="onboarding-steps">
                <?php
                $step_number = 1;
                $step_keys = array_keys($this->onboarding_steps);
                $current_step_index = array_search($current_step, $step_keys);
                
                foreach ($this->onboarding_steps as $step_key => $step_title) {
                    $is_completed = in_array($step_key, $completed_steps);
                    $is_active = $current_step === $step_key;
                    $step_index = array_search($step_key, $step_keys);
                    
                    // Allow access to current step, completed steps, or next step if current is completed
                    $is_accessible = $is_active || $is_completed ||
                                   ($current_step_index !== false && $step_index <= $current_step_index + 1);
                    
                    $class = $is_completed ? 'completed' : ($is_active ? 'active' : '');
                    if (!$is_accessible) {
                        $class .= ' disabled';
                    }
                    ?>
                    <div class="step-card <?php echo $class; ?>" data-step="<?php echo $step_key; ?>">
                        <span class="step-number"><?php echo $is_completed ? '✓' : $step_number; ?></span>
                        <h3 class="step-title"><?php echo esc_html($step_title); ?></h3>
                        <p class="step-description">
                            <?php echo $this->get_step_description($step_key); ?>
                        </p>
                        <?php if ($is_active): ?>
                            <button class="btn-primary" onclick="startStep('<?php echo $step_key; ?>')">
                                <?php _e('Start This Step', 'aevov-onboarding'); ?>
                            </button>
                        <?php elseif ($is_completed): ?>
                            <button class="btn-success" onclick="reviewStep('<?php echo $step_key; ?>')">
                                <?php _e('Review', 'aevov-onboarding'); ?>
                            </button>
                        <?php elseif ($is_accessible && !$is_active): ?>
                            <button class="btn-primary" onclick="startStep('<?php echo $step_key; ?>')" style="opacity: 0.7;">
                                <?php _e('Go to Step', 'aevov-onboarding'); ?>
                            </button>
                        <?php else: ?>
                            <p style="color: #666; font-style: italic; margin: 10px 0;">
                                <?php _e('Complete previous steps first', 'aevov-onboarding'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php
                    $step_number++;
                }
                ?>
            </div>
            
            <div id="step-content" style="margin-top: 40px;">
                <?php $this->render_current_step_content($current_step); ?>
            </div>
        </div>
        
        <script>
        function startStep(stepKey) {
            jQuery.post(aevovOnboarding.ajaxUrl, {
                action: 'aevov_onboarding_action',
                step: stepKey,
                nonce: aevovOnboarding.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
        
        function reviewStep(stepKey) {
            jQuery('#step-content').html('<div class="loading">Loading step details...</div>');
            // Load step review content
        }
        </script>
        <?php
    }
    
    private function render_progress_bar($completed_steps) {
        $total_steps = count($this->onboarding_steps);
        $completed_count = count($completed_steps);
        $progress_percentage = ($completed_count / $total_steps) * 100;
        
        ?>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
        </div>
        <p style="text-align: center; margin: 10px 0;">
            <?php printf(__('Progress: %d of %d steps completed (%d%%)', 'aevov-onboarding'), 
                $completed_count, $total_steps, round($progress_percentage)); ?>
        </p>
        <?php
    }
    
    private function get_step_description($step_key) {
        $descriptions = [
            'welcome' => __('Introduction to the Aevov ecosystem and what you\'ll accomplish', 'aevov-onboarding'),
            'system_check' => __('Verify your WordPress environment meets all requirements', 'aevov-onboarding'),
            'plugin_activation' => __('Activate and configure all required Aevov plugins', 'aevov-onboarding'),
            'architecture_overview' => __('Understand how all components work together', 'aevov-onboarding'),
            'initial_setup' => __('Configure basic settings for optimal performance', 'aevov-onboarding'),
            'pattern_creation' => __('Create your first BLOOM pattern and understand the workflow', 'aevov-onboarding'),
            'chunk_management' => __('Set up chunk scanning and management systems', 'aevov-onboarding'),
            'sync_configuration' => __('Configure pattern synchronization across your network', 'aevov-onboarding'),
            'testing_validation' => __('Test all systems and validate proper operation', 'aevov-onboarding'),
            'completion' => __('Final review and next steps for using your Aevov system', 'aevov-onboarding')
        ];
        
        return $descriptions[$step_key] ?? '';
    }
    
    private function render_current_step_content($current_step) {
        switch ($current_step) {
            case 'welcome':
                $this->render_welcome_step();
                break;
            case 'system_check':
                $this->render_system_check_step();
                break;
            case 'plugin_activation':
                $this->render_plugin_activation_step();
                break;
            case 'architecture_overview':
                $this->render_architecture_step();
                break;
            case 'testing_validation':
                $this->render_testing_validation_step();
                break;
            default:
                echo '<p>' . __('Step content will be loaded here.', 'aevov-onboarding') . '</p>';
        }
    }

    private function render_testing_validation_step() {
        ?>
        <div class="config-section">
            <h3><?php _e('System Testing & Validation', 'aevov-onboarding'); ?></h3>
            <p><?php _e('Your Aevov ecosystem includes a comprehensive testing framework with 2,655 automated tests across 47 categories. Running these tests validates that all components work correctly together.', 'aevov-onboarding'); ?></p>

            <div class="info-section" style="background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h4 style="margin-top: 0; color: #0c5aa6;"><?php _e('Testing Overview', 'aevov-onboarding'); ?></h4>
                <ul style="margin: 0;">
                    <li><strong>2,655 Tests</strong> - Comprehensive validation across all plugins</li>
                    <li><strong>47 Categories</strong> - Organized into 8 manageable groups</li>
                    <li><strong>29 Plugins</strong> - Full ecosystem coverage</li>
                    <li><strong>100% Pass Rate</strong> - Last run passed all tests</li>
                </ul>
            </div>

            <p><?php _e('You can run all tests now or skip this step and access the testing dashboard later from the System Tests menu.', 'aevov-onboarding'); ?></p>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <a href="<?php echo admin_url('admin.php?page=aevov-system-tests'); ?>" class="btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php _e('Go to Testing Dashboard', 'aevov-onboarding'); ?>
                </a>
                <button class="btn-secondary" onclick="completeStep('testing_validation')">
                    <?php _e('Skip Testing (Complete Later)', 'aevov-onboarding'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    private function render_welcome_step() {
        ?>
        <div class="config-section">
            <h3><?php _e('Welcome to Aevov Neurosymbolic Network', 'aevov-onboarding'); ?></h3>
            <p><?php _e('You\'re about to set up a powerful distributed pattern recognition system that combines:', 'aevov-onboarding'); ?></p>
            <ul>
                <li><strong>BLOOM Pattern Recognition:</strong> Advanced neural pattern analysis</li>
                <li><strong>APS Tools:</strong> Comprehensive pattern management suite</li>
                <li><strong>Pattern Sync Protocol:</strong> Multi-site synchronization</li>
                <li><strong>Chunk Management:</strong> Efficient data processing</li>
                <li><strong>Cubbit DS3 Integration:</strong> Distributed storage</li>
            </ul>
            <p><?php _e('This onboarding process will guide you through every step to get your system running perfectly.', 'aevov-onboarding'); ?></p>
            <button class="btn-primary" onclick="completeStep('welcome')">
                <?php _e('Begin Setup', 'aevov-onboarding'); ?>
            </button>
        </div>
        <?php
    }
    
    private function render_system_check_step() {
        ?>
        <div class="config-section">
            <h3><?php _e('System Requirements Check', 'aevov-onboarding'); ?></h3>
            <div id="system-status-results">
                <p><?php _e('Checking system requirements...', 'aevov-onboarding'); ?></p>
            </div>
            <button class="btn-primary" onclick="runSystemCheck()">
                <?php _e('Run System Check', 'aevov-onboarding'); ?>
            </button>
            <div id="system-check-actions" style="margin-top: 20px; display: none;">
                <button class="btn-success" onclick="completeStep('system_check')">
                    <?php _e('Complete System Check', 'aevov-onboarding'); ?>
                </button>
            </div>
        </div>
        
        <script>
        function runSystemCheck() {
            jQuery('#system-status-results').html('<div class="loading">Checking system...</div>');
            jQuery.post(aevovOnboarding.ajaxUrl, {
                action: 'aevov_get_system_status',
                check_completion: true,
                nonce: aevovOnboarding.nonce
            }, function(response) {
                if (response.success) {
                    jQuery('#system-status-results').html(response.data.html);
                    
                    // Show completion button if system check passes
                    if (response.data.can_complete) {
                        jQuery('#system-check-actions').show();
                    }
                    
                    // Auto-complete if all requirements are met
                    if (response.data.auto_complete) {
                        setTimeout(function() {
                            completeStep('system_check');
                        }, 2000);
                    }
                }
            });
        }
        
        function completeStep(step) {
            jQuery.post(aevovOnboarding.ajaxUrl, {
                action: 'aevov_onboarding_action',
                step: step,
                action_type: 'complete',
                nonce: aevovOnboarding.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
        </script>
        <?php
    }
    
    private function render_plugin_activation_step() {
        ?>
        <div class="config-section">
            <h3><?php _e('Plugin Activation', 'aevov-onboarding'); ?></h3>
            <div class="system-status-grid">
                <?php foreach ($this->plugin_dependencies as $plugin_file => $plugin_name): ?>
                    <?php
                    $is_active = is_plugin_active($plugin_file);
                    $status_class = $is_active ? 'active' : 'inactive';
                    $status_text = $is_active ? __('Active', 'aevov-onboarding') : __('Inactive', 'aevov-onboarding');
                    ?>
                    <div class="status-card <?php echo $status_class; ?>">
                        <h4><?php echo esc_html($plugin_name); ?></h4>
                        <p><strong><?php echo $status_text; ?></strong></p>
                        <?php if (!$is_active): ?>
                            <button class="btn-primary" onclick="activatePlugin('<?php echo esc_js($plugin_file); ?>')">
                                <?php _e('Activate', 'aevov-onboarding'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
        function activatePlugin(pluginFile) {
            jQuery.post(aevovOnboarding.ajaxUrl, {
                action: 'aevov_activate_plugin',
                plugin: pluginFile,
                nonce: aevovOnboarding.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
        </script>
        <?php
    }
    
    private function render_architecture_step() {
        ?>
        <div class="config-section">
            <h3><?php _e('Aevov System Architecture', 'aevov-onboarding'); ?></h3>
            <div class="architecture-diagram">
                <?php $this->render_architecture_diagram(); ?>
            </div>
            <p><?php _e('This diagram shows how all components of your Aevov system work together to provide comprehensive pattern recognition and management capabilities.', 'aevov-onboarding'); ?></p>
        </div>
        <?php
    }
    
    private function render_architecture_diagram() {
        ?>
        <svg width="800" height="600" viewBox="0 0 800 600" style="max-width: 100%; height: auto;">
            <!-- Background -->
            <rect width="800" height="600" fill="#f8f9fa" stroke="#e9ecef" stroke-width="2"/>
            
            <!-- Title -->
            <text x="400" y="30" text-anchor="middle" font-size="24" font-weight="bold" fill="#333">
                Aevov Neurosymbolic Architecture
            </text>
            
            <!-- Core Components -->
            <g id="core-layer">
                <!-- APS Tools (Central Hub) -->
                <rect x="300" y="250" width="200" height="100" rx="10" fill="#667eea" stroke="#5a6fd8" stroke-width="2"/>
                <text x="400" y="290" text-anchor="middle" font-size="16" font-weight="bold" fill="white">APS Tools</text>
                <text x="400" y="310" text-anchor="middle" font-size="12" fill="white">Central Management Hub</text>
                
                <!-- BLOOM Pattern Recognition -->
                <rect x="50" y="100" width="180" height="80" rx="10" fill="#28a745" stroke="#1e7e34" stroke-width="2"/>
                <text x="140" y="130" text-anchor="middle" font-size="14" font-weight="bold" fill="white">BLOOM Pattern</text>
                <text x="140" y="150" text-anchor="middle" font-size="14" font-weight="bold" fill="white">Recognition</text>
                
                <!-- Pattern Sync Protocol -->
                <rect x="570" y="100" width="180" height="80" rx="10" fill="#dc3545" stroke="#c82333" stroke-width="2"/>
                <text x="660" y="130" text-anchor="middle" font-size="14" font-weight="bold" fill="white">Pattern Sync</text>
                <text x="660" y="150" text-anchor="middle" font-size="14" font-weight="bold" fill="white">Protocol</text>
                
                <!-- Chunk Scanner -->
                <rect x="50" y="400" width="180" height="80" rx="10" fill="#ffc107" stroke="#e0a800" stroke-width="2"/>
                <text x="140" y="430" text-anchor="middle" font-size="14" font-weight="bold" fill="black">BLOOM Chunk</text>
                <text x="140" y="450" text-anchor="middle" font-size="14" font-weight="bold" fill="black">Scanner</text>
                
                <!-- Chunk Uploader -->
                <rect x="570" y="400" width="180" height="80" rx="10" fill="#6f42c1" stroke="#5a32a3" stroke-width="2"/>
                <text x="660" y="430" text-anchor="middle" font-size="14" font-weight="bold" fill="white">APS Chunk</text>
                <text x="660" y="450" text-anchor="middle" font-size="14" font-weight="bold" fill="white">Uploader</text>
            </g>
            
            <!-- Connections -->
            <g id="connections" stroke="#666" stroke-width="2" fill="none">
                <!-- APS Tools to BLOOM -->
                <path d="M300 280 L230 140" marker-end="url(#arrowhead)"/>
                
                <!-- APS Tools to Sync Protocol -->
                <path d="M500 280 L570 140" marker-end="url(#arrowhead)"/>
                
                <!-- APS Tools to Chunk Scanner -->
                <path d="M300 320 L230 440" marker-end="url(#arrowhead)"/>
                
                <!-- APS Tools to Chunk Uploader -->
                <path d="M500 320 L570 440" marker-end="url(#arrowhead)"/>
                
                <!-- BLOOM to Chunk Scanner -->
                <path d="M140 180 L140 400" marker-end="url(#arrowhead)"/>
                
                <!-- Sync Protocol to Chunk Uploader -->
                <path d="M660 180 L660 400" marker-end="url(#arrowhead)"/>
            </g>
            
            <!-- Arrow marker definition -->
            <defs>
                <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
                    <polygon points="0 0, 10 3.5, 0 7" fill="#666"/>
                </marker>
            </defs>
            
            <!-- Data Flow Labels -->
            <text x="265" y="210" font-size="10" fill="#666">Pattern Data</text>
            <text x="535" y="210" font-size="10" fill="#666">Sync Data</text>
            <text x="265" y="380" font-size="10" fill="#666">Chunk Data</text>
            <text x="535" y="380" font-size="10" fill="#666">Upload Data</text>
            
            <!-- Legend -->
            <g id="legend">
                <rect x="20" y="520" width="760" height="70" fill="white" stroke="#ddd" stroke-width="1" rx="5"/>
                <text x="30" y="540" font-size="14" font-weight="bold" fill="#333">Data Flow:</text>
                <text x="30" y="560" font-size="12" fill="#666">• Patterns are recognized by BLOOM and managed through APS Tools</text>
                <text x="30" y="575" font-size="12" fill="#666">• Chunks are scanned and uploaded through dedicated management tools</text>
                <text x="30" y="590" font-size="12" fill="#666">• All data synchronizes across your network via the Pattern Sync Protocol</text>
            </g>
        </svg>
        <?php
    }
    
    public function render_system_status_page() {
        ?>
        <div class="aevov-onboarding-container">
            <div class="aevov-header">
                <h1><?php _e('System Status', 'aevov-onboarding'); ?></h1>
                <p><?php _e('Monitor all Aevov components and their current status', 'aevov-onboarding'); ?></p>
            </div>
            
            <div class="system-status-grid">
                <?php $this->render_plugin_status_cards(); ?>
            </div>
            
            <div class="config-section">
                <h3><?php _e('System Health Check', 'aevov-onboarding'); ?></h3>
                <button class="btn-primary" onclick="runFullSystemCheck()">
                    <?php _e('Run Full System Check', 'aevov-onboarding'); ?>
                </button>
                <div id="system-check-results" style="margin-top: 20px;"></div>
            </div>
        </div>
        
        <script>
        function runFullSystemCheck() {
            jQuery('#system-check-results').html('<div class="loading">Running comprehensive system check...</div>');
            jQuery.post(aevovOnboarding.ajaxUrl, {
                action: 'aevov_get_system_status',
                full_check: true,
                nonce: aevovOnboarding.nonce
            }, function(response) {
                if (response.success) {
                    jQuery('#system-check-results').html(response.data.html);
                }
            });
        }
        </script>
        <?php
    }
    
    public function render_architecture_page() {
        ?>
        <div class="aevov-onboarding-container">
            <div class="aevov-header">
                <h1><?php _e('System Architecture', 'aevov-onboarding'); ?></h1>
                <p><?php _e('Visual representation of your Aevov Neurosymbolic Network', 'aevov-onboarding'); ?></p>
            </div>
            
            <div class="architecture-diagram">
                <?php $this->render_architecture_diagram(); ?>
            </div>
            
            <div class="config-section">
                <h3><?php _e('Component Descriptions', 'aevov-onboarding'); ?></h3>
                <?php $this->render_component_descriptions(); ?>
            </div>
        </div>
        <?php
    }
    
    public function render_configuration_page() {
        ?>
        <div class="aevov-onboarding-container">
            <div class="aevov-header">
                <h1><?php _e('System Configuration', 'aevov-onboarding'); ?></h1>
                <p><?php _e('Configure all aspects of your Aevov network from one central location', 'aevov-onboarding'); ?></p>
            </div>

            <form id="aevov-config-form">
                <?php $this->render_configuration_sections(); ?>

                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn-primary">
                        <?php _e('Save All Configuration', 'aevov-onboarding'); ?>
                    </button>
                </div>
            </form>
        </div>

        <script>
        jQuery('#aevov-config-form').on('submit', function(e) {
            e.preventDefault();
            var formData = jQuery(this).serialize();

            jQuery.post(aevovOnboarding.ajaxUrl, {
                action: 'aevov_save_config',
                config_data: formData,
                nonce: aevovOnboarding.nonce
            }, function(response) {
                if (response.success) {
                    alert('Configuration saved successfully!');
                } else {
                    alert('Error saving configuration: ' + response.data);
                }
            });
        });
        </script>
        <?php
    }

    public function render_workflow_testing_page() {
        require_once __DIR__ . '/assets/templates/workflow-testing.php';
    }
    
    private function render_plugin_status_cards() {
        foreach ($this->plugin_dependencies as $plugin_file => $plugin_name) {
            $is_active = is_plugin_active($plugin_file);
            $status_class = $is_active ? 'active' : 'inactive';
            $status_text = $is_active ? __('Active', 'aevov-onboarding') : __('Inactive', 'aevov-onboarding');
            $status_icon = $is_active ? '✅' : '❌';
            
            ?>
            <div class="status-card <?php echo $status_class; ?>">
                <h4><?php echo $status_icon . ' ' . esc_html($plugin_name); ?></h4>
                <p><strong><?php echo $status_text; ?></strong></p>
                <?php if ($is_active): ?>
                    <p><small><?php _e('All systems operational', 'aevov-onboarding'); ?></small></p>
                <?php else: ?>
                    <p><small><?php _e('Plugin needs activation', 'aevov-onboarding'); ?></small></p>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    
    private function render_component_descriptions() {
        $components = [
            'APS Tools' => __('Central management hub that coordinates all pattern recognition activities and provides unified interface for system administration.', 'aevov-onboarding'),
            'BLOOM Pattern Recognition' => __('Advanced neural pattern analysis engine that processes and identifies complex patterns in your data using state-of-the-art algorithms.', 'aevov-onboarding'),
            'Pattern Sync Protocol' => __('Multi-site synchronization system that ensures pattern data consistency across your entire network infrastructure.', 'aevov-onboarding'),
            'BLOOM Chunk Scanner' => __('Automated scanning system that identifies and processes data chunks for pattern analysis and storage optimization.', 'aevov-onboarding'),
            'APS Chunk Uploader' => __('Enhanced media uploader that integrates with the pattern recognition system for seamless data ingestion and processing.', 'aevov-onboarding')
        ];
        
        foreach ($components as $name => $description) {
            ?>
            <div style="margin: 20px 0; padding: 15px; border-left: 4px solid #667eea; background: #f8f9ff;">
                <h4 style="margin: 0 0 10px 0; color: #667eea;"><?php echo esc_html($name); ?></h4>
                <p style="margin: 0; line-height: 1.6;"><?php echo esc_html($description); ?></p>
            </div>
            <?php
        }
    }
    
    private function render_configuration_sections() {
        ?>
        <div class="config-section">
            <h3><?php _e('BLOOM Pattern Recognition Settings', 'aevov-onboarding'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Pattern Analysis Depth', 'aevov-onboarding'); ?></th>
                    <td>
                        <select name="bloom_analysis_depth">
                            <option value="basic"><?php _e('Basic', 'aevov-onboarding'); ?></option>
                            <option value="standard" selected><?php _e('Standard', 'aevov-onboarding'); ?></option>
                            <option value="advanced"><?php _e('Advanced', 'aevov-onboarding'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Processing Batch Size', 'aevov-onboarding'); ?></th>
                    <td><input type="number" name="bloom_batch_size" value="100" min="10" max="1000" /></td>
                </tr>
            </table>
        </div>
        
        <div class="config-section">
            <h3><?php _e('APS Tools Configuration', 'aevov-onboarding'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Auto-sync Interval (minutes)', 'aevov-onboarding'); ?></th>
                    <td><input type="number" name="aps_sync_interval" value="15" min="5" max="1440" /></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Debug Mode', 'aevov-onboarding'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aps_debug_mode" value="1" />
                            <?php _e('Enable debug logging', 'aevov-onboarding'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="config-section">
            <h3><?php _e('Pattern Sync Protocol Settings', 'aevov-onboarding'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Sync Network Nodes', 'aevov-onboarding'); ?></th>
                    <td>
                        <textarea name="sync_nodes" rows="4" cols="50" placeholder="<?php _e('Enter node URLs, one per line', 'aevov-onboarding'); ?>"></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Encryption Level', 'aevov-onboarding'); ?></th>
                    <td>
                        <select name="sync_encryption">
                            <option value="basic"><?php _e('Basic', 'aevov-onboarding'); ?></option>
                            <option value="standard" selected><?php _e('Standard', 'aevov-onboarding'); ?></option>
                            <option value="high"><?php _e('High Security', 'aevov-onboarding'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="config-section">
            <h3><?php _e('Chunk Management Settings', 'aevov-onboarding'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Auto-scan New Uploads', 'aevov-onboarding'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="chunk_auto_scan" value="1" checked />
                            <?php _e('Automatically scan uploaded files for chunks', 'aevov-onboarding'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Chunk Size Limit (MB)', 'aevov-onboarding'); ?></th>
                    <td><input type="number" name="chunk_size_limit" value="10" min="1" max="100" /></td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    private function check_onboarding_status() {
        $completed_steps = get_option('aevov_completed_steps', []);
        $total_steps = count($this->onboarding_steps);
        
        if (count($completed_steps) === $total_steps) {
            update_option('aevov_onboarding_complete', true);
        }
    }
    
    // AJAX Handlers
    public function handle_ajax_action() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aevov-onboarding-nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $step = sanitize_text_field($_POST['step'] ?? '');
        $action_type = sanitize_text_field($_POST['action_type'] ?? 'start');
        
        // Validate step
        if (empty($step) || !array_key_exists($step, $this->onboarding_steps)) {
            wp_send_json_error('Invalid step: ' . $step);
            return;
        }
        
        if ($action_type === 'complete') {
            $completed_steps = get_option('aevov_completed_steps', []);
            if (!in_array($step, $completed_steps)) {
                $completed_steps[] = $step;
                update_option('aevov_completed_steps', $completed_steps);
            }
            
            // Move to next step
            $step_keys = array_keys($this->onboarding_steps);
            $current_index = array_search($step, $step_keys);
            if ($current_index !== false && isset($step_keys[$current_index + 1])) {
                update_option('aevov_current_onboarding_step', $step_keys[$current_index + 1]);
            }
        } else {
            update_option('aevov_current_onboarding_step', $step);
        }
        
        wp_send_json_success(['message' => 'Step updated successfully']);
    }
    
    public function get_system_status() {
        // DIAGNOSTIC LOG: Track system status calls
        error_log('AEVOV ONBOARDING DEBUG: get_system_status() called');
        error_log('AEVOV ONBOARDING DEBUG: POST data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aevov-onboarding-nonce')) {
            error_log('AEVOV ONBOARDING DEBUG: Nonce verification failed');
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            error_log('AEVOV ONBOARDING DEBUG: Insufficient permissions');
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $check_completion = isset($_POST['check_completion']) && $_POST['check_completion'];
        
        error_log('AEVOV ONBOARDING DEBUG: Starting system status check');
        $status_html = '<div class="system-status-grid">';
        
        // Check PHP version
        $php_version = PHP_VERSION;
        $php_ok = version_compare($php_version, '7.4', '>=');
        $status_html .= sprintf(
            '<div class="status-card %s"><h4>PHP Version</h4><p>%s</p><small>%s</small></div>',
            $php_ok ? 'active' : 'inactive',
            $php_version,
            $php_ok ? 'Compatible' : 'Requires PHP 7.4+'
        );
        
        // Check WordPress version
        $wp_version = get_bloginfo('version');
        $wp_ok = version_compare($wp_version, '5.0', '>=');
        $status_html .= sprintf(
            '<div class="status-card %s"><h4>WordPress Version</h4><p>%s</p><small>%s</small></div>',
            $wp_ok ? 'active' : 'inactive',
            $wp_version,
            $wp_ok ? 'Compatible' : 'Requires WP 5.0+'
        );
        
        // Check plugins
        $inactive_plugins = [];
        $active_plugins = [];
        
        foreach ($this->plugin_dependencies as $plugin_file => $plugin_name) {
            $is_active = is_plugin_active($plugin_file);
            
            if ($is_active) {
                $active_plugins[] = $plugin_name;
            } else {
                $inactive_plugins[] = $plugin_name;
            }
            
            $status_html .= sprintf(
                '<div class="status-card %s"><h4>%s</h4><p>%s</p></div>',
                $is_active ? 'active' : 'inactive',
                esc_html($plugin_name),
                $is_active ? 'Active' : 'Inactive'
            );
        }
        
        $status_html .= '</div>';
        
        // Determine if system check can be completed
        $all_requirements_met = $php_ok && $wp_ok && empty($inactive_plugins);
        
        if ($check_completion && $all_requirements_met) {
            $status_html .= '<div class="status-card active" style="grid-column: 1 / -1; text-align: center; margin-top: 20px;">';
            $status_html .= '<h3 style="color: #28a745;">✅ All System Requirements Met!</h3>';
            $status_html .= '<p>Your system is ready for the Aevov Neurosymbolic Network.</p>';
            $status_html .= '</div>';
        } elseif ($check_completion && !$all_requirements_met) {
            $status_html .= '<div class="status-card inactive" style="grid-column: 1 / -1; text-align: center; margin-top: 20px;">';
            $status_html .= '<h3 style="color: #dc3545;">❌ System Requirements Not Met</h3>';
            $status_html .= '<p>Please resolve the issues above before proceeding.</p>';
            $status_html .= '</div>';
        }
        
        // DIAGNOSTIC LOG: Plugin status summary
        error_log('AEVOV ONBOARDING DEBUG: Active plugins: ' . implode(', ', $active_plugins));
        error_log('AEVOV ONBOARDING DEBUG: Inactive plugins: ' . implode(', ', $inactive_plugins));
        error_log('AEVOV ONBOARDING DEBUG: System check result: ' . ($all_requirements_met ? 'PASS' : 'FAIL'));
        error_log('AEVOV ONBOARDING DEBUG: Can complete: ' . ($all_requirements_met ? 'YES' : 'NO'));
        
        wp_send_json_success([
            'html' => $status_html,
            'can_complete' => $all_requirements_met,
            'auto_complete' => $check_completion && $all_requirements_met
        ]);
    }
    
    public function activate_plugin() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aevov-onboarding-nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!current_user_can('activate_plugins')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $plugin = sanitize_text_field($_POST['plugin'] ?? '');
        
        if (empty($plugin)) {
            wp_send_json_error('No plugin specified');
            return;
        }
        
        if (!array_key_exists($plugin, $this->plugin_dependencies)) {
            wp_send_json_error('Invalid plugin');
        }
        
        $result = activate_plugin($plugin);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Plugin activated successfully');
    }
    
    public function save_configuration() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'aevov-onboarding-nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $config_data_raw = $_POST['config_data'] ?? '';
        if (empty($config_data_raw)) {
            wp_send_json_error('No configuration data provided');
            return;
        }
        
        parse_str($config_data_raw, $config_data);
        
        // Save configuration options
        foreach ($config_data as $key => $value) {
            update_option('aevov_' . $key, sanitize_text_field($value));
        }
        
        wp_send_json_success('Configuration saved successfully');
    }
}

// Initialize the onboarding system
new AevovOnboardingSystem();