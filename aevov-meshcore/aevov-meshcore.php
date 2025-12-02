<?php
/**
 * Plugin Name: Aevov Meshcore
 * Plugin URI: https://aevov.com/meshcore
 * Description: Deep mesh networking support enabling decentralized peer-to-peer communication as an alternative to traditional ISPs. Provides WebRTC P2P, DHT discovery, mesh routing, and bandwidth sharing.
 * Version: 1.0.0
 * Author: Aevov Team
 * Author URI: https://aevov.com
 * License: MIT
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Text Domain: aevov-meshcore
 * Domain Path: /languages
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AEVOV_MESHCORE_VERSION', '1.0.0');
define('AEVOV_MESHCORE_PATH', plugin_dir_path(__FILE__));
define('AEVOV_MESHCORE_URL', plugin_dir_url(__FILE__));
define('AEVOV_MESHCORE_BASENAME', plugin_basename(__FILE__));

/**
 * Main Meshcore Plugin Class
 *
 * Orchestrates the entire mesh networking ecosystem including:
 * - WebRTC peer-to-peer connections
 * - DHT-based decentralized discovery
 * - Multi-hop mesh routing
 * - Bandwidth sharing and relay
 * - Integration with AevIP protocol
 */
class MeshcorePlugin
{
    /**
     * Singleton instance
     *
     * @var MeshcorePlugin|null
     */
    private static ?MeshcorePlugin $instance = null;

    /**
     * Node manager instance
     *
     * @var Core\NodeManager|null
     */
    private ?Core\NodeManager $node_manager = null;

    /**
     * P2P connection manager
     *
     * @var P2P\ConnectionManager|null
     */
    private ?P2P\ConnectionManager $connection_manager = null;

    /**
     * DHT discovery service
     *
     * @var Discovery\DHTService|null
     */
    private ?Discovery\DHTService $dht_service = null;

    /**
     * Mesh routing engine
     *
     * @var Routing\MeshRouter|null
     */
    private ?Routing\MeshRouter $mesh_router = null;

    /**
     * Relay and bandwidth sharing manager
     *
     * @var Relay\RelayManager|null
     */
    private ?Relay\RelayManager $relay_manager = null;

    /**
     * AevIP integration coordinator
     *
     * @var Core\AevIPIntegration|null
     */
    private ?Core\AevIPIntegration $aevip_integration = null;

    /**
     * Stealth manager for obfuscation and privacy
     *
     * @var Stealth\StealthManager|null
     */
    private ?Stealth\StealthManager $stealth_manager = null;

    /**
     * Traffic randomizer
     *
     * @var Stealth\TrafficRandomizer|null
     */
    private ?Stealth\TrafficRandomizer $traffic_randomizer = null;

    /**
     * Fingerprint eliminator
     *
     * @var Stealth\FingerprintEliminator|null
     */
    private ?Stealth\FingerprintEliminator $fingerprint_eliminator = null;

    /**
     * Onion relay handler
     *
     * @var Stealth\OnionRelayHandler|null
     */
    private ?Stealth\OnionRelayHandler $onion_relay_handler = null;

    /**
     * Code obfuscator
     *
     * @var Stealth\CodeObfuscator|null
     */
    private ?Stealth\CodeObfuscator $code_obfuscator = null;

