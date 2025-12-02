<?php
/**
 * Manages plugin caching operations
 */
class BLOOM_Cache_Manager {
    private $cache_group = 'bloom_pattern_system';
    private $default_expiration = 3600; // 1 hour
    
    public function get_cached($key) {
        return wp_cache_get($key, $this->cache_group);
    }

    public function set_cached($key, $data, $expiration = null) {
        $expiration = $expiration ?? $this->default_expiration;
        return wp_cache_set($key, $data, $this->cache_group, $expiration);
    }

    public function delete_cached($key) {
        return wp_cache_delete($key, $this->cache_group);
    }

    public function flush_cache_group() {
        global $wp_object_cache;
        
        if (is_object($wp_object_cache) && method_exists($wp_object_cache, 'delete_group')) {
            return $wp_object_cache->delete_group($this->cache_group);
        }
        
        return false;
    }

    public function cache_patterns($patterns) {
        foreach ($patterns as $pattern) {
            $key = 'pattern_' . $pattern['pattern_hash'];
            $this->set_cached($key, $pattern);
        }
    }

    public function get_cached_pattern($pattern_hash) {
        return $this->get_cached('pattern_' . $pattern_hash);
    }

    public function invalidate_pattern_cache($pattern_hash) {
        return $this->delete_cached('pattern_' . $pattern_hash);
    }

    public function warm_cache() {
        global $wpdb;
        
        $patterns = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}bloom_patterns 
             WHERE status = 'active'
             ORDER BY used_count DESC 
             LIMIT 1000",
            ARRAY_A
        );

        if ($patterns) {
            $this->cache_patterns($patterns);
        }
    }
}