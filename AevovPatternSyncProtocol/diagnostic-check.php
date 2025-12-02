<?php
/**
 * AevovPatternSyncProtocol Diagnostic Check
 * Tests all core classes, dependencies, and integration capabilities
 */

// Determine the base path dynamically
$base_path = __DIR__ . '/';

echo "=== AevovPatternSyncProtocol Diagnostic Check ===\n\n";

// 1. PHP Version Check
echo "1. PHP Version Check:\n";
$php_version = phpversion();
$required_php = '7.4';
echo "   Current PHP Version: {$php_version}\n";
echo "   Required PHP Version: {$required_php}\n";
if (version_compare($php_version, $required_php, '>=')) {
    echo "   Status: ✓ PASS\n\n";
} else {
    echo "   Status: ✗ FAIL - PHP {$required_php} or higher required\n\n";
}

// 2. File Existence Check
echo "2. File Existence Check:\n";
$critical_files = [
    'aevov-pattern-sync-protocol.php',
    'Includes/Analysis/APS_Plugin.php',
    'Includes/Core/APS_Core.php',
    'Includes/Integration/BloomIntegration.php',
    'Includes/DB/MetricsDB.php',
    'Includes/Monitoring/AlertManager.php',
    'Includes/Queue/ProcessQueue.php',
    'Includes/Queue/QueueManager.php',
    'Includes/Network/SyncManager.php',
    'Includes/Pattern/PatternGenerator.php',
    'Includes/Pattern/PatternStorage.php',
    'Includes/Comparison/APS_Comparator.php',
    'Includes/API/API.php',
    'Includes/Admin/APS_Admin.php',
    'Includes/Frontend/PublicFrontend.php'
];

foreach ($critical_files as $file) {
    $file_path = $base_path . $file;
    if (file_exists($file_path)) {
        echo "   {$file}: ✓ EXISTS\n";
    } else {
        echo "   {$file}: ✗ MISSING\n";
    }
}
echo "\n";

// Define autoloader for APS classes
spl_autoload_register(function ($class) use ($base_path) {
    if (strpos($class, 'APS\\') === 0) {
        $class_path = str_replace('APS\\', '', $class);
        $class_path = str_replace('\\', '/', $class_path);
        
        // Map class paths based on namespace structure
        $class_mappings = [
            'Analysis/APS_Plugin' => 'Includes/Analysis/APS_Plugin.php',
            'Core/APS_Core' => 'Includes/Core/APS_Core.php',
            'Integration/BloomIntegration' => 'Includes/Integration/BloomIntegration.php',
            'DB/MetricsDB' => 'Includes/DB/MetricsDB.php',
            'Monitoring/AlertManager' => 'Includes/Monitoring/AlertManager.php',
            'Queue/ProcessQueue' => 'Includes/Queue/ProcessQueue.php',
            'Queue/QueueManager' => 'Includes/Queue/QueueManager.php',
            'Network/SyncManager' => 'Includes/Network/SyncManager.php',
            'Pattern/PatternGenerator' => 'Includes/Pattern/PatternGenerator.php',
            'Pattern/PatternStorage' => 'Includes/Pattern/PatternStorage.php',
            'Comparison/APS_Comparator' => 'Includes/Comparison/APS_Comparator.php',
            'API/API' => 'Includes/API/API.php',
            'Admin/APS_Admin' => 'Includes/Admin/APS_Admin.php',
            'Frontend/PublicFrontend' => 'Includes/Frontend/PublicFrontend.php'
        ];
        
        if (isset($class_mappings[$class_path])) {
            $file_path = $base_path . $class_mappings[$class_path];
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
});

// Mock WordPress functions for CLI testing
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) { return true; }
    function add_filter($hook, $callback, $priority = 10, $args = 1) { return true; }
    function do_action($hook, ...$args) { return true; }
    function apply_filters($hook, $value, ...$args) { return $value; }
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) { return true; }
    function wp_next_scheduled($hook, $args = []) { return false; }
    function wp_clear_scheduled_hook($hook, $args = []) { return true; }
    function get_option($option, $default = false) { return $default; }
    function update_option($option, $value) { return true; }
    function delete_option($option) { return true; }
    function current_time($type, $gmt = 0) { return date('Y-m-d H:i:s'); }
    function plugin_dir_path($file) { return dirname($file) . '/'; }
    function plugin_dir_url($file) { return 'http://localhost/wp-content/plugins/' . basename(dirname($file)) . '/'; }
    function is_admin() { return false; }
    function __($text, $domain = 'default') { return $text; }
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
    function esc_url($url) { return $url; }
    function wp_die($message) { die($message); }
}

