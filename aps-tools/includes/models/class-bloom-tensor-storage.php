<?php
namespace APSTools\Models;

class BloomTensorStorage {
    private static $instance = null;
    private $storage_type = 'cubbit'; // New default storage type
    private $db_table_name;
    private $tensor_data_table;
    private $cubbit_endpoint = 'https://s3.cubbit.eu';
    private $cubbit_region = 'eu-central-1';
    private $cubbit_access_key;
    private $cubbit_secret_key;
    private $cubbit_bucket_name;
    private $uploads_dir; // Still needed for local temporary files or fallback

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->db_table_name = $wpdb->prefix . 'aps_bloom_tensors';
        $this->tensor_data_table = $wpdb->prefix . 'aps_tensor_data';

        if (function_exists('wp_upload_dir')) {
            $this->uploads_dir = wp_upload_dir()['basedir'] . '/bloom-chunks';
        } else {
            $this->uploads_dir = '/tmp/bloom-chunks'; // Fallback for non-WordPress environment
        }

        // Load Cubbit credentials
        $this->cubbit_access_key = get_option('cubbit_access_key');
        $this->cubbit_secret_key = get_option('cubbit_secret_key');
        $this->cubbit_bucket_name = get_option('cubbit_bucket_name');
        
        // Setup storage based on type
        switch ($this->storage_type) {
            case 'database':
                $this->log_error('BloomTensorStorage: Setting up database storage.');
                $this->setup_database_storage();
                break;
            case 'cubbit':
                $this->log_error('BloomTensorStorage: Setting up Cubbit storage.');
                $this->setup_cubbit_storage();
                break;
            default: // Fallback to database if an unknown type is set
                $this->log_error('BloomTensorStorage: Unknown storage type, falling back to database storage.');
                $this->storage_type = 'database';
                $this->setup_database_storage();
                break;
        }
        
