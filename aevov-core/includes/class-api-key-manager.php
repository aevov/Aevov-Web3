<?php
/**
 * API Key Manager - Secure Storage and Encryption
 *
 * Handles secure storage of API keys for all Aevov plugins
 * Uses WordPress secret keys for encryption
 *
 * @package AevovCore
 * @since 1.0.0
 */

namespace Aevov\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class APIKeyManager
 */
class APIKeyManager {

    /**
     * Encryption method
     */
    const CIPHER_METHOD = 'AES-256-CBC';

    /**
     * Key version for rotation tracking
     */
    const KEY_VERSION = 1;

    /**
     * Get encryption key from WordPress constants
     *
     * @param int $version Key version (for rotation support)
     * @return string Encryption key
     */
    private static function get_encryption_key($version = self::KEY_VERSION) {
        // Use WordPress AUTH_KEY as base for encryption
        $base_key = defined('AUTH_KEY') && AUTH_KEY !== 'put your unique phrase here'
            ? AUTH_KEY
            : wp_salt('auth');

        // Add SECURE_AUTH_KEY for additional entropy
        if (defined('SECURE_AUTH_KEY') && SECURE_AUTH_KEY !== 'put your unique phrase here') {
            $base_key .= SECURE_AUTH_KEY;
        }

        // Include version in key derivation for rotation support
        $versioned_key = $base_key . '_v' . $version;

        // Ensure key is proper length for AES-256 (32 bytes)
        return hash('sha256', $versioned_key, true);
    }

    /**
     * Check if AUTH_KEY has changed (potential security issue)
     *
     * @return bool True if changed
     */
    private static function has_auth_key_changed() {
        $current_key_hash = hash('sha256', defined('AUTH_KEY') ? AUTH_KEY : '');
        $stored_key_hash = get_option('aevov_auth_key_hash', '');

        if (empty($stored_key_hash)) {
            // First time - store current hash
            update_option('aevov_auth_key_hash', $current_key_hash, false);
            return false;
        }

        return $current_key_hash !== $stored_key_hash;
    }

    /**
     * Encrypt API key
     *
     * @param string $plaintext API key to encrypt
     * @param int $version Key version to use
     * @return string Encrypted API key (base64 encoded with version prefix)
     */
    public static function encrypt($plaintext, $version = self::KEY_VERSION) {
        if (empty($plaintext)) {
            return '';
        }

        $key = self::get_encryption_key($version);
        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            error_log('[Aevov Core] Encryption failed');
            self::log_audit('encrypt_failed', null, null);
            return '';
        }

        // Combine version byte, IV and encrypted data
        // Format: [1 byte version][IV][encrypted data]
        $result = chr($version) . $iv . $encrypted;

