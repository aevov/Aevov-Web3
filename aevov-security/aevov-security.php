<?php
/**
 * Plugin Name: Aevov Security
 * Description: Centralized security functions for all Aevov plugins - authentication, sanitization, CSRF protection
 * Version: 1.0.0
 * Author: Aevov Team
 * License: MIT
 */

namespace Aevov\Security;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load security helper
require_once plugin_dir_path(__FILE__) . 'includes/class-security-helper.php';

/**
 * Initialize Aevov Security
 */
class AevovSecurity {

    public function __construct() {
        // Make security helper globally available
        add_action('plugins_loaded', [$this, 'init'], 1); // Priority 1 to load early
    }

    public function init() {
        // Security helper is now available to all plugins
        do_action('aevov_security_loaded');
    }
}

new AevovSecurity();
