<?php
/**
 * Aevov Onboarding System Debug Script
 * Run this to test the onboarding system functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If not in WordPress, simulate basic WordPress environment for testing
    define('ABSPATH', dirname(__FILE__) . '/');
    
    // Basic WordPress function stubs for testing
    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success($data = null) {
            echo "SUCCESS: " . json_encode($data) . "\n";
        }
    }
    
    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error($data = null) {
            echo "ERROR: " . json_encode($data) . "\n";
        }
    }
    
    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str) {
            return strip_tags(trim($str));
        }
    }
    
    if (!function_exists('is_plugin_active')) {
        function is_plugin_active($plugin) {
            return false; // Simulate inactive plugins for testing
        }
    }
    
    if (!function_exists('get_option')) {
        function get_option($option, $default = false) {
            return $default;
        }
    }
    
    if (!function_exists('update_option')) {
        function update_option($option, $value) {
            return true;
        }
    }
}

echo "=== Aevov Onboarding System Debug ===\n\n";

// Test 1: Check if assets exist
echo "1. Checking Asset Files:\n";
$js_file = __DIR__ . '/assets/js/onboarding.js';
$css_file = __DIR__ . '/assets/css/onboarding.css';

echo "   JavaScript file: " . ($js_file && file_exists($js_file) ? "✅ EXISTS" : "❌ MISSING") . "\n";
echo "   CSS file: " . ($css_file && file_exists($css_file) ? "✅ EXISTS" : "❌ MISSING") . "\n";

if (file_exists($js_file)) {
    $js_size = filesize($js_file);
    echo "   JavaScript size: " . number_format($js_size) . " bytes\n";
}

if (file_exists($css_file)) {
    $css_size = filesize($css_file);
    echo "   CSS size: " . number_format($css_size) . " bytes\n";
}

echo "\n";

// Test 2: Check JavaScript functions
echo "2. Checking JavaScript Functions:\n";
if (file_exists($js_file)) {
    $js_content = file_get_contents($js_file);
    $functions = ['startStep', 'completeStep', 'runSystemCheck', 'activatePlugin'];
    
    foreach ($functions as $func) {
        $exists = strpos($js_content, "function $func(") !== false || strpos($js_content, "$func: function(") !== false;
        echo "   $func(): " . ($exists ? "✅ FOUND" : "❌ MISSING") . "\n";
    }
    
    // Check for jQuery dependency
    $has_jquery = strpos($js_content, 'jQuery') !== false || strpos($js_content, '$') !== false;
    echo "   jQuery usage: " . ($has_jquery ? "✅ FOUND" : "❌ MISSING") . "\n";
    
    // Check for AJAX calls
    $has_ajax = strpos($js_content, '$.post') !== false || strpos($js_content, 'jQuery.post') !== false;
    echo "   AJAX calls: " . ($has_ajax ? "✅ FOUND" : "❌ MISSING") . "\n";
} else {
    echo "   ❌ Cannot check - JavaScript file missing\n";
}

echo "\n";

// Test 3: Simulate AJAX handler testing
echo "3. Testing AJAX Handlers:\n";

// Simulate the onboarding class
if (class_exists('AevovOnboarding\\AevovOnboardingSystem')) {
    echo "   Onboarding class: ✅ LOADED\n";
    
    // Test step validation
    $onboarding = new AevovOnboarding\AevovOnboardingSystem();
    $reflection = new ReflectionClass($onboarding);
    $steps_property = $reflection->getProperty('onboarding_steps');
    $steps_property->setAccessible(true);
    $steps = $steps_property->getValue($onboarding);
    
    echo "   Available steps: " . count($steps) . "\n";
    foreach ($steps as $key => $title) {
        echo "     - $key: $title\n";
    }
    
} else {
    echo "   ❌ Onboarding class not loaded\n";
}

echo "\n";

// Test 4: Check WordPress hooks
echo "4. Checking WordPress Integration:\n";

if (function_exists('add_action')) {
    echo "   WordPress hooks: ✅ AVAILABLE\n";
    
    // Check if hooks are properly registered
    global $wp_filter;
    $hooks_to_check = [
        'admin_menu',
        'admin_enqueue_scripts', 
        'wp_ajax_aevov_onboarding_action',
        'wp_ajax_aevov_get_system_status',
        'wp_ajax_aevov_activate_plugin'
    ];
    
    foreach ($hooks_to_check as $hook) {
        $registered = isset($wp_filter[$hook]) && !empty($wp_filter[$hook]);
        echo "   $hook: " . ($registered ? "✅ REGISTERED" : "❌ NOT REGISTERED") . "\n";
    }
} else {
    echo "   ❌ WordPress hooks not available\n";
}

echo "\n";

// Test 5: Plugin dependency check
echo "5. Checking Plugin Dependencies:\n";
$dependencies = [
    'aps-tools/aps-tools.php' => 'APS Tools',
    'bloom-pattern-recognition/bloom-pattern-system.php' => 'BLOOM Pattern Recognition',
    'AevovPatternSyncProtocol/aevov-pattern-sync-protocol.php' => 'Aevov Pattern Sync Protocol',
    'bloom-chunk-scanner/bloom-chunk-scanner.php' => 'BLOOM Chunk Scanner',
    'APS Chunk Uploader/chunk-uploader.php' => 'APS Chunk Uploader'
];

foreach ($dependencies as $plugin_file => $plugin_name) {
    $plugin_path = dirname(__DIR__) . '/' . dirname($plugin_file);
    $exists = is_dir($plugin_path);
    $active = function_exists('is_plugin_active') ? is_plugin_active($plugin_file) : false;
    
    echo "   $plugin_name:\n";
    echo "     Directory: " . ($exists ? "✅ EXISTS" : "❌ MISSING") . "\n";
    echo "     Active: " . ($active ? "✅ ACTIVE" : "❌ INACTIVE") . "\n";
}

echo "\n";

// Test 6: Simulate JavaScript AJAX call
echo "6. Simulating AJAX Call:\n";

// Simulate POST data
$_POST = [
    'action' => 'aevov_onboarding_action',
    'step' => 'welcome',
    'action_type' => 'start',
    'nonce' => 'test_nonce'
];

echo "   Simulated POST data:\n";
foreach ($_POST as $key => $value) {
    echo "     $key: $value\n";
}

// Test parameter validation
$step = sanitize_text_field($_POST['step'] ?? '');
$action_type = sanitize_text_field($_POST['action_type'] ?? 'start');

echo "   Sanitized parameters:\n";
echo "     step: '$step'\n";
echo "     action_type: '$action_type'\n";

echo "\n";

// Test 7: Check file permissions
echo "7. Checking File Permissions:\n";
$files_to_check = [
    __DIR__ . '/aevov-onboarding.php',
    __DIR__ . '/assets/js/onboarding.js',
    __DIR__ . '/assets/css/onboarding.css'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $perms = fileperms($file);
        $readable = is_readable($file);
        echo "   " . basename($file) . ": " . ($readable ? "✅ READABLE" : "❌ NOT READABLE") . " (perms: " . decoct($perms & 0777) . ")\n";
    } else {
        echo "   " . basename($file) . ": ❌ MISSING\n";
    }
}

echo "\n";

// Test 8: Browser console debugging suggestions
echo "8. Browser Debugging Suggestions:\n";
echo "   To debug in browser console:\n";
echo "   1. Open browser developer tools (F12)\n";
echo "   2. Go to Console tab\n";
echo "   3. Check for JavaScript errors\n";
echo "   4. Test AJAX manually:\n";
echo "      jQuery.post(aevovOnboarding.ajaxUrl, {\n";
echo "          action: 'aevov_onboarding_action',\n";
echo "          step: 'welcome',\n";
echo "          action_type: 'start',\n";
echo "          nonce: aevovOnboarding.nonce\n";
echo "      }).done(function(response) {\n";
echo "          console.log('Success:', response);\n";
echo "      }).fail(function(xhr, status, error) {\n";
echo "          console.log('Error:', error);\n";
echo "      });\n";

echo "\n";

echo "=== Debug Complete ===\n";
echo "If issues persist:\n";
echo "1. Check WordPress error logs\n";
echo "2. Enable WordPress debug mode (WP_DEBUG = true)\n";
echo "3. Check browser console for JavaScript errors\n";
echo "4. Verify all plugins are properly activated\n";
echo "5. Check file permissions on the server\n";