<?php
/**
 * Encryption Manager
 *
 * Handles all encryption and decryption operations using AES-256-GCM
 * with user-specific entropy for maximum security.
 *
 * @package AevovVisionDepth\Privacy
 * @since 1.0.0
 */

namespace AevovVisionDepth\Privacy;

class Encryption_Manager {

    /**
     * Encryption cipher method
     *
     * @var string
     */
    private $cipher = 'aes-256-gcm';

    /**
     * Encryption key cache
     *
     * @var array
     */
    private $key_cache = [];

    /**
     * Tag length for GCM mode (16 bytes = 128 bits)
     *
     * @var int
     */
    private $tag_length = 16;

    /**
     * Constructor
     */
    public function __construct() {
        // Verify that encryption is available
        if (!extension_loaded('openssl')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                _e('Vision Depth requires OpenSSL PHP extension for encryption.', 'aevov-vision-depth');
                echo '</p></div>';
            });
        }
    }

    /**
     * Initialize encryption manager
     *
     * @return void
     */
    public function init() {
        // Ensure master encryption key exists
        $this->ensure_master_key();

        // Add filter to clear key cache on logout
        add_action('wp_logout', [$this, 'clear_key_cache']);
    }

    /**
     * Encrypt data for a specific user
     *
     * @param mixed $data Data to encrypt (will be JSON encoded)
     * @param int $user_id User ID
     * @return string|false Base64 encoded encrypted data or false on failure
     */
    public function encrypt_data($data, $user_id) {
        if (!extension_loaded('openssl')) {
            error_log('[Vision Depth] OpenSSL extension not available for encryption');
            return false;
        }

        // JSON encode data
        $json_data = json_encode($data);
        if ($json_data === false) {
            error_log('[Vision Depth] Failed to JSON encode data for encryption');
            return false;
        }

        // Get user encryption key
        $key = $this->get_user_encryption_key($user_id);
        if (!$key) {
            error_log('[Vision Depth] Failed to get encryption key for user ' . $user_id);
            return false;
        }

        // Generate random IV (Initialization Vector)
        $iv_length = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);

        // Encrypt data with GCM mode
        $tag = '';
        $encrypted = openssl_encrypt(
            $json_data,
            $this->cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            $this->tag_length
        );

        if ($encrypted === false) {
            error_log('[Vision Depth] OpenSSL encryption failed: ' . openssl_error_string());
            return false;
        }

        // Package: IV + Tag + Encrypted Data
        $package = $iv . $tag . $encrypted;

        // Base64 encode for storage
        return base64_encode($package);
    }

    /**
     * Decrypt data for a specific user
     *
     * @param string $encrypted_data Base64 encoded encrypted data
     * @param int $user_id User ID
     * @return mixed|null Decrypted data or null on failure
     */
    public function decrypt_data($encrypted_data, $user_id) {
        if (!extension_loaded('openssl')) {
            error_log('[Vision Depth] OpenSSL extension not available for decryption');
            return null;
        }

        // Base64 decode
        $package = base64_decode($encrypted_data, true);
        if ($package === false) {
            error_log('[Vision Depth] Failed to base64 decode encrypted data');
            return null;
        }

        // Get user encryption key
        $key = $this->get_user_encryption_key($user_id);
        if (!$key) {
            error_log('[Vision Depth] Failed to get encryption key for user ' . $user_id);
            return null;
        }

        // Extract components
        $iv_length = openssl_cipher_iv_length($this->cipher);
        $iv = substr($package, 0, $iv_length);
        $tag = substr($package, $iv_length, $this->tag_length);
        $encrypted = substr($package, $iv_length + $this->tag_length);

        // Decrypt data
        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            error_log('[Vision Depth] OpenSSL decryption failed: ' . openssl_error_string());
            return null;
        }

        // JSON decode
        $data = json_decode($decrypted, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log('[Vision Depth] Failed to JSON decode decrypted data: ' . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /**
     * Get or generate encryption key for a specific user
     *
     * Uses user-specific entropy combined with master key for maximum security
     *
     * @param int $user_id User ID
     * @return string|false Encryption key or false on failure
     */
    private function get_user_encryption_key($user_id) {
        // Check cache
        if (isset($this->key_cache[$user_id])) {
            return $this->key_cache[$user_id];
        }

        // Get master key
        $master_key = $this->get_master_key();
        if (!$master_key) {
            return false;
        }

        // Get user entropy (stored in user meta)
        $user_entropy = get_user_meta($user_id, 'avd_encryption_entropy', true);

        if (!$user_entropy) {
            // Generate new entropy for user
            $user_entropy = bin2hex(openssl_random_pseudo_bytes(32));
            update_user_meta($user_id, 'avd_encryption_entropy', $user_entropy);
        }

        // Derive key using PBKDF2
        $key = hash_pbkdf2(
            'sha256',
            $master_key . $user_entropy,
            wp_salt('auth') . $user_id,
            10000, // iterations
            32,    // key length (256 bits)
            true   // raw binary
        );

        // Cache the key
        $this->key_cache[$user_id] = $key;

        return $key;
    }

    /**
     * Get master encryption key
     *
     * @return string|false Master key or false on failure
     */
    private function get_master_key() {
        $master_key = get_option('avd_master_encryption_key');

        if (!$master_key) {
            error_log('[Vision Depth] Master encryption key not found');
            return false;
        }

        return $master_key;
    }

    /**
     * Ensure master encryption key exists
     *
     * @return void
     */
    private function ensure_master_key() {
        $master_key = get_option('avd_master_encryption_key');

        if (!$master_key) {
            // Generate new master key
            $master_key = bin2hex(openssl_random_pseudo_bytes(32));

            // Store master key
            add_option('avd_master_encryption_key', $master_key, '', 'no');

            // Log key generation
            error_log('[Vision Depth] Generated new master encryption key');
        }
    }

    /**
     * Rotate user encryption key
     *
     * Re-encrypts all user data with a new key
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function rotate_user_key($user_id) {
        global $wpdb;

        // Get all encrypted data for user
        $table = $wpdb->prefix . 'avd_scraped_data';
        $user_data = $wpdb->get_results($wpdb->prepare(
            "SELECT id, encrypted_data FROM {$table} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (empty($user_data)) {
            return true; // No data to rotate
        }

        // Decrypt all data with old key
        $decrypted_data = [];
        foreach ($user_data as $row) {
            $decrypted = $this->decrypt_data($row['encrypted_data'], $user_id);
            if ($decrypted === null) {
                error_log('[Vision Depth] Failed to decrypt data during key rotation: ' . $row['id']);
                return false;
            }
            $decrypted_data[$row['id']] = $decrypted;
        }

        // Generate new entropy
        $new_entropy = bin2hex(openssl_random_pseudo_bytes(32));
        update_user_meta($user_id, 'avd_encryption_entropy', $new_entropy);

        // Clear key cache to force new key generation
        unset($this->key_cache[$user_id]);

        // Re-encrypt all data with new key
        foreach ($decrypted_data as $id => $data) {
            $encrypted = $this->encrypt_data($data, $user_id);
            if ($encrypted === false) {
                error_log('[Vision Depth] Failed to re-encrypt data during key rotation: ' . $id);
                // Note: Some data may have been re-encrypted - this is a partial failure
                return false;
            }

            // Update database
            $wpdb->update(
                $table,
                ['encrypted_data' => $encrypted],
                ['id' => $id],
                ['%s'],
                ['%d']
            );
        }

        // Log key rotation
        $activity_table = $wpdb->prefix . 'avd_activity_log';
        $wpdb->insert(
            $activity_table,
            [
                'user_id' => $user_id,
                'action_type' => 'encryption_key_rotated',
                'action_description' => sprintf(
                    __('Rotated encryption key and re-encrypted %d records', 'aevov-vision-depth'),
                    count($decrypted_data)
                ),
            ],
            ['%d', '%s', '%s']
        );

        return true;
    }

    /**
     * Test encryption and decryption
     *
     * @return array Test results
     */
    public function test_encryption() {
        $test_data = [
            'test' => 'encryption',
            'timestamp' => time(),
            'array' => [1, 2, 3],
            'unicode' => '测试 тест δοκιμή',
        ];

        $test_user_id = 1; // Use admin user for testing

        // Test encryption
        $encrypted = $this->encrypt_data($test_data, $test_user_id);
        if ($encrypted === false) {
            return [
                'success' => false,
                'error' => 'Encryption failed',
            ];
        }

        // Test decryption
        $decrypted = $this->decrypt_data($encrypted, $test_user_id);
        if ($decrypted === null) {
            return [
                'success' => false,
                'error' => 'Decryption failed',
            ];
        }

        // Verify data integrity
        if ($decrypted !== $test_data) {
            return [
                'success' => false,
                'error' => 'Decrypted data does not match original',
                'original' => $test_data,
                'decrypted' => $decrypted,
            ];
        }

        return [
            'success' => true,
            'encrypted_length' => strlen($encrypted),
            'original_length' => strlen(json_encode($test_data)),
        ];
    }

    /**
     * Clear encryption key cache
     *
     * @return void
     */
    public function clear_key_cache() {
        $this->key_cache = [];
    }

    /**
     * Get encryption statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        global $wpdb;

        $table = $wpdb->prefix . 'avd_scraped_data';

        $total_encrypted = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table}"
        );

        $total_size = $wpdb->get_var(
            "SELECT SUM(LENGTH(encrypted_data)) FROM {$table}"
        );

        $users_with_data = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$table}"
        );

        return [
            'total_encrypted_records' => intval($total_encrypted),
            'total_encrypted_size_bytes' => intval($total_size),
            'total_encrypted_size_mb' => round(intval($total_size) / (1024 * 1024), 2),
            'users_with_encrypted_data' => intval($users_with_data),
            'cipher_method' => $this->cipher,
            'key_length_bits' => 256,
            'openssl_available' => extension_loaded('openssl'),
        ];
    }

    /**
     * Export encryption configuration (without keys)
     *
     * @return array Configuration
     */
    public function export_config() {
        return [
            'cipher' => $this->cipher,
            'tag_length' => $this->tag_length,
            'key_derivation' => 'PBKDF2-SHA256',
            'iterations' => 10000,
            'key_length_bits' => 256,
            'iv_generation' => 'openssl_random_pseudo_bytes',
            'mode' => 'GCM (Galois/Counter Mode)',
            'authenticated' => true,
        ];
    }
}
