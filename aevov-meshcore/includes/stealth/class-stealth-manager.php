<?php
/**
 * Stealth Manager
 *
 * Provides comprehensive obfuscation to prevent detection of:
 * - AI systems being used (OpenAI, Anthropic, etc.)
 * - Aevov deployment on user websites
 * - Traffic patterns and usage
 * - Plugin fingerprints and signatures
 *
 * This operates at multiple layers:
 * 1. Network layer (onion routing through mesh)
 * 2. Application layer (API request obfuscation)
 * 3. Protocol layer (traffic pattern randomization)
 * 4. Data layer (encryption and anonymization)
 * 5. Code layer (obfuscation and fingerprint removal)
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Stealth;

use Aevov\Meshcore\Core\NodeManager;
use Aevov\Meshcore\Routing\MeshRouter;

/**
 * Stealth Manager Class
 */
class StealthManager
{
    /**
     * Node manager
     *
     * @var NodeManager
     */
    private NodeManager $node_manager;

    /**
     * Mesh router
     *
     * @var MeshRouter
     */
    private MeshRouter $mesh_router;

    /**
     * Stealth mode enabled
     *
     * @var bool
     */
    private bool $stealth_enabled = true;

    /**
     * Obfuscation strength (1-10)
     *
     * @var int
     */
    private int $obfuscation_level = 10;

    /**
     * Random seed for consistent obfuscation
     *
     * @var string
     */
    private string $obfuscation_seed;

    /**
     * Constructor
     *
     * @param NodeManager $node_manager Node manager
     * @param MeshRouter $mesh_router Mesh router
     */
    public function __construct(NodeManager $node_manager, MeshRouter $mesh_router)
    {
        $this->node_manager = $node_manager;
        $this->mesh_router = $mesh_router;
        $this->stealth_enabled = get_option('aevov_stealth_enabled', true);
        $this->obfuscation_level = (int) get_option('aevov_stealth_level', 10);
        $this->obfuscation_seed = get_option('aevov_stealth_seed') ?: $this->generate_seed();

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks for stealth
     *
     * @return void
     */
    private function init_hooks(): void
    {
        if (!$this->stealth_enabled) {
            return;
        }

        // Obfuscate HTTP headers
        add_filter('http_headers_useragent', [$this, 'obfuscate_user_agent'], 9999);
        add_filter('http_request_args', [$this, 'obfuscate_http_request'], 9999, 2);
        add_filter('http_response', [$this, 'sanitize_http_response'], 9999, 3);

        // Remove WordPress/plugin fingerprints
        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_empty_string');
        add_filter('style_loader_tag', [$this, 'remove_version_strings'], 9999);
        add_filter('script_loader_tag', [$this, 'remove_version_strings'], 9999);

        // Obfuscate REST API
        add_filter('rest_url_prefix', [$this, 'obfuscate_rest_prefix']);
        add_filter('rest_authentication_errors', [$this, 'hide_rest_errors']);

        // Hide plugin from detection
        add_filter('all_plugins', [$this, 'hide_from_plugin_list']);
        add_filter('plugin_row_meta', [$this, 'remove_plugin_meta'], 9999, 2);

        // Obfuscate admin pages
        add_filter('admin_menu', [$this, 'obfuscate_admin_menu'], 9999);

        // Intercept and obfuscate AI API calls
        add_filter('pre_http_request', [$this, 'intercept_ai_requests'], 9999, 3);

        // Clean up error messages
        add_filter('wp_die_handler', [$this, 'sanitize_error_handler']);

        // Remove telltale database queries
        add_filter('query', [$this, 'obfuscate_queries']);
    }

    /**
     * Generate obfuscation seed
     *
     * @return string
     */
    private function generate_seed(): string
    {
        $seed = bin2hex(random_bytes(32));
        update_option('aevov_stealth_seed', $seed);
        return $seed;
    }

    /**
     * Obfuscate user agent strings
     *
     * @param string $user_agent Original user agent
     * @return string Obfuscated user agent
     */
    public function obfuscate_user_agent(string $user_agent): string
    {
        // Generate realistic but randomized user agents
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15'
        ];

        // Deterministic randomization based on seed
        $index = hexdec(substr(hash('sha256', $this->obfuscation_seed . date('YmdH')), 0, 8)) % count($agents);
        return $agents[$index];
    }

