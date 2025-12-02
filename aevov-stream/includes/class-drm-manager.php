<?php
/**
 * DRM Manager - Digital Rights Management for Aevov Streams
 *
 * Provides content protection through encryption, licensing, and access control.
 *
 * @package AevovStream
 * @since 1.0.0
 */

namespace AevovStream;

if (!defined('ABSPATH')) {
    exit;
}

class DRMManager {

    /**
     * Encryption algorithm
     *
     * @var string
     */
    private $cipher = 'aes-256-gcm';

    /**
     * Database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Licenses table name
     *
     * @var string
     */
    private $licenses_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->licenses_table = $wpdb->prefix . 'aevov_drm_licenses';

        add_action('init', [$this, 'create_tables']);
    }

    /**
     * Create database tables
     *
     * @return void
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->licenses_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            license_id VARCHAR(64) NOT NULL UNIQUE,
            content_id VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            license_type VARCHAR(50) NOT NULL DEFAULT 'view',
            permissions LONGTEXT NULL,
            encryption_key_hash VARCHAR(64) NOT NULL,
            issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            max_plays INT UNSIGNED NULL,
            plays_used INT UNSIGNED DEFAULT 0,
            device_id VARCHAR(255) NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            metadata LONGTEXT NULL,
            INDEX content_id (content_id),
            INDEX user_id (user_id),
            INDEX status (status),
            INDEX expires_at (expires_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get the encryption key from WordPress options or generate a new one
     *
     * @return string Encryption key
     */
    private function get_encryption_key() {
        $key = get_option('aevov_drm_encryption_key');

        if (!$key) {
            $key = bin2hex(random_bytes(32));
            update_option('aevov_drm_encryption_key', $key, false);
        }

        return $key;
    }

    /**
     * Generate a content-specific key
     *
     * @param string $content_id Content identifier
     * @return string Content key
     */
    private function derive_content_key($content_id) {
        $master_key = $this->get_encryption_key();
        return hash_hmac('sha256', $content_id, $master_key, true);
    }

    /**
     * Encrypt content
     *
     * @param string $content Content to encrypt
     * @param string|null $content_id Optional content ID for key derivation
     * @return array Encrypted data with IV and tag
     */
    public function encrypt($content, $content_id = null) {
        if ($content_id) {
            $key = $this->derive_content_key($content_id);
        } else {
            $key = hex2bin($this->get_encryption_key());
        }

        $iv = random_bytes(12); // GCM recommended IV size
        $tag = '';

        $encrypted = openssl_encrypt(
            $content,
            $this->cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            return new \WP_Error('encryption_failed', 'Failed to encrypt content');
        }

        return [
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'algorithm' => $this->cipher
        ];
    }

    /**
     * Decrypt content
     *
     * @param array $encrypted_data Encrypted data array
     * @param string|null $content_id Optional content ID for key derivation
     * @return string|WP_Error Decrypted content
     */
    public function decrypt($encrypted_data, $content_id = null) {
        if ($content_id) {
            $key = $this->derive_content_key($content_id);
        } else {
            $key = hex2bin($this->get_encryption_key());
        }

        $data = base64_decode($encrypted_data['data']);
        $iv = base64_decode($encrypted_data['iv']);
        $tag = base64_decode($encrypted_data['tag']);

        $decrypted = openssl_decrypt(
            $data,
            $this->cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            return new \WP_Error('decryption_failed', 'Failed to decrypt content');
        }

        return $decrypted;
    }

    /**
     * Create a license for content
     *
     * @param array $params License parameters
     * @return string|WP_Error License ID
     */
    public function create_license($params) {
        $license_id = wp_generate_uuid4();
        $content_id = $params['content_id'] ?? '';

        if (empty($content_id)) {
            return new \WP_Error('invalid_content', 'Content ID is required');
        }

        $key_hash = hash('sha256', $this->derive_content_key($content_id));

        $result = $this->wpdb->insert(
            $this->licenses_table,
            [
                'license_id' => $license_id,
                'content_id' => $content_id,
                'user_id' => $params['user_id'] ?? get_current_user_id(),
                'license_type' => sanitize_key($params['type'] ?? 'view'),
                'permissions' => json_encode($params['permissions'] ?? ['view']),
                'encryption_key_hash' => $key_hash,
                'expires_at' => isset($params['expires_in'])
                    ? gmdate('Y-m-d H:i:s', time() + intval($params['expires_in']))
                    : null,
                'max_plays' => $params['max_plays'] ?? null,
                'device_id' => sanitize_text_field($params['device_id'] ?? ''),
                'status' => 'active',
                'metadata' => json_encode($params['metadata'] ?? [])
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            return new \WP_Error('license_creation_failed', 'Failed to create license');
        }

        do_action('aevov_drm_license_created', $license_id, $params);

        return $license_id;
    }

    /**
     * Validate a license
     *
     * @param string $license_id License ID
     * @param string $content_id Content ID
     * @param array $context Validation context (device_id, etc.)
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    public function validate_license($license_id, $content_id, $context = []) {
        $license = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->licenses_table}
             WHERE license_id = %s AND content_id = %s",
            $license_id,
            $content_id
        ));

        if (!$license) {
            return new \WP_Error('license_not_found', 'License not found');
        }

        // Check status
        if ($license->status !== 'active') {
            return new \WP_Error('license_inactive', 'License is not active');
        }

        // Check expiration
        if ($license->expires_at && strtotime($license->expires_at) < time()) {
            $this->revoke_license($license_id, 'expired');
            return new \WP_Error('license_expired', 'License has expired');
        }

        // Check play count
        if ($license->max_plays && $license->plays_used >= $license->max_plays) {
            return new \WP_Error('license_exhausted', 'License play limit reached');
        }

        // Check device if specified
        if (!empty($license->device_id) && !empty($context['device_id'])) {
            if ($license->device_id !== $context['device_id']) {
                return new \WP_Error('device_mismatch', 'License is bound to a different device');
            }
        }

        // Increment play count
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->licenses_table}
             SET plays_used = plays_used + 1
             WHERE license_id = %s",
            $license_id
        ));

        return true;
    }

    /**
     * Get license details
     *
     * @param string $license_id License ID
     * @return object|null License object
     */
    public function get_license($license_id) {
        $license = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->licenses_table} WHERE license_id = %s",
            $license_id
        ));

        if ($license) {
            $license->permissions = json_decode($license->permissions, true);
            $license->metadata = json_decode($license->metadata, true);
        }

        return $license;
    }

    /**
     * Get licenses for content
     *
     * @param string $content_id Content ID
     * @return array Licenses
     */
    public function get_content_licenses($content_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->licenses_table}
             WHERE content_id = %s AND status = 'active'",
            $content_id
        ));
    }

    /**
     * Get user licenses
     *
     * @param int $user_id User ID
     * @return array Licenses
     */
    public function get_user_licenses($user_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->licenses_table}
             WHERE user_id = %d AND status = 'active'",
            $user_id
        ));
    }

    /**
     * Revoke a license
     *
     * @param string $license_id License ID
     * @param string $reason Revocation reason
     * @return bool Success status
     */
    public function revoke_license($license_id, $reason = 'manual') {
        $result = $this->wpdb->update(
            $this->licenses_table,
            [
                'status' => 'revoked',
                'metadata' => json_encode(['revocation_reason' => $reason, 'revoked_at' => current_time('mysql')])
            ],
            ['license_id' => $license_id],
            ['%s', '%s'],
            ['%s']
        );

        if ($result !== false) {
            do_action('aevov_drm_license_revoked', $license_id, $reason);
        }

        return $result !== false;
    }

    /**
     * Clean up expired licenses
     *
     * @return int Number of licenses cleaned up
     */
    public function cleanup_expired_licenses() {
        return $this->wpdb->query(
            "UPDATE {$this->licenses_table}
             SET status = 'expired'
             WHERE expires_at IS NOT NULL
             AND expires_at < NOW()
             AND status = 'active'"
        );
    }

    /**
     * Generate a signed content URL
     *
     * @param string $content_url Original content URL
     * @param string $license_id License ID
     * @param int $expires_in Seconds until expiration
     * @return string Signed URL
     */
    public function generate_signed_url($content_url, $license_id, $expires_in = 3600) {
        $expires = time() + $expires_in;
        $signature_data = $content_url . '|' . $license_id . '|' . $expires;
        $signature = hash_hmac('sha256', $signature_data, $this->get_encryption_key());

        return add_query_arg([
            'license' => $license_id,
            'expires' => $expires,
            'signature' => $signature
        ], $content_url);
    }

    /**
     * Verify a signed URL
     *
     * @param string $url Signed URL
     * @return bool|WP_Error True if valid
     */
    public function verify_signed_url($url) {
        $parsed = wp_parse_url($url);
        parse_str($parsed['query'] ?? '', $params);

        if (empty($params['license']) || empty($params['expires']) || empty($params['signature'])) {
            return new \WP_Error('invalid_url', 'Missing signature parameters');
        }

        if (intval($params['expires']) < time()) {
            return new \WP_Error('url_expired', 'Signed URL has expired');
        }

        $base_url = strtok($url, '?');
        $signature_data = $base_url . '|' . $params['license'] . '|' . $params['expires'];
        $expected_signature = hash_hmac('sha256', $signature_data, $this->get_encryption_key());

        if (!hash_equals($expected_signature, $params['signature'])) {
            return new \WP_Error('invalid_signature', 'URL signature is invalid');
        }

        return true;
    }
}
