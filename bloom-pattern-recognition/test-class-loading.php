<?php
/**
 * Test script to verify BLOOM class loading
 */

// Define WordPress-like constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// Define plugin constants
define('BLOOM_PATH', __DIR__ . '/');

// Test class loading in the same order as the main plugin
echo "Testing BLOOM class loading...\n";

$files_to_load = [
    'includes/utilities/class-data-validator.php',
    'includes/utilities/class-error-handler.php',
    'includes/models/class-pattern-model.php',
    'includes/models/class-chunk-model.php',
    'includes/models/class-tensor-model.php',
    'includes/processing/class-tensor-processor.php',
    'includes/network/class-network-manager.php',
    'includes/network/class-message-queue.php',
];

$loaded_files = [];
$failed_files = [];

foreach ($files_to_load as $file) {
    $full_path = BLOOM_PATH . $file;
    if (file_exists($full_path)) {
        require_once $full_path;
        $loaded_files[] = $file;
        echo "✓ Loaded: $file\n";
    } else {
        $failed_files[] = $file;
        echo "✗ Missing: $file\n";
    }
}

// Test class existence
$required_classes = [
    'BLOOM\Models\PatternModel',
    'BLOOM\Models\ChunkModel', 
    'BLOOM\Models\TensorModel',
    'BLOOM\Processing\TensorProcessor',
    'BLOOM\Utilities\DataValidator',
    'BLOOM\Utilities\ErrorHandler'
];

echo "\nTesting class existence...\n";
$missing_classes = [];
foreach ($required_classes as $class) {
    if (class_exists($class)) {
        echo "✓ Class exists: $class\n";
    } else {
        $missing_classes[] = $class;
        echo "✗ Class missing: $class\n";
    }
}

// Summary
echo "\n=== SUMMARY ===\n";
echo "Files loaded: " . count($loaded_files) . "/" . count($files_to_load) . "\n";
echo "Classes found: " . (count($required_classes) - count($missing_classes)) . "/" . count($required_classes) . "\n";

if (empty($failed_files) && empty($missing_classes)) {
    echo "✓ All dependencies loaded successfully!\n";
    echo "The APSIntegration class should now work properly.\n";
} else {
    echo "✗ Issues found:\n";
    if (!empty($failed_files)) {
        echo "  Missing files: " . implode(', ', $failed_files) . "\n";
    }
    if (!empty($missing_classes)) {
        echo "  Missing classes: " . implode(', ', $missing_classes) . "\n";
    }
}