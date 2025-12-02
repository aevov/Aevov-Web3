<?php
/**
 * Scraper Manager
 *
 * Manages web scraping operations using Ultimate Web Scraper Toolkit,
 * integrating with privacy system and reward distribution.
 *
 * @package AevovVisionDepth\Scraper
 * @since 1.0.0
 */

namespace AevovVisionDepth\Scraper;

class Scraper_Manager {

    private $web_browser;
    private $rate_limiter;
    private $data_extractor;

    public function __construct() {
        $this->rate_limiter = new Rate_Limiter();
        $this->data_extractor = new Data_Extractor();
    }

    public function init() {
        // Initialize Ultimate Web Scraper if available
        if (file_exists(AVD_LIB . 'ultimate_web_scraper_toolkit/support/web_browser.php')) {
            require_once AVD_LIB . 'ultimate_web_scraper_toolkit/support/web_browser.php';
            $this->web_browser = new \WebBrowser();
        }

        add_action('wp_ajax_avd_scrape_url', [$this, 'ajax_scrape_url']);
    }

    public function scrape_url($url, $user_id, $options = []) {
        // Check rate limits
        if (!$this->rate_limiter->check_limit($user_id, 'scrape')) {
            return new \WP_Error('rate_limit', __('Rate limit exceeded', 'aevov-vision-depth'));
        }

        // Check consent
        $privacy = vision_depth()->privacy;
        if (!$privacy->user_has_consented($user_id)) {
            return new \WP_Error('no_consent', __('User consent required', 'aevov-vision-depth'));
        }

        if (!$this->web_browser) {
            return new \WP_Error('no_browser', __('Web browser not initialized', 'aevov-vision-depth'));
        }

        // Perform scrape
        $result = $this->web_browser->Process($url, get_option('avd_user_agent'));

        if (!$result['success']) {
            return new \WP_Error('scrape_failed', $result['error'] ?? __('Scrape failed', 'aevov-vision-depth'));
        }

        // Extract data
        $extracted = $this->data_extractor->extract($result);

        // Process through privacy system
        $processed = $privacy->process_scraped_data($extracted, $user_id);

        // Store in database
        $this->store_scraped_data($processed, $user_id);

        // Record rate limit action
        $this->rate_limiter->record_action($user_id, 'scrape');

        // Queue for pattern analysis
        do_action('avd_data_scraped', $processed, $user_id);

        // Award scrape reward
        $this->award_scrape_reward($user_id);

        return $processed;
    }

    private function store_scraped_data($processed, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'avd_scraped_data';

        $wpdb->insert($table, [
            'user_id' => $user_id,
            'url_hash' => $processed['url_hash'],
            'content_hash' => $processed['content_hash'],
            'encrypted_data' => $processed['encrypted_data'],
            'privacy_mode' => $processed['privacy_mode'],
            'data_type' => 'web_scrape',
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);

        return $wpdb->insert_id;
    }

    private function award_scrape_reward($user_id) {
        global $wpdb;
        $reward_amount = get_option('avd_reward_per_scrape', 0.001);

        $wpdb->insert($wpdb->prefix . 'avd_user_rewards', [
            'user_id' => $user_id,
            'reward_type' => 'scrape',
            'amount' => $reward_amount,
            'status' => 'pending',
        ], ['%d', '%s', '%f', '%s']);
    }

    public function process_job($job_id) {
        $job = get_post($job_id);
        if (!$job || $job->post_type !== 'avd_scrape_job') return;

        $url = get_post_meta($job_id, 'url', true);
        $user_id = get_post_meta($job_id, 'user_id', true);

        $result = $this->scrape_url($url, $user_id);

        if (is_wp_error($result)) {
            update_post_meta($job_id, 'status', 'failed');
            update_post_meta($job_id, 'error', $result->get_error_message());
        } else {
            update_post_meta($job_id, 'status', 'completed');
            wp_delete_post($job_id, true);
        }
    }

    public function render_settings_page() {
        include AVD_PATH . 'templates/scraper-settings.php';
    }

    public function rest_scrape_url($request) {
        $url = $request->get_param('url');
        $user_id = get_current_user_id();

        $result = $this->scrape_url($url, $user_id);

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['success' => false, 'error' => $result->get_error_message()], 400);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    public function ajax_scrape_url() {
        check_ajax_referer('avd_frontend_' . get_current_user_id(), 'nonce');

        $url = sanitize_url($_POST['url']);
        $user_id = get_current_user_id();

        $result = $this->scrape_url($url, $user_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }
}