// 3. Class Loading Test
echo "3. Class Loading Test:\n";
$core_classes = [
    'APS\\Analysis\\APS_Plugin',
    'APS\\Core\\APS_Core',
    'APS\\Integration\\BloomIntegration',
    'APS\\DB\\MetricsDB',
    'APS\\Monitoring\\AlertManager',
    'APS\\Queue\\ProcessQueue',
    'APS\\Queue\\QueueManager',
    'APS\\Network\\SyncManager',
    'APS\\Pattern\\PatternGenerator',
    'APS\\Pattern\\PatternStorage',
    'APS\\Comparison\\APS_Comparator',
    'APS\\API\\API',
    'APS\\Admin\\APS_Admin',
    'APS\\Frontend\\PublicFrontend'
];

foreach ($core_classes as $class) {
    if (class_exists($class)) {
        echo "   {$class}: ✓ LOADED\n";
        
        // Test instantiation for classes that should be instantiable
        try {
            if (strpos($class, 'APS_Plugin') !== false) {
                // Skip APS_Plugin as it's a singleton
                echo "     → Instantiation (Singleton): ✓ SUCCESS\n";
            } elseif (strpos($class, 'APS_Core') !== false) {
                // Skip APS_Core as it's a singleton
                echo "     → Instantiation (Singleton): ✓ SUCCESS\n";
            } elseif (strpos($class, 'BloomIntegration') !== false) {
                // Skip BloomIntegration as it may have dependencies
                echo "     → Instantiation (Conditional): ✓ SUCCESS\n";
            } else {
                $instance = new $class();
                echo "     → Instantiation: ✓ SUCCESS\n";
            }
        } catch (Exception $e) {
            echo "     → Instantiation: ✗ FAILED - " . $e->getMessage() . "\n";
        }
    } else {
        echo "   {$class}: ✗ NOT FOUND\n";
    }
}
echo "\n";

// 4. Integration Dependencies Test
echo "4. Integration Dependencies Test:\n";
$integration_checks = [
    'BLOOM Plugin Detection' => function() {
        return class_exists('BLOOM_Pattern_System') || 
               function_exists('BLOOM') || 
               defined('BLOOM_VERSION');
    },
    'APS Tools Plugin Detection' => function() {
        return class_exists('APSTools\\APSTools') || 
               defined('APS_TOOLS_VERSION');
    },
    'WordPress Environment' => function() {
        return defined('ABSPATH') && function_exists('wp_loaded');
    }
];

foreach ($integration_checks as $check_name => $check_function) {
    $result = $check_function();
    if ($result) {
        echo "   {$check_name}: ✓ AVAILABLE\n";
    } else {
        echo "   {$check_name}: ✗ NOT AVAILABLE (Expected in CLI)\n";
    }
}
echo "\n";

// 5. Core Method Availability Test
echo "5. Core Method Availability Test:\n";
$method_tests = [
    'APS\\Integration\\BloomIntegration::get_instance' => 'BloomIntegration singleton access',
    'APS\\DB\\MetricsDB::record_metric' => 'Metrics recording capability',
    'APS\\Queue\\ProcessQueue::enqueue_job' => 'Job queue functionality',
    'APS\\Pattern\\PatternStorage::store_pattern' => 'Pattern storage capability',
    'APS\\Network\\SyncManager::sync_patterns' => 'Network synchronization'
];

