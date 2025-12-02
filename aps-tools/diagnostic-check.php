<?php
/**
 * APS Tools Diagnostic Check
 * Comprehensive testing tool for APS Tools plugin dependencies and functionality
 */

// Prevent direct access
if (!defined('ABSPATH') && php_sapi_name() === 'cli') {
    // Allow CLI access for testing
}

echo "=== APS Tools Diagnostic Check ===\n\n";

// 1. PHP Version Check
echo "1. PHP Version Check:\n";
$php_version = phpversion();
$required_php = '7.4';
echo "   Current PHP Version: {$php_version}\n";
echo "   Required PHP Version: {$required_php}\n";
if (version_compare($php_version, $required_php, '>=')) {
    echo "   Status: ✓ PASS\n\n";
} else {
    echo "   Status: ✗ FAIL\n\n";
}

// 2. File Existence Check
echo "2. File Existence Check:\n";
// Determine base path - if running from root, add aps-tools prefix
$base_path = '';
if (file_exists('aps-tools/aps-tools.php')) {
    $base_path = 'aps-tools/';
}

$required_files = [
    $base_path . 'aps-tools.php',
    $base_path . 'includes/models/class-bloom-tensor-storage.php',
    $base_path . 'includes/models/class-bloom-model-manager.php',
    $base_path . 'includes/handlers/class-table-handler.php',
    $base_path . 'includes/handlers/class-pattern-handler.php',
    $base_path . 'includes/handlers/class-chunk-batch-processor.php',
    $base_path . 'includes/handlers/class-chunk-import-handler.php',
    $base_path . 'includes/handlers/class-media-chunk-handler.php',
    $base_path . 'includes/scanner/class-directory-scanner.php',
    $base_path . 'includes/scanner/class-batch-processor.php',
    $base_path . 'includes/services/class-media-monitor.php',
    $base_path . 'includes/class-bloom-bulk-upload-manager.php',
    $base_path . 'includes/class-aps-tools-frontend.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "   {$file}: ✓ EXISTS\n";
    } else {
        echo "   {$file}: ✗ MISSING\n";
    }
}
echo "\n";

// 3. Class Loading Test
echo "3. Class Loading Test:\n";

