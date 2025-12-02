<?php
/*
Plugin Name: Cubbit Directory Manager Extension (Fixed)
Plugin URI: https://algorithmpress.com
Description: Enhanced directory management for Cubbit S3 storage with improved security and performance
Version: 2.0
Author: WPWakanda, LLC
Author URI: https://yourwebsite.com/
*/

if (!defined('ABSPATH')) {
    exit;
}

class CubbitDirectoryManagerExtension {
    private $endpoint = 'https://s3.cubbit.eu';
    private $region = 'eu-central-1';
    private $log_file;
    private $cache_duration = 300; // 5 minutes

    public function __construct() {
        // Initialize after plugins are loaded
        add_action('plugins_loaded', [$this, 'init'], 15);
        
        // Create log file
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/cubbit-directory-extension.log';
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if main plugin is active
        if (!class_exists('CubbitDirectoryManager')) {
            add_action('admin_notices', [$this, 'plugin_dependency_notice']);
            return;
        }
        
        // Validate configuration
        if (!$this->validate_configuration()) {
            add_action('admin_notices', [$this, 'configuration_notice']);
            return;
        }
        
        // Register AJAX handlers
        add_action('wp_ajax_cubbit_create_folder', [$this, 'ajax_create_folder']);
        add_action('wp_ajax_cubbit_delete_folder', [$this, 'ajax_delete_folder']);
        add_action('wp_ajax_cubbit_rename_folder', [$this, 'ajax_rename_folder']);
        add_action('wp_ajax_cubbit_move_items', [$this, 'ajax_move_items']);
        add_action('wp_ajax_cubbit_get_folder_info', [$this, 'ajax_get_folder_info']);
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets'], 25);
        
        // Register with APS Tools integration protocol
        add_action('aps_tools_register_integrations', [$this, 'register_with_aps_tools']);
        
        // Add cleanup for cache
        add_action('wp_scheduled_delete', [$this, 'cleanup_cache']);
    }

    /**
     * Validate Cubbit configuration
     */
    private function validate_configuration() {
        $config = $this->get_cubbit_config();
        return !empty($config['access_key']) && !empty($config['secret_key']) && !empty($config['bucket_name']);
    }

    /**
     * Get Cubbit configuration with caching
     */
    private function get_cubbit_config() {
        $config = wp_cache_get('cubbit_config');
        if (!$config) {
            $config = [
                'access_key' => get_option('cubbit_access_key'),
                'secret_key' => get_option('cubbit_secret_key'),
                'bucket_name' => get_option('cubbit_bucket_name')
            ];
            wp_cache_set('cubbit_config', $config, '', $this->cache_duration);
        }
        return $config;
    }

    /**
     * Register with APS Tools integration protocol
     */
    public function register_with_aps_tools($manager) {
        if (method_exists($manager, 'register_integration')) {
            $manager->register_integration('cubbit_directory_extension', [
                'name' => 'Cubbit Directory Manager Extension',
                'version' => '2.0',
                'status' => $this->get_status(),
                'health_check' => [$this, 'health_check']
            ]);
        }
    }

    /**
     * Get plugin status
     */
    private function get_status() {
        if (!$this->validate_configuration()) {
            return 'configuration_error';
        }
        if (!class_exists('CubbitDirectoryManager')) {
            return 'dependency_missing';
        }
        return 'active';
    }

    /**
     * Health check for monitoring
     */
    public function health_check() {
        $checks = [
            'config' => $this->validate_configuration(),
            'dependencies' => class_exists('CubbitDirectoryManager'),
            'endpoint_reachable' => $this->test_endpoint_connectivity()
        ];
        
        return $checks;
    }

    /**
     * Test endpoint connectivity
     */
    private function test_endpoint_connectivity() {
        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code < 400;
    }

    /**
     * Add admin notice if main plugin is not active
     */
    public function plugin_dependency_notice() {
        ?>
        <div class="notice notice-error">
            <p>Cubbit Directory Manager Extension requires the Cubbit Directory Manager plugin to be active. Please activate it first.</p>
        </div>
        <?php
    }

