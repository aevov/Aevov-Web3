<?php
/**
 * Plugin Name: Aevov Diagnostic Network
 * Plugin URI: https://aevov.com/diagnostic-network
 * Description: Comprehensive AI-powered diagnostic network for the Aevov neurosymbolic system with visual testing, auto-fixing, and multi-AI integration
 * Version: 1.0.0
 * Author: Aevov Systems
 * License: GPL v2 or later
 * Network: true
 * Text Domain: aevov-diagnostic-network
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ADN_VERSION', '1.0.0');
define('ADN_PLUGIN_FILE', __FILE__);
define('ADN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ADN_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    if (strpos($class, 'ADN\\') === 0) {
        $class_file = str_replace('\\', '/', substr($class, 4));
        $file = ADN_PLUGIN_DIR . 'includes/' . $class_file . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Plugin activation hook
register_activation_hook(__FILE__, function() {
    // Check system requirements
    if (class_exists('ADN\\Core\\Activator')) {
        $requirements_check = ADN\Core\Activator::check_requirements();
        if (is_wp_error($requirements_check)) {
            wp_die(
                $requirements_check->get_error_message(),
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }
        
        // Run activation procedures
        ADN\Core\Activator::activate();
    }
});

// Plugin deactivation hook
register_deactivation_hook(__FILE__, function() {
    if (class_exists('ADN\\Core\\Deactivator')) {
        ADN\Core\Deactivator::deactivate();
    }
});

// Add custom cron schedules
add_filter('cron_schedules', function($schedules) {
    if (class_exists('ADN\\Core\\Activator')) {
        return ADN\Core\Activator::add_cron_schedules($schedules);
    }
    return $schedules;
});

// Initialize the plugin
add_action('init', function() { // Changed from 'plugins_loaded' to 'init'
    // Load text domain for translations
    load_plugin_textdomain(
        'aevov-diagnostic-network',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
    
    // Initialize main plugin class
    // Note: Initializing here might be too late for some plugin functionalities
    // that rely on earlier hooks. If issues arise, consider moving initialization
    // to a separate action or ensuring all necessary components are loaded.
    if (class_exists('ADN\\Core\\DiagnosticNetwork')) {
        ADN\Core\DiagnosticNetwork::instance();
    }
});

// Admin notices for plugin requirements
add_action('admin_notices', function() {
    // Check if plugin was just activated
    if (get_transient('adn_activation_notice')) {
        delete_transient('adn_activation_notice');
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html__('Aevov Diagnostic Network', 'aevov-diagnostic-network') . '</strong> ';
        echo esc_html__('has been activated successfully! Visit the', 'aevov-diagnostic-network') . ' ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=adn-dashboard')) . '">';
        echo esc_html__('Diagnostic Dashboard', 'aevov-diagnostic-network') . '</a> ';
        echo esc_html__('to get started.', 'aevov-diagnostic-network') . '</p>';
        echo '</div>';
    }
    
    // Check for missing dependencies
    if (!class_exists('ADN\\Core\\DiagnosticNetwork')) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>' . esc_html__('Aevov Diagnostic Network Error:', 'aevov-diagnostic-network') . '</strong> ';
        echo esc_html__('Core classes could not be loaded. Please check file permissions and try reactivating the plugin.', 'aevov-diagnostic-network') . '</p>';
        echo '</div>';
    }
});

// Set activation notice transient
add_action('activated_plugin', function($plugin) {
    if ($plugin === plugin_basename(__FILE__)) {
        set_transient('adn_activation_notice', true, 30);
    }
});