<?php

namespace AevovSuperAppForge;

use Exception;

/**
 * WebSocket Server for Super App Forge
 *
 * Handles real-time communication between the forge and connected clients.
 * This implementation uses WordPress transients for message queue management
 * and supports bidirectional communication.
 */
class WebSocketServer {

    private $server_id;
    private $connections = [];
    private $message_queue = [];
    private $is_running = false;
    private $port = 8080;
    private $host = '0.0.0.0';

    /**
     * Initialize WebSocket Server
     *
     * @param array $config Server configuration.
     */
    public function __construct( $config = [] ) {
        $this->server_id = 'aevov_websocket_' . uniqid();
        $this->port = $config['port'] ?? get_option('aevov_websocket_port', 8080);
        $this->host = $config['host'] ?? get_option('aevov_websocket_host', '0.0.0.0');

        // Initialize connection tracking
        $this->load_connections();

        // Register shutdown handler
        register_shutdown_function([$this, 'shutdown']);
    }

    /**
     * Start the WebSocket server
     *
     * @return bool Success status.
     */
    public function start() {
        try {
            $this->is_running = true;

            // Store server status
            update_option('aevov_websocket_status', [
                'running' => true,
                'server_id' => $this->server_id,
                'port' => $this->port,
                'host' => $this->host,
                'started_at' => current_time('mysql'),
                'pid' => getmypid()
            ]);

            error_log("WebSocket Server started on {$this->host}:{$this->port}");

            return true;

        } catch (Exception $e) {
            error_log("Failed to start WebSocket server: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Stop the WebSocket server
     */
    public function stop() {
        $this->is_running = false;

        // Update server status
        update_option('aevov_websocket_status', [
            'running' => false,
            'server_id' => $this->server_id,
            'stopped_at' => current_time('mysql')
        ]);

        // Close all connections
        foreach ($this->connections as $conn_id => $connection) {
            $this->close_connection($conn_id);
        }

        error_log("WebSocket Server stopped");
    }

    /**
     * Broadcast data to all connected clients
     *
     * @param mixed $data Data to broadcast.
     * @param array $exclude_ids Connection IDs to exclude.
     * @return int Number of clients that received the message.
     */
    public function broadcast( $data, $exclude_ids = [] ) {
        $message = $this->prepare_message($data);
        $sent_count = 0;

        foreach ($this->connections as $conn_id => $connection) {
            if (in_array($conn_id, $exclude_ids)) {
                continue;
            }

            if ($this->send_to_connection($conn_id, $message)) {
                $sent_count++;
            }
        }

        // Log broadcast
        $this->log_message('broadcast', $message, [
            'sent_to' => $sent_count,
            'total_connections' => count($this->connections)
        ]);

        return $sent_count;
    }

    /**
     * Send data to a specific connection
     *
     * @param string $conn_id Connection ID.
     * @param mixed $data Data to send.
     * @return bool Success status.
     */
    public function send_to_connection( $conn_id, $data ) {
        if (!isset($this->connections[$conn_id])) {
            return false;
        }

        $message = $this->prepare_message($data);

        // Store message in queue for the connection
        $queue_key = "aevov_ws_queue_{$conn_id}";
        $queue = get_transient($queue_key) ?: [];
        $queue[] = [
            'message' => $message,
            'timestamp' => time(),
            'delivered' => false
        ];

        set_transient($queue_key, $queue, 3600); // 1 hour

        // Update connection last activity
        $this->connections[$conn_id]['last_activity'] = time();
        $this->save_connections();

        return true;
    }

    /**
     * Register a new client connection
     *
     * @param array $connection_data Connection metadata.
     * @return string Connection ID.
     */
    public function register_connection( $connection_data = [] ) {
        $conn_id = 'conn_' . uniqid() . '_' . wp_generate_password(8, false);

        $this->connections[$conn_id] = array_merge([
            'id' => $conn_id,
            'connected_at' => time(),
            'last_activity' => time(),
            'remote_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => get_current_user_id(),
            'metadata' => []
        ], $connection_data);

        $this->save_connections();

        // Send welcome message
        $this->send_to_connection($conn_id, [
            'type' => 'connection',
            'status' => 'connected',
            'connection_id' => $conn_id,
            'server_time' => current_time('mysql')
        ]);

        error_log("New WebSocket connection: {$conn_id}");

        return $conn_id;
    }

    /**
     * Close a specific connection
     *
     * @param string $conn_id Connection ID.
     * @return bool Success status.
     */
    public function close_connection( $conn_id ) {
        if (!isset($this->connections[$conn_id])) {
            return false;
        }

        // Send disconnect message
        $this->send_to_connection($conn_id, [
            'type' => 'connection',
            'status' => 'disconnected',
            'reason' => 'server_closed'
        ]);

        // Clear message queue
        delete_transient("aevov_ws_queue_{$conn_id}");

        // Remove connection
        unset($this->connections[$conn_id]);
        $this->save_connections();

        error_log("WebSocket connection closed: {$conn_id}");

        return true;
    }

    /**
     * Get messages for a specific connection
     *
     * @param string $conn_id Connection ID.
     * @param bool $mark_delivered Mark messages as delivered.
     * @return array Messages.
     */
    public function get_messages( $conn_id, $mark_delivered = true ) {
        if (!isset($this->connections[$conn_id])) {
            return [];
        }

        $queue_key = "aevov_ws_queue_{$conn_id}";
        $queue = get_transient($queue_key) ?: [];

        if (empty($queue)) {
            return [];
        }

        $messages = [];
        foreach ($queue as $i => $item) {
            if (!$item['delivered']) {
                $messages[] = $item['message'];

                if ($mark_delivered) {
                    $queue[$i]['delivered'] = true;
                    $queue[$i]['delivered_at'] = time();
                }
            }
        }

        if ($mark_delivered) {
            set_transient($queue_key, $queue, 3600);
        }

        // Update last activity
        $this->connections[$conn_id]['last_activity'] = time();
        $this->save_connections();

        return $messages;
    }

    /**
     * Process incoming message from a connection
     *
     * @param string $conn_id Connection ID.
     * @param mixed $data Incoming data.
     * @return array Response.
     */
    public function handle_message( $conn_id, $data ) {
        if (!isset($this->connections[$conn_id])) {
            return ['error' => 'Invalid connection'];
        }

        $message = is_string($data) ? json_decode($data, true) : $data;

        if (!is_array($message) || !isset($message['type'])) {
            return ['error' => 'Invalid message format'];
        }

        // Update last activity
        $this->connections[$conn_id]['last_activity'] = time();
        $this->save_connections();

        // Route message based on type
        switch ($message['type']) {
            case 'ping':
                return ['type' => 'pong', 'timestamp' => time()];

            case 'status':
                return $this->get_server_status();

            case 'forge_update':
                return $this->handle_forge_update($conn_id, $message);

            case 'app_progress':
                return $this->handle_app_progress($conn_id, $message);

            default:
                // Allow custom message handlers via filter
                return apply_filters('aevov_websocket_message_handler', [
                    'type' => 'ack',
                    'received' => true
                ], $message, $conn_id);
        }
    }

    /**
     * Handle forge update message
     *
     * @param string $conn_id Connection ID.
     * @param array $message Message data.
     * @return array Response.
     */
    private function handle_forge_update( $conn_id, $message ) {
        $job_id = $message['job_id'] ?? null;
        $status = $message['status'] ?? 'unknown';
        $progress = $message['progress'] ?? 0;

        if (!$job_id) {
            return ['error' => 'Job ID required'];
        }

        // Update job status
        update_option("aevov_forge_job_{$job_id}", [
            'status' => $status,
            'progress' => $progress,
            'updated_at' => current_time('mysql'),
            'connection_id' => $conn_id
        ]);

        // Broadcast to other connections
        $this->broadcast([
            'type' => 'forge_update',
            'job_id' => $job_id,
            'status' => $status,
            'progress' => $progress
        ], [$conn_id]);

        return ['type' => 'ack', 'job_id' => $job_id];
    }

    /**
     * Handle app progress message
     *
     * @param string $conn_id Connection ID.
     * @param array $message Message data.
     * @return array Response.
     */
    private function handle_app_progress( $conn_id, $message ) {
        $app_id = $message['app_id'] ?? null;
        $stage = $message['stage'] ?? 'unknown';
        $progress = $message['progress'] ?? 0;

        if (!$app_id) {
            return ['error' => 'App ID required'];
        }

        // Store progress
        update_option("aevov_app_progress_{$app_id}", [
            'stage' => $stage,
            'progress' => $progress,
            'updated_at' => current_time('mysql'),
            'connection_id' => $conn_id
        ]);

        return ['type' => 'ack', 'app_id' => $app_id];
    }

    /**
     * Get server status
     *
     * @return array Server status.
     */
    public function get_server_status() {
        return [
            'type' => 'status',
            'running' => $this->is_running,
            'server_id' => $this->server_id,
            'connections' => count($this->connections),
            'host' => $this->host,
            'port' => $this->port,
            'uptime' => $this->get_uptime()
        ];
    }

    /**
     * Clean up stale connections
     *
     * @param int $timeout Timeout in seconds (default 300 = 5 minutes).
     * @return int Number of connections cleaned up.
     */
    public function clean_stale_connections( $timeout = 300 ) {
        $now = time();
        $cleaned = 0;

        foreach ($this->connections as $conn_id => $connection) {
            $idle_time = $now - $connection['last_activity'];

            if ($idle_time > $timeout) {
                $this->close_connection($conn_id);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            error_log("Cleaned {$cleaned} stale WebSocket connections");
        }

        return $cleaned;
    }

    /**
     * Get server uptime
     *
     * @return int Uptime in seconds.
     */
    private function get_uptime() {
        $status = get_option('aevov_websocket_status', []);
        if (empty($status['started_at'])) {
            return 0;
        }

        return strtotime(current_time('mysql')) - strtotime($status['started_at']);
    }

    /**
     * Prepare message for sending
     *
     * @param mixed $data Data to prepare.
     * @return string JSON encoded message.
     */
    private function prepare_message( $data ) {
        if (is_string($data)) {
            return $data;
        }

        $message = is_array($data) ? $data : ['data' => $data];

        if (!isset($message['timestamp'])) {
            $message['timestamp'] = time();
        }

        if (!isset($message['server_id'])) {
            $message['server_id'] = $this->server_id;
        }

        return wp_json_encode($message);
    }

    /**
     * Log message activity
     *
     * @param string $type Message type.
     * @param string $message Message content.
     * @param array $metadata Additional metadata.
     */
    private function log_message( $type, $message, $metadata = [] ) {
        $log_entry = [
            'type' => $type,
            'message' => substr($message, 0, 500), // Limit size
            'metadata' => $metadata,
            'timestamp' => current_time('mysql')
        ];

        // Store in log (keep last 100 entries)
        $log = get_option('aevov_websocket_log', []);
        $log[] = $log_entry;
        $log = array_slice($log, -100);
        update_option('aevov_websocket_log', $log);
    }

    /**
     * Load connections from storage
     */
    private function load_connections() {
        $this->connections = get_transient('aevov_websocket_connections') ?: [];
    }

    /**
     * Save connections to storage
     */
    private function save_connections() {
        set_transient('aevov_websocket_connections', $this->connections, 7200); // 2 hours
    }

    /**
     * Shutdown handler
     */
    public function shutdown() {
        if ($this->is_running) {
            $this->stop();
        }
    }

    /**
     * Get all active connections
     *
     * @return array Connections.
     */
    public function get_connections() {
        return $this->connections;
    }
}
