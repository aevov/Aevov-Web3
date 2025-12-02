<?php
/**
 * Plugin Name: Aevov Unified Dashboard
 * Plugin URI: https://aevov.com/unified-dashboard
 * Description: Sophisticated, dead-simple unified control center for all 29 Aevov plugins with comprehensive onboarding, pattern creation, and real-time monitoring
 * Version: 1.0.0
 * Author: Aevov Systems
 * License: GPL v2 or later
 * Network: true
 * Text Domain: aevov-unified-dashboard
 * Requires PHP: 7.4
 */

namespace AevovUnifiedDashboard;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AUD_VERSION', '1.0.0');
define('AUD_PLUGIN_FILE', __FILE__);
define('AUD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AUD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AUD_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Unified Dashboard Class
 *
 * Comprehensive control center that integrates all 29 Aevov plugins:
 * - 3 Core plugins (APS, Bloom, APS Tools)
 * - 26 Sister plugins
 *
 * Features:
 * - Beautiful, simple, appealing UI
 * - Comprehensive onboarding system
 * - Real-time plugin status monitoring
 * - Pattern creation and visualization
 * - Cross-plugin workflows
 * - Performance analytics
 */
class UnifiedDashboard {
    private static $instance = null;

    /**
     * All 29 Aevov Plugins
     */
    private $all_plugins = [
        // Core 3 Plugins
        'core' => [
            'AevovPatternSyncProtocol' => [
                'name' => 'Aevov Pattern Sync Protocol',
                'file' => 'AevovPatternSyncProtocol/aevov-pattern-sync-protocol.php',
                'description' => 'Core pattern synchronization and blockchain protocol',
                'icon' => '🔄',
                'category' => 'Core System',
                'priority' => 1,
                'features' => ['Pattern Sync', 'Blockchain', 'Consensus', 'Proof of Contribution']
            ],
            'bloom-pattern-recognition' => [
                'name' => 'Bloom Pattern Recognition',
                'file' => 'bloom-pattern-recognition/bloom-pattern-system.php',
                'description' => 'Advanced AI pattern recognition and neural processing',
                'icon' => '🧠',
                'category' => 'Core System',
                'priority' => 2,
                'features' => ['AI Recognition', 'Neural Networks', 'Pattern Analysis', 'Learning']
            ],
            'aps-tools' => [
                'name' => 'APS Tools',
                'file' => 'aps-tools/aps-tools.php',
                'description' => 'Administrative and utility tools for APS ecosystem',
                'icon' => '🛠️',
                'category' => 'Core System',
                'priority' => 3,
                'features' => ['Admin Tools', 'Utilities', 'Management', 'Configuration']
            ]
        ],

        // Sister Plugins (26 total)
        'sister' => [
            'aevov-application-forge' => [
                'name' => 'Application Forge',
                'file' => 'aevov-application-forge/aevov-application-forge.php',
                'description' => 'Generate applications from patterns',
                'icon' => '⚒️',
                'category' => 'Creation Tools',
                'features' => ['App Generation', 'Code Creation', 'Templates']
            ],
            'aevov-chat-ui' => [
                'name' => 'Chat UI',
                'file' => 'aevov-chat-ui/aevov-chat-ui.php',
                'description' => 'Interactive chat interface for Aevov',
                'icon' => '💬',
                'category' => 'User Interface',
                'features' => ['Chat', 'Conversations', 'Real-time']
            ],
            'aevov-chunk-registry' => [
                'name' => 'Chunk Registry',
                'file' => 'aevov-chunk-registry/aevov-chunk-registry.php',
                'description' => 'Manage and track data chunks',
                'icon' => '📦',
                'category' => 'Data Management',
                'features' => ['Chunk Storage', 'Registry', 'Tracking']
            ],
            'aevov-cognitive-engine' => [
                'name' => 'Cognitive Engine',
                'file' => 'aevov-cognitive-engine/aevov-cognitive-engine.php',
                'description' => 'Advanced cognitive processing and reasoning',
                'icon' => '🤔',
                'category' => 'AI Engines',
                'features' => ['Cognition', 'Reasoning', 'Decision Making']
            ],
            'aevov-cubbit-cdn' => [
                'name' => 'Cubbit CDN',
                'file' => 'aevov-cubbit-cdn/aevov-cubbit-cdn.php',
                'description' => 'Decentralized CDN integration',
                'icon' => '☁️',
                'category' => 'Infrastructure',
                'features' => ['CDN', 'Distributed Storage', 'Caching']
            ],
            'aevov-cubbit-downloader' => [
                'name' => 'Cubbit Downloader',
                'file' => 'aevov-cubbit-downloader/aevov-cubbit-downloader.php',
                'description' => 'Download manager for Cubbit network',
                'icon' => '⬇️',
                'category' => 'Infrastructure',
                'features' => ['Downloads', 'Transfer', 'Management']
            ],
            'aevov-demo-system' => [
                'name' => 'Demo System',
                'file' => 'aevov-demo-system/aevov-demo-system.php',
                'description' => 'Comprehensive demo and testing environment',
                'icon' => '🎭',
                'category' => 'Development',
                'features' => ['Demo', 'Testing', 'Examples']
            ],
            'aevov-diagnostic-network' => [
                'name' => 'Diagnostic Network',
                'file' => 'aevov-diagnostic-network/aevov-diagnostic-network.php',
                'description' => 'System diagnostics and monitoring',
                'icon' => '🔍',
                'category' => 'Monitoring',
                'features' => ['Diagnostics', 'Health Checks', 'Monitoring']
            ],
            'aevov-embedding-engine' => [
                'name' => 'Embedding Engine',
                'file' => 'aevov-embedding-engine/aevov-embedding-engine.php',
                'description' => 'Generate vector embeddings for AI',
                'icon' => '🎯',
                'category' => 'AI Engines',
                'features' => ['Embeddings', 'Vectors', 'AI Models']
            ],
            'aevov-image-engine' => [
                'name' => 'Image Engine',
                'file' => 'aevov-image-engine/aevov-image-engine.php',
                'description' => 'Advanced image processing and generation',
                'icon' => '🖼️',
                'category' => 'Media Engines',
                'features' => ['Image Processing', 'Generation', 'Analysis']
            ],
            'aevov-language-engine' => [
                'name' => 'Language Engine',
                'file' => 'aevov-language-engine/aevov-language-engine.php',
                'description' => 'Natural language processing',
                'icon' => '🗣️',
                'category' => 'AI Engines',
                'features' => ['NLP', 'Text Analysis', 'Language Models']
            ],
            'aevov-language-engine-v2' => [
                'name' => 'Language Engine V2',
                'file' => 'aevov-language-engine-v2/aevov-language-engine-v2.php',
                'description' => 'Next-gen language processing',
                'icon' => '🗨️',
                'category' => 'AI Engines',
                'features' => ['Advanced NLP', 'v2 Features', 'Enhanced Models']
            ],
            'aevov-memory-core' => [
                'name' => 'Memory Core',
                'file' => 'aevov-memory-core/aevov-memory-core.php',
                'description' => 'Persistent memory and state management',
                'icon' => '💾',
                'category' => 'Data Management',
                'features' => ['Memory', 'State', 'Persistence']
            ],
            'aevov-music-forge' => [
                'name' => 'Music Forge',
                'file' => 'aevov-music-forge/aevov-music-forge.php',
                'description' => 'AI-powered music generation',
                'icon' => '🎵',
                'category' => 'Media Engines',
                'features' => ['Music Generation', 'Audio', 'Composition']
            ],
            'aevov-neuro-architect' => [
                'name' => 'Neuro Architect',
                'file' => 'aevov-neuro-architect/aevov-neuro-architect.php',
                'description' => 'Neural network architecture design',
                'icon' => '🏗️',
                'category' => 'AI Engines',
                'features' => ['Neural Design', 'Architecture', 'Network Building']
            ],
            'aevov-onboarding-system' => [
                'name' => 'Onboarding System',
                'file' => 'aevov-onboarding-system/aevov-onboarding.php',
                'description' => 'User onboarding and tutorials',
                'icon' => '👋',
                'category' => 'User Interface',
                'features' => ['Onboarding', 'Tutorials', 'Getting Started']
            ],
            'aevov-physics-engine' => [
                'name' => 'Physics Engine',
                'file' => 'aevov-physics-engine/aevov-physics-engine.php',
                'description' => 'Physics simulation and modeling',
                'icon' => '⚛️',
                'category' => 'Simulation',
                'features' => ['Physics', 'Simulation', 'Modeling']
            ],
            'aevov-playground' => [
                'name' => 'Playground',
                'file' => 'aevov-playground/aevov-playground.php',
                'description' => 'Experimental testing environment',
                'icon' => '🎪',
                'category' => 'Development',
                'features' => ['Testing', 'Experiments', 'Sandbox']
            ],
            'aevov-reasoning-engine' => [
                'name' => 'Reasoning Engine',
                'file' => 'aevov-reasoning-engine/aevov-reasoning-engine.php',
                'description' => 'Logical reasoning and inference',
                'icon' => '🧮',
                'category' => 'AI Engines',
                'features' => ['Reasoning', 'Logic', 'Inference']
            ],
            'aevov-security' => [
                'name' => 'Security',
                'file' => 'aevov-security/aevov-security.php',
                'description' => 'Security and encryption features',
                'icon' => '🔒',
                'category' => 'Infrastructure',
                'features' => ['Security', 'Encryption', 'Protection']
            ],
            'aevov-simulation-engine' => [
                'name' => 'Simulation Engine',
                'file' => 'aevov-simulation-engine/aevov-simulation-engine.php',
                'description' => 'Complex system simulation',
                'icon' => '🎮',
                'category' => 'Simulation',
                'features' => ['Simulation', 'Modeling', 'Scenarios']
            ],
            'aevov-stream' => [
                'name' => 'Stream',
                'file' => 'aevov-stream/aevov-stream.php',
                'description' => 'Real-time data streaming',
                'icon' => '📡',
                'category' => 'Infrastructure',
                'features' => ['Streaming', 'Real-time', 'Data Flow']
            ],
            'aevov-super-app-forge' => [
                'name' => 'Super-App Forge',
                'file' => 'aevov-super-app-forge/aevov-super-app-forge.php',
                'description' => 'Create comprehensive super-applications',
                'icon' => '⚡',
                'category' => 'Creation Tools',
                'features' => ['Super-Apps', 'Complex Apps', 'Integration']
            ],
            'aevov-transcription-engine' => [
                'name' => 'Transcription Engine',
                'file' => 'aevov-transcription-engine/aevov-transcription-engine.php',
                'description' => 'Audio transcription and STT',
                'icon' => '🎤',
                'category' => 'Media Engines',
                'features' => ['Transcription', 'Speech-to-Text', 'Audio Analysis']
            ],
            'aevov-vision-depth' => [
                'name' => 'Vision Depth',
                'file' => 'aevov-vision-depth/aevov-vision-depth.php',
                'description' => 'Privacy-first behavioral intelligence',
                'icon' => '👁️',
                'category' => 'AI Engines',
                'features' => ['Vision AI', 'Behavioral Analysis', 'Privacy-First']
            ],
            'bloom-chunk-scanner' => [
                'name' => 'Bloom Chunk Scanner',
                'file' => 'bloom-chunk-scanner/bloom-chunk-scanner.php',
                'description' => 'Scan and analyze data chunks',
                'icon' => '🔬',
                'category' => 'Data Management',
                'features' => ['Scanning', 'Chunk Analysis', 'Detection']
            ]
        ]
    ];

    /**
     * Onboarding steps
     */
    private $onboarding_steps = [
        'welcome' => [
            'title' => 'Welcome to Aevov',
            'subtitle' => 'Your Neurosymbolic Intelligence Platform',
            'icon' => '🎉',
            'description' => 'Get started with the most comprehensive AI ecosystem',
            'duration' => '1 min'
        ],
        'system_check' => [
            'title' => 'System Check',
            'subtitle' => 'Verifying your environment',
            'icon' => '✅',
            'description' => 'Checking all 29 plugins and dependencies',
            'duration' => '30 sec'
        ],
        'architecture' => [
            'title' => 'Architecture Overview',
            'subtitle' => 'Understanding the Aevov Ecosystem',
            'icon' => '🏛️',
            'description' => 'Learn how all components work together',
            'duration' => '2 min'
        ],
        'pattern_creation' => [
            'title' => 'Create Your First Pattern',
            'subtitle' => 'Experience the power of pattern synchronization',
            'icon' => '✨',
            'description' => 'Create and sync your first pattern across the network',
            'duration' => '3 min'
        ],
        'exploration' => [
            'title' => 'Explore Features',
            'subtitle' => 'Discover what you can build',
            'icon' => '🚀',
            'description' => 'Tour the capabilities of each plugin',
            'duration' => '5 min'
        ],
        'completion' => [
            'title' => 'Ready to Build!',
            'subtitle' => 'Your ecosystem is ready',
            'icon' => '🎯',
            'description' => 'Start creating amazing AI-powered applications',
            'duration' => '1 min'
        ]
    ];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Enqueue assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Ajax handlers
        add_action('wp_ajax_aud_get_plugin_status', [$this, 'ajax_get_plugin_status']);
        add_action('wp_ajax_aud_activate_plugin', [$this, 'ajax_activate_plugin']);
        add_action('wp_ajax_aud_create_pattern', [$this, 'ajax_create_pattern']);
        add_action('wp_ajax_aud_get_dashboard_stats', [$this, 'ajax_get_dashboard_stats']);
        add_action('wp_ajax_aud_complete_onboarding_step', [$this, 'ajax_complete_onboarding_step']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Aevov Dashboard',
            'Aevov Dashboard',
            'manage_options',
            'aevov-unified-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-networking',
            2
        );

        // Submenu pages
        add_submenu_page(
            'aevov-unified-dashboard',
            'Plugin Manager',
            'Plugin Manager',
            'manage_options',
            'aevov-plugin-manager',
            [$this, 'render_plugin_manager_page']
        );

        add_submenu_page(
            'aevov-unified-dashboard',
            'Pattern Creator',
            'Pattern Creator',
            'manage_options',
            'aevov-pattern-creator',
            [$this, 'render_pattern_creator_page']
        );

        add_submenu_page(
            'aevov-unified-dashboard',
            'System Monitor',
            'System Monitor',
            'manage_options',
            'aevov-system-monitor',
            [$this, 'render_system_monitor_page']
        );

        add_submenu_page(
            'aevov-unified-dashboard',
            'Onboarding',
            'Onboarding',
            'manage_options',
            'aevov-onboarding',
            [$this, 'render_onboarding_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'aevov-') === false) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'aud-dashboard-css',
            AUD_PLUGIN_URL . 'assets/css/dashboard.css',
            [],
            AUD_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'aud-dashboard-js',
            AUD_PLUGIN_URL . 'assets/js/dashboard.js',
            ['jquery'],
            AUD_VERSION,
            true
        );

        // Localize script
        wp_localize_script('aud-dashboard-js', 'audData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aud_nonce'),
            'plugins' => $this->all_plugins,
            'onboardingSteps' => $this->onboarding_steps,
            'isOnboardingComplete' => get_option('aud_onboarding_complete', false),
            'currentStep' => get_option('aud_current_onboarding_step', 'welcome')
        ]);
    }

    public function register_rest_routes() {
        register_rest_route('aevov/v1', '/dashboard/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_dashboard_stats'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aevov/v1', '/plugins/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_plugins_status'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('aevov/v1', '/patterns/create', [
            'methods' => 'POST',
            'callback' => [$this, 'create_pattern'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    public function render_dashboard_page() {
        include AUD_PLUGIN_DIR . 'templates/dashboard.php';
    }

    public function render_plugin_manager_page() {
        include AUD_PLUGIN_DIR . 'templates/plugin-manager.php';
    }

    public function render_pattern_creator_page() {
        include AUD_PLUGIN_DIR . 'templates/pattern-creator.php';
    }

    public function render_system_monitor_page() {
        include AUD_PLUGIN_DIR . 'templates/system-monitor.php';
    }

    public function render_onboarding_page() {
        include AUD_PLUGIN_DIR . 'templates/onboarding.php';
    }

    public function ajax_get_plugin_status() {
        check_ajax_referer('aud_nonce', 'nonce');

        $status = $this->get_all_plugins_status();
        wp_send_json_success($status);
    }

    public function ajax_get_dashboard_stats() {
        check_ajax_referer('aud_nonce', 'nonce');

        $stats = $this->get_dashboard_statistics();
        wp_send_json_success($stats);
    }

    public function ajax_activate_plugin() {
        check_ajax_referer('aud_nonce', 'nonce');

        $plugin_file = sanitize_text_field($_POST['plugin']);

        if (!current_user_can('activate_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }

        $result = activate_plugin($plugin_file);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success('Plugin activated successfully');
    }

    public function ajax_create_pattern() {
        check_ajax_referer('aud_nonce', 'nonce');

        $pattern_data = [
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'data' => json_decode(stripslashes($_POST['data']), true)
        ];

        // Create pattern via APS
        if (function_exists('APS\\create_pattern')) {
            $pattern_id = \APS\create_pattern($pattern_data);
            wp_send_json_success(['pattern_id' => $pattern_id]);
        } else {
            wp_send_json_error('APS Plugin not available');
        }
    }

    public function ajax_complete_onboarding_step() {
        check_ajax_referer('aud_nonce', 'nonce');

        $step = sanitize_text_field($_POST['step']);

        update_option('aud_current_onboarding_step', $step);

        if ($step === 'completion') {
            update_option('aud_onboarding_complete', true);
        }

        wp_send_json_success();
    }

    private function get_all_plugins_status() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);

        $status = [
            'core' => [],
            'sister' => [],
            'summary' => [
                'total' => 29,
                'active' => 0,
                'inactive' => 0
            ]
        ];

        foreach ($this->all_plugins as $type => $plugins) {
            foreach ($plugins as $slug => $plugin_info) {
                $is_active = in_array($plugin_info['file'], $active_plugins);

                $status[$type][$slug] = array_merge($plugin_info, [
                    'active' => $is_active,
                    'installed' => isset($all_plugins[$plugin_info['file']])
                ]);

                if ($is_active) {
                    $status['summary']['active']++;
                } else {
                    $status['summary']['inactive']++;
                }
            }
        }

        return $status;
    }

    private function get_dashboard_statistics() {
        global $wpdb;

        $stats = [
            'plugins' => [
                'total' => 29,
                'active' => 0,
                'categories' => []
            ],
            'patterns' => [
                'total' => 0,
                'synced' => 0,
                'pending' => 0
            ],
            'system' => [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'php_version' => PHP_VERSION,
                'wp_version' => get_bloginfo('version')
            ],
            'performance' => [
                'api_calls_today' => 0,
                'patterns_created_today' => 0,
                'sync_operations' => 0
            ]
        ];

        // Get plugin stats
        $plugin_status = $this->get_all_plugins_status();
        $stats['plugins']['active'] = $plugin_status['summary']['active'];

        // Get pattern stats (if APS is available)
        if (class_exists('APS\\Pattern_DB')) {
            $pattern_table = $wpdb->prefix . 'aps_patterns';
            if ($wpdb->get_var("SHOW TABLES LIKE '$pattern_table'") === $pattern_table) {
                $stats['patterns']['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $pattern_table");
                $stats['patterns']['synced'] = $wpdb->get_var("SELECT COUNT(*) FROM $pattern_table WHERE sync_status = 'synced'");
            }
        }

        return $stats;
    }

    public function get_dashboard_stats($request) {
        return rest_ensure_response($this->get_dashboard_statistics());
    }

    public function get_plugins_status($request) {
        return rest_ensure_response($this->get_all_plugins_status());
    }

    public function create_pattern($request) {
        $params = $request->get_json_params();

        // Validate required fields
        if (empty($params['name']) || empty($params['data'])) {
            return new \WP_Error('missing_fields', 'Pattern name and data are required', ['status' => 400]);
        }

        // Create pattern via APS
        if (function_exists('APS\\create_pattern')) {
            $pattern_id = \APS\create_pattern($params);
            return rest_ensure_response(['pattern_id' => $pattern_id, 'success' => true]);
        }

        return new \WP_Error('aps_unavailable', 'APS Plugin not available', ['status' => 503]);
    }
}

// Initialize the plugin
function aud_init() {
    return UnifiedDashboard::get_instance();
}

// Start the plugin
add_action('plugins_loaded', __NAMESPACE__ . '\\aud_init');
