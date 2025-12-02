<?php
/**
 * Debug logging for BLOOM Pattern Recognition issues
 */

// Log missing classes and dependencies
function bloom_debug_log($message, $context = []) {
    $options = get_option('bloom_options');
    $debug_mode = isset($options['debug_mode']) && $options['debug_mode'];

    if ( ! ( defined('WP_DEBUG') && WP_DEBUG ) && ! $debug_mode ) {
        return;
    }

    $log_entry = [
        'timestamp' => current_time('mysql'),
        'message' => $message,
        'context' => $context,
        'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
    ];
    
    error_log('BLOOM DEBUG: ' . json_encode($log_entry, JSON_PRETTY_PRINT));
}

// Check for missing dependencies
function bloom_check_dependencies() {
    $missing_classes = [];
    $required_classes = [
        'BLOOM\Core',
        'BLOOM\Models\TensorModel',
        'BLOOM\Models\ChunkModel', 
        'BLOOM\Models\PatternModel',
        'BLOOM\Processing\TensorProcessor',
        'BLOOM\Network\NetworkManager',
        'BLOOM\Integration\APSIntegration'
    ];
    
    foreach ($required_classes as $class) {
        if (!class_exists($class)) {
            $missing_classes[] = $class;
        }
    }
    
    if (!empty($missing_classes)) {
        bloom_debug_log('Missing required classes', ['classes' => $missing_classes]);
    }
    
    return $missing_classes;
}

// Check plugin integration
function bloom_check_plugin_integration() {
    $issues = [];
    
    // Check if APS is active
    if (!function_exists('APS')) {
        $issues[] = 'APS Plugin not active or accessible';
    }
    
    // Check if APS Tools is active
    if (!class_exists('APSTools\APSTools')) {
        $issues[] = 'APS Tools plugin not active or accessible';
    }
    
    // Check database tables
    global $wpdb;
    $tables_to_check = [
        $wpdb->prefix . 'bloom_tensors',
        $wpdb->prefix . 'bloom_chunks',
        $wpdb->prefix . 'bloom_patterns'
    ];
    
    foreach ($tables_to_check as $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        if (!$exists) {
            $issues[] = "Missing database table: $table";
        }
    }
    
    if (!empty($issues)) {
        bloom_debug_log('Plugin integration issues', ['issues' => $issues]);
    }
    
    return $issues;
}

// Run diagnostics
add_action('admin_init', function() {
    if (current_user_can('manage_options') && isset($_GET['bloom_debug'])) {
        $missing_classes = bloom_check_dependencies();
        $integration_issues = bloom_check_plugin_integration();
        
        echo '<div class="notice notice-info"><p><strong>BLOOM Debug Results:</strong></p>';
        
        if (!empty($missing_classes)) {
            echo '<p><strong>Missing Classes:</strong> ' . implode(', ', $missing_classes) . '</p>';
        }
        
        if (!empty($integration_issues)) {
            echo '<p><strong>Integration Issues:</strong> ' . implode(', ', $integration_issues) . '</p>';
        }
        
        if (empty($missing_classes) && empty($integration_issues)) {
            echo '<p>All dependencies and integrations appear to be working correctly.</p>';
        }
        
        echo '</div>';
    }
});