        // Only add WordPress hooks if WordPress is loaded
        if (function_exists('add_action')) {
            add_action('admin_notices', [$this, 'render_batch_process_button']);
            add_action('wp_ajax_batch_process_tensor_data', [$this, 'handle_batch_process']);
            add_action('manage_bloom_chunk_posts_columns', [$this, 'add_chunk_columns']);
            add_action('manage_bloom_chunk_posts_custom_column', [$this, 'render_chunk_columns'], 10, 2);
        }
    }

    private function setup_database_storage() {
        $this->create_database_tables();
    }

    private function setup_cubbit_storage() {
        // Ensure Cubbit credentials are set up
        if (empty($this->cubbit_access_key) || empty($this->cubbit_secret_key) || empty($this->cubbit_bucket_name)) {
            $this->log_error('Cubbit storage setup failed: Missing Cubbit credentials or bucket name.');
            // Optionally, fall back to database storage if Cubbit is not configured
            // $this->storage_type = 'database';
            // $this->setup_database_storage();
        }
        $this->create_database_tables(); // Ensure local DB tables for metadata still exist
    }

    private function create_database_tables() {
        error_log('BloomTensorStorage: create_database_tables called.');
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->db_table_name} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, chunk_id bigint(20) unsigned NOT NULL, tensor_data_url VARCHAR(255) NOT NULL, PRIMARY KEY (id), UNIQUE KEY chunk_id (chunk_id)) $charset_collate;";
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->tensor_data_table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, chunk_id bigint(20) unsigned NOT NULL, tensor_data longtext NOT NULL, tensor_shape varchar(255), tensor_type varchar(50), processed_at datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY chunk_id (chunk_id)) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
        error_log('BloomTensorStorage: dbDelta executed for both tables.');
    }

    public function render_batch_process_button() {
        $screen = get_current_screen();
        if ($screen->post_type !== 'bloom_chunk') return;
        $unprocessed = $this->count_unprocessed_chunks();
        if ($unprocessed === 0) return;
        echo '<div class="notice notice-info"><p>' . sprintf(__('Found %d unprocessed tensor chunks.', 'aps-tools'), $unprocessed) . '<button id="process-tensors" class="button button-primary">' . __('Process All Chunks', 'aps-tools') . '</button></p></div><script>jQuery(document).ready(function($) {$("#process-tensors").on("click", function() {var $btn = $(this);$btn.prop("disabled", true).text("Processing...");$.ajax({url: ajaxurl,type: "POST",data: {action: "batch_process_tensor_data",nonce: "' . wp_create_nonce("process_tensors") . '"},success: function(response) {if (response.success) {location.reload();} else {alert("Error processing chunks: " + response.data.message);$btn.prop("disabled", false).text("Process All Chunks");}}});});});</script>';
    }

    public function handle_batch_process() {
        check_ajax_referer('process_tensors', 'nonce');
        $processed = 0;
        $errors = [];
        $chunks = get_posts(['post_type' => 'bloom_chunk', 'posts_per_page' => -1, 'fields' => 'ids']);
        foreach ($chunks as $chunk_id) {
            $file_path = get_post_meta($chunk_id, '_chunk_file', true);
            if (!$file_path || !file_exists($file_path)) continue;
            try {
                if ($this->store_tensor_data($chunk_id, $file_path)) {
                    $processed++;
                }
            } catch (\Exception $e) {
                $errors[] = "Chunk {$chunk_id}: " . $e->getMessage();
            }
        }
        wp_send_json_success(['processed' => $processed, 'errors' => $errors]);
    }

    private function count_unprocessed_chunks() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(p.ID) FROM {$wpdb->posts} p LEFT JOIN {$this->tensor_data_table} t ON p.ID = t.chunk_id WHERE p.post_type = 'bloom_chunk' AND t.id IS NULL");
    }

    public function store_tensor_data($chunk_id, $file_path) {
        $content = file_get_contents($file_path);
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON data');
        }
        global $wpdb;
        $result = $wpdb->replace($this->tensor_data_table, ['chunk_id' => $chunk_id, 'tensor_data' => $content, 'tensor_shape' => isset($data['shape']) ? json_encode($data['shape']) : null, 'tensor_type' => isset($data['dtype']) ? $data['dtype'] : null], ['%d', '%s', '%s', '%s']);
        if ($result === false) {
            $this->log_error('BloomTensorStorage: Failed to replace into tensor_data_table. Error: ' . $wpdb->last_error);
            return false;
        }
        $this->log_error('BloomTensorStorage: Replaced into tensor_data_table. Result: ' . ($result ? 'true' : 'false'));

        switch ($this->storage_type) {
            case 'cubbit':
                return $this->save_tensor_data_to_cubbit($chunk_id, $content);
            case 'database':
                return true; // Already stored in database
            default:
                $this->log_error('BloomTensorStorage: Unknown storage type encountered in store_tensor_data.');
                return false;
        }
    }

    private function save_tensor_data_to_cubbit($chunk_id, $tensor_data_content) {
        if (empty($this->cubbit_access_key) || empty($this->cubbit_secret_key) || empty($this->cubbit_bucket_name)) {
            $this->log_error('Cubbit credentials not configured. Cannot save tensor data to Cubbit.');
            return false;
        }

        $object_key = 'bloom-chunks/' . $chunk_id . '.json';
        $upload_success = $this->put_object(
            $this->cubbit_bucket_name,
            $object_key,
            $tensor_data_content,
            $this->cubbit_access_key,
            $this->cubbit_secret_key
        );

        if ($upload_success['success']) {
            $this->log_error('BloomTensorStorage: Successfully uploaded tensor data to Cubbit: ' . $object_key);
            // Update the tensor_data_url in the database to point to the Cubbit URL
            global $wpdb;
            $url = $this->cubbit_endpoint . '/' . $this->cubbit_bucket_name . '/' . $object_key;
            $result = $wpdb->replace($this->db_table_name, ['chunk_id' => $chunk_id, 'tensor_data_url' => $url], ['%d', '%s']);
            if ($result === false) {
                $this->log_error('BloomTensorStorage: Failed to update tensor_data_url for Cubbit. Error: ' . $wpdb->last_error);
                return false;
            }
            return true;
        } else {
            $this->log_error('BloomTensorStorage: Failed to upload tensor data to Cubbit: ' . ($upload_success['message'] ?? 'Unknown error'));
            return false;
        }
    }

    private function get_tensor_data_from_cubbit($chunk_id) {
        if (empty($this->cubbit_access_key) || empty($this->cubbit_secret_key) || empty($this->cubbit_bucket_name)) {
            $this->log_error('Cubbit credentials not configured. Cannot retrieve tensor data from Cubbit.');
            return false;
        }

        global $wpdb;
        $result = $wpdb->get_row($wpdb->prepare("SELECT tensor_data_url FROM {$this->db_table_name} WHERE chunk_id = %d", $chunk_id), ARRAY_A);
        if (!$result || empty($result['tensor_data_url'])) {
            $this->log_error('No Cubbit URL found for chunk ID: ' . $chunk_id);
            return false;
        }

        $object_key = str_replace($this->cubbit_endpoint . '/' . $this->cubbit_bucket_name . '/', '', $result['tensor_data_url']);
        $download_result = $this->get_object(
            $this->cubbit_bucket_name,
            $object_key,
            $this->cubbit_access_key,
            $this->cubbit_secret_key
        );

        if ($download_result['success']) {
            $this->log_error('BloomTensorStorage: Successfully retrieved tensor data from Cubbit: ' . $object_key);
            return $download_result['content'];
        } else {
            $this->log_error('BloomTensorStorage: Failed to retrieve tensor data from Cubbit: ' . ($download_result['message'] ?? 'Unknown error'));
            return false;
        }
    }

    public function get_tensor_data($chunk_id) {
        global $wpdb;
        switch ($this->storage_type) {
            case 'database':
                return $wpdb->get_var($wpdb->prepare("SELECT tensor_data FROM {$this->tensor_data_table} WHERE chunk_id = %d", $chunk_id));
            case 'cubbit':
                return $this->get_tensor_data_from_cubbit($chunk_id);
            default:
                $this->log_error('BloomTensorStorage: Unknown storage type encountered in get_tensor_data.');
                return false;
        }
    }

    public function get_all_stored_chunks() {
        global $wpdb;
        return $wpdb->get_results("SELECT c.chunk_id, c.tensor_data_url, t.tensor_shape, t.tensor_type FROM {$this->db_table_name} c LEFT JOIN {$this->tensor_data_table} t ON c.chunk_id = t.chunk_id", ARRAY_A);
    }

    public function add_chunk_columns($columns) {
        $columns['chunk_data'] = __('Pattern Data', 'aps-tools');
        return $columns;
    }

    public function render_chunk_columns($column, $post_id) {
        if ($column === 'chunk_data') {
            $data = $this->get_tensor_data($post_id);
            if ($data) {
                $json = json_decode($data, true);
                echo '<pre style="max-height:100px;overflow:auto;">' . esc_html(json_encode($json, JSON_PRETTY_PRINT)) . '</pre>';
            }
        }
    }

    /**
     * Common method to log errors
     */
    private function log_error($message) {
        // You might want to integrate with a more robust logging system
        error_log('BloomTensorStorage: ' . $message);
    }

    /**
     * Generate signing key for AWS Signature V4
     */
    private function derive_signature_key($key, $date, $region, $service) {
        $kDate = hash_hmac('sha256', $date, "AWS4" . $key, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        return $kSigning;
    }

    /**
     * Put object to S3 with AWS Signature V4
     * Adapted from Cubbit Directory Manager
     */
    private function put_object($bucket, $key, $content, $access_key, $secret_key) {
        $amzDate = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        
        // Create canonical request
        $canonicalHeaders = implode("\n", [
            "host:s3.cubbit.eu",
            "x-amz-content-sha256:" . hash('sha256', $content),
            "x-amz-date:{$amzDate}"
        ]);
        
        $signedHeaders = "host;x-amz-content-sha256;x-amz-date";
        
        $canonicalRequest = implode("\n", [
            "PUT",
            "/" . rawurlencode($bucket) . "/" . str_replace('%2F', '/', rawurlencode($key)),
            "",
            $canonicalHeaders . "\n",
            $signedHeaders,
            hash('sha256', $content)
        ]);
        
        // Create string to sign
        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = "{$datestamp}/{$this->cubbit_region}/s3/aws4_request";
        $stringToSign = implode("\n", [
            $algorithm,
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest)
        ]);
        
        // Calculate signature
        $signingKey = $this->derive_signature_key($secret_key, $datestamp, $this->cubbit_region, 's3');
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        
        // Create authorization header
        $authorizationHeader = "{$algorithm} " .
                               "Credential={$access_key}/{$credentialScope}, " .
                               "SignedHeaders={$signedHeaders}, " .
                               "Signature={$signature}";
        
        // Make request
        $url = "{$this->cubbit_endpoint}/{$bucket}/" . str_replace('%2F', '/', rawurlencode($key));
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: s3.cubbit.eu",
            "x-amz-content-sha256: " . hash('sha256', $content),
            "x-amz-date: {$amzDate}",
            "Authorization: {$authorizationHeader}",
            "Content-Length: " . strlen($content)
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $httpCode === 200,
            'message' => $httpCode === 200 ? 'Object created successfully' : "HTTP Error: {$httpCode}"
        ];
    }

    /**
     * Get object from S3 with AWS Signature V4
     * Adapted from Cubbit Authenticated Downloader
     */
    private function get_object($bucket, $key, $access_key, $secret_key) {
        // Format date for AWS requirement
        $amzDate = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        
        // Properly URL encode the path components while preserving slashes
        $path_parts = explode('/', $key);
        $encoded_parts = array_map('rawurlencode', $path_parts);
        $encodedKey = implode('/', $encoded_parts);
        
        // Create headers
        $headers = [
            'Host' => 's3.cubbit.eu',
            'x-amz-content-sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'x-amz-date' => $amzDate
        ];
        
        // Format canonical headers
        ksort($headers);
        $canonicalHeaders = '';
        $signedHeaders = '';
        
        foreach ($headers as $header_key => $value) {
            $canonicalHeaders .= strtolower($header_key) . ':' . trim($value) . "\n";
            $signedHeaders .= strtolower($header_key) . ';';
        }
        
        $signedHeaders = rtrim($signedHeaders, ';');
        
        // Create canonical request
        $canonicalRequest = "GET\n" .
                           "/" . rawurlencode($bucket) . "/" . str_replace('%2F', '/', rawurlencode($key)) . "\n" .
                           "\n" . // Empty query string
                           $canonicalHeaders . "\n" .
                           $signedHeaders . "\n" .
                           "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855";
        
        // Create string to sign
        $algorithm = "AWS4-HMAC-SHA256";
        $credentialScope = "{$datestamp}/{$this->cubbit_region}/s3/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
        
        // Calculate signature
        $signingKey = $this->derive_signature_key($secret_key, $datestamp, $this->cubbit_region, 's3');
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        
        // Create authorization header
        $authorizationHeader = "{$algorithm} " .
                               "Credential={$access_key}/{$credentialScope}, " .
                               "SignedHeaders={$signedHeaders}, " .
                               "Signature={$signature}";
        
        // Set up cURL request
        $url = "{$this->cubbit_endpoint}/{$bucket}/{$encodedKey}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minute timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 30 second connection timeout
        
        // Prepare headers for cURL
        $curlHeaders = [];
        foreach ($headers as $header_key => $value) {
            $curlHeaders[] = "{$header_key}: {$value}";
        }
        $curlHeaders[] = "Authorization: {$authorizationHeader}";
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        
        // Execute request
        $file_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        
        // Check for errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            $this->log_error("cURL Error downloading {$key}: {$error}");
            curl_close($ch);
            return [
                'success' => false,
                'message' => "Error downloading {$key}: {$error}"
            ];
        }
        
        curl_close($ch);
        
        if ($http_code !== 200) {
            $this->log_error("Failed to download {$key}. HTTP Code: {$http_code}");
            return [
                'success' => false,
                'message' => "Failed to download {$key}. HTTP Code: {$http_code}"
            ];
        }
        
        // Check if the response is XML (error)
        if (strpos($content_type, 'application/xml') !== false && strpos($file_content, '<?xml') === 0) {
            // This is likely an error response
            $this->log_error("Error response for {$key}: {$file_content}");
            return [
                'success' => false,
                'message' => "Error downloading {$key}: " . $this->extract_error_message($file_content)
            ];
        }
        
        return [
            'success' => true,
            'message' => "Successfully downloaded {$key}",
            'content' => $file_content
        ];
    }

    /**
     * Extract error message from XML response
     */
    private function extract_error_message($xml_content) {
        try {
            $xml = simplexml_load_string($xml_content);
            if ($xml && isset($xml->Message)) {
                return (string)$xml->Message;
            }
        } catch (Exception $e) {
            // Ignore parsing errors
        }
        return 'Unknown error occurred';
    }
}

// Only initialize if WordPress is loaded
if (function_exists('add_action')) {
    add_action('plugins_loaded', function() {
        BloomTensorStorage::instance();
    });
}