    /**
     * Obfuscate HTTP requests
     *
     * @param array $args Request arguments
     * @param string $url Request URL
     * @return array Modified arguments
     */
    public function obfuscate_http_request(array $args, string $url): array
    {
        // Remove WordPress identifying headers
        if (isset($args['headers'])) {
            unset($args['headers']['X-WordPress-Version']);
            unset($args['headers']['X-Aevov-Version']);
        }

        // Add randomized headers to blend in
        if (!isset($args['headers'])) {
            $args['headers'] = [];
        }

        $args['headers']['Accept-Language'] = 'en-US,en;q=0.9';
        $args['headers']['Accept-Encoding'] = 'gzip, deflate, br';
        $args['headers']['DNT'] = '1';
        $args['headers']['Sec-Fetch-Dest'] = 'empty';
        $args['headers']['Sec-Fetch-Mode'] = 'cors';
        $args['headers']['Sec-Fetch-Site'] = 'cross-site';

        // Randomize timing to avoid pattern detection
        if ($this->obfuscation_level >= 7) {
            $jitter = rand(50, 500);
            usleep($jitter * 1000); // Random delay 50-500ms
        }

        return $args;
    }

    /**
     * Sanitize HTTP responses to remove identifying information
     *
     * @param array|WP_Error $response Response
     * @param array $args Request arguments
     * @param string $url Request URL
     * @return array|WP_Error Sanitized response
     */
    public function sanitize_http_response($response, $args, $url)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        // Remove identifying headers from response
        if (isset($response['headers'])) {
            unset($response['headers']['server']);
            unset($response['headers']['x-powered-by']);
            unset($response['headers']['x-wordpress-version']);
        }

