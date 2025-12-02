<?php
/**
 * includes/DB/class-aps-cache.php
 */

namespace APS\DB;

class APS_Cache {
    private $cache_prefix = 'aps_';
    private $default_expiration = 3600; // 1 hour
    private $memory_cache = [];
    
    public function get($key, $use_memory_cache = true) {
        $cache_key = $this->cache_prefix . $key;

        // Check memory cache first
        if ($use_memory_cache && isset($this->memory_cache[$cache_key])) {
            if ($this->memory_cache[$cache_key]['expires'] > time()) {
                return $this->memory_cache[$cache_key]['data'];
            }
            unset($this->memory_cache[$cache_key]);
        }

        // Check WordPress transient
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            if ($use_memory_cache) {
                $this->memory_cache[$cache_key] = [
                    'data' => $cached,
                    'expires' => time() + $this->default_expiration
                ];
            }
            return $cached;
        }

        return false;
    }

    public function set($key, $value, $expiration = null) {
        $cache_key = $this->cache_prefix . $key;
        $expiration = $expiration ?? $this->default_expiration;

        // Set memory cache
        $this->memory_cache[$cache_key] = [
            'data' => $value,
            'expires' => time() + $expiration
        ];

        // Set WordPress transient
        return set_transient($cache_key, $value, $expiration);
    }

    public function delete($key) {
        $cache_key = $this->cache_prefix . $key;
        
        // Clear memory cache
        unset($this->memory_cache[$cache_key]);
        
        // Clear WordPress transient
        return delete_transient($cache_key);
    }

    public function flush() {
        global $wpdb;
        
        // Clear memory cache
        $this->memory_cache = [];
        
        // Clear all transients with our prefix
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_' . $this->cache_prefix . '%'
            )
        );
    }

    public function get_pattern_data($pattern_hash) {
        return $this->get('pattern_' . $pattern_hash);
    }

    public function set_pattern_data($pattern_hash, $data) {
        return $this->set('pattern_' . $pattern_hash, $data);
    }

    public function get_comparison_result($comparison_id) {
        return $this->get('comparison_' . $comparison_id);
    }

    public function set_comparison_result($comparison_id, $result) {
        return $this->set('comparison_' . $comparison_id, $result);
    }
}