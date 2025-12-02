<?php
/**
 * Cubbit Integration Protocol
 * 
 * Comprehensive integration protocol for all Cubbit DS3 functionalities
 * within the APS Tools ecosystem, providing seamless operation with
 * the Aevov network plugins.
 * 
 * @package APS_Tools
 * @subpackage Integrations
 * @since 1.0.0
 */

namespace APS_Tools\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

class Cubbit_Integration_Protocol {
    
    private $endpoint = 'https://s3.cubbit.eu';
    private $region = 'eu-central-1';
    private $log_file;
    private $integration_status = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/aps-cubbit-integration.log';
        
        add_action('init', [$this, 'initialize_integration']);
        add_action('admin_menu', [$this, 'add_integration_menu']);
        add_action('wp_ajax_aps_cubbit_test_integration', [$this, 'ajax_test_integration']);
        add_action('wp_ajax_aps_cubbit_sync_config', [$this, 'ajax_sync_config']);
        add_action('wp_ajax_aps_cubbit_repair_plugins', [$this, 'ajax_repair_plugins']);
        
        // Hook into APS Tools core functionality
        add_filter('aps_tools_storage_providers', [$this, 'register_cubbit_provider']);
        add_filter('aps_tools_tensor_storage_options', [$this, 'add_cubbit_tensor_storage']);
        add_action('aps_tools_pattern_sync', [$this, 'sync_patterns_to_cubbit']);
    }
    
    /**
     * Initialize the integration
     */
    public function initialize_integration() {
        $this->check_cubbit_plugins();
        $this->sync_configurations();
        $this->setup_dual_storage_architecture();
    }
    
    /**
     * Add integration menu to APS Tools
     */
    public function add_integration_menu() {
        add_submenu_page(
            'aps-tools',
            'Cubbit Integration',
            'Cubbit Integration',
            'manage_options',
            'aps-cubbit-integration',
            [$this, 'render_integration_page']
        );
        
        add_submenu_page(
            'aps-tools',
            'LiteSpeed Integration',
            'LiteSpeed Integration',
            'manage_options',
            'aps-litespeed-integration',
            [$this, 'render_litespeed_page']
        );
    }
    
    /**
     * Check which Cubbit plugins are available and their status
     */
    private function check_cubbit_plugins() {
        $plugins = [
            'cubbit-directory-manager' => [
                'class' => 'CubbitDirectoryManager',
                'file' => 'Cubbit DS3/Cubbit Object Retrieval/cubbit-retrieval.php',
                'name' => 'Cubbit Directory Manager',
                'required' => true
            ],
            'cubbit-authenticated-downloader' => [
                'class' => 'CubbitAuthenticatedDownloader',
                'file' => 'Cubbit DS3/Cubbit Authenticated Downloader/cubbit-authenticated-downloader.php',
                'name' => 'Cubbit Authenticated Downloader',
                'required' => false
            ],
            'cubbit-directory-extension' => [
                'class' => 'CubbitDirectoryManagerExtension',
                'file' => 'Cubbit DS3/Cubbit Directory Manager Extension/cubbit-directory-manager-extension.php',
                'name' => 'Cubbit Directory Manager Extension',
                'required' => false
            ],
            'cubbit-folder-management' => [
                'class' => null, // Uses functions, not class
                'file' => 'Cubbit DS3/DS3 folder management/DS3-folder-management.php',
                'name' => 'DS3 Folder Management',
                'required' => false
            ]
        ];
        
        foreach ($plugins as $key => $plugin) {
            $status = [
                'active' => false,
                'class_exists' => false,
                'file_exists' => false,
                'needs_repair' => false,
                'issues' => []
            ];
            
            // Check if file exists
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin['file'];
            $status['file_exists'] = file_exists($plugin_path);
            
            // Check if class exists (for class-based plugins)
            if ($plugin['class']) {
                $status['class_exists'] = class_exists($plugin['class']);
                $status['active'] = $status['class_exists'];
            } else {
                // For function-based plugins, check if functions exist
                $status['active'] = function_exists('create_folder') && function_exists('delete_file');
            }
            
            // Check for specific issues
            if ($key === 'cubbit-folder-management' && $status['file_exists']) {
                $content = file_get_contents($plugin_path);
                if (strpos($content, 'your-access-key') !== false) {
                    $status['needs_repair'] = true;
                    $status['issues'][] = 'Hard-coded credentials need to be replaced';
                }
            }
            
            $this->integration_status[$key] = $status;
        }
        
        $this->log_message('Cubbit plugin status check completed');
    }
    
    /**
     * Sync configurations between all Cubbit plugins and APS Tools
     */
    private function sync_configurations() {
        // Get APS Tools Cubbit configuration
        $aps_config = [
            'access_key' => get_option('aps_cubbit_access_key', ''),
            'secret_key' => get_option('aps_cubbit_secret_key', ''),
            'bucket_name' => get_option('aps_cubbit_bucket_name', ''),
            'endpoint' => get_option('aps_cubbit_endpoint', $this->endpoint),
            'region' => get_option('aps_cubbit_region', $this->region)
        ];
        
        // Get Cubbit Directory Manager configuration
        $cubbit_config = [
            'access_key' => get_option('cubbit_access_key', ''),
            'secret_key' => get_option('cubbit_secret_key', ''),
            'bucket_name' => get_option('cubbit_bucket_name', '')
        ];
        
        // Sync configurations - prioritize APS Tools config if available
        if (!empty($aps_config['access_key']) && empty($cubbit_config['access_key'])) {
            update_option('cubbit_access_key', $aps_config['access_key']);
            update_option('cubbit_secret_key', $aps_config['secret_key']);
            update_option('cubbit_bucket_name', $aps_config['bucket_name']);
            $this->log_message('Synced APS Tools config to Cubbit plugins');
        } elseif (!empty($cubbit_config['access_key']) && empty($aps_config['access_key'])) {
            update_option('aps_cubbit_access_key', $cubbit_config['access_key']);
            update_option('aps_cubbit_secret_key', $cubbit_config['secret_key']);
            update_option('aps_cubbit_bucket_name', $cubbit_config['bucket_name']);
            update_option('aps_cubbit_endpoint', $this->endpoint);
            update_option('aps_cubbit_region', $this->region);
            $this->log_message('Synced Cubbit config to APS Tools');
        }
    }
    
    /**
     * Setup dual storage architecture (QUIC Cloud + Cubbit)
     */
    private function setup_dual_storage_architecture() {
        // Register Cubbit as secondary storage for tensor processing
        add_filter('aps_tools_tensor_storage_fallback', function($primary_failed, $tensor_data) {
            if ($primary_failed) {
                return $this->store_tensor_in_cubbit($tensor_data);
            }
            return false;
        }, 10, 2);
        
        // Register Cubbit for pattern backup
        add_action('aps_tools_pattern_backup', [$this, 'backup_patterns_to_cubbit']);
        
        // Setup cross-site pattern synchronization via Cubbit
        add_action('aps_tools_cross_site_sync', [$this, 'sync_cross_site_via_cubbit']);
    }
    
    /**
     * Store tensor data in Cubbit storage
     */
    private function store_tensor_in_cubbit($tensor_data) {
        if (!$this->is_cubbit_configured()) {
            return false;
        }
        
        try {
            $tensor_key = 'tensors/' . uniqid('tensor_') . '.dat';
            $result = $this->upload_to_cubbit($tensor_key, serialize($tensor_data), 'application/octet-stream');
            
            if ($result) {
                $this->log_message("Tensor stored in Cubbit: {$tensor_key}");
                return $tensor_key;
            }
        } catch (Exception $e) {
            $this->log_message("Failed to store tensor in Cubbit: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Backup patterns to Cubbit
     */
    public function backup_patterns_to_cubbit($patterns) {
        if (!$this->is_cubbit_configured()) {
            return;
        }
        
        $backup_data = [
            'timestamp' => time(),
            'site_url' => get_site_url(),
            'patterns' => $patterns
        ];
        
        $backup_key = 'backups/patterns/' . date('Y/m/d/') . uniqid('pattern_backup_') . '.json';
        $this->upload_to_cubbit($backup_key, json_encode($backup_data), 'application/json');
        
        $this->log_message("Pattern backup stored in Cubbit: {$backup_key}");
    }
    
    /**
     * Sync patterns across sites via Cubbit
     */
    public function sync_cross_site_via_cubbit($site_id, $patterns) {
        if (!$this->is_cubbit_configured()) {
            return;
        }
        
        $sync_key = "sync/{$site_id}/" . uniqid('sync_') . '.json';
        $sync_data = [
            'timestamp' => time(),
            'source_site' => get_site_url(),
            'target_site' => $site_id,
            'patterns' => $patterns
        ];
        
        $this->upload_to_cubbit($sync_key, json_encode($sync_data), 'application/json');
        $this->log_message("Cross-site sync data stored in Cubbit: {$sync_key}");
    }
    
    /**
     * Upload data to Cubbit storage
     */
    private function upload_to_cubbit($key, $data, $content_type = 'text/plain') {
        $config = $this->get_cubbit_config();
        if (!$config) {
            return false;
        }
        
        // Use AWS Signature V4 for authentication
        $amz_date = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        
        $file_hash = hash('sha256', $data);
        $file_size = strlen($data);
        
        // Create headers
        $headers = [
            'Host' => 's3.cubbit.eu',
            'Content-Length' => $file_size,
            'Content-Type' => $content_type,
            'x-amz-content-sha256' => $file_hash,
            'x-amz-date' => $amz_date
        ];
        
        // Create canonical request
        $encoded_key = str_replace('%2F', '/', rawurlencode($key));
        $canonical_uri = "/{$config['bucket']}/{$encoded_key}";
        
        ksort($headers);
        $canonical_headers = '';
        $signed_headers = '';
        
        foreach ($headers as $header_key => $value) {
            $canonical_headers .= strtolower($header_key) . ':' . trim($value) . "\n";
            $signed_headers .= strtolower($header_key) . ';';
        }
        
        $signed_headers = rtrim($signed_headers, ';');
        
        $canonical_request = "PUT\n" .
                           $canonical_uri . "\n" .
                           "\n" .
                           $canonical_headers . "\n" .
                           $signed_headers . "\n" .
                           $file_hash;
        
        // Create string to sign
        $algorithm = "AWS4-HMAC-SHA256";
        $credential_scope = "{$datestamp}/{$this->region}/s3/aws4_request";
        $string_to_sign = "{$algorithm}\n{$amz_date}\n{$credential_scope}\n" . hash('sha256', $canonical_request);
        
        // Calculate signature
        $signing_key = $this->derive_signature_key($config['secret_key'], $datestamp, $this->region, 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        
        // Create authorization header
        $authorization_header = "{$algorithm} " .
                               "Credential={$config['access_key']}/{$credential_scope}, " .
                               "SignedHeaders={$signed_headers}, " .
                               "Signature={$signature}";
        
        // Prepare cURL request
        $url = "{$this->endpoint}/{$config['bucket']}/{$encoded_key}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        
        $curl_headers = [];
        foreach ($headers as $header_key => $value) {
            $curl_headers[] = "{$header_key}: {$value}";
        }
        $curl_headers[] = "Authorization: {$authorization_header}";
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($http_code >= 200 && $http_code < 300);
    }
    
    /**
     * Get Cubbit configuration
     */
    private function get_cubbit_config() {
        $access_key = get_option('aps_cubbit_access_key') ?: get_option('cubbit_access_key');
        $secret_key = get_option('aps_cubbit_secret_key') ?: get_option('cubbit_secret_key');
        $bucket = get_option('aps_cubbit_bucket_name') ?: get_option('cubbit_bucket_name');
        
        if (empty($access_key) || empty($secret_key) || empty($bucket)) {
            return false;
        }
        
        return [
            'access_key' => $access_key,
            'secret_key' => $secret_key,
            'bucket' => $bucket
        ];
    }
    
    /**
     * Check if Cubbit is properly configured
     */
    private function is_cubbit_configured() {
        return $this->get_cubbit_config() !== false;
    }
    
    /**
     * Derive AWS Signature V4 signing key
     */
    private function derive_signature_key($key, $date, $region, $service) {
        $k_date = hash_hmac('sha256', $date, "AWS4" . $key, true);
        $k_region = hash_hmac('sha256', $region, $k_date, true);
        $k_service = hash_hmac('sha256', $service, $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        return $k_signing;
    }
    
    /**
     * Register Cubbit as storage provider
     */
    public function register_cubbit_provider($providers) {
        $providers['cubbit'] = [
            'name' => 'Cubbit DS3',
            'description' => 'Distributed, geo-fenced S3-compatible storage',
            'class' => __CLASS__,
            'configured' => $this->is_cubbit_configured(),
            'capabilities' => [
                'tensor_storage',
                'pattern_backup',
                'cross_site_sync',
                'bulk_download',
                'recursive_permissions'
            ]
        ];
        
        return $providers;
    }
    
    /**
     * Add Cubbit tensor storage option
     */
    public function add_cubbit_tensor_storage($options) {
        if ($this->is_cubbit_configured()) {
            $options['cubbit'] = 'Cubbit DS3 Storage';
        }
        return $options;
    }
    
    /**
     * Sync patterns to Cubbit
     */
    public function sync_patterns_to_cubbit($patterns) {
        $this->backup_patterns_to_cubbit($patterns);
    }
    
    /**
     * AJAX handler for testing integration
     */
    public function ajax_test_integration() {
        check_ajax_referer('aps_tools_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $results = $this->run_integration_tests();
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for syncing configuration
     */
    public function ajax_sync_config() {
        check_ajax_referer('aps_tools_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $this->sync_configurations();
        wp_send_json_success('Configuration synced successfully');
    }
    
    /**
     * AJAX handler for repairing plugins
     */
    public function ajax_repair_plugins() {
        check_ajax_referer('aps_tools_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $results = $this->repair_cubbit_plugins();
        wp_send_json_success($results);
    }
    
    /**
     * Run comprehensive integration tests
     */
    private function run_integration_tests() {
        $tests = [
            'configuration' => $this->test_configuration(),
            'connectivity' => $this->test_connectivity(),
            'plugin_status' => $this->integration_status,
            'dual_storage' => $this->test_dual_storage(),
            'cross_site_sync' => $this->test_cross_site_sync()
        ];
        
        return $tests;
    }
    
    /**
     * Test configuration
     */
    private function test_configuration() {
        $config = $this->get_cubbit_config();
        return [
            'configured' => $config !== false,
            'details' => $config ? 'Configuration found' : 'Configuration missing'
        ];
    }
    
    /**
     * Test connectivity
     */
    private function test_connectivity() {
        if (!$this->is_cubbit_configured()) {
            return ['success' => false, 'message' => 'Not configured'];
        }
        
        // Test by trying to list bucket contents
        $config = $this->get_cubbit_config();
        $amz_date = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        
        $query_params = [
            'list-type' => '2',
            'max-keys' => '1'
        ];
        $canonical_query_string = http_build_query($query_params);
        
        $canonical_headers = implode("\n", [
            "host:s3.cubbit.eu",
            "x-amz-content-sha256:e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
            "x-amz-date:{$amz_date}"
        ]);
        
        $signed_headers = "host;x-amz-content-sha256;x-amz-date";
        
        $canonical_request = implode("\n", [
            "GET",
            "/{$config['bucket']}/",
            $canonical_query_string,
            $canonical_headers . "\n",
            $signed_headers,
            "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855"
        ]);
        
        $algorithm = "AWS4-HMAC-SHA256";
        $credential_scope = "{$datestamp}/{$this->region}/s3/aws4_request";
        $string_to_sign = implode("\n", [
            $algorithm,
            $amz_date,
            $credential_scope,
            hash('sha256', $canonical_request)
        ]);
        
        $signing_key = $this->derive_signature_key($config['secret_key'], $datestamp, $this->region, 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        
        $authorization_header = implode(", ", [
            "{$algorithm} Credential={$config['access_key']}/{$credential_scope}",
            "SignedHeaders={$signed_headers}",
            "Signature={$signature}"
        ]);
        
        $url = "{$this->endpoint}/{$config['bucket']}/?" . $canonical_query_string;
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
        
        return [
            'success' => $http_code === 200,
            'message' => $http_code === 200 ? 'Connection successful' : "Connection failed (HTTP {$http_code})"
        ];
    }
    
    /**
     * Test dual storage architecture
     */
    private function test_dual_storage() {
        // Test if both QUIC Cloud and Cubbit are available
        $quic_available = class_exists('APS_Tools\\Models\\Bloom_Tensor_Storage');
        $cubbit_available = $this->is_cubbit_configured();
        
        return [
            'quic_cloud' => $quic_available,
            'cubbit' => $cubbit_available,
            'dual_storage_ready' => $quic_available && $cubbit_available
        ];
    }
    
    /**
     * Test cross-site synchronization
     */
    private function test_cross_site_sync() {
        if (!$this->is_cubbit_configured()) {
            return ['available' => false, 'message' => 'Cubbit not configured'];
        }
        
        // Test by creating a sync test file
        $test_data = ['test' => true, 'timestamp' => time()];
        $test_key = 'sync/test/' . uniqid('test_') . '.json';
        
        $result = $this->upload_to_cubbit($test_key, json_encode($test_data), 'application/json');
        
        return [
            'available' => $result,
            'message' => $result ? 'Cross-site sync ready' : 'Cross-site sync failed'
        ];
    }
    
    /**
     * Repair Cubbit plugins
     */
    private function repair_cubbit_plugins() {
        $repairs = [];
        
        // Fix DS3 folder management hard-coded credentials
        if (isset($this->integration_status['cubbit-folder-management']) && 
            $this->integration_status['cubbit-folder-management']['needs_repair']) {
            
            $plugin_path = WP_PLUGIN_DIR . '/Cubbit DS3/DS3 folder management/DS3-folder-management.php';
            if (file_exists($plugin_path)) {
                $content = file_get_contents($plugin_path);
                
                // Replace hard-coded credentials with dynamic ones
                $content = str_replace(
                    "'your-access-key'",
                    "get_option('cubbit_access_key', '')",
                    $content
                );
                $content = str_replace(
                    "'your-secret-key'",
                    "get_option('cubbit_secret_key', '')",
                    $content
                );
                $content = str_replace(
                    "'your-bucket-name'",
                    "get_option('cubbit_bucket_name', '')",
                    $content
                );
                
                if (file_put_contents($plugin_path, $content)) {
                    $repairs['ds3-folder-management'] = 'Fixed hard-coded credentials';
                } else {
                    $repairs['ds3-folder-management'] = 'Failed to fix credentials (permission issue)';
                }
            }
        }
        
        return $repairs;
    }
    
    /**
     * Render integration page
     */
    public function render_integration_page() {
        ?>
        <div class="wrap">
            <h1>Cubbit Integration Protocol</h1>
            
            <div class="aps-integration-dashboard">
                <div class="integration-status">
                    <h2>Integration Status</h2>
                    <div id="integration-status-content">
                        <p><span class="spinner is-active"></span> Loading integration status...</p>
                    </div>
                </div>
                
                <div class="integration-actions">
                    <h2>Actions</h2>
                    <button id="test-integration" class="button button-primary">Test Integration</button>
                    <button id="sync-config" class="button">Sync Configuration</button>
                    <button id="repair-plugins" class="button">Repair Plugins</button>
                </div>
                
                <div class="dual-storage-config">
                    <h2>Dual Storage Architecture</h2>
                    <p>Configure QUIC Cloud + Cubbit dual storage for optimal performance and redundancy.</p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Primary Storage</th>
                            <td>QUIC Cloud (High Performance)</td>
                        </tr>
                        <tr>
                            <th scope="row">Secondary Storage</th>
                            <td>Cubbit DS3 (Distributed, Geo-fenced)</td>
                        </tr>
                        <tr>
                            <th scope="row">Failover Strategy</th>
                            <td>Automatic failover to Cubbit if QUIC Cloud unavailable</td>
                        </tr>
                    </table>
                </div>
                
                <div class="xai-integration">
                    <h2>XAI Integration</h2>
                    <p>Transparency, traceability, and explainability for all storage operations.</p>
                    <div id="xai-status">
                        <p>XAI Engine Status: <span id="xai-engine-status">Checking...</span></p>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Load integration status
            loadIntegrationStatus();
            
            $('#test-integration').on('click', function() {
                testIntegration();
            });
            
            $('#sync-config').on('click', function() {
                syncConfiguration();
            });
            
            $('#repair-plugins').on('click', function() {
                repairPlugins();
            });
            
            function loadIntegrationStatus() {
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'aps_cubbit_test_integration',
                        nonce: '<?php echo wp_create_nonce('aps_tools_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayIntegrationStatus(response.data);
                        }
                    }
                });
            }
            
            function displayIntegrationStatus(data) {
                let html = '<div class="integration-grid">';
                
                // Configuration status
                html += '<div class="status-card">';
                html += '<h3>Configuration</h3>';
                html += '<p class="status-' + (data.configuration.configured ? 'success' : 'error') + '">';
                html += data.configuration.configured ? '✓ Configured' : '✗ Not Configured';
                html += '</p></div>';
                
                // Connectivity status
                html += '<div class="status-card">';
                html += '<h3>Connectivity</h3>';
                html += '<p class="status-' + (data.connectivity.success ? 'success' : 'error') + '">';
                html += data.connectivity.success ? '✓ Connected' : '✗ Connection Failed';
                html += '</p></div>';
                
                // Plugin status
                html += '<div class="status-card">';
                html += '<h3>Plugins</h3>';
                let pluginCount = Object.keys(data.plugin_status).length;
                let activeCount = Object.values(data.plugin_status).filter(p => p.active).length;
                html += '<p>Active: ' + activeCount + '/' + pluginCount + '</p></div>';
                
                // Dual storage status
                html += '<div class="status-card">';
                html += '<h3>Dual Storage</h3>';
                html += '<p class="status-' + (data.dual_storage.dual_storage_ready ? 'success' : 'warning') + '">';
                html += data.dual_storage.dual_storage_ready ? '✓ Ready' : '⚠ Partial';
                html += '</p></div>';
                
                html += '</div>';
                $('#integration-status-content').html(html);
            }
            
            function testIntegration() {
                $('#test-integration').prop('disabled', true).text('Testing...');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'aps_cubbit_test_integration',
                        nonce: '<?php echo wp_create_nonce('aps_tools_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayIntegrationStatus(response.data);
                            alert('Integration test completed. Check status above.');
                        } else {
                            alert('Integration test failed: ' + response.data);
                        }
                    },
                    complete: function() {
                        $('#test-integration').prop('disabled', false).text('Test Integration');
                    }
                });
            }
            
            function syncConfiguration() {
                $('#sync-config').prop('disabled', true).text('Syncing...');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'aps_cubbit_sync_config',
                        nonce: '<?php echo wp_create_nonce('aps_tools_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Configuration synced successfully');
                            loadIntegrationStatus();
                        } else {
                            alert('Sync failed: ' + response.data);
                        }
                    },
                    complete: function() {
                        $('#sync-config').prop('disabled', false).text('Sync Configuration');
                    }
                });
            }
            
            function repairPlugins() {
                $('#repair-plugins').prop('disabled', true).text('Repairing...');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'aps_cubbit_repair_plugins',
                        nonce: '<?php echo wp_create_nonce('aps_tools_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            let repairs = response.data;
                            let message = 'Repairs completed:\n';
                            for (let plugin in repairs) {
                                message += '- ' + plugin + ': ' + repairs[plugin] + '\n';
                            }
                            alert(message);
                            loadIntegrationStatus();
                        } else {
                            alert('Repair failed: ' + response.data);
                        }
                    },
                    complete: function() {
                        $('#repair-plugins').prop('disabled', false).text('Repair Plugins');
                    }
                });
            }
        });
        </script>
        
        <style>
        .aps-integration-dashboard {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .integration-status, .integration-actions, .dual-storage-config, .xai-integration {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .integration-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .status-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        
        .status-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .status-success {
            color: #46b450;
            font-weight: bold;
        }
        
        .status-error {
            color: #dc3232;
            font-weight: bold;
        }
        
        .status-warning {
            color: #ffb900;
            font-weight: bold;
        }
        </style>
        <?php
    }
    
    /**
     * Render LiteSpeed integration page
     */
    public function render_litespeed_page() {
        ?>
        <div class="wrap">
            <h1>LiteSpeed Integration</h1>
            
            <div class="litespeed-integration">
                <div class="integration-overview">
                    <h2>LiteSpeed Cache Integration</h2>
                    <p>Optimize APS Tools and Cubbit integration with LiteSpeed caching for maximum performance.</p>
                </div>
                
                <div class="cache-configuration">
                    <h3>Cache Configuration</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('aps_litespeed_config'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Tensor Caching</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="aps_litespeed_tensor_cache" value="1"
                                               <?php checked(get_option('aps_litespeed_tensor_cache', 0)); ?>>
                                        Cache tensor processing results
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Enable Pattern Caching</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="aps_litespeed_pattern_cache" value="1"
                                               <?php checked(get_option('aps_litespeed_pattern_cache', 0)); ?>>
                                        Cache pattern recognition results
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Cubbit Response Caching</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="aps_litespeed_cubbit_cache" value="1"
                                               <?php checked(get_option('aps_litespeed_cubbit_cache', 0)); ?>>
                                        Cache Cubbit API responses
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Cache TTL (seconds)</th>
                                <td>
                                    <input type="number" name="aps_litespeed_cache_ttl"
                                           value="<?php echo esc_attr(get_option('aps_litespeed_cache_ttl', 3600)); ?>"
                                           min="60" max="86400">
                                    <p class="description">Time to live for cached items (60 seconds to 24 hours)</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="save_litespeed_config" class="button button-primary" value="Save Configuration">
                        </p>
                    </form>
                </div>
                
                <div class="cache-status">
                    <h3>Cache Status</h3>
                    <div id="cache-status-display">
                        <p>Loading cache status...</p>
                    </div>
                    
                    <div class="cache-actions">
                        <button id="clear-tensor-cache" class="button">Clear Tensor Cache</button>
                        <button id="clear-pattern-cache" class="button">Clear Pattern Cache</button>
                        <button id="clear-cubbit-cache" class="button">Clear Cubbit Cache</button>
                        <button id="clear-all-cache" class="button button-secondary">Clear All APS Cache</button>
                    </div>
                </div>
                
                <div class="performance-metrics">
                    <h3>Performance Metrics</h3>
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <h4>Cache Hit Rate</h4>
                            <p id="cache-hit-rate">--</p>
                        </div>
                        <div class="metric-card">
                            <h4>Average Response Time</h4>
                            <p id="avg-response-time">--</p>
                        </div>
                        <div class="metric-card">
                            <h4>Cached Items</h4>
                            <p id="cached-items-count">--</p>
                        </div>
                        <div class="metric-card">
                            <h4>Cache Size</h4>
                            <p id="cache-size">--</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .litespeed-integration {
            max-width: 1200px;
        }
        
        .integration-overview, .cache-configuration, .cache-status, .performance-metrics {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .cache-actions {
            margin-top: 15px;
        }
        
        .cache-actions button {
            margin-right: 10px;
            margin-bottom: 5px;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .metric-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        
        .metric-card h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #555;
        }
        
        .metric-card p {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            margin: 0;
        }
        </style>
        <?php
        
        // Handle form submission
        if (isset($_POST['save_litespeed_config']) && check_admin_referer('aps_litespeed_config')) {
            update_option('aps_litespeed_tensor_cache', isset($_POST['aps_litespeed_tensor_cache']) ? 1 : 0);
            update_option('aps_litespeed_pattern_cache', isset($_POST['aps_litespeed_pattern_cache']) ? 1 : 0);
            update_option('aps_litespeed_cubbit_cache', isset($_POST['aps_litespeed_cubbit_cache']) ? 1 : 0);
            update_option('aps_litespeed_cache_ttl', intval($_POST['aps_litespeed_cache_ttl']));
            
            echo '<div class="notice notice-success"><p>LiteSpeed configuration saved successfully!</p></div>';
        }
    }
    
    /**
     * Log messages
     */
    private function log_message($message) {
        error_log('[' . date('Y-m-d H:i:s') . '] APS Cubbit Integration: ' . $message . "\n", 3, $this->log_file);
    }
}

// Initialize the integration protocol
new Cubbit_Integration_Protocol();