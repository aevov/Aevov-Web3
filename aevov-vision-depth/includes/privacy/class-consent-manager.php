<?php
/**
 * Consent Manager
 *
 * Manages user consent for data collection, privacy mode preferences,
 * and consent lifecycle tracking for GDPR compliance.
 *
 * @package AevovVisionDepth\Privacy
 * @since 1.0.0
 */

namespace AevovVisionDepth\Privacy;

class Consent_Manager {

    /**
     * WordPress database object
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Consent table name
     *
     * @var string
     */
    private $table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'avd_user_consent';
    }

    /**
     * Initialize consent manager
     *
     * @return void
     */
    public function init() {
        // Add hooks
        add_action('wp_footer', [$this, 'render_consent_banner'], 100);
        add_action('wp_ajax_avd_give_consent', [$this, 'ajax_give_consent']);
        add_action('wp_ajax_avd_revoke_consent', [$this, 'ajax_revoke_consent']);
        add_action('wp_ajax_avd_update_privacy_mode', [$this, 'ajax_update_privacy_mode']);
    }

    /**
     * Check if user has given consent
     *
     * @param int $user_id User ID (null for current user)
     * @return bool
     */
    public function has_consent($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $consent = $this->get_consent_record($user_id);

        return $consent && $consent->consent_given == 1;
    }

    /**
     * Get user's privacy mode
     *
     * @param int $user_id User ID (null for current user)
     * @return string Privacy mode (maximum|balanced|minimal)
     */
    public function get_privacy_mode($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return get_option('avd_default_privacy_mode', 'balanced');
        }

        $consent = $this->get_consent_record($user_id);

        return $consent ? $consent->privacy_mode : get_option('avd_default_privacy_mode', 'balanced');
    }

    /**
     * Update user consent
     *
     * @param int $user_id User ID
     * @param bool $consent_given Consent status
     * @param string $privacy_mode Privacy mode
     * @return bool Success status
     */
    public function update_consent($user_id, $consent_given, $privacy_mode = 'balanced') {
        // Validate privacy mode
        if (!in_array($privacy_mode, ['maximum', 'balanced', 'minimal'])) {
            $privacy_mode = 'balanced';
        }

        // Get existing consent record
        $existing = $this->get_consent_record($user_id);

        $data = [
            'user_id' => $user_id,
            'consent_given' => $consent_given ? 1 : 0,
            'privacy_mode' => $privacy_mode,
            'consent_date' => $consent_given ? current_time('mysql') : null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ];

        if ($existing) {
            // Update existing record
            $result = $this->wpdb->update(
                $this->table,
                $data,
                ['user_id' => $user_id],
                ['%d', '%d', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Insert new record
            $result = $this->wpdb->insert(
                $this->table,
                $data,
                ['%d', '%d', '%s', '%s', '%s', '%s']
            );
        }

        // Log consent change
        $this->log_consent_change($user_id, $consent_given, $privacy_mode);

        // Trigger action hook
        do_action('avd_consent_updated', $user_id, $consent_given, $privacy_mode);

        // If consent was revoked, delete user data
        if (!$consent_given) {
            $this->handle_consent_revocation($user_id);
        }

        return $result !== false;
    }

    /**
     * Get consent information for a user
     *
     * @param int $user_id User ID
     * @return array Consent information
     */
    public function get_consent_info($user_id) {
        $consent = $this->get_consent_record($user_id);

        if (!$consent) {
            return [
                'has_consent' => false,
                'privacy_mode' => get_option('avd_default_privacy_mode', 'balanced'),
                'consent_date' => null,
                'last_updated' => null,
            ];
        }

        return [
            'has_consent' => $consent->consent_given == 1,
            'privacy_mode' => $consent->privacy_mode,
            'consent_date' => $consent->consent_date,
            'last_updated' => $consent->last_updated,
            'ip_address' => $consent->ip_address,
        ];
    }

    /**
     * Get privacy mode configuration
     *
     * @param string $mode Privacy mode name
     * @return array Configuration details
     */
    public function get_privacy_mode_config($mode) {
        $configs = [
            'maximum' => [
                'name' => __('Maximum Privacy', 'aevov-vision-depth'),
                'description' => __('No URL collection, minimal tracking, maximum anonymization', 'aevov-vision-depth'),
                'retention_days' => 7,
                'collect_urls' => false,
                'collect_forms' => false,
                'collect_content' => 'titles_only',
                'anonymization_level' => 'maximum',
                'icon' => 'dashicons-lock',
                'color' => '#2196F3',
            ],
            'balanced' => [
                'name' => __('Balanced Privacy', 'aevov-vision-depth'),
                'description' => __('Sanitized URLs, moderate tracking, standard anonymization', 'aevov-vision-depth'),
                'retention_days' => 7,
                'collect_urls' => 'sanitized',
                'collect_forms' => 'field_names_only',
                'collect_content' => 'main_content',
                'anonymization_level' => 'standard',
                'icon' => 'dashicons-shield',
                'color' => '#4CAF50',
            ],
            'minimal' => [
                'name' => __('Minimal Privacy', 'aevov-vision-depth'),
                'description' => __('Full feature extraction, comprehensive tracking, basic anonymization', 'aevov-vision-depth'),
                'retention_days' => 30,
                'collect_urls' => true,
                'collect_forms' => 'encrypted',
                'collect_content' => 'full',
                'anonymization_level' => 'basic',
                'icon' => 'dashicons-visibility',
                'color' => '#FF9800',
            ],
        ];

        return $configs[$mode] ?? $configs['balanced'];
    }

    /**
     * Render consent banner for users without consent
     *
     * @return void
     */
    public function render_consent_banner() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Check if user has already given or denied consent
        $consent = $this->get_consent_record($user_id);
        if ($consent) {
            return; // User has already made a decision
        }

        // Render consent banner
        include AVD_PATH . 'templates/consent-banner.php';
    }

    /**
     * Get consent record from database
     *
     * @param int $user_id User ID
     * @return object|null Consent record
     */
    private function get_consent_record($user_id) {
        static $cache = [];

        if (isset($cache[$user_id])) {
            return $cache[$user_id];
        }

        $consent = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d",
            $user_id
        ));

        $cache[$user_id] = $consent;

        return $consent;
    }

    /**
     * Log consent change to activity log
     *
     * @param int $user_id User ID
     * @param bool $consent_given Consent status
     * @param string $privacy_mode Privacy mode
     * @return void
     */
    private function log_consent_change($user_id, $consent_given, $privacy_mode) {
        $activity_table = $this->wpdb->prefix . 'avd_activity_log';

        $this->wpdb->insert(
            $activity_table,
            [
                'user_id' => $user_id,
                'action_type' => $consent_given ? 'consent_given' : 'consent_revoked',
                'action_description' => sprintf(
                    __('User %s consent with %s privacy mode', 'aevov-vision-depth'),
                    $consent_given ? 'gave' : 'revoked',
                    $privacy_mode
                ),
                'metadata' => json_encode([
                    'privacy_mode' => $privacy_mode,
                    'consent_given' => $consent_given,
                ]),
                'ip_address' => $this->get_client_ip(),
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Handle consent revocation - delete user data
     *
     * @param int $user_id User ID
     * @return void
     */
    private function handle_consent_revocation($user_id) {
        // Delete all user data from Vision Depth tables
        $tables = [
            $this->wpdb->prefix . 'avd_scraped_data',
            $this->wpdb->prefix . 'avd_scrape_jobs',
            $this->wpdb->prefix . 'avd_rate_limits',
            $this->wpdb->prefix . 'avd_behavioral_patterns',
            $this->wpdb->prefix . 'avd_user_rewards',
        ];

        foreach ($tables as $table) {
            $this->wpdb->delete($table, ['user_id' => $user_id], ['%d']);
        }

        // Log deletion
        $activity_table = $this->wpdb->prefix . 'avd_activity_log';
        $this->wpdb->insert(
            $activity_table,
            [
                'user_id' => $user_id,
                'action_type' => 'data_deleted_on_revocation',
                'action_description' => __('All user data deleted due to consent revocation', 'aevov-vision-depth'),
                'ip_address' => $this->get_client_ip(),
            ],
            ['%d', '%s', '%s', '%s']
        );
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /**
     * AJAX: Give consent
     *
     * @return void
     */
    public function ajax_give_consent() {
        check_ajax_referer('avd_consent', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => __('User not logged in', 'aevov-vision-depth')]);
        }

        $privacy_mode = sanitize_text_field($_POST['privacy_mode'] ?? 'balanced');

        $result = $this->update_consent($user_id, true, $privacy_mode);

        if ($result) {
            wp_send_json_success([
                'message' => __('Consent saved successfully', 'aevov-vision-depth'),
                'privacy_mode' => $privacy_mode,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save consent', 'aevov-vision-depth')]);
        }
    }

    /**
     * AJAX: Revoke consent
     *
     * @return void
     */
    public function ajax_revoke_consent() {
        check_ajax_referer('avd_consent', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => __('User not logged in', 'aevov-vision-depth')]);
        }

        $result = $this->update_consent($user_id, false, 'balanced');

        if ($result) {
            wp_send_json_success([
                'message' => __('Consent revoked and data deleted', 'aevov-vision-depth'),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to revoke consent', 'aevov-vision-depth')]);
        }
    }

    /**
     * AJAX: Update privacy mode
     *
     * @return void
     */
    public function ajax_update_privacy_mode() {
        check_ajax_referer('avd_consent', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => __('User not logged in', 'aevov-vision-depth')]);
        }

        $privacy_mode = sanitize_text_field($_POST['privacy_mode'] ?? 'balanced');

        // Only update if user has already given consent
        if (!$this->has_consent($user_id)) {
            wp_send_json_error(['message' => __('Consent required before changing privacy mode', 'aevov-vision-depth')]);
        }

        $result = $this->update_consent($user_id, true, $privacy_mode);

        if ($result) {
            wp_send_json_success([
                'message' => __('Privacy mode updated', 'aevov-vision-depth'),
                'privacy_mode' => $privacy_mode,
                'config' => $this->get_privacy_mode_config($privacy_mode),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to update privacy mode', 'aevov-vision-depth')]);
        }
    }

    /**
     * Get consent statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        $total_users = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$this->table}"
        );

        $consented_users = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$this->table} WHERE consent_given = 1"
        );

        $by_privacy_mode = $this->wpdb->get_results(
            "SELECT privacy_mode, COUNT(*) as count
             FROM {$this->table}
             WHERE consent_given = 1
             GROUP BY privacy_mode",
            ARRAY_A
        );

        $recent_consents = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE consent_given = 1 AND consent_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        return [
            'total_users' => intval($total_users),
            'consented_users' => intval($consented_users),
            'consent_rate' => $total_users > 0 ? round(($consented_users / $total_users) * 100, 2) : 0,
            'by_privacy_mode' => $by_privacy_mode,
            'recent_consents_7d' => intval($recent_consents),
        ];
    }
}
