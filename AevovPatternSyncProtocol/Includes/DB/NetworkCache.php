<?php
/**
 * Network Cache management for APS Plugin
 * 
 * @package APS
 * @subpackage DB
 */

namespace APS\DB;

use APS\Core\Logger;

class NetworkCache {
    private $logger;
    private $cache_prefix = 'aps_network_';
    private $default_ttl = 3600; // 1 hour
    private $max_cache_size = 1000;
    
    public function __construct() {
        $this->logger = Logger::get_instance();
        $this->init_cache_cleanup();
    }
    
    private function init_cache_cleanup() {
        if (function_exists('add_action')) {
            add_action('aps_cache_cleanup', [$this, 'cleanup_expired_cache']);
        }
    }
    
    public function get($key, $default = null) {
        $cache_key = $this->get_cache_key($key);
        
        if (function_exists('get_transient')) {
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $this->logger->debug("Cache hit for key: {$key}");
                return $cached_data;
            }
        }
        
        $this->logger->debug("Cache miss for key: {$key}");
        return $default;
    }
    
    public function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->default_ttl;
        }
        
        $cache_key = $this->get_cache_key($key);
        
        if (function_exists('set_transient')) {
            $result = set_transient($cache_key, $value, $ttl);
            if ($result) {
                $this->logger->debug("Cache set for key: {$key}, TTL: {$ttl}");
                return true;
            }
        }
        
        $this->logger->warning("Failed to set cache for key: {$key}");
        return false;
    }
    
    public function delete($key) {
        $cache_key = $this->get_cache_key($key);
        
        if (function_exists('delete_transient')) {
            $result = delete_transient($cache_key);
            if ($result) {
                $this->logger->debug("Cache deleted for key: {$key}");
                return true;
            }
        }
        
        return false;
    }
    
    public function flush() {
        global $wpdb;
        
        if (isset($wpdb)) {
            $pattern = $this->cache_prefix . '%';
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            ));
            
            $this->logger->info("Network cache flushed");
            return true;
        }
        
        return false;
    }
    
    public function get_cache_stats() {
        global $wpdb;
        
        $stats = [
            'total_entries' => 0,
            'total_size' => 0,
            'expired_entries' => 0
        ];
        
        if (isset($wpdb)) {
            $pattern = $this->cache_prefix . '%';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            ));
            
            $stats['total_entries'] = count($results);
            
            foreach ($results as $row) {
                $stats['total_size'] += strlen($row->option_value);
                
                // Check if transient is expired
                $timeout_key = str_replace('_transient_', '_transient_timeout_', $row->option_name);
                $timeout = $wpdb->get_var($wpdb->prepare(
                    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                    $timeout_key
                ));
                
                if ($timeout && $timeout < time()) {
                    $stats['expired_entries']++;
                }
            }
        }
        
        return $stats;
    }
    
    public function cleanup_expired_cache() {
        global $wpdb;
        
        if (!isset($wpdb)) {
            return false;
        }
        
        $pattern = $this->cache_prefix . '%';
        $expired_count = 0;
        
        // Get all cache entries
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        ));
        
        foreach ($results as $row) {
            $timeout_key = str_replace('_transient_', '_transient_timeout_', $row->option_name);
            $timeout = $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                $timeout_key
            ));
            
            if ($timeout && $timeout < time()) {
                // Delete expired transient
                delete_transient(str_replace('_transient_', '', $row->option_name));
                $expired_count++;
            }
        }
        
        $this->logger->info("Cleaned up {$expired_count} expired cache entries");
        return $expired_count;
    }
    
    public function cache_network_data($site_id, $data_type, $data, $ttl = null) {
        $key = "site_{$site_id}_{$data_type}";
        return $this->set($key, $data, $ttl);
    }
    
    public function get_network_data($site_id, $data_type, $default = null) {
        $key = "site_{$site_id}_{$data_type}";
        return $this->get($key, $default);
    }
    
    public function invalidate_site_cache($site_id) {
        global $wpdb;
        
        if (!isset($wpdb)) {
            return false;
        }
        
        $pattern = $this->cache_prefix . "site_{$site_id}_%";
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        ));
        
        $this->logger->info("Invalidated cache for site {$site_id}, deleted {$deleted} entries");
        return $deleted;
    }
    
    public function cache_pattern_sync($pattern_id, $sync_data, $ttl = 1800) {
        $key = "pattern_sync_{$pattern_id}";
        return $this->set($key, $sync_data, $ttl);
    }
    
    public function get_pattern_sync($pattern_id) {
        $key = "pattern_sync_{$pattern_id}";
        return $this->get($key);
    }
    
    public function cache_bloom_response($request_hash, $response, $ttl = 3600) {
        $key = "bloom_response_{$request_hash}";
        return $this->set($key, $response, $ttl);
    }
    
    public function get_bloom_response($request_hash) {
        $key = "bloom_response_{$request_hash}";
        return $this->get($key);
    }
    
    private function get_cache_key($key) {
        return $this->cache_prefix . $key;
    }
    
    public function set_default_ttl($ttl) {
        $this->default_ttl = absint($ttl);
    }
    
    public function get_default_ttl() {
        return $this->default_ttl;
    }
    
    public function is_cache_full() {
        $stats = $this->get_cache_stats();
        return $stats['total_entries'] >= $this->max_cache_size;
    }
    
    public function optimize_cache() {
        // Clean expired entries first
        $expired_cleaned = $this->cleanup_expired_cache();
        
        // If still full, remove oldest entries
        if ($this->is_cache_full()) {
            global $wpdb;
            
            if (isset($wpdb)) {
                $pattern = $this->cache_prefix . '%';
                $oldest_entries = $wpdb->get_results($wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} 
                     WHERE option_name LIKE %s 
                     ORDER BY option_id ASC 
                     LIMIT %d",
                    $pattern,
                    intval($this->max_cache_size * 0.1) // Remove 10% of max size
                ));
                
                $removed_count = 0;
                foreach ($oldest_entries as $entry) {
                    delete_transient(str_replace('_transient_', '', $entry->option_name));
                    $removed_count++;
                }
                
                $this->logger->info("Cache optimization: removed {$removed_count} oldest entries");
            }
        }
        
        return true;
    }
}