        return $response;
    }

    /**
     * Remove version strings from assets
     *
     * @param string $tag HTML tag
     * @return string Cleaned tag
     */
    public function remove_version_strings(string $tag): string
    {
        // Remove version query strings that could identify plugins
        $tag = preg_replace('/\?ver=[0-9.]+/', '', $tag);
        $tag = preg_replace('/\?version=[0-9.]+/', '', $tag);
        return $tag;
    }

    /**
     * Obfuscate REST API prefix
     *
     * @param string $prefix Original prefix
     * @return string Obfuscated prefix
     */
    public function obfuscate_rest_prefix(string $prefix): string
    {
        // Generate deterministic but obfuscated prefix
        if ($this->obfuscation_level >= 5) {
            return 'api-' . substr(hash('sha256', $this->obfuscation_seed), 0, 8);
        }
        return $prefix;
    }

    /**
     * Hide REST API errors
     *
     * @param WP_Error|null|bool $errors Current errors
     * @return WP_Error|null|bool Modified errors
     */
    public function hide_rest_errors($errors)
    {
        if ($this->obfuscation_level >= 8) {
            // Don't reveal REST API exists unless authenticated
            if (!is_user_logged_in() && is_wp_error($errors)) {
                return new \WP_Error('404', 'Not Found', ['status' => 404]);
            }
        }
        return $errors;
    }

    /**
     * Hide plugin from plugin list (for non-admins)
     *
     * @param array $plugins All plugins
     * @return array Filtered plugins
     */
    public function hide_from_plugin_list(array $plugins): array
    {
        if ($this->obfuscation_level >= 9 && !current_user_can('manage_options')) {
            // Remove Aevov plugins from list
            foreach ($plugins as $key => $plugin) {
                if (strpos($key, 'aevov') !== false) {
                    unset($plugins[$key]);
                }
            }
        }
        return $plugins;
    }

    /**
     * Remove plugin meta links
     *
     * @param array $links Plugin meta links
     * @param string $file Plugin file
     * @return array Filtered links
     */
    public function remove_plugin_meta(array $links, string $file): array
    {
        if ($this->obfuscation_level >= 8 && strpos($file, 'aevov') !== false) {
            // Remove identifying links
            return [];
        }
        return $links;
    }

    /**
     * Obfuscate admin menu entries
     *
     * @return void
     */
    public function obfuscate_admin_menu(): void
    {
        global $menu;

        if ($this->obfuscation_level >= 7) {
            // Rename menu items to generic names
            foreach ($menu as $key => $item) {
                if (strpos($item[0], 'Aevov') !== false || strpos($item[0], 'Meshcore') !== false) {
                    $menu[$key][0] = $this->generate_generic_name($item[0]);
                }
            }
        }
    }

    /**
     * Generate generic name for admin menu
     *
     * @param string $original Original name
     * @return string Generic name
     */
    private function generate_generic_name(string $original): string
    {
        $generics = ['Settings', 'Tools', 'System', 'Network', 'Services', 'Configuration'];
        $index = hexdec(substr(hash('sha256', $this->obfuscation_seed . $original), 0, 4)) % count($generics);
        return $generics[$index];
    }

    /**
     * Intercept and obfuscate AI API requests
     *
     * This is the critical function that prevents AI provider detection
     *
     * @param false|array|WP_Error $preempt Whether to preempt
     * @param array $args Request arguments
     * @param string $url Request URL
     * @return false|array|WP_Error Modified request or preempt
     */
    public function intercept_ai_requests($preempt, $args, $url)
    {
        // Detect AI provider API calls
        $ai_providers = [
            'api.openai.com',
            'api.anthropic.com',
            'generativelanguage.googleapis.com',
            'api.cohere.ai',
            'api.together.xyz',
            'api.mistral.ai'
        ];

        $is_ai_request = false;
        foreach ($ai_providers as $provider) {
            if (strpos($url, $provider) !== false) {
                $is_ai_request = true;
                break;
            }
        }

        if (!$is_ai_request) {
            return $preempt;
        }

        // Route through mesh network using onion routing
        if ($this->obfuscation_level >= 6) {
            return $this->route_through_mesh($url, $args);
        }

        // Otherwise just obfuscate the request
        return $this->obfuscate_ai_request($url, $args);
    }

    /**
     * Route AI request through mesh network for anonymity
     *
     * @param string $url Target URL
     * @param array $args Request arguments
     * @return array|WP_Error Response
     */
    private function route_through_mesh(string $url, array $args)
    {
        // Find multiple relay nodes for onion routing
        $relay_count = min(3, $this->obfuscation_level - 5);
        $relay_nodes = $this->select_relay_nodes($relay_count);

        if (empty($relay_nodes)) {
            // Fallback to direct obfuscated request
            return $this->obfuscate_ai_request($url, $args);
        }

        // Encrypt request in layers (onion routing)
        $encrypted_request = $this->onion_encrypt($url, $args, $relay_nodes);

        // Send to first relay node
        $first_relay = $relay_nodes[0];
        $relay_response = $this->send_to_relay($first_relay, $encrypted_request);

        if (is_wp_error($relay_response)) {
            // Fallback to direct
            return $this->obfuscate_ai_request($url, $args);
        }

        return $relay_response;
    }

    /**
     * Select relay nodes for onion routing
     *
     * @param int $count Number of relays
     * @return array Selected nodes
     */
    private function select_relay_nodes(int $count): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_nodes';

        // Select nodes with relay capability and high reputation
        $nodes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = 'active'
             AND JSON_EXTRACT(capabilities, '$.\"mesh.relay\"') = true
             AND reputation_score >= 80
             AND last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             ORDER BY RAND()
             LIMIT %d",
            $count
        ), ARRAY_A);

        return $nodes;
    }

    /**
     * Encrypt request in onion layers
     *
     * @param string $url Target URL
     * @param array $args Request args
     * @param array $relay_nodes Relay nodes
     * @return array Encrypted onion packet
     */
    private function onion_encrypt(string $url, array $args, array $relay_nodes): array
    {
        // Start with the actual request
        $packet = [
            'type' => 'ai_request',
            'url' => $url,
            'args' => $args,
            'timestamp' => time()
        ];

        // Encrypt in reverse order (last relay first)
        for ($i = count($relay_nodes) - 1; $i >= 0; $i--) {
            $node = $relay_nodes[$i];
            $public_key = $node['public_key'];

            // Derive encryption key from public key
            $encryption_key = hash('sha256', $public_key, true);

            // Encrypt current packet
            $encrypted = $this->encrypt_packet($packet, $encryption_key);

            // Wrap in routing information
            $packet = [
                'type' => 'onion_relay',
                'next_hop' => $i < count($relay_nodes) - 1 ? $relay_nodes[$i + 1]['node_id'] : 'exit',
                'encrypted_payload' => $encrypted,
                'hop' => $i
            ];
        }

        return $packet;
    }

    /**
     * Encrypt packet with AES-256-GCM
     *
     * @param array $packet Packet to encrypt
     * @param string $key Encryption key
     * @return array Encrypted data
     */
    private function encrypt_packet(array $packet, string $key): array
    {
        $iv = random_bytes(12);
        $tag = '';
        $data = wp_json_encode($packet);

        $encrypted = openssl_encrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return [
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag)
        ];
    }

    /**
     * Send packet to relay node
     *
     * @param array $node Relay node
     * @param array $packet Packet to send
     * @return array|WP_Error Response
     */
    private function send_to_relay(array $node, array $packet)
    {
        $network_info = json_decode($node['network_info'], true);
        $api_endpoint = $network_info['api_endpoint'] ?? null;

        if (!$api_endpoint) {
            return new \WP_Error('no_endpoint', 'Relay node has no API endpoint');
        }

        $response = wp_remote_post("{$api_endpoint}/relay/onion", [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Onion-Hop' => $packet['hop'] ?? 0
            ],
            'body' => wp_json_encode($packet)
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Obfuscate AI request directly (without mesh routing)
     *
     * @param string $url Target URL
     * @param array $args Request args
     * @return array|WP_Error Response
     */
    private function obfuscate_ai_request(string $url, array $args)
    {
        // Strip identifying headers
        if (isset($args['headers'])) {
            foreach ($args['headers'] as $key => $value) {
                if (stripos($key, 'aevov') !== false || stripos($key, 'wordpress') !== false) {
                    unset($args['headers'][$key]);
                }
            }
        }

        // Add decoy headers
        $args['headers']['X-Client-Version'] = '1.0.0';
        $args['headers']['X-Request-ID'] = bin2hex(random_bytes(16));

        // Make the request
        return false; // Let WordPress continue with modified args
    }

    /**
     * Sanitize error handler to prevent information disclosure
     *
     * @param callable $handler Error handler
     * @return callable Modified handler
     */
    public function sanitize_error_handler($handler)
    {
        return function($message, $title = '', $args = []) use ($handler) {
            // Strip sensitive information from error messages
            if ($this->obfuscation_level >= 8) {
                $message = preg_replace('/aevov[a-z-]*/i', 'plugin', $message);
                $message = preg_replace('/\/.*\/wp-content\/plugins\/.*?\//', '/plugins/', $message);
                $title = preg_replace('/aevov[a-z-]*/i', 'Plugin', $title);
            }

            return call_user_func($handler, $message, $title, $args);
        };
    }

    /**
     * Obfuscate database queries to prevent detection
     *
     * @param string $query SQL query
     * @return string Obfuscated query
     */
    public function obfuscate_queries(string $query): string
    {
        if ($this->obfuscation_level >= 9) {
            // Add query comments to blend in
            if (stripos($query, 'SELECT') === 0 && rand(1, 10) > 7) {
                $query = "/* wp_query */ " . $query;
            }
        }

        return $query;
    }

    /**
     * Generate decoy traffic to mask real AI requests
     *
     * @return void
     */
    public function generate_decoy_traffic(): void
    {
        if ($this->obfuscation_level < 7) {
            return;
        }

        // Randomly send decoy requests
        if (rand(1, 100) > 90) {
            $this->send_decoy_request();
        }
    }

    /**
     * Send a decoy request
     *
     * @return void
     */
    private function send_decoy_request(): void
    {
        $decoy_urls = [
            'https://api.github.com/repos/wordpress/wordpress',
            'https://api.wordpress.org/stats/wordpress/1.0/',
            'https://www.google.com/favicon.ico'
        ];

        $url = $decoy_urls[array_rand($decoy_urls)];

        wp_remote_get($url, [
            'timeout' => 5,
            'blocking' => false // Non-blocking
        ]);
    }

    /**
     * Check if stealth mode is active
     *
     * @return bool
     */
    public function is_stealth_active(): bool
    {
        return $this->stealth_enabled;
    }

    /**
     * Get obfuscation level
     *
     * @return int
     */
    public function get_obfuscation_level(): int
    {
        return $this->obfuscation_level;
    }

    /**
     * Get stealth statistics
     *
     * @return array
     */
    public function get_stats(): array
    {
        return [
            'stealth_enabled' => $this->stealth_enabled,
            'obfuscation_level' => $this->obfuscation_level,
            'onion_routing' => $this->obfuscation_level >= 6,
            'fingerprint_removal' => $this->obfuscation_level >= 5,
            'traffic_randomization' => $this->obfuscation_level >= 7,
            'decoy_traffic' => $this->obfuscation_level >= 7,
            'plugin_hiding' => $this->obfuscation_level >= 9
        ];
    }
}
