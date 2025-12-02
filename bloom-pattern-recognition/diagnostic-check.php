<?php
/**
 * Standalone diagnostic script for BLOOM Pattern Recognition
 * Tests class loading and basic functionality without WordPress
 */

// Simulate basic WordPress constants and functions for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// Mock WordPress functions
if (!function_exists('current_time')) {
    function current_time($type) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id() {
        return 1;
    }
}

if (!function_exists('esc_sql')) {
    function esc_sql($data) {
        return addslashes($data);
    }
}

// Mock wpdb class
class MockWpdb {
    public $prefix = 'wp_';
    
    public function get_charset_collate() {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
    
    public function prepare($query, ...$args) {
        return vsprintf(str_replace('%s', "'%s'", $query), $args);
    }
    
    public function get_results($query, $output = OBJECT) {
        return [];
    }
    
    public function get_var($query) {
        return 0;
    }
    
    public function insert($table, $data) {
        return true;
    }
    
    public function update($table, $data, $where) {
        return true;
    }
    
    public function query($query) {
        return true;
    }
}

// Set up global wpdb
$GLOBALS['wpdb'] = new MockWpdb();

// Define plugin constants
define('BLOOM_VERSION', '1.0.0');
define('BLOOM_PATH', __DIR__ . '/');
define('BLOOM_CHUNK_SIZE', 7 * 1024 * 1024);
define('BLOOM_MIN_PHP_VERSION', '7.4');

echo "=== BLOOM Pattern Recognition Diagnostic Check ===\n\n";

// Check PHP version
echo "1. PHP Version Check:\n";
echo "   Current PHP Version: " . PHP_VERSION . "\n";
echo "   Required PHP Version: " . BLOOM_MIN_PHP_VERSION . "\n";
echo "   Status: " . (version_compare(PHP_VERSION, BLOOM_MIN_PHP_VERSION, '>=') ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Check if files exist
echo "2. File Existence Check:\n";
$required_files = [
    'includes/models/class-tensor-model.php',
    'includes/models/class-chunk-model.php', 
    'includes/models/class-pattern-model.php',
    'includes/processing/class-tensor-processor.php',
    'includes/utilities/class-data-validator.php',
    'includes/utilities/class-error-handler.php',
    'includes/network/class-network-manager.php',
    'includes/network/class-message-queue.php',
    'includes/core/class-plugin-activator.php',
    'includes/integration/class-aps-integration.php'
];

foreach ($required_files as $file) {
    $exists = file_exists(BLOOM_PATH . $file);
    echo "   $file: " . ($exists ? "✓ EXISTS" : "✗ MISSING") . "\n";
}

echo "\n3. Class Loading Test:\n";

// Test class loading
$classes_to_test = [
    'BLOOM\\Models\\TensorModel' => 'includes/models/class-tensor-model.php',
    'BLOOM\\Models\\ChunkModel' => 'includes/models/class-chunk-model.php',
    'BLOOM\\Models\\PatternModel' => 'includes/models/class-pattern-model.php',
    'BLOOM\\Processing\\TensorProcessor' => 'includes/processing/class-tensor-processor.php',
    'BLOOM\\Utilities\\DataValidator' => 'includes/utilities/class-data-validator.php',
    'BLOOM\\Utilities\\ErrorHandler' => 'includes/utilities/class-error-handler.php',
    'BLOOM\\Network\\NetworkManager' => 'includes/network/class-network-manager.php',
    'BLOOM\\Network\\MessageQueue' => 'includes/network/class-message-queue.php',
    'BLOOM\\Core\\PluginActivator' => 'includes/core/class-plugin-activator.php',
    'BLOOM\\Integration\\APSIntegration' => 'includes/integration/class-aps-integration.php'
];

foreach ($classes_to_test as $class_name => $file_path) {
    try {
        if (file_exists(BLOOM_PATH . $file_path)) {
            require_once BLOOM_PATH . $file_path;
            
            if (class_exists($class_name)) {
                echo "   $class_name: ✓ LOADED\n";
                
                // Try to instantiate (basic test)
                try {
                    $instance = new $class_name();
                    echo "     → Instantiation: ✓ SUCCESS\n";
                } catch (Exception $e) {
                    echo "     → Instantiation: ✗ FAILED (" . $e->getMessage() . ")\n";
                } catch (Error $e) {
                    echo "     → Instantiation: ✗ FAILED (" . $e->getMessage() . ")\n";
                }
            } else {
                echo "   $class_name: ✗ CLASS NOT FOUND\n";
            }
        } else {
            echo "   $class_name: ✗ FILE MISSING\n";
        }
    } catch (Exception $e) {
        echo "   $class_name: ✗ LOAD ERROR (" . $e->getMessage() . ")\n";
    } catch (Error $e) {
        echo "   $class_name: ✗ LOAD ERROR (" . $e->getMessage() . ")\n";
    }
}

echo "\n4. Integration Test:\n";

// Test basic integration
try {
    $tensor_model = new BLOOM\Models\TensorModel();
    $chunk_model = new BLOOM\Models\ChunkModel();
    $pattern_model = new BLOOM\Models\PatternModel();
    $tensor_processor = new BLOOM\Processing\TensorProcessor();
    
    echo "   Core models integration: ✓ SUCCESS\n";
    
    // Test method existence
    $methods_to_check = [
        [$tensor_model, 'create'],
        [$chunk_model, 'store_chunk'],
        [$pattern_model, 'create'],
        [$tensor_processor, 'process_pattern']
    ];
    
    foreach ($methods_to_check as [$object, $method]) {
        if (method_exists($object, $method)) {
            echo "   Method " . get_class($object) . "::$method: ✓ EXISTS\n";
        } else {
            echo "   Method " . get_class($object) . "::$method: ✗ MISSING\n";
        }
    }
    
} catch (Exception $e) {
    echo "   Core models integration: ✗ FAILED (" . $e->getMessage() . ")\n";
} catch (Error $e) {
    echo "   Core models integration: ✗ FAILED (" . $e->getMessage() . ")\n";
}

echo "\n5. Database Schema Test:\n";

// Test table creation methods
try {
    $models = [
        new BLOOM\Models\TensorModel(),
        new BLOOM\Models\ChunkModel(),
        new BLOOM\Models\PatternModel(),
        new BLOOM\Network\MessageQueue(),
        new BLOOM\Utilities\ErrorHandler()
    ];
    
    foreach ($models as $model) {
        if (method_exists($model, 'create_table')) {
            echo "   " . get_class($model) . "::create_table(): ✓ METHOD EXISTS\n";
        } else {
            echo "   " . get_class($model) . "::create_table(): ✗ METHOD MISSING\n";
        }
    }
    
} catch (Exception $e) {
    echo "   Database schema test: ✗ FAILED (" . $e->getMessage() . ")\n";
} catch (Error $e) {
    echo "   Database schema test: ✗ FAILED (" . $e->getMessage() . ")\n";
}

echo "\n=== Diagnostic Complete ===\n";
echo "If all tests show ✓ SUCCESS or ✓ EXISTS, the plugin should work correctly.\n";
echo "Any ✗ FAILED or ✗ MISSING items need to be addressed.\n\n";