    /**
     * Add admin notice for configuration issues
     */
    public function configuration_notice() {
        ?>
        <div class="notice notice-warning">
            <p>Cubbit Directory Manager Extension requires proper Cubbit configuration. Please configure your credentials in the Cubbit Directory Manager settings.</p>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'cubbit-directory') === false) {
            return;
        }

        wp_enqueue_style(
            'cubbit-extension-styles', 
            plugin_dir_url(__FILE__) . 'css/extension.css', 
            ['cubbit-directory-styles'], 
            '2.0'
        );
        
        wp_enqueue_script(
            'cubbit-extension-script', 
            plugin_dir_url(__FILE__) . 'js/extension.js', 
            ['jquery', 'cubbit-directory-script'], 
            '2.0', 
            true
        );
        
        // Pass data to JS
        wp_localize_script('cubbit-extension-script', 'cubbitExtensionData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cubbit-extension-nonce')
        ]);
    }

    /**
     * AJAX handler for creating folders
     */
    public function ajax_create_folder() {
        check_ajax_referer('cubbit-extension-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $folder_name = isset($_POST['folder_name']) ? sanitize_text_field($_POST['folder_name']) : '';
        $parent_path = isset($_POST['parent_path']) ? sanitize_text_field($_POST['parent_path']) : '';
        
        if (empty($folder_name)) {
            wp_send_json_error('Folder name is required');
            return;
        }
        
        // Validate folder name
        if (!$this->validate_folder_name($folder_name)) {
            wp_send_json_error('Invalid folder name. Use only letters, numbers, hyphens, and underscores.');
            return;
        }
        
        // Create the folder
        $result = $this->create_folder($folder_name, $parent_path);
        
        if ($result['success']) {
            // Clear cache
            $this->clear_directory_cache($parent_path);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX handler for deleting folders
     */
    public function ajax_delete_folder() {
        check_ajax_referer('cubbit-extension-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $folder_path = isset($_POST['folder_path']) ? sanitize_text_field($_POST['folder_path']) : '';
        
        if (empty($folder_path)) {
            wp_send_json_error('Folder path is required');
            return;
        }
        
        // Delete the folder
        $result = $this->delete_folder($folder_path);
        
        if ($result['success']) {
            // Clear cache
            $parent_path = dirname($folder_path);
            $this->clear_directory_cache($parent_path);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX handler for renaming folders
     */
    public function ajax_rename_folder() {
        check_ajax_referer('cubbit-extension-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $old_path = isset($_POST['old_path']) ? sanitize_text_field($_POST['old_path']) : '';
        $new_name = isset($_POST['new_name']) ? sanitize_text_field($_POST['new_name']) : '';
        
        if (empty($old_path) || empty($new_name)) {
            wp_send_json_error('Both old path and new name are required');
            return;
        }
        
        // Validate new name
        if (!$this->validate_folder_name($new_name)) {
            wp_send_json_error('Invalid folder name. Use only letters, numbers, hyphens, and underscores.');
            return;
        }
        
        // Rename the folder
        $result = $this->rename_folder($old_path, $new_name);
        
        if ($result['success']) {
            // Clear cache
            $parent_path = dirname($old_path);
            $this->clear_directory_cache($parent_path);
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX handler for moving items
     */
    public function ajax_move_items() {
        check_ajax_referer('cubbit-extension-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $items = isset($_POST['items']) ? array_map('sanitize_text_field', $_POST['items']) : [];
        $destination = isset($_POST['destination']) ? sanitize_text_field($_POST['destination']) : '';
        
        if (empty($items) || empty($destination)) {
            wp_send_json_error('Items and destination are required');
            return;
        }
        
        // Move the items
        $result = $this->move_items($items, $destination);
        
        if ($result['success']) {
            // Clear cache for affected directories
            foreach ($items as $item) {
                $parent_path = dirname($item);
                $this->clear_directory_cache($parent_path);
            }
            $this->clear_directory_cache($destination);
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX handler for getting folder information
     */
    public function ajax_get_folder_info() {
        check_ajax_referer('cubbit-extension-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $folder_path = isset($_POST['folder_path']) ? sanitize_text_field($_POST['folder_path']) : '';
        
        if (empty($folder_path)) {
            wp_send_json_error('Folder path is required');
            return;
        }
        
        // Get folder information
        $result = $this->get_folder_info($folder_path);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Create a folder in Cubbit S3
     */
    private function create_folder($folder_name, $parent_path = '') {
        $config = $this->get_cubbit_config();
        $bucket_name = $config['bucket_name'];
        $access_key = $config['access_key'];
        $secret_key = $config['secret_key'];
        
        if (empty($access_key) || empty($secret_key)) {
            return [
                'success' => false,
                'message' => 'Missing Cubbit credentials'
            ];
        }
        
        // Construct the full folder path
        $folder_path = $parent_path;
        if (!empty($parent_path) && substr($parent_path, -1) !== '/') {
            $folder_path .= '/';
        }
        $folder_path .= $folder_name . '/';
        
        try {
            // Create an empty object to represent the folder
            $result = $this->put_object($bucket_name, $folder_path, '', $access_key, $secret_key);
            
            if ($result['success']) {
                $this->log_message("Created folder: {$folder_path}");
                return [
                    'success' => true,
                    'message' => "Folder '{$folder_name}' created successfully",
                    'path' => $folder_path
                ];
            } else {
                return $result;
            }
            
        } catch (Exception $e) {
            $this->log_message("Error creating folder {$folder_path}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error creating folder: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a folder and all its contents
     */
    private function delete_folder($folder_path) {
        $config = $this->get_cubbit_config();
        $bucket_name = $config['bucket_name'];
        $access_key = $config['access_key'];
        $secret_key = $config['secret_key'];
        
        if (empty($access_key) || empty($secret_key)) {
            return [
                'success' => false,
                'message' => 'Missing Cubbit credentials'
            ];
        }
        
        try {
            // Ensure folder path ends with /
            if (substr($folder_path, -1) !== '/') {
                $folder_path .= '/';
            }
            
            // List all objects in the folder
            $objects = $this->list_objects_recursive($bucket_name, $folder_path, $access_key, $secret_key);
            
            if (empty($objects)) {
                // Just delete the folder marker
                $result = $this->delete_object($bucket_name, $folder_path, $access_key, $secret_key);
            } else {
                // Delete all objects in the folder
                $deleted_count = 0;
                $failed_count = 0;
                
                foreach ($objects as $object_key) {
                    $delete_result = $this->delete_object($bucket_name, $object_key, $access_key, $secret_key);
                    if ($delete_result['success']) {
                        $deleted_count++;
                    } else {
                        $failed_count++;
                    }
                }
                
                // Delete the folder marker itself
                $this->delete_object($bucket_name, $folder_path, $access_key, $secret_key);
                
                $result = [
                    'success' => $failed_count === 0,
                    'message' => "Deleted {$deleted_count} items" . ($failed_count > 0 ? ", {$failed_count} failed" : "")
                ];
            }
            
            if ($result['success']) {
                $this->log_message("Deleted folder: {$folder_path}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->log_message("Error deleting folder {$folder_path}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error deleting folder: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Rename a folder
     */
    private function rename_folder($old_path, $new_name) {
        $config = $this->get_cubbit_config();
        $bucket_name = $config['bucket_name'];
        $access_key = $config['access_key'];
        $secret_key = $config['secret_key'];
        
        if (empty($access_key) || empty($secret_key)) {
            return [
                'success' => false,
                'message' => 'Missing Cubbit credentials'
            ];
        }
        
        try {
            // Ensure old path ends with /
            if (substr($old_path, -1) !== '/') {
                $old_path .= '/';
            }
            
            // Construct new path
            $parent_path = dirname(rtrim($old_path, '/'));
            $new_path = ($parent_path === '.' ? '' : $parent_path . '/') . $new_name . '/';
            
            // List all objects in the old folder
            $objects = $this->list_objects_recursive($bucket_name, $old_path, $access_key, $secret_key);
            
            $moved_count = 0;
            $failed_count = 0;
            
            // Move each object to the new location
            foreach ($objects as $object_key) {
                $relative_path = substr($object_key, strlen($old_path));
                $new_object_key = $new_path . $relative_path;
                
                // Copy object to new location
                $copy_result = $this->copy_object($bucket_name, $object_key, $new_object_key, $access_key, $secret_key);
                
                if ($copy_result['success']) {
                    // Delete old object
                    $delete_result = $this->delete_object($bucket_name, $object_key, $access_key, $secret_key);
                    if ($delete_result['success']) {
                        $moved_count++;
                    } else {
                        $failed_count++;
                    }
                } else {
                    $failed_count++;
                }
            }
            
            // Create new folder marker and delete old one
            $this->put_object($bucket_name, $new_path, '', $access_key, $secret_key);
            $this->delete_object($bucket_name, $old_path, $access_key, $secret_key);
            
            $result = [
                'success' => $failed_count === 0,
                'message' => "Moved {$moved_count} items" . ($failed_count > 0 ? ", {$failed_count} failed" : ""),
                'new_path' => $new_path
            ];
            
            if ($result['success']) {
                $this->log_message("Renamed folder from {$old_path} to {$new_path}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->log_message("Error renaming folder {$old_path}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error renaming folder: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Move items to a new location
     */
    private function move_items($items, $destination) {
        $config = $this->get_cubbit_config();
        $bucket_name = $config['bucket_name'];
        $access_key = $config['access_key'];
        $secret_key = $config['secret_key'];
        
        if (empty($access_key) || empty($secret_key)) {
            return [
                'success' => false,
                'message' => 'Missing Cubbit credentials'
            ];
        }
        
        try {
            // Ensure destination ends with /
            if (!empty($destination) && substr($destination, -1) !== '/') {
                $destination .= '/';
            }
            
            $moved_count = 0;
            $failed_count = 0;
            $errors = [];
            
            foreach ($items as $item) {
                $item_name = basename(rtrim($item, '/'));
                $new_key = $destination . $item_name;
                
                // If it's a folder, add trailing slash
                if (substr($item, -1) === '/') {
                    $new_key .= '/';
                    
                    // Move all objects in the folder
                    $objects = $this->list_objects_recursive($bucket_name, $item, $access_key, $secret_key);
                    
                    foreach ($objects as $object_key) {
                        $relative_path = substr($object_key, strlen($item));
                        $new_object_key = $new_key . $relative_path;
                        
                        $copy_result = $this->copy_object($bucket_name, $object_key, $new_object_key, $access_key, $secret_key);
                        
                        if ($copy_result['success']) {
                            $this->delete_object($bucket_name, $object_key, $access_key, $secret_key);
                        } else {
                            $errors[] = "Failed to move {$object_key}";
                            $failed_count++;
                        }
                    }
                    
                    // Create new folder marker and delete old one
                    $this->put_object($bucket_name, $new_key, '', $access_key, $secret_key);
                    $this->delete_object($bucket_name, $item, $access_key, $secret_key);
                    
                } else {
                    // Move single file
                    $copy_result = $this->copy_object($bucket_name, $item, $new_key, $access_key, $secret_key);
                    
                    if ($copy_result['success']) {
                        $delete_result = $this->delete_object($bucket_name, $item, $access_key, $secret_key);
                        if ($delete_result['success']) {
                            $moved_count++;
                        } else {
                            $errors[] = "Failed to delete original {$item}";
                            $failed_count++;
                        }
                    } else {
                        $errors[] = "Failed to copy {$item}";
                        $failed_count++;
                    }
                }
            }
            
            $result = [
                'success' => $failed_count === 0,
                'message' => "Moved {$moved_count} items" . ($failed_count > 0 ? ", {$failed_count} failed" : ""),
                'errors' => $errors
            ];
            
            if ($result['success']) {
                $this->log_message("Moved " . count($items) . " items to {$destination}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->log_message("Error moving items: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error moving items: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get folder information
     */
    private function get_folder_info($folder_path) {
        $config = $this->get_cubbit_config();
        $bucket_name = $config['bucket_name'];
        $access_key = $config['access_key'];
        $secret_key = $config['secret_key'];
        
        if (empty($access_key) || empty($secret_key)) {
            return [
                'success' => false,
                'message' => 'Missing Cubbit credentials'
            ];
        }
        
        try {
            // Ensure folder path ends with /
            if (substr($folder_path, -1) !== '/') {
                $folder_path .= '/';
            }
            
            // List objects in the folder
            $objects = $this->list_objects_recursive($bucket_name, $folder_path, $access_key, $secret_key);
            
            $file_count = 0;
            $folder_count = 0;
            $total_size = 0;
            
            foreach ($objects as $object) {
                if (substr($object, -1) === '/') {
                    $folder_count++;
                } else {
                    $file_count++;
                    // Get object size (this would require additional API calls)
                }
            }
            
            return [
                'success' => true,
                'data' => [
                    'path' => $folder_path,
                    'file_count' => $file_count,
                    'folder_count' => $folder_count,
                    'total_size' => $total_size
                ]
            ];
            
        } catch (Exception $e) {
            $this->log_message("Error getting folder info for {$folder_path}: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error getting folder information: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Put object to S3 with AWS Signature V4
     */
    private function put_object($bucket, $key, $content, $access_key, $secret_key) {
        $amz_date = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        
        // Create canonical request
        $canonical_headers = implode("\n", [
            "host:s3.cubbit.eu",
            "x-amz-content-sha256:" . hash('sha256', $content),
            "x-amz-date:{$amz_date}"
        ]);
        
        $signed_headers = "host;x-amz-content-sha256;x-amz-date";
        
        $canonical_request = implode("\n", [
            "PUT",
            "/{$bucket}/" . rawurlencode($key),
            "",
            $canonical_headers . "\n",
            $signed_headers,
            hash('sha256', $content)
        ]);
        
        // Create string to sign
        $algorithm = "AWS4-HMAC-SHA256";
        $credential_scope = "{$datestamp}/{$this->region}/s3/aws4_request";
        $string_to_sign = implode("\n", [
            $algorithm,
            $amz_date,
            $credential_scope,
            hash('sha256', $canonical_request)
        ]);
        
        // Calculate signature
        $signing_key = $this->derive_signature_key($secret_key, $datestamp, $this->region, 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        
        // Create authorization header
        $authorization_header = implode(", ", [
            "{$algorithm} Credential={$access_key}/{$credential_scope}",
            "SignedHeaders={$signed_headers}",
            "Signature={$signature}"
        ]);
        
        // Make request
        $url = "{$this->endpoint}/{$bucket}/" . rawurlencode($key);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: s3.cubbit.eu",
            "x-amz-content-sha256: " . hash('sha256', $content),
            "x-amz-date: {$amz_date}",
            "Authorization: {$authorization_header}",
            "Content-Length: " . strlen($content)
        ]);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $http_code === 200,
            'message' => $http_code === 200 ? 'Object created successfully' : "HTTP Error: {$http_code}"
        ];
    }

    /**
     * Delete object from S3 with AWS Signature V4
     */
    private function delete_object($bucket, $key, $access_key, $secret_key) {
        $amz_date = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        
        // Create canonical request
        $canonical_headers = implode("\n", [
            "host:s3.cubbit.eu",
            "x-amz-content-sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
            "x-amz-date:{$amz_date}"
        ]);
        
        $signed_headers = "host;x-amz-content-sha256;x-amz-date";
        
        $canonical_request = implode("\n", [
            "DELETE",
            "/{$bucket}/" . rawurlencode($key),
            "",
            $canonical_headers . "\n",
            $signed_headers,
            "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
        ]);
        
        // Create string to sign
        $algorithm = "AWS4-HMAC-SHA256";
        $credential_scope = "{$datestamp}/{$this->region}/s3/aws4_request";
        $string_to_sign = implode("\n", [
            $algorithm,
            $amz_date,
            $credential_scope,
            hash('sha256', $canonical_request)
        ]);
        
        // Calculate signature
        $signing_key = $this->derive_signature_key($secret_key, $datestamp, $this->region, 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        
        // Create authorization header
        $authorization_header = implode(", ", [
            "{$algorithm} Credential={$access_key}/{$credential_scope}",
            "SignedHeaders={$signed_headers}",
            "Signature={$signature}"
        ]);
        
        // Make request
        $url = "{$this->endpoint}/{$bucket}/" . rawurlencode($key);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: s3.cubbit.eu",
            "x-amz-content-sha256: e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
            "x-amz-date: {$amz_date}",
            "Authorization: {$authorization_header}"
        ]);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $http_code === 204,
            'message' => $http_code === 204 ? 'Object deleted successfully' : "HTTP Error: {$http_code}"
        ];
    }

    /**
     * Copy object in S3 with AWS Signature V4
     */
    private function copy_object($bucket, $source_key, $dest_key, $access_key, $secret_key) {
        $amz_date = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        
        $copy_source = "/{$bucket}/" . rawurlencode($source_key);
        
        // Create canonical request
        $canonical_headers = implode("\n", [
            "host:s3.cubbit.eu",
            "x-amz-content-sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
            "x-amz-copy-source:{$copy_source}",
            "x-amz-date:{$amz_date}"
        ]);
        
        $signed_headers = "host;x-amz-content-sha256;x-amz-copy-source;x-amz-date";
        
        $canonical_request = implode("\n", [
            "PUT",
            "/{$bucket}/" . rawurlencode($dest_key),
            "",
            $canonical_headers . "\n",
            $signed_headers,
            "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
        ]);
        
        // Create string to sign
        $algorithm = "AWS4-HMAC-SHA256";
        $credential_scope = "{$datestamp}/{$this->region}/s3/aws4_request";
        $string_to_sign = implode("\n", [
            $algorithm,
            $amz_date,
            $credential_scope,
            hash('sha256', $canonical_request)
        ]);
        
        // Calculate signature
        $signing_key = $this->derive_signature_key($secret_key, $datestamp, $this->region, 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        
        // Create authorization header
        $authorization_header = implode(", ", [
            "{$algorithm} Credential={$access_key}/{$credential_scope}",
            "SignedHeaders={$signed_headers}",
            "Signature={$signature}"
        ]);
        
        // Make request
        $url = "{$this->endpoint}/{$bucket}/" . rawurlencode($dest_key);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: s3.cubbit.eu",
            "x-amz-content-sha256: e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
            "x-amz-copy-source: {$copy_source}",
            "x-amz-date: {$amz_date}",
            "Authorization: {$authorization_header}"
        ]);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $http_code === 200,
            'message' => $http_code === 200 ? 'Object copied successfully' : "HTTP Error: {$http_code}"
        ];
    }

    /**
     * List objects recursively
     */
    private function list_objects_recursive($bucket, $prefix, $access_key, $secret_key) {
        $all_objects = [];
        
        try {
            $amz_date = gmdate('Ymd\THis\Z');
            $datestamp = gmdate('Ymd');
            
            $query_params = [
                'list-type' => '2',
                'prefix' => $prefix
            ];
            ksort($query_params);
            $canonical_query_string = http_build_query($query_params);
            
            // Create canonical headers
            $canonical_headers = implode("\n", [
                "host:s3.cubbit.eu",
                "x-amz-content-sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
                "x-amz-date:{$amz_date}"
            ]);
            
            $signed_headers = "host;x-amz-content-sha256;x-amz-date";
            
            // Create canonical request
            $canonical_request = implode("\n", [
                "GET",
                "/{$bucket}/",
                $canonical_query_string,
                $canonical_headers . "\n",
                $signed_headers,
                "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
            ]);
            
            // Create string to sign
            $algorithm = "AWS4-HMAC-SHA256";
            $credential_scope = "{$datestamp}/{$this->region}/s3/aws4_request";
            $string_to_sign = implode("\n", [
                $algorithm,
                $amz_date,
                $credential_scope,
                hash('sha256', $canonical_request)
            ]);
            
            // Calculate signature
            $signing_key = $this->derive_signature_key($secret_key, $datestamp, $this->region, 's3');
            $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
            
            // Create authorization header
            $authorization_header = implode(", ", [
                "{$algorithm} Credential={$access_key}/{$credential_scope}",
                "SignedHeaders={$signed_headers}",
                "Signature={$signature}"
            ]);
            
            // Make request
            $url = "{$this->endpoint}/{$bucket}/?" . $canonical_query_string;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Host: s3.cubbit.eu",
                "x-amz-content-sha256: e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
                "x-amz-date: {$amz_date}",
                "Authorization: {$authorization_header}"
            ]);
            
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200) {
                return $all_objects;
            }
            
            // Parse XML response
            $xml = simplexml_load_string($result);
            if ($xml === false) {
                return $all_objects;
            }
            
            // Process files and folders
            if (isset($xml->Contents)) {
                foreach ($xml->Contents as $object) {
                    $key = (string)$object->Key;
                    $all_objects[] = $key;
                }
            }
            
            return $all_objects;
            
        } catch (Exception $e) {
            $this->log_message('Error in list_objects_recursive: ' . $e->getMessage());
            return $all_objects;
        }
    }

    /**
     * Generate signing key for AWS Signature V4
     */
    private function derive_signature_key($key, $date, $region, $service) {
        $k_date = hash_hmac('sha256', $date, "AWS4" . $key, true);
        $k_region = hash_hmac('sha256', $region, $k_date, true);
        $k_service = hash_hmac('sha256', $service, $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        return $k_signing;
    }

    /**
     * Validate folder name
     */
    private function validate_folder_name($name) {
        // Allow letters, numbers, hyphens, underscores, and periods
        return preg_match('/^[a-zA-Z0-9._-]+$/', $name) && strlen($name) <= 255;
    }

    /**
     * Clear directory cache
     */
    private function clear_directory_cache($path) {
        $cache_key = 'cubbit_dir_' . md5($path);
        wp_cache_delete($cache_key);
    }

    /**
     * Cleanup cache
     */
    public function cleanup_cache() {
        // This would be called by WordPress scheduled events
        // Implementation depends on specific caching strategy
        $this->log_message("Cache cleanup completed");
    }

    /**
     * Log messages with structured format
     */
    private function log_message($message) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'plugin' => 'Cubbit Directory Manager Extension',
            'message' => $message,
            'user_id' => get_current_user_id()
        ];
        
        error_log('[' . date('Y-m-d H:i:s') . '] [Cubbit Dir Extension] ' . $message . "\n", 3, $this->log_file);
    }
}

// Initialize plugin
new CubbitDirectoryManagerExtension();

// Register activation hook
register_activation_hook(__FILE__, 'cubbit_directory_extension_activate');

// Activation function
function cubbit_directory_extension_activate() {
    // Create CSS and JS directories
    $plugin_dir = plugin_dir_path(__FILE__);
    $css_dir = $plugin_dir . 'css';
    $js_dir = $plugin_dir . 'js';
    
    // Create directories if they don't exist
    if (!is_dir($css_dir)) {
        mkdir($css_dir, 0755, true);
    }
    
    if (!is_dir($js_dir)) {
        mkdir($js_dir, 0755, true);
    }
    
    // Create default CSS file
    if (!file_exists($css_dir . '/extension.css')) {
        $css_content = '/* Cubbit Directory Manager Extension Styles */
.cubbit-extension-toolbar {
    margin-bottom: 15px;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.cubbit-extension-button {
    margin-right: 10px;
    background: linear-gradient(135deg, #0073aa, #005a87);
    border: none;
    color: white;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.cubbit-extension-button:hover {
    background: linear-gradient(135deg, #005a87, #004066);
    transform: translateY(-1px);
}

.cubbit-extension-button:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

.cubbit-folder-info {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-top: 10px;
}

.cubbit-folder-info h4 {
    margin-top: 0;
    color: #0073aa;
}

.cubbit-progress-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
}

.cubbit-progress-content {
    background-color: #fff;
    padding: 25px;
    border-radius: 8px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.cubbit-progress-bar {
    height: 20px;
    background-color: #f3f3f3;
    border-radius: 10px;
    overflow: hidden;
    margin: 15px 0;
}

.cubbit-progress-inner {
    height: 100%;
    background: linear-gradient(90deg, #0073aa, #00a0d2);
    width: 0;
    transition: width 0.5s ease;
    border-radius: 10px;
}';
        file_put_contents($css_dir . '/extension.css', $css_content);
    }
    
    // Create default JS file
    if (!file_exists($js_dir . '/extension.js')) {
        $js_content = '// Cubbit Directory Manager Extension JavaScript
jQuery(document).ready(function($) {
    console.log("Cubbit Directory Manager Extension loaded");
    
    // Initialize extension functionality
    if (typeof cubbitExtensionData !== "undefined") {
        // Extension is properly loaded
        initializeExtensionFeatures();
    }
    
    function initializeExtensionFeatures() {
        // Add event listeners for extension buttons
        $(document).on("click", ".cubbit-create-folder", handleCreateFolder);
        $(document).on("click", ".cubbit-delete-folder", handleDeleteFolder);
        $(document).on("click", ".cubbit-rename-folder", handleRenameFolder);
        $(document).on("click", ".cubbit-move-items", handleMoveItems);
        $(document).on("click", ".cubbit-folder-info", handleFolderInfo);
    }
    
    function handleCreateFolder() {
        // Implementation for folder creation
        console.log("Create folder clicked");
    }
    
    function handleDeleteFolder() {
        // Implementation for folder deletion
        console.log("Delete folder clicked");
    }
    
    function handleRenameFolder() {
        // Implementation for folder renaming
        console.log("Rename folder clicked");
    }
    
    function handleMoveItems() {
        // Implementation for moving items
        console.log("Move items clicked");
    }
    
    function handleFolderInfo() {
        // Implementation for folder information
        console.log("Folder info clicked");
    }
});';
        file_put_contents($js_dir . '/extension.js', $js_content);
    }
}