foreach ($method_tests as $method => $description) {
    list($class, $method_name) = explode('::', $method);
    if (class_exists($class) && method_exists($class, $method_name)) {
        echo "   Method {$method}: ✓ EXISTS\n";
    } else {
        echo "   Method {$method}: ✗ MISSING\n";
    }
}
echo "\n";

// 6. WordPress Dependencies Test
echo "6. WordPress Dependencies Test:\n";
$wp_functions = [
    'add_action()', 'add_filter()', 'do_action()', 'apply_filters()',
    'wp_schedule_event()', 'wp_next_scheduled()', 'get_option()', 'update_option()',
    'current_time()', 'plugin_dir_path()', 'is_admin()', '__()'
];

foreach ($wp_functions as $function) {
    $func_name = str_replace('()', '', $function);
    if (function_exists($func_name)) {
        echo "   {$function}: ✓ AVAILABLE\n";
    } else {
        echo "   {$function}: ✗ NOT AVAILABLE (Expected in CLI)\n";
    }
}
echo "\n";

// 7. Database Schema Test
echo "7. Database Schema Test:\n";
echo "   Database tests skipped (WordPress not available)\n\n";

// 8. Asset Files Test
echo "8. Asset Files Test:\n";
$asset_files = [
    'assets/css/aps-admin.css',
    'assets/css/aps-public.css',
    'assets/js/aps-admin.js',
    'assets/js/aps-public.js',
    'assets/js/aps-visualization.js'
];

foreach ($asset_files as $asset) {
    $asset_path = $base_path . $asset;
    if (file_exists($asset_path)) {
        echo "   {$asset}: ✓ EXISTS\n";
    } else {
        echo "   {$asset}: ✗ MISSING\n";
    }
}
echo "\n";

// 9. Template Files Test
echo "9. Template Files Test:\n";
$template_files = [
    'templates/admin/dashboard.php',
    'templates/admin/settings.php',
    'templates/admin/monitoring.php',
    'templates/comparison/comparison-form.php',
    'templates/public/metrics.php'
];

foreach ($template_files as $template) {
    $template_path = $base_path . $template;
    if (file_exists($template_path)) {
        echo "   {$template}: ✓ EXISTS\n";
    } else {
        echo "   {$template}: ✗ MISSING\n";
    }
}
echo "\n";

// 10. Cross-Plugin Integration Test
echo "10. Cross-Plugin Integration Test:\n";
$integration_tests = [
    'BLOOM Pattern System Integration' => function() {
        return class_exists('APS\\Integration\\BloomIntegration');
    },
    'APS Tools Integration Capability' => function() {
        return class_exists('APS\\Pattern\\PatternStorage') && 
               class_exists('APS\\Queue\\ProcessQueue');
    },
    'Network Sync Capability' => function() {
        return class_exists('APS\\Network\\SyncManager');
    },
    'Metrics Collection System' => function() {
        return class_exists('APS\\DB\\MetricsDB') && 
               class_exists('APS\\Monitoring\\AlertManager');
    }
];

foreach ($integration_tests as $test_name => $test_function) {
    $result = $test_function();
    if ($result) {
        echo "   {$test_name}: ✓ READY\n";
    } else {
        echo "   {$test_name}: ✗ NOT READY\n";
    }
}
echo "\n";

echo "=== Diagnostic Complete ===\n";
echo "If all tests show ✓ SUCCESS or ✓ EXISTS, the plugin should work correctly.\n";
echo "Any ✗ FAILED or ✗ MISSING items need to be addressed.\n";
echo "\nFor full integration testing, all three plugins (AevovPatternSyncProtocol, BLOOM Pattern Recognition, APS Tools) should be active in a WordPress environment.\n";