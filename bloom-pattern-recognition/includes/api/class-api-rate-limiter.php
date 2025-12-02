<?php
/**
 * Manages API rate limiting
 */
class BLOOM_API_Rate_Limiter {
    private $limit_window = 3600; // 1 hour
    private $max_requests = 1000;
    private $cache_group = 'bloom_rate_limits';
    
    public function check_limit($request) {
        $client_id = $this->get_client_id($request);
        $current_usage = $this->get_usage($client_id);
        
        if ($current_usage >= $this->max_requests) {
            return false;
        }

        $this->increment_usage($client_id);
        return true;
    }

    private function get_usage($client_id) {
        $key = $this->get_cache_key($client_id);
        return (int) wp_cache_get($key, $this->cache_group);
    }

    private function increment_usage($client_id) {
        $key = $this->get_cache_key($client_id);
        $current = $this->get_usage($client_id);
        
        wp_cache_set(
            $key,
            $current + 1,
            $this->cache_group,
            $this->limit_window
        );
    }

    private function get_cache_key($client_id) {
        $window = floor(time() / $this->limit_window);
        return "rate_limit_{$client_id}_{$window}";
    }

    private function get_client_id($request) {
        return $request->get_header('X-Client-ID') ?? 
               $request->get_ip_address();
    }
}