    /**
     * Get singleton instance
     *
     * @return MeshcorePlugin
     */
    public static function get_instance(): MeshcorePlugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_components();
        $this->register_hooks();
    }

    /**
     * Load required files
     *
     * @return void
     */
    private function load_dependencies(): void
    {
        // Core components
        require_once AEVOV_MESHCORE_PATH . 'includes/core/class-node-manager.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/core/class-mesh-network.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/core/class-aevip-integration.php';

        // P2P layer
        require_once AEVOV_MESHCORE_PATH . 'includes/p2p/class-connection-manager.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/p2p/class-webrtc-signaling.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/p2p/class-nat-traversal.php';

        // Discovery
        require_once AEVOV_MESHCORE_PATH . 'includes/discovery/class-dht-service.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/discovery/class-peer-discovery.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/discovery/class-service-registry.php';

        // Routing
        require_once AEVOV_MESHCORE_PATH . 'includes/routing/class-mesh-router.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/routing/class-routing-table.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/routing/class-path-optimizer.php';

        // Relay and bandwidth
        require_once AEVOV_MESHCORE_PATH . 'includes/relay/class-relay-manager.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/relay/class-bandwidth-manager.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/relay/class-incentive-system.php';

        // API
        require_once AEVOV_MESHCORE_PATH . 'includes/api/class-rest-api.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/api/class-websocket-server.php';

        // Security
        require_once AEVOV_MESHCORE_PATH . 'includes/security/class-mesh-security.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/security/class-encryption-manager.php';

        // Stealth (obfuscation and privacy)
        require_once AEVOV_MESHCORE_PATH . 'includes/stealth/class-stealth-manager.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/stealth/class-traffic-randomizer.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/stealth/class-fingerprint-eliminator.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/stealth/class-onion-relay-handler.php';
        require_once AEVOV_MESHCORE_PATH . 'includes/stealth/class-code-obfuscator.php';
    }

    /**
     * Initialize all components
     *
     * @return void
     */
    private function init_components(): void
    {
        // Initialize core
        $this->node_manager = new Core\NodeManager();
        $this->aevip_integration = new Core\AevIPIntegration($this->node_manager);

        // Initialize P2P layer
        $this->connection_manager = new P2P\ConnectionManager($this->node_manager);

        // Initialize discovery
        $this->dht_service = new Discovery\DHTService($this->node_manager);

        // Initialize routing
        $this->mesh_router = new Routing\MeshRouter($this->node_manager, $this->connection_manager);

        // Initialize relay
        $this->relay_manager = new Relay\RelayManager(
            $this->node_manager,
            $this->connection_manager,
            $this->mesh_router
        );

        // Initialize stealth components FIRST (for maximum privacy)
        $this->traffic_randomizer = new Stealth\TrafficRandomizer();
        $this->fingerprint_eliminator = new Stealth\FingerprintEliminator();
        $this->code_obfuscator = new Stealth\CodeObfuscator();
        $this->onion_relay_handler = new Stealth\OnionRelayHandler($this->node_manager);
        $this->stealth_manager = new Stealth\StealthManager($this->node_manager, $this->mesh_router);

        // Initialize API
        new API\RestAPI(
            $this->node_manager,
            $this->connection_manager,
            $this->dht_service,
            $this->mesh_router,
            $this->relay_manager
        );

        // Initialize WebSocket server for signaling
        new API\WebSocketServer($this->connection_manager);
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    private function register_hooks(): void
    {
        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Plugin loaded
        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);

        // Admin interface
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // Cron jobs for mesh maintenance
        add_action('aevov_meshcore_heartbeat', [$this, 'heartbeat_tick']);
        add_action('aevov_meshcore_cleanup', [$this, 'cleanup_stale_connections']);

        // Integration with AevIP
        add_action('aevip_node_registered', [$this->aevip_integration, 'on_aevip_node_registered'], 10, 2);
        add_filter('aevip_transport_methods', [$this->aevip_integration, 'add_mesh_transport'], 10, 1);
    }

    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate(): void
    {
        // Create database tables
        $this->create_tables();

        // Schedule cron jobs
        if (!wp_next_scheduled('aevov_meshcore_heartbeat')) {
            wp_schedule_event(time(), 'every_minute', 'aevov_meshcore_heartbeat');
        }
        if (!wp_next_scheduled('aevov_meshcore_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'aevov_meshcore_cleanup');
        }

        // Generate node identity if not exists
        $this->node_manager->ensure_node_identity();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate(): void
    {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('aevov_meshcore_heartbeat');
        wp_clear_scheduled_hook('aevov_meshcore_cleanup');

        // Gracefully disconnect from mesh
        $this->connection_manager->disconnect_all();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     *
     * @return void
     */
    private function create_tables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            // Mesh nodes
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}meshcore_nodes (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                node_id varchar(64) NOT NULL,
                peer_id varchar(64) NOT NULL,
                public_key text NOT NULL,
                capabilities longtext NOT NULL,
                network_info longtext NOT NULL,
                last_seen datetime NOT NULL,
                status varchar(20) DEFAULT 'active',
                reputation_score int DEFAULT 100,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY node_id (node_id),
                KEY peer_id (peer_id),
                KEY status (status),
                KEY last_seen (last_seen)
            ) $charset_collate;",

            // Active connections
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}meshcore_connections (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                connection_id varchar(64) NOT NULL,
                local_node_id varchar(64) NOT NULL,
                remote_node_id varchar(64) NOT NULL,
                connection_type varchar(20) NOT NULL,
                status varchar(20) DEFAULT 'connecting',
                quality_score float DEFAULT 0,
                bandwidth_up int DEFAULT 0,
                bandwidth_down int DEFAULT 0,
                latency int DEFAULT 0,
                packet_loss float DEFAULT 0,
                bytes_sent bigint DEFAULT 0,
                bytes_received bigint DEFAULT 0,
                established_at datetime DEFAULT NULL,
                last_activity datetime NOT NULL,
                metadata longtext,
                PRIMARY KEY (id),
                UNIQUE KEY connection_id (connection_id),
                KEY local_node_id (local_node_id),
                KEY remote_node_id (remote_node_id),
                KEY status (status)
            ) $charset_collate;",

            // Routing table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}meshcore_routes (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                destination_id varchar(64) NOT NULL,
                next_hop_id varchar(64) NOT NULL,
                hop_count int NOT NULL,
                path_quality float DEFAULT 0,
                path_cost float DEFAULT 0,
                path_nodes longtext,
                expires_at datetime NOT NULL,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY route_key (destination_id, next_hop_id),
                KEY destination_id (destination_id),
                KEY next_hop_id (next_hop_id),
                KEY expires_at (expires_at)
            ) $charset_collate;",

            // DHT entries
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}meshcore_dht (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                key_hash varchar(64) NOT NULL,
                value_data longtext NOT NULL,
                node_id varchar(64) NOT NULL,
                ttl int DEFAULT 3600,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY key_hash (key_hash),
                KEY node_id (node_id),
                KEY expires_at (expires_at)
            ) $charset_collate;",

            // Bandwidth tokens
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}meshcore_bandwidth_tokens (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                node_id varchar(64) NOT NULL,
                tokens_earned bigint DEFAULT 0,
                tokens_spent bigint DEFAULT 0,
                bytes_relayed bigint DEFAULT 0,
                bytes_consumed bigint DEFAULT 0,
                reputation_modifier float DEFAULT 1.0,
                last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY node_id (node_id)
            ) $charset_collate;",

            // Relay statistics
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}meshcore_relay_stats (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                relay_node_id varchar(64) NOT NULL,
                source_node_id varchar(64) NOT NULL,
                destination_node_id varchar(64) NOT NULL,
                bytes_relayed bigint NOT NULL,
                packets_relayed int NOT NULL,
                tokens_earned bigint NOT NULL,
                relay_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY relay_node_id (relay_node_id),
                KEY relay_time (relay_time)
            ) $charset_collate;",

            // Service registry
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}meshcore_services (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                service_id varchar(64) NOT NULL,
                service_name varchar(255) NOT NULL,
                service_type varchar(50) NOT NULL,
                node_id varchar(64) NOT NULL,
                endpoint text NOT NULL,
                metadata longtext,
                status varchar(20) DEFAULT 'active',
                registered_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY service_id (service_id),
                KEY service_type (service_type),
                KEY node_id (node_id),
                KEY status (status)
            ) $charset_collate;"
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $table_sql) {
            dbDelta($table_sql);
        }
    }

    /**
     * Plugins loaded hook
     *
     * @return void
     */
    public function on_plugins_loaded(): void
    {
        // Load text domain
        load_plugin_textdomain('aevov-meshcore', false, dirname(AEVOV_MESHCORE_BASENAME) . '/languages');

        // Trigger loaded action
        do_action('aevov_meshcore_loaded', $this);
    }

    /**
     * Register admin menu
     *
     * @return void
     */
    public function register_admin_menu(): void
    {
        add_menu_page(
            __('Meshcore Network', 'aevov-meshcore'),
            __('Meshcore', 'aevov-meshcore'),
            'manage_options',
            'aevov-meshcore',
            [$this, 'render_admin_page'],
            'dashicons-networking',
            30
        );

        add_submenu_page(
            'aevov-meshcore',
            __('Network Dashboard', 'aevov-meshcore'),
            __('Dashboard', 'aevov-meshcore'),
            'manage_options',
            'aevov-meshcore',
            [$this, 'render_admin_page']
        );

        add_submenu_page(
            'aevov-meshcore',
            __('Mesh Nodes', 'aevov-meshcore'),
            __('Nodes', 'aevov-meshcore'),
            'manage_options',
            'aevov-meshcore-nodes',
            [$this, 'render_nodes_page']
        );

        add_submenu_page(
            'aevov-meshcore',
            __('Connections', 'aevov-meshcore'),
            __('Connections', 'aevov-meshcore'),
            'manage_options',
            'aevov-meshcore-connections',
            [$this, 'render_connections_page']
        );

        add_submenu_page(
            'aevov-meshcore',
            __('Settings', 'aevov-meshcore'),
            __('Settings', 'aevov-meshcore'),
            'manage_options',
            'aevov-meshcore-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void
    {
        if (strpos($hook, 'aevov-meshcore') === false) {
            return;
        }

        wp_enqueue_style(
            'aevov-meshcore-admin',
            AEVOV_MESHCORE_URL . 'assets/css/admin.css',
            [],
            AEVOV_MESHCORE_VERSION
        );

        wp_enqueue_script(
            'aevov-meshcore-admin',
            AEVOV_MESHCORE_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api'],
            AEVOV_MESHCORE_VERSION,
            true
        );

        wp_localize_script('aevov-meshcore-admin', 'aevovMeshcore', [
            'apiUrl' => rest_url('aevov-meshcore/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'nodeId' => $this->node_manager->get_node_id(),
            'wsUrl' => $this->get_websocket_url()
        ]);
    }

    /**
     * Enqueue frontend assets
     *
     * @return void
     */
    public function enqueue_frontend_assets(): void
    {
        wp_enqueue_script(
            'aevov-meshcore-p2p',
            AEVOV_MESHCORE_URL . 'assets/js/meshcore-p2p.js',
            [],
            AEVOV_MESHCORE_VERSION,
            true
        );

        wp_localize_script('aevov-meshcore-p2p', 'aevovMeshcore', [
            'apiUrl' => rest_url('aevov-meshcore/v1'),
            'wsUrl' => $this->get_websocket_url(),
            'nodeId' => $this->node_manager->get_node_id()
        ]);
    }

    /**
     * Get WebSocket server URL
     *
     * @return string
     */
    private function get_websocket_url(): string
    {
        $host = parse_url(home_url(), PHP_URL_HOST);
        $port = get_option('aevov_meshcore_ws_port', 8080);
        $protocol = is_ssl() ? 'wss' : 'ws';

        return "{$protocol}://{$host}:{$port}";
    }

    /**
     * Render main admin page
     *
     * @return void
     */
    public function render_admin_page(): void
    {
        require_once AEVOV_MESHCORE_PATH . 'templates/admin-dashboard.php';
    }

    /**
     * Render nodes page
     *
     * @return void
     */
    public function render_nodes_page(): void
    {
        require_once AEVOV_MESHCORE_PATH . 'templates/admin-nodes.php';
    }

    /**
     * Render connections page
     *
     * @return void
     */
    public function render_connections_page(): void
    {
        require_once AEVOV_MESHCORE_PATH . 'templates/admin-connections.php';
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        require_once AEVOV_MESHCORE_PATH . 'templates/admin-settings.php';
    }

    /**
     * Heartbeat tick for mesh maintenance
     *
     * @return void
     */
    public function heartbeat_tick(): void
    {
        // Announce presence to DHT
        $this->dht_service->announce_node();

        // Update routing tables
        $this->mesh_router->update_routes();

        // Check connection health
        $this->connection_manager->health_check();

        // Process relay queue
        $this->relay_manager->process_relay_queue();
    }

    /**
     * Cleanup stale connections
     *
     * @return void
     */
    public function cleanup_stale_connections(): void
    {
        $this->connection_manager->cleanup_stale();
        $this->dht_service->cleanup_expired();
        $this->mesh_router->cleanup_expired_routes();
    }

    /**
     * Get node manager
     *
     * @return Core\NodeManager
     */
    public function get_node_manager(): Core\NodeManager
    {
        return $this->node_manager;
    }

    /**
     * Get connection manager
     *
     * @return P2P\ConnectionManager
     */
    public function get_connection_manager(): P2P\ConnectionManager
    {
        return $this->connection_manager;
    }

    /**
     * Get DHT service
     *
     * @return Discovery\DHTService
     */
    public function get_dht_service(): Discovery\DHTService
    {
        return $this->dht_service;
    }

    /**
     * Get mesh router
     *
     * @return Routing\MeshRouter
     */
    public function get_mesh_router(): Routing\MeshRouter
    {
        return $this->mesh_router;
    }

    /**
     * Get relay manager
     *
     * @return Relay\RelayManager
     */
    public function get_relay_manager(): Relay\RelayManager
    {
        return $this->relay_manager;
    }

    /**
     * Get stealth manager
     *
     * @return Stealth\StealthManager
     */
    public function get_stealth_manager(): Stealth\StealthManager
    {
        return $this->stealth_manager;
    }

    /**
     * Get traffic randomizer
     *
     * @return Stealth\TrafficRandomizer
     */
    public function get_traffic_randomizer(): Stealth\TrafficRandomizer
    {
        return $this->traffic_randomizer;
    }

    /**
     * Get fingerprint eliminator
     *
     * @return Stealth\FingerprintEliminator
     */
    public function get_fingerprint_eliminator(): Stealth\FingerprintEliminator
    {
        return $this->fingerprint_eliminator;
    }

    /**
     * Get code obfuscator
     *
     * @return Stealth\CodeObfuscator
     */
    public function get_code_obfuscator(): Stealth\CodeObfuscator
    {
        return $this->code_obfuscator;
    }

    /**
     * Get onion relay handler
     *
     * @return Stealth\OnionRelayHandler
     */
    public function get_onion_relay_handler(): Stealth\OnionRelayHandler
    {
        return $this->onion_relay_handler;
    }
}

// Initialize plugin
MeshcorePlugin::get_instance();
