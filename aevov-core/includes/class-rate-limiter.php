<?php
/**
 * Rate Limiter Middleware for Aevov Ecosystem
 *
 * Provides centralized rate limiting for all REST API endpoints across
 * all 34 Aevov plugins. Supports multiple limiting strategies and Redis
 * for distributed rate limiting.
 *
 * Features:
 * - IP-based rate limiting
 * - User-based rate limiting
 * - Endpoint-specific limits
 * - Redis-backed distributed limiting
 * - Configurable limits per plugin/endpoint
 * - Rate limit headers (X-RateLimit-*)
 * - Multiple time windows (minute, hour, day)
 * - Bypass for trusted IPs/users
 *
 * @package AevovCore
 * @since 1.0.0
 */

namespace Aevov\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RateLimiter
 */
class RateLimiter {

    /**
     * Limiting strategies
     */
    const STRATEGY_IP = 'ip';
    const STRATEGY_USER = 'user';
    const STRATEGY_ENDPOINT = 'endpoint';
    const STRATEGY_COMBINED = 'combined';

    /**
     * Time windows (in seconds)
     */
    const WINDOW_MINUTE = 60;
    const WINDOW_HOUR = 3600;
    const WINDOW_DAY = 86400;

    /**
     * Default rate limits
     */
    const DEFAULT_LIMITS = [
        'minute' => 60,      // 60 requests per minute
        'hour' => 1000,      // 1000 requests per hour
        'day' => 10000,      // 10000 requests per day
    ];

    /**
     * Redis client instance
     *
     * @var \Redis|null
     */
    private $redis = null;

    /**
     * Whether to use Redis for storage
     *
     * @var bool
     */
    private $use_redis = false;

    /**
     * Fallback storage (when Redis unavailable)
     *
     * @var array
     */
    private static $fallback_storage = [];

    /**
     * Configuration
     *
     * @var array
     */
    private $config = [];

    /**
     * Trusted IPs (bypass rate limiting)
     *
     * @var array
     */
    private $trusted_ips = [];

    /**
     * Trusted user IDs (bypass rate limiting)
     *
     * @var array
     */
    private $trusted_users = [];

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct($config = []) {
        $this->config = wp_parse_args($config, [
            'strategy' => self::STRATEGY_COMBINED,
            'use_redis' => true,
            'redis_host' => getenv('WP_REDIS_HOST') ?: 'redis',
            'redis_port' => getenv('WP_REDIS_PORT') ?: 6379,
            'redis_database' => 1, // Use database 1 for rate limiting
            'default_limits' => self::DEFAULT_LIMITS,
            'enable_headers' => true,
            'log_violations' => true,
        ]);

        // Initialize Redis if enabled
        if ($this->config['use_redis']) {
            $this->init_redis();
        }

        // Load trusted IPs and users
        $this->trusted_ips = apply_filters('aevov_rate_limit_trusted_ips', [
            '127.0.0.1',
            '::1',
        ]);

        $this->trusted_users = apply_filters('aevov_rate_limit_trusted_users', []);

        // Register REST API filter
        add_filter('rest_pre_dispatch', [$this, 'check_rate_limit'], 10, 3);
    }

    /**
     * Initialize Redis connection
     *
     * @return bool Success status
     */
    private function init_redis() {
        if (!class_exists('Redis')) {
            error_log('[Aevov Rate Limiter] Redis extension not available, using fallback storage');
            $this->use_redis = false;
            return false;
        }

        try {
            $this->redis = new \Redis();

            $connected = $this->redis->connect(
                $this->config['redis_host'],
                (int) $this->config['redis_port'],
                2.0 // 2 second timeout
            );

            if (!$connected) {
                throw new \Exception('Failed to connect to Redis');
            }

            // Select database
            $this->redis->select((int) $this->config['redis_database']);

            // Test connection
            $this->redis->ping();

            $this->use_redis = true;

            error_log('[Aevov Rate Limiter] Redis connection established');

            return true;

        } catch (\Exception $e) {
            error_log('[Aevov Rate Limiter] Redis initialization failed: ' . $e->getMessage());
            $this->use_redis = false;
            $this->redis = null;
            return false;
        }
    }