        // Return base64 encoded
        return base64_encode($result);
    }

    /**
     * Decrypt API key
     *
     * @param string $encrypted Encrypted API key (base64 encoded)
     * @return string Decrypted API key
     */
    public static function decrypt($encrypted) {
        if (empty($encrypted)) {
            return '';
        }

        $data = base64_decode($encrypted);

        if ($data === false) {
            error_log('[Aevov Core] Base64 decode failed');
            self::log_audit('decrypt_failed', null, null, 'base64_decode_failed');
            return '';
        }

        // Check for version byte (new format)
        $version = self::KEY_VERSION;
        $offset = 0;

        if (strlen($data) > 0) {
            $first_byte = ord($data[0]);
            // Version bytes are 1-10, anything else is likely old format without version
            if ($first_byte >= 1 && $first_byte <= 10) {
                $version = $first_byte;
                $offset = 1;
            }
        }

        $key = self::get_encryption_key($version);
        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = substr($data, $offset, $iv_length);
        $encrypted_data = substr($data, $offset + $iv_length);

        $decrypted = openssl_decrypt(
            $encrypted_data,
            self::CIPHER_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            error_log('[Aevov Core] Decryption failed');
            self::log_audit('decrypt_failed', null, null, 'openssl_decrypt_failed');
            return '';
        }

        return $decrypted;
    }

    /**
     * Store API key securely
     *
     * @param string $plugin_name Plugin name
     * @param string $key_name Key name (e.g., 'openai', 'stability')
     * @param string $api_key API key to store
     * @return bool Success status
     */
    public static function store($plugin_name, $key_name, $api_key) {
        if (empty($api_key)) {
            return delete_option("aevov_{$plugin_name}_{$key_name}_key");
        }

        $encrypted = self::encrypt($api_key);

        if (empty($encrypted)) {
            return false;
        }

        // Store encrypted key
        $stored = update_option("aevov_{$plugin_name}_{$key_name}_key", $encrypted, false);

        // Log key update (without revealing key)
        if ($stored) {
            $masked = self::mask_key($api_key);
            error_log("[Aevov Core] API key stored for {$plugin_name}/{$key_name}: {$masked}");
            do_action('aevov_api_key_updated', $plugin_name, $key_name);
        }

        return $stored;
    }

    /**
     * Retrieve API key
     *
     * @param string $plugin_name Plugin name
     * @param string $key_name Key name
     * @param bool $log_access Whether to log access (default: true)
     * @return string Decrypted API key
     */
    public static function retrieve($plugin_name, $key_name, $log_access = true) {
        $encrypted = get_option("aevov_{$plugin_name}_{$key_name}_key", '');

        if (empty($encrypted)) {
            return '';
        }

        // Check if AUTH_KEY changed (security check)
        if (self::has_auth_key_changed()) {
            error_log('[Aevov Core] WARNING: AUTH_KEY has changed! API keys may not decrypt correctly.');
            self::log_audit('auth_key_changed', $plugin_name, $key_name);
            // Don't return empty - try to decrypt anyway, it might work with old format
        }

        $decrypted = self::decrypt($encrypted);

        // Log successful access
        if ($log_access && !empty($decrypted)) {
            self::log_audit('key_accessed', $plugin_name, $key_name);
        }

        return $decrypted;
    }

    /**
     * Delete API key
     *
     * @param string $plugin_name Plugin name
     * @param string $key_name Key name
     * @return bool Success status
     */
    public static function delete($plugin_name, $key_name) {
        $deleted = delete_option("aevov_{$plugin_name}_{$key_name}_key");

        if ($deleted) {
            error_log("[Aevov Core] API key deleted for {$plugin_name}/{$key_name}");
            do_action('aevov_api_key_deleted', $plugin_name, $key_name);
        }

        return $deleted;
    }

    /**
     * Mask API key for display
     *
     * @param string $api_key API key to mask
     * @return string Masked API key
     */
    public static function mask_key($api_key) {
        if (empty($api_key)) {
            return '';
        }

        $length = strlen($api_key);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        // Show first 4 and last 4 characters
        return substr($api_key, 0, 4) . str_repeat('*', $length - 8) . substr($api_key, -4);
    }

    /**
     * Validate API key format
     *
     * @param string $api_key API key to validate
     * @param string $type Key type (openai, stability, etc.)
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate($api_key, $type = 'generic') {
        if (empty($api_key)) {
            return new \WP_Error('empty_key', 'API key cannot be empty');
        }

        // Type-specific validation
        switch ($type) {
            case 'openai':
                if (!preg_match('/^sk-[A-Za-z0-9]{32,}$/', $api_key)) {
                    return new \WP_Error('invalid_format', 'Invalid OpenAI API key format (should start with sk-)');
                }
                break;

            case 'anthropic':
                if (!preg_match('/^sk-ant-[A-Za-z0-9_-]{32,}$/', $api_key)) {
                    return new \WP_Error('invalid_format', 'Invalid Anthropic API key format (should start with sk-ant-)');
                }
                break;

            case 'stability':
                if (strlen($api_key) < 20) {
                    return new \WP_Error('invalid_format', 'Stability AI key appears too short');
                }
                break;

            case 'generic':
                if (strlen($api_key) < 10) {
                    return new \WP_Error('invalid_format', 'API key appears too short');
                }
                break;
        }

        return true;
    }

    /**
     * Rotate all API keys (re-encrypt with new encryption key)
     *
     * @return int Number of keys rotated
     */
    public static function rotate_all_keys() {
        global $wpdb;

        // Find all Aevov API key options
        $keys = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options}
             WHERE option_name LIKE 'aevov_%_key'",
            ARRAY_A
        );

        $rotated = 0;

        foreach ($keys as $key_data) {
            $option_name = $key_data['option_name'];
            $encrypted = $key_data['option_value'];

            // Decrypt with old key
            $decrypted = self::decrypt($encrypted);

            if (!empty($decrypted)) {
                // Re-encrypt with new key
                $new_encrypted = self::encrypt($decrypted);

                if (!empty($new_encrypted)) {
                    update_option($option_name, $new_encrypted, false);
                    $rotated++;
                }
            }
        }

        error_log("[Aevov Core] Rotated {$rotated} API keys");

        return $rotated;
    }

    /**
     * Get all stored API keys (for admin display)
     *
     * @return array Array of [plugin => [key_name => masked_value]]
     */
    public static function get_all_keys_masked() {
        global $wpdb;

        $keys = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE 'aevov_%_key'",
            ARRAY_A
        );

        $result = [];

        foreach ($keys as $key_data) {
            $option_name = $key_data['option_name'];

            // Parse option name: aevov_{plugin}_{keyname}_key
            if (preg_match('/^aevov_([^_]+)_(.+)_key$/', $option_name, $matches)) {
                $plugin = $matches[1];
                $key_name = $matches[2];

                $encrypted = get_option($option_name);
                $decrypted = self::decrypt($encrypted);

                if (!isset($result[$plugin])) {
                    $result[$plugin] = [];
                }

                $result[$plugin][$key_name] = self::mask_key($decrypted);
            }
        }

        return $result;
    }

    /**
     * Log audit event for key operations
     *
     * @param string $action Action performed (key_accessed, key_stored, key_deleted, etc.)
     * @param string|null $plugin_name Plugin name
     * @param string|null $key_name Key name
     * @param string|null $details Additional details
     */
    private static function log_audit($action, $plugin_name = null, $key_name = null, $details = null) {
        global $wpdb;

        // Create audit table if needed
        self::maybe_create_audit_table();

        $table = $wpdb->prefix . 'aevov_api_key_audit';

        $user_id = get_current_user_id();
        $ip_address = self::get_client_ip();

        // Insert audit record
        $wpdb->insert(
            $table,
            [
                'action' => $action,
                'plugin_name' => $plugin_name,
                'key_name' => $key_name,
                'user_id' => $user_id ?: null,
                'ip_address' => $ip_address,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'details' => $details,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        // Also fire action for monitoring
        do_action('aevov_api_key_audit', $action, $plugin_name, $key_name, $user_id, $ip_address);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Create audit table if needed
     */
    private static function maybe_create_audit_table() {
        global $wpdb;

        $table = $wpdb->prefix . 'aevov_api_key_audit';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        // Create table
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action varchar(50) NOT NULL,
            plugin_name varchar(100) DEFAULT NULL,
            key_name varchar(100) DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            details text,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY action (action),
            KEY plugin_name (plugin_name),
            KEY key_name (key_name),
            KEY user_id (user_id),
            KEY ip_address (ip_address),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        error_log('[Aevov Core] Created API key audit table');
    }

    /**
     * Get audit log entries
     *
     * @param array $filters Filters (action, plugin_name, key_name, user_id)
     * @param int $limit Number of entries to return
     * @param int $offset Offset for pagination
     * @return array Audit entries
     */
    public static function get_audit_log($filters = [], $limit = 100, $offset = 0) {
        global $wpdb;

        $table = $wpdb->prefix . 'aevov_api_key_audit';

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'action = %s';
            $params[] = $filters['action'];
        }

        if (!empty($filters['plugin_name'])) {
            $where[] = 'plugin_name = %s';
            $params[] = $filters['plugin_name'];
        }

        if (!empty($filters['key_name'])) {
            $where[] = 'key_name = %s';
            $params[] = $filters['key_name'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['ip_address'])) {
            $where[] = 'ip_address = %s';
            $params[] = $filters['ip_address'];
        }

        $where_clause = implode(' AND ', $where);

        $query = "SELECT * FROM $table
                  WHERE $where_clause
                  ORDER BY created_at DESC
                  LIMIT %d OFFSET %d";

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
    }

    /**
     * Get audit statistics
     *
     * @param int $days Number of days to analyze
     * @return array Statistics
     */
    public static function get_audit_statistics($days = 30) {
        global $wpdb;

        $table = $wpdb->prefix . 'aevov_api_key_audit';

        $query = $wpdb->prepare(
            "SELECT
                action,
                COUNT(*) as count,
                COUNT(DISTINCT plugin_name) as unique_plugins,
                COUNT(DISTINCT key_name) as unique_keys,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT ip_address) as unique_ips
             FROM $table
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY action",
            $days
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Cleanup old audit records
     *
     * @param int $days Delete records older than this many days
     * @return int Number of records deleted
     */
    public static function cleanup_audit_log($days = 90) {
        global $wpdb;

        $table = $wpdb->prefix . 'aevov_api_key_audit';

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        if ($deleted) {
            error_log("[Aevov Core] Cleaned up {$deleted} old audit records");
        }

        return $deleted;
    }
}
