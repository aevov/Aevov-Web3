<?php
/**
 * Plugin Name: Aevov Pattern Sync-protocol
 * Description: Advanced pattern comparison and synchronization system for BLOOM Pattern Recognition
 * Version: 1.0.0
 * Text Domain: aps
 * Domain Path: /languages
 */

namespace APS\Analysis;

if (!defined('ABSPATH')) exit;

final class APS_Plugin {
    private static $instance = null;
    public $admin;
    public $loader;
    private $initialized = false;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct($loader = null, $admin = null) {
        $this->loader = $loader;
        $this->admin = $admin;
        $this->define_constants();
        if (function_exists('add_action')) {
            add_action('plugins_loaded', [$this, 'initialize'], 0);
        }
    }

    private function define_constants() {
        if (!defined('APS_VERSION')) {
            define('APS_VERSION', '1.0.0');
        }
        if (!defined('APS_FILE')) {
            define('APS_FILE', __FILE__);
        }
        if (!defined('APS_PATH')) {
            define('APS_PATH', plugin_dir_path(dirname(__FILE__, 2)));
        }
        if (!defined('APS_URL')) {
            define('APS_URL', plugin_dir_url(dirname(__FILE__, 2)));
        }
        if (!defined('APS_ASSETS')) {
            define('APS_ASSETS', APS_URL . 'assets/');
        }
    }

    public function initialize() {
        if ($this->initialized) return;

        if (!$this->check_bloom_dependencies()) {
            if (function_exists('add_action')) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>APS requires BLOOM Pattern Recognition System.</p></div>';
                });
            }
            return;
        }

        $this->initialized = true;
        // Dependencies are now loaded via Composer autoloader
        $this->init_components();
        $this->init_hooks();

        if (function_exists('do_action')) {
            do_action('aps_initialized');
        }
    }

    private function check_bloom_dependencies() {
        // Multiple methods to check if BLOOM Pattern Recognition is available
        
        // Method 1: Check for integration class
        if (class_exists('BLOOM_APS_Integration')) {
            return true;
        }
        
        // Method 2: Check for core BLOOM classes
        if (class_exists('BLOOM\\Core\\BloomPatternSystem') ||
            class_exists('BLOOM\\Integration\\APSIntegration')) {
            return true;
        }
        
        // Method 3: Check if BLOOM functions exist
        if (function_exists('BLOOM')) { // Check for the global BLOOM() function
            return true;
        }
        
        // Method 4: Check if plugin is active
        if (function_exists('is_plugin_active')) {
            $possible_paths = [
                'bloom-pattern-recognition/bloom-pattern-system.php',
                'bloom-pattern-recognition/bloom-pattern-recognition.php',
                'bloom-pattern-recognition/index.php'
            ];
            
            foreach ($possible_paths as $path) {
                if (is_plugin_active($path)) {
                    return true;
                }
            }
        }
        
        // Method 5: Check if plugin files exist and are loaded (only if WP_PLUGIN_DIR is defined)
        if (defined('WP_PLUGIN_DIR')) {
            $possible_files = [
                WP_PLUGIN_DIR . '/bloom-pattern-recognition/bloom-pattern-system.php',
                WP_PLUGIN_DIR . '/bloom-pattern-recognition/bloom-pattern-recognition.php',
                WP_PLUGIN_DIR . '/bloom-pattern-recognition/index.php'
            ];
            
            $included_files = get_included_files();
            foreach ($possible_files as $file) {
                if (file_exists($file) && in_array($file, $included_files)) {
                    return true;
                }
            }
        }
        
        // Method 6: Check for post types that BLOOM should register (only if function exists)
        if (function_exists('post_type_exists') &&
            (post_type_exists('bloom_model') || post_type_exists('bloom_pattern'))) {
            return true;
        }
        
        return false;
    }

    // Removed load_dependencies method as Composer handles autoloading

    private $poc;
    private $pattern_of_the_day;
    private $featured_pattern;
    private $pattern_spotlight;

    private function init_components() {
        // Use fully qualified class names as Composer autoloader is active
        if ($this->loader === null) {
            $this->loader = new \APS\Core\Loader();
        }
        
        if ($this->admin === null) {
            $this->admin = new \APS\Admin\APS_Admin();
        }

        $this->poc = new \APS\Decentralized\ProofOfContribution();
        $this->pattern_of_the_day = new \APS\Features\PatternOfTheDay(new \APS\DB\APS_Pattern_DB());
        $this->featured_pattern = new \APS\Features\FeaturedPattern();
        $this->pattern_spotlight = new \APS\Features\PatternSpotlight();
    }

    public function submit_contribution($data)
    {
        $contributor = new \Aevov\Decentralized\Contributor('test');
        $contribution = new \Aevov\Decentralized\Contribution($contributor, $data);
        return $this->poc->submitContribution($contribution);
    }

    private function init_hooks() {
        if (function_exists('register_activation_hook')) {
            register_activation_hook(APS_FILE, [$this, 'activate']);
        }
        if (function_exists('register_deactivation_hook')) {
            register_deactivation_hook(APS_FILE, [$this, 'deactivate']);
        }

        $this->loader->add_action('init', $this, 'load_text_domain');
        
        if (function_exists('is_admin') && is_admin()) {
            // Admin menu integration is now handled by APS Tools - removed duplicate registration
            // $this->loader->add_action('admin_menu', $this->admin, 'add_menu_pages');
            $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_assets');
        }

        $this->loader->add_action('rest_api_init', function () {
            $poc_endpoint = new \APS\API\Endpoints\ProofOfContributionEndpoint($this->poc);
            $poc_endpoint->register_routes();
        });

        $this->loader->run();
    }
    
    

    public function load_text_domain() {
        if (function_exists('load_plugin_textdomain') && function_exists('plugin_basename')) {
            load_plugin_textdomain('aps', false, dirname(plugin_basename(APS_FILE)) . '/languages');
        }
    }

    public function activate() {
        if (function_exists('current_user_can') && !current_user_can('activate_plugins')) return;
        require_once APS_PATH . 'Includes/Core/APS_Activator.php';
        \APS\Core\APS_Activator::activate();
    }

    public function deactivate() {
        if (function_exists('current_user_can') && !current_user_can('activate_plugins')) return;
        require_once APS_PATH . 'Includes/Core/Deactivator.php';
        \APS\Core\Deactivator::deactivate();
    }

    public function __clone() {}

    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}




function APS() {
    return APS_Plugin::instance();
}

// Only instantiate when WordPress is loaded to avoid circular dependencies
if (defined('ABSPATH')) {
    $GLOBALS['aps'] = APS();
}