    /**
     * Check rate limit for current request
     *
     * WordPress REST API filter callback
     *
     * @param mixed $result Response to replace the requested version with
     * @param \WP_REST_Server $server Server instance
     * @param \WP_REST_Request $request Request used to generate the response
     * @return mixed|\WP_Error
     */
    public function check_rate_limit($result, $server, $request) {
        // Only check for Aevov endpoints
        $route = $request->get_route();

        if (strpos($route, '/aevov/') !== 0) {
            return $result;
        }

        // Get identifier for rate limiting
        $identifier = $this->get_identifier($request);

        // Check if identifier is trusted
        if ($this->is_trusted($identifier)) {
            return $result;
        }

        // Get limits for this endpoint
        $limits = $this->get_limits_for_endpoint($route);

        // Check each time window
        foreach ($limits as $window_name => $limit) {
            $window = $this->get_window_seconds($window_name);

            $check_result = $this->check_limit(
                $identifier,
                $route,
                $limit,
                $window,
                $window_name
            );

            if (is_wp_error($check_result)) {
                // Rate limit exceeded
                if ($this->config['log_violations']) {
                    $this->log_violation($identifier, $route, $window_name, $limit);
                }

                // Fire action for monitoring
                do_action('aevov_rate_limit_exceeded', $identifier, $route, $window_name, $limit);

                return $check_result;
            }
        }

        // Increment counters for all windows
        foreach ($limits as $window_name => $limit) {
            $window = $this->get_window_seconds($window_name);
            $this->increment_counter($identifier, $route, $window, $window_name);
        }

        // Add rate limit headers
        if ($this->config['enable_headers']) {
            add_filter('rest_post_dispatch', function($response) use ($identifier, $route, $limits) {
                return $this->add_rate_limit_headers($response, $identifier, $route, $limits);
            });
        }

        return $result;
    }

    /**
     * Get identifier for rate limiting
     *
     * Uses strategy to determine how to identify the requester
     *
     * @param \WP_REST_Request $request Request object
     * @return string Unique identifier
     */
    private function get_identifier($request) {
        $strategy = $this->config['strategy'];

        switch ($strategy) {
            case self::STRATEGY_IP:
                return 'ip:' . $this->get_client_ip();

            case self::STRATEGY_USER:
                $user_id = get_current_user_id();
                return $user_id ? 'user:' . $user_id : 'ip:' . $this->get_client_ip();

            case self::STRATEGY_ENDPOINT:
                return 'endpoint:' . $request->get_route();

            case self::STRATEGY_COMBINED:
            default:
                $user_id = get_current_user_id();
                $ip = $this->get_client_ip();

                if ($user_id) {
                    return 'user:' . $user_id . ':ip:' . $ip;
                }

                return 'ip:' . $ip;
        }
    }

