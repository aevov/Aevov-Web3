<?php
/**
 * Privacy Manager
 *
 * Coordinates all privacy-related functionality including consent, encryption,
 * anonymization, and GDPR compliance.
 *
 * @package AevovVisionDepth\Privacy
 * @since 1.0.0
 */

namespace AevovVisionDepth\Privacy;

class Privacy_Manager {

    /**
     * Consent Manager
     *
     * @var Consent_Manager
     */
    private $consent;

    /**
     * Encryption Manager
     *
     * @var Encryption_Manager
     */
    private $encryption;

    /**
     * Anonymization Manager
     *
     * @var Anonymization_Manager
     */
    private $anonymization;

    /**
     * Constructor
     */
    public function __construct() {
        $this->consent = new Consent_Manager();
        $this->encryption = new Encryption_Manager();
        $this->anonymization = new Anonymization_Manager();
    }

    /**
     * Initialize privacy manager
     *
     * @return void
     */
    public function init() {
        $this->consent->init();
        $this->encryption->init();
        $this->anonymization->init();

        // AJAX handlers
        add_action('wp_ajax_avd_update_consent', [$this, 'ajax_update_consent']);
        add_action('wp_ajax_avd_export_data', [$this, 'ajax_export_data']);
        add_action('wp_ajax_avd_delete_data', [$this, 'ajax_delete_data']);
    }

    /**
     * Check if user has given consent
     *
     * @param int $user_id User ID (optional, defaults to current user)
     * @return bool
     */
    public function user_has_consented($user_id = null) {
        return $this->consent->has_consent($user_id);
    }

    /**
     * Get user's privacy mode
     *
     * @param int $user_id User ID (optional, defaults to current user)
     * @return string Privacy mode (maximum, balanced, minimal)
     */
    public function get_privacy_mode($user_id = null) {
        return $this->consent->get_privacy_mode($user_id);
    }

    /**
     * Process and secure scraped data before storage
     *
     * @param array $data Raw scraped data
     * @param int $user_id User ID
     * @return array Processed data ready for storage
     */
    public function process_scraped_data($data, $user_id) {
        // Get user's privacy mode
        $privacy_mode = $this->get_privacy_mode($user_id);

        // Anonymize data based on privacy mode
        $anonymized_data = $this->anonymization->anonymize_data($data, $privacy_mode);

        // Encrypt data
        $encrypted_data = $this->encryption->encrypt_data($anonymized_data, $user_id);

        return [
            'encrypted_data' => $encrypted_data,
            'content_hash' => hash('sha256', json_encode($anonymized_data)),
            'url_hash' => isset($data['url']) ? hash('sha256', $data['url']) : null,
            'privacy_mode' => $privacy_mode,
        ];
    }

    /**
     * Decrypt and retrieve scraped data
     *
     * @param string $encrypted_data Encrypted data
     * @param int $user_id User ID
     * @return array|null Decrypted data or null on failure
     */
    public function retrieve_scraped_data($encrypted_data, $user_id) {
        return $this->encryption->decrypt_data($encrypted_data, $user_id);
    }

    /**
     * Export all user data (GDPR compliance)
     *
     * @param int $user_id User ID
     * @return array User data export
     */
    public function export_user_data($user_id) {
        global $wpdb;

        $export = [
            'user_id' => $user_id,
            'export_date' => current_time('mysql'),
            'consent_info' => $this->consent->get_consent_info($user_id),
            'scraped_data' => [],
            'patterns' => [],
            'rewards' => [],
            'activity' => [],
        ];

        // Get scraped data
        $table_data = $wpdb->prefix . 'avd_scraped_data';
        $scraped_data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_data} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        foreach ($scraped_data as $row) {
            $decrypted = $this->encryption->decrypt_data($row['encrypted_data'], $user_id);
            $export['scraped_data'][] = [
                'id' => $row['id'],
                'data' => $decrypted,
                'privacy_mode' => $row['privacy_mode'],
                'created_at' => $row['created_at'],
            ];
        }