// Define autoloader for APS Tools classes
spl_autoload_register(function ($class) use ($base_path) {
    if (strpos($class, 'APSTools\\') === 0) {
        $class_path = str_replace('APSTools\\', '', $class);
        $class_path = str_replace('\\', '/', $class_path);
        
        // Map class paths
        $class_mappings = [
            'Models/BloomTensorStorage' => 'includes/models/class-bloom-tensor-storage.php',
            'Models/BloomModelManager' => 'includes/models/class-bloom-model-manager.php',
            'Handlers/TableHandler' => 'includes/handlers/class-table-handler.php',
            'Handlers/PatternHandler' => 'includes/handlers/class-pattern-handler.php',
            'Handlers/ChunkBatchProcessor' => 'includes/handlers/class-chunk-batch-processor.php',
            'Handlers/ChunkImportHandler' => 'includes/handlers/class-chunk-import-handler.php',
            'Handlers/MediaChunkHandler' => 'includes/handlers/class-media-chunk-handler.php',
            'Scanner/DirectoryScanner' => 'includes/scanner/class-directory-scanner.php',
            'Scanner/BatchProcessor' => 'includes/scanner/class-batch-processor.php',
            'Services/MediaMonitor' => 'includes/services/class-media-monitor.php'
        ];
        
        if (isset($class_mappings[$class_path])) {
            $file_path = $base_path . $class_mappings[$class_path];
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
});

$test_classes = [
    'APSTools\Models\BloomTensorStorage',
    'APSTools\Models\BloomModelManager',
    'APSTools\Handlers\TableHandler',
    'APSTools\Handlers\PatternHandler',
    'APSTools\Handlers\ChunkBatchProcessor',
    'APSTools\Handlers\ChunkImportHandler',
    'APSTools\Handlers\MediaChunkHandler',
    'APSTools\Scanner\DirectoryScanner',
    'APSTools\Scanner\BatchProcessor',
    'APSTools\Services\MediaMonitor'
];

foreach ($test_classes as $class) {
    try {
        if (class_exists($class)) {
            echo "   {$class}: ✓ LOADED\n";
            
            // Test instantiation
            try {
                $reflection = new ReflectionClass($class);
                
                // Check if it's a singleton
                if ($reflection->hasMethod('instance')) {
                    $instance = $class::instance();
                    echo "     → Instantiation (Singleton): ✓ SUCCESS\n";
                } else {
                    // Try regular instantiation
                    $constructor = $reflection->getConstructor();
                    if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
                        echo "     → Instantiation: ⚠ REQUIRES PARAMETERS\n";
                    } else {
                        $instance = new $class();
                        echo "     → Instantiation: ✓ SUCCESS\n";
                    }
                }
            } catch (Exception $e) {
                echo "     → Instantiation: ✗ FAILED ({$e->getMessage()})\n";
            }
        } else {
            echo "   {$class}: ✗ NOT FOUND\n";
        }
    } catch (Exception $e) {
        echo "   {$class}: ✗ ERROR ({$e->getMessage()})\n";
    }
}
echo "\n";

// 4. Main Plugin Class Test
echo "4. Main Plugin Class Test:\n";
try {
    require_once $base_path . 'aps-tools.php';
    
    if (class_exists('APSTools\APSTools')) {
        echo "   APSTools\APSTools: ✓ LOADED\n";
        
        // Test singleton instantiation
        try {
            $aps_tools = \APSTools\APSTools::instance();
            echo "     → Instantiation: ✓ SUCCESS\n";
        } catch (Exception $e) {
            echo "     → Instantiation: ✗ FAILED ({$e->getMessage()})\n";
        }
    } else {
        echo "   APSTools\APSTools: ✗ NOT FOUND\n";
    }
} catch (Exception $e) {
    echo "   Main Plugin: ✗ ERROR ({$e->getMessage()})\n";
}
echo "\n";

// 5. Integration Test
echo "5. Integration Test:\n";
try {
    // Test key methods exist
    $methods_to_test = [
        'APSTools\Models\BloomTensorStorage' => ['store_tensor_data', 'get_tensor_data'],
        'APSTools\Handlers\TableHandler' => ['instance'],
        'APSTools\Handlers\PatternHandler' => ['instance'],
        'APSTools\Scanner\BatchProcessor' => ['process_directory']
    ];
    
    foreach ($methods_to_test as $class => $methods) {
        if (class_exists($class)) {
            foreach ($methods as $method) {
                if (method_exists($class, $method)) {
                    echo "   Method {$class}::{$method}: ✓ EXISTS\n";
                } else {
                    echo "   Method {$class}::{$method}: ✗ MISSING\n";
                }
            }
        }
    }
} catch (Exception $e) {
    echo "   Integration test error: {$e->getMessage()}\n";
}
echo "\n";

// 6. WordPress Dependencies Test
echo "6. WordPress Dependencies Test:\n";
$wp_functions = [
    'wp_upload_dir',
    'get_posts',
    'get_post_meta',
    'wp_create_nonce',
    'check_ajax_referer',
    'wp_send_json_success',
    'add_action',
    'add_filter'
];

foreach ($wp_functions as $function) {
    if (function_exists($function)) {
        echo "   {$function}(): ✓ AVAILABLE\n";
    } else {
        echo "   {$function}(): ✗ NOT AVAILABLE (Expected in CLI)\n";
    }
}
echo "\n";

// 7. Database Schema Test (if WordPress functions available)
echo "7. Database Schema Test:\n";
if (function_exists('wp_upload_dir')) {
    try {
        $bloom_storage = \APSTools\Models\BloomTensorStorage::instance();
        echo "   BloomTensorStorage database setup: ✓ INITIALIZED\n";
    } catch (Exception $e) {
        echo "   BloomTensorStorage database setup: ✗ FAILED ({$e->getMessage()})\n";
    }
} else {
    echo "   Database tests skipped (WordPress not available)\n";
}
echo "\n";

// 8. Asset Files Test
echo "8. Asset Files Test:\n";
$asset_files = [
    $base_path . 'assets/css/admin.css',
    $base_path . 'assets/js/admin.js',
    $base_path . 'assets/js/directory-scanner.js',
    $base_path . 'assets/js/pattern-handler.js',
    $base_path . 'assets/js/bloom-integration.js'
];

foreach ($asset_files as $file) {
    if (file_exists($file)) {
        echo "   {$file}: ✓ EXISTS\n";
    } else {
        echo "   {$file}: ✗ MISSING\n";
    }
}
echo "\n";

// 9. Template Files Test
echo "9. Template Files Test:\n";
$template_files = [
    $base_path . 'templates/dashboard.php',
    $base_path . 'templates/status.php',
    $base_path . 'templates/analysis.php',
    $base_path . 'templates/comparison.php',
    $base_path . 'templates/patterns.php',
    $base_path . 'templates/bloom.php',
    $base_path . 'templates/stored-chunks.php'
];

foreach ($template_files as $file) {
    if (file_exists($file)) {
        echo "   {$file}: ✓ EXISTS\n";
    } else {
        echo "   {$file}: ✗ MISSING\n";
    }
}
echo "\n";

echo "=== Diagnostic Complete ===\n";
echo "If all tests show ✓ SUCCESS or ✓ EXISTS, the plugin should work correctly.\n";
echo "Any ✗ FAILED or ✗ MISSING items need to be addressed.\n";