    /**
     * Get client IP address
     *
     * Handles proxies and load balancers
     *
     * @return string IP address
     */
    private function get_client_ip() {
        // Check for proxy headers in order of preference
        $headers = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // General proxy
            'HTTP_CLIENT_IP',            // Client IP
            'REMOTE_ADDR',               // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (X-Forwarded-For can have multiple)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Check if identifier is trusted
     *
     * @param string $identifier Identifier to check
     * @return bool True if trusted
     */
    private function is_trusted($identifier) {
        // Check trusted IPs
        if (strpos($identifier, 'ip:') === 0) {
            $ip = substr($identifier, 3);

            // Extract IP from combined identifier
            if (strpos($ip, ':') !== false) {
                $parts = explode(':', $ip);
                $ip = end($parts);
            }

            if (in_array($ip, $this->trusted_ips)) {
                return true;
            }
        }

        // Check trusted users
        if (strpos($identifier, 'user:') !== false) {
            preg_match('/user:(\d+)/', $identifier, $matches);
            if (!empty($matches[1]) && in_array((int)$matches[1], $this->trusted_users)) {
                return true;
            }
        }

        return apply_filters('aevov_rate_limit_is_trusted', false, $identifier);
    }

    /**
     * Get rate limits for specific endpoint
     *
     * @param string $endpoint Endpoint route
     * @return array Limits by window
     */
    private function get_limits_for_endpoint($endpoint) {
        // Default limits
        $limits = $this->config['default_limits'];

        // Check for endpoint-specific limits
        $custom_limits = apply_filters('aevov_rate_limit_endpoint_limits', [], $endpoint);

        if (!empty($custom_limits)) {
            $limits = wp_parse_args($custom_limits, $limits);
        }

        // Special limits for specific endpoint patterns
        if (strpos($endpoint, '/aevov/v1/language/chat') !== false) {
            // Language engine chat has lower limits (LLM API costs)
            $limits = [
                'minute' => 10,
                'hour' => 100,
                'day' => 500,
            ];
        } elseif (strpos($endpoint, '/aevov/v1/image/generate') !== false) {
            // Image generation has very low limits
            $limits = [
                'minute' => 5,
                'hour' => 50,
                'day' => 200,
            ];
        } elseif (strpos($endpoint, '/aevov/v1/music/generate') !== false) {
            // Music generation has low limits
            $limits = [
                'minute' => 3,
                'hour' => 30,
                'day' => 100,
            ];
        }

        return apply_filters('aevov_rate_limit_get_limits', $limits, $endpoint);
    }

    /**
     * Get window duration in seconds
     *
     * @param string $window_name Window name (minute, hour, day)
     * @return int Seconds
     */
    private function get_window_seconds($window_name) {
        $windows = [
            'minute' => self::WINDOW_MINUTE,
            'hour' => self::WINDOW_HOUR,
            'day' => self::WINDOW_DAY,
        ];

        return $windows[$window_name] ?? self::WINDOW_MINUTE;
    }

    /**
     * Check if limit is exceeded
     *
     * @param string $identifier Requester identifier
     * @param string $endpoint Endpoint route
     * @param int $limit Maximum requests allowed
     * @param int $window Window duration in seconds
     * @param string $window_name Window name for error message
     * @return bool|\WP_Error True if OK, WP_Error if exceeded
     */
    private function check_limit($identifier, $endpoint, $limit, $window, $window_name) {
        $key = $this->get_storage_key($identifier, $endpoint, $window_name);

        $current = $this->get_counter($key);

        if ($current >= $limit) {
            $ttl = $this->get_ttl($key);

            return new \WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    'Rate limit exceeded. Maximum %d requests per %s allowed. Try again in %d seconds.',
                    $limit,
                    $window_name,
                    $ttl
                ),
                [
                    'status' => 429,
                    'limit' => $limit,
                    'window' => $window_name,
                    'retry_after' => $ttl,
                    'current' => $current,
                ]
            );
        }

        return true;
    }

    /**
     * Increment request counter
     *
     * @param string $identifier Requester identifier
     * @param string $endpoint Endpoint route
     * @param int $window Window duration in seconds
     * @param string $window_name Window name
     * @return int New counter value
     */
    private function increment_counter($identifier, $endpoint, $window, $window_name) {
        $key = $this->get_storage_key($identifier, $endpoint, $window_name);

        if ($this->use_redis && $this->redis) {
            try {
                // Increment counter
                $value = $this->redis->incr($key);

                // Set expiry on first increment
                if ($value === 1) {
                    $this->redis->expire($key, $window);
                }

                return $value;

            } catch (\Exception $e) {
                error_log('[Aevov Rate Limiter] Redis increment failed: ' . $e->getMessage());
                // Fall through to fallback storage
            }
        }

        // Fallback to WordPress transients/options
        return $this->increment_counter_fallback($key, $window);
    }

    /**
     * Get current counter value
     *
     * @param string $key Storage key
     * @return int Current count
     */
    private function get_counter($key) {
        if ($this->use_redis && $this->redis) {
            try {
                $value = $this->redis->get($key);
                return $value !== false ? (int) $value : 0;
            } catch (\Exception $e) {
                error_log('[Aevov Rate Limiter] Redis get failed: ' . $e->getMessage());
                // Fall through to fallback
            }
        }

        return $this->get_counter_fallback($key);
    }

    /**
     * Get TTL for key
     *
     * @param string $key Storage key
     * @return int Seconds until expiry
     */
    private function get_ttl($key) {
        if ($this->use_redis && $this->redis) {
            try {
                $ttl = $this->redis->ttl($key);
                return $ttl > 0 ? $ttl : 60;
            } catch (\Exception $e) {
                error_log('[Aevov Rate Limiter] Redis TTL failed: ' . $e->getMessage());
                // Fall through to fallback
            }
        }

        return $this->get_ttl_fallback($key);
    }

    /**
     * Generate storage key
     *
     * @param string $identifier Requester identifier
     * @param string $endpoint Endpoint route
     * @param string $window Window name
     * @return string Storage key
     */
    private function get_storage_key($identifier, $endpoint, $window) {
        // Sanitize endpoint (remove slashes and special chars)
        $endpoint_clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $endpoint);

        return sprintf(
            'aevov_ratelimit:%s:%s:%s',
            $identifier,
            $endpoint_clean,
            $window
        );
    }

    /**
     * Increment counter using fallback storage
     *
     * @param string $key Storage key
     * @param int $window Window duration
     * @return int New value
     */
    private function increment_counter_fallback($key, $window) {
        if (!isset(self::$fallback_storage[$key])) {
            self::$fallback_storage[$key] = [
                'value' => 0,
                'expires' => time() + $window,
            ];
        }

        // Check if expired
        if (self::$fallback_storage[$key]['expires'] < time()) {
            self::$fallback_storage[$key] = [
                'value' => 0,
                'expires' => time() + $window,
            ];
        }

        self::$fallback_storage[$key]['value']++;

        return self::$fallback_storage[$key]['value'];
    }

    /**
     * Get counter using fallback storage
     *
     * @param string $key Storage key
     * @return int Current value
     */
    private function get_counter_fallback($key) {
        if (!isset(self::$fallback_storage[$key])) {
            return 0;
        }

        // Check if expired
        if (self::$fallback_storage[$key]['expires'] < time()) {
            return 0;
        }

        return self::$fallback_storage[$key]['value'];
    }

    /**
     * Get TTL using fallback storage
     *
     * @param string $key Storage key
     * @return int Seconds until expiry
     */
    private function get_ttl_fallback($key) {
        if (!isset(self::$fallback_storage[$key])) {
            return 60;
        }

        $ttl = self::$fallback_storage[$key]['expires'] - time();

        return $ttl > 0 ? $ttl : 60;
    }

    /**
     * Add rate limit headers to response
     *
     * @param \WP_REST_Response $response Response object
     * @param string $identifier Requester identifier
     * @param string $endpoint Endpoint route
     * @param array $limits Limits configuration
     * @return \WP_REST_Response Modified response
     */
    private function add_rate_limit_headers($response, $identifier, $endpoint, $limits) {
        // Use the smallest window for headers (typically 'minute')
        $window_name = 'minute';
        $limit = $limits[$window_name] ?? 60;
        $window = $this->get_window_seconds($window_name);

        $key = $this->get_storage_key($identifier, $endpoint, $window_name);
        $current = $this->get_counter($key);
        $remaining = max(0, $limit - $current);
        $ttl = $this->get_ttl($key);
        $reset_time = time() + $ttl;

        // Add standard rate limit headers
        $response->header('X-RateLimit-Limit', $limit);
        $response->header('X-RateLimit-Remaining', $remaining);
        $response->header('X-RateLimit-Reset', $reset_time);
        $response->header('X-RateLimit-Window', $window_name);

        return $response;
    }

    /**
     * Log rate limit violation
     *
     * @param string $identifier Requester identifier
     * @param string $endpoint Endpoint route
     * @param string $window Window name
     * @param int $limit Limit exceeded
     */
    private function log_violation($identifier, $endpoint, $window, $limit) {
        error_log(sprintf(
            '[Aevov Rate Limiter] Violation: %s exceeded %d requests/%s on %s',
            $identifier,
            $limit,
            $window,
            $endpoint
        ));

        // Store violation in database for monitoring
        global $wpdb;

        $table = $wpdb->prefix . 'aevov_rate_limit_violations';

        // Create table if it doesn't exist
        $this->maybe_create_violations_table();

        // Insert violation record
        $wpdb->insert(
            $table,
            [
                'identifier' => $identifier,
                'endpoint' => $endpoint,
                'window' => $window,
                'limit_exceeded' => $limit,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );
    }

    /**
     * Create violations table if needed
     */
    private function maybe_create_violations_table() {
        global $wpdb;

        $table = $wpdb->prefix . 'aevov_rate_limit_violations';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        // Create table
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            endpoint varchar(255) NOT NULL,
            window varchar(50) NOT NULL,
            limit_exceeded int(11) NOT NULL,
            user_agent text,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY identifier (identifier),
            KEY endpoint (endpoint),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get rate limit statistics
     *
     * @param string $identifier Optional identifier to get stats for
     * @param string $endpoint Optional endpoint to get stats for
     * @return array Statistics
     */
    public function get_statistics($identifier = null, $endpoint = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'aevov_rate_limit_violations';

        $where = ['1=1'];
        $params = [];

        if ($identifier) {
            $where[] = 'identifier = %s';
            $params[] = $identifier;
        }

        if ($endpoint) {
            $where[] = 'endpoint = %s';
            $params[] = $endpoint;
        }

        $where_clause = implode(' AND ', $where);

        $query = "SELECT
            COUNT(*) as total_violations,
            COUNT(DISTINCT identifier) as unique_identifiers,
            COUNT(DISTINCT endpoint) as unique_endpoints,
            MAX(created_at) as last_violation
            FROM $table
            WHERE $where_clause";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_row($query, ARRAY_A);
    }

    /**
     * Clear rate limit for identifier
     *
     * Admin function to reset rate limits
     *
     * @param string $identifier Identifier to clear
     * @return int Number of keys cleared
     */
    public function clear_limits($identifier) {
        $cleared = 0;

        if ($this->use_redis && $this->redis) {
            try {
                // Find all keys for this identifier
                $pattern = sprintf('aevov_ratelimit:%s:*', $identifier);
                $keys = $this->redis->keys($pattern);

                if (!empty($keys)) {
                    $cleared = $this->redis->del($keys);
                }

            } catch (\Exception $e) {
                error_log('[Aevov Rate Limiter] Clear limits failed: ' . $e->getMessage());
            }
        }

        // Clear from fallback storage
        foreach (self::$fallback_storage as $key => $data) {
            if (strpos($key, "aevov_ratelimit:{$identifier}:") === 0) {
                unset(self::$fallback_storage[$key]);
                $cleared++;
            }
        }

        error_log("[Aevov Rate Limiter] Cleared {$cleared} rate limit keys for {$identifier}");

        return $cleared;
    }

    /**
     * Cleanup old violation records
     *
     * @param int $days Delete records older than this many days
     * @return int Number of records deleted
     */
    public function cleanup_violations($days = 30) {
        global $wpdb;

        $table = $wpdb->prefix . 'aevov_rate_limit_violations';

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        if ($deleted) {
            error_log("[Aevov Rate Limiter] Cleaned up {$deleted} old violation records");
        }

        return $deleted;
    }
}