        // Get patterns
        $table_patterns = $wpdb->prefix . 'avd_behavioral_patterns';
        $export['patterns'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_patterns} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        // Get rewards
        $table_rewards = $wpdb->prefix . 'avd_user_rewards';
        $export['rewards'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_rewards} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        // Get activity
        $table_activity = $wpdb->prefix . 'avd_activity_log';
        $export['activity'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_activity} WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        return $export;
    }

    /**
     * Delete all user data (GDPR right to be forgotten)
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function delete_user_data($user_id) {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'avd_scraped_data',
            $wpdb->prefix . 'avd_user_consent',
            $wpdb->prefix . 'avd_scrape_jobs',
            $wpdb->prefix . 'avd_rate_limits',
            $wpdb->prefix . 'avd_behavioral_patterns',
            $wpdb->prefix . 'avd_user_rewards',
            $wpdb->prefix . 'avd_activity_log',
        ];

        foreach ($tables as $table) {
            $wpdb->delete($table, ['user_id' => $user_id], ['%d']);
        }

        // Log deletion (anonymously)
        $wpdb->insert(
            $wpdb->prefix . 'avd_activity_log',
            [
                'user_id' => 0, // Anonymous
                'action_type' => 'user_data_deleted',
                'action_description' => 'User exercised right to be forgotten',
                'metadata' => json_encode(['original_user_id' => $user_id]),
            ],
            ['%d', '%s', '%s', '%s']
        );

        return true;
    }

    /**
     * Render privacy settings page
     *
     * @return void
     */
    public function render_settings_page() {
        $user_id = get_current_user_id();
        $has_consent = $this->user_has_consented($user_id);
        $privacy_mode = $this->get_privacy_mode($user_id);
        $consent_info = $this->consent->get_consent_info($user_id);

        include AVD_PATH . 'templates/privacy-settings.php';
    }

    /**
     * REST API permission check
     *
     * @param \WP_REST_Request $request Request object
     * @return bool
     */
    public function rest_permission_check($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        // Check if user has given consent
        if (!$this->user_has_consented($user_id)) {
            return new \WP_Error(
                'no_consent',
                __('User consent required before data collection', 'aevov-vision-depth'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * REST API: Update user consent
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function rest_update_consent($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('User not logged in', 'aevov-vision-depth'),
            ], 401);
        }

        $consent_given = $request->get_param('consent');
        $privacy_mode = $request->get_param('privacy_mode') ?: 'balanced';

        $result = $this->consent->update_consent($user_id, $consent_given, $privacy_mode);

        return new \WP_REST_Response([
            'success' => $result,
            'message' => $result
                ? __('Consent updated successfully', 'aevov-vision-depth')
                : __('Failed to update consent', 'aevov-vision-depth'),
        ], $result ? 200 : 500);
    }

    /**
     * REST API: Get user data export
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function rest_get_user_data($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('User not logged in', 'aevov-vision-depth'),
            ], 401);
        }

        $data = $this->export_user_data($user_id);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * REST API: Delete user data
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function rest_delete_user_data($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('User not logged in', 'aevov-vision-depth'),
            ], 401);
        }

        // Require confirmation
        $confirm = $request->get_param('confirm');
        if ($confirm !== 'DELETE_MY_DATA') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Confirmation required', 'aevov-vision-depth'),
            ], 400);
        }

        $result = $this->delete_user_data($user_id);

        return new \WP_REST_Response([
            'success' => $result,
            'message' => $result
                ? __('All data deleted successfully', 'aevov-vision-depth')
                : __('Failed to delete data', 'aevov-vision-depth'),
        ], $result ? 200 : 500);
    }

    /**
     * AJAX: Update consent
     *
     * @return void
     */
    public function ajax_update_consent() {
        check_ajax_referer('avd_admin', 'nonce');

        $user_id = get_current_user_id();
        $consent = isset($_POST['consent']) && $_POST['consent'] === 'true';
        $privacy_mode = sanitize_text_field($_POST['privacy_mode'] ?? 'balanced');

        $result = $this->consent->update_consent($user_id, $consent, $privacy_mode);

        wp_send_json([
            'success' => $result,
            'message' => $result ? __('Consent updated', 'aevov-vision-depth') : __('Update failed', 'aevov-vision-depth'),
        ]);
    }

    /**
     * AJAX: Export user data
     *
     * @return void
     */
    public function ajax_export_data() {
        check_ajax_referer('avd_admin', 'nonce');

        $user_id = get_current_user_id();
        $data = $this->export_user_data($user_id);

        wp_send_json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * AJAX: Delete user data
     *
     * @return void
     */
    public function ajax_delete_data() {
        check_ajax_referer('avd_admin', 'nonce');

        $user_id = get_current_user_id();
        $confirm = sanitize_text_field($_POST['confirm'] ?? '');

        if ($confirm !== 'DELETE_MY_DATA') {
            wp_send_json([
                'success' => false,
                'message' => __('Confirmation required', 'aevov-vision-depth'),
            ]);
            return;
        }

        $result = $this->delete_user_data($user_id);

        wp_send_json([
            'success' => $result,
            'message' => $result ? __('Data deleted', 'aevov-vision-depth') : __('Deletion failed', 'aevov-vision-depth'),
        ]);
    }
}
