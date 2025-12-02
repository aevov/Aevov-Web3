<?php
/**
 * API Rate Limiter
 * Controls API request frequency to prevent abuse
 * 
 * @package APS
 * @subpackage API
 */

namespace APS\API;

class RateLimiter {
    private $limit;
    private $window;
    private $storage_prefix = 'aps_rate_limit_';
    
    public function __construct($limit = 60, $window = 3600) {
        $this->limit = $limit; // Requests per window
        $this->window = $window; // Window in seconds
    }
    
    /**
     * Check if a request is within rate limits
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function check_limit($request) {
        // Get client identifier (IP + API key if available)
        $client_id = $this->get_client_identifier($request);
        
        // Get current timestamp
        $current_time = time();
        
        // Get stored request data
        $stored_data = $this->get_stored_data($client_id);
        
        // If no stored data or window has expired, reset
        if (!$stored_data || $stored_data['window_start'] + $this->window < $current_time) {
            $this->reset_window($client_id, $current_time);
            return true;
        }
        
        // Check if limit exceeded
        if ($stored_data['request_count'] >= $this->limit) {
            return false;
        }
        
        // Increment request count
        $this->increment_request_count($client_id);
        return true;
    }
    
    /**
     * Get rate limit headers for response
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    public function get_rate_limit_headers($request) {
        $client_id = $this->get_client_identifier($request);
        $stored_data = $this->get_stored_data($client_id);
        
        $remaining = $this->limit;
        $reset_time = time() + $this->window;
        
        if ($stored_data) {
            $remaining = max(0, $this->limit - $stored_data['request_count']);
            $reset_time = $stored_data['window_start'] + $this->window;
        }
        
        return [
            'X-RateLimit-Limit' => $this->limit,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $reset_time
        ];
    }
    
    /**
     * Get client identifier for rate limiting
     *
     * @param \WP_REST_Request $request
     * @return string
     */
    private function get_client_identifier($request) {
        // Get IP address
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Get API key if available
        $api_key = '';
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $api_key = $matches[1];
        }
        
        // Combine IP and API key for unique identifier
        return md5($ip . $api_key);
    }
    
    /**
     * Get stored request data for client
     *
     * @param string $client_id
     * @return array|null
     */
    private function get_stored_data($client_id) {
        $key = $this->storage_prefix . $client_id;
        $data = get_option($key, null);
        
        if ($data) {
            return json_decode($data, true);
        }
        
        return null;
    }
    
    /**
     * Reset rate limit window for client
     *
     * @param string $client_id
     * @param int $timestamp
     */
    private function reset_window($client_id, $timestamp) {
        $key = $this->storage_prefix . $client_id;
        $data = [
            'window_start' => $timestamp,
            'request_count' => 1
        ];
        
        update_option($key, json_encode($data), false);
    }
    
    /**
     * Increment request count for client
     *
     * @param string $client_id
     */
    private function increment_request_count($client_id) {
        $key = $this->storage_prefix . $client_id;
        $stored_data = $this->get_stored_data($client_id);
        
        if ($stored_data) {
            $stored_data['request_count']++;
            update_option($key, json_encode($stored_data), false);
        }
    }
    
    /**
     * Get current rate limit status for client
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    public function get_status($request) {
        $client_id = $this->get_client_identifier($request);
        $stored_data = $this->get_stored_data($client_id);
        
        $status = [
            'limit' => $this->limit,
            'window' => $this->window,
            'remaining' => $this->limit,
            'reset' => time() + $this->window
        ];
        
        if ($stored_data) {
            $status['remaining'] = max(0, $this->limit - $stored_data['request_count']);
            $status['reset'] = $stored_data['window_start'] + $this->window;
            $status['request_count'] = $stored_data['request_count'];
        }
        
        return $status;
    }
    
    /**
     * Clear rate limit data for client
     *
     * @param \WP_REST_Request $request
     */
    public function clear_client_data($request) {
        $client_id = $this->get_client_identifier($request);
        $key = $this->storage_prefix . $client_id;
        delete_option($key);
    }
    
    /**
     * Clear all rate limit data
     */
    public function clear_all_data() {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $this->storage_prefix . '%'
            )
        );
    }
    
    /**
     * Set custom rate limit for specific client
     *
     * @param string $client_id
     * @param int $limit
     * @param int $window
     */
    public function set_custom_limit($client_id, $limit, $window) {
        $key = $this->storage_prefix . 'custom_' . $client_id;
        $data = [
            'limit' => $limit,
            'window' => $window
        ];
        
        update_option($key, json_encode($data), false);
    }
    
    /**
     * Get custom rate limit for specific client
     *
     * @param string $client_id
     * @return array|null
     */
    public function get_custom_limit($client_id) {
        $key = $this->storage_prefix . 'custom_' . $client_id;
        $data = get_option($key, null);
        
        if ($data) {
            return json_decode($data, true);
        }
        
        return null;
    }
}