<?php
/**
 * WebSocket Server for Application Forge
 *
 * Provides real-time communication for application generation progress.
 * Uses file-based message queue with SSE fallback for WordPress compatibility.
 *
 * @package AevovApplicationForge
 * @since 1.0.0
 */

namespace AevovApplicationForge;

if (!defined('ABSPATH')) {
    exit;
}

class WebSocketServer {

    /**
     * Message queue directory
     *
     * @var string
     */
    private $queue_dir;

    /**
     * Server port
     *
     * @var int
     */
    private $port;

    /**
     * Connected clients tracking
     *
     * @var array
     */
    private $clients = [];

    /**
     * Channel subscriptions
     *
     * @var array
     */
    private $channels = [];

    /**
     * Database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct($config = []) {
        global $wpdb;
        $this->wpdb = $wpdb;

        $upload_dir = wp_upload_dir();
        $this->queue_dir = $upload_dir['basedir'] . '/aevov-websocket-queue';
        $this->port = $config['port'] ?? 8089;

        $this->init_queue_directory();
        $this->create_tables();
    }

    /**
     * Initialize the message queue directory
     *
     * @return void
     */
    private function init_queue_directory() {
        if (!file_exists($this->queue_dir)) {
            wp_mkdir_p($this->queue_dir);

            // Security: prevent directory listing
            file_put_contents($this->queue_dir . '/index.php', '<?php // Silence is golden');
            file_put_contents($this->queue_dir . '/.htaccess', 'deny from all');
        }
    }

    /**
     * Create database tables for message persistence
     *
     * @return void
     */
    private function create_tables() {
        $table_name = $this->wpdb->prefix . 'aevov_ws_messages';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            channel VARCHAR(255) NOT NULL,
            client_id VARCHAR(64) NULL,
            message_type VARCHAR(50) NOT NULL DEFAULT 'broadcast',
            payload LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            delivered TINYINT(1) DEFAULT 0,
            INDEX channel (channel),
            INDEX client_id (client_id),
            INDEX created_at (created_at),
            INDEX delivered (delivered)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Create connections table
        $conn_table = $this->wpdb->prefix . 'aevov_ws_connections';
        $sql_conn = "CREATE TABLE IF NOT EXISTS {$conn_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id VARCHAR(64) NOT NULL UNIQUE,
            channel VARCHAR(255) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            metadata LONGTEXT NULL,
            INDEX channel (channel),
            INDEX user_id (user_id),
            INDEX last_seen (last_seen)
        ) {$charset_collate};";

        dbDelta($sql_conn);
    }

    /**
     * Generate a unique client ID
     *
     * @return string Client ID
     */
    public function generate_client_id() {
        return wp_generate_uuid4();
    }

    /**
     * Register a new client connection
     *
     * @param string $client_id Client identifier
     * @param string $channel Channel to subscribe to
     * @param int|null $user_id WordPress user ID
     * @return bool Success status
     */
    public function connect($client_id, $channel, $user_id = null) {
        $conn_table = $this->wpdb->prefix . 'aevov_ws_connections';

        $result = $this->wpdb->replace(
            $conn_table,
            [
                'client_id' => $client_id,
                'channel' => $channel,
                'user_id' => $user_id,
                'last_seen' => current_time('mysql'),
                'metadata' => json_encode([
                    'ip' => $this->get_client_ip(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'connected_at' => current_time('mysql')
                ])
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );

        if ($result !== false) {
            do_action('aevov_ws_client_connected', $client_id, $channel, $user_id);
            return true;
        }

        return false;
    }

    /**
     * Disconnect a client
     *
     * @param string $client_id Client identifier
     * @return bool Success status
     */
    public function disconnect($client_id) {
        $conn_table = $this->wpdb->prefix . 'aevov_ws_connections';

        $result = $this->wpdb->delete(
            $conn_table,
            ['client_id' => $client_id],
            ['%s']
        );

        if ($result !== false) {
            do_action('aevov_ws_client_disconnected', $client_id);
            return true;
        }

        return false;
    }

    /**
     * Update client heartbeat
     *
     * @param string $client_id Client identifier
     * @return bool Success status
     */
    public function heartbeat($client_id) {
        $conn_table = $this->wpdb->prefix . 'aevov_ws_connections';

        return $this->wpdb->update(
            $conn_table,
            ['last_seen' => current_time('mysql')],
            ['client_id' => $client_id],
            ['%s'],
            ['%s']
        ) !== false;
    }

    /**
     * Broadcast message to all clients on a channel
     *
     * @param mixed $data Data to broadcast
     * @param string $channel Target channel (default: 'default')
     * @return int Number of messages queued
     */
    public function broadcast($data, $channel = 'default') {
        $table_name = $this->wpdb->prefix . 'aevov_ws_messages';

        $payload = is_array($data) || is_object($data) ? json_encode($data) : $data;

        // Store message in database
        $this->wpdb->insert(
            $table_name,
            [
                'channel' => $channel,
                'client_id' => null, // null = broadcast to all
                'message_type' => 'broadcast',
                'payload' => $payload,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600) // 1 hour expiry
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        $message_id = $this->wpdb->insert_id;

        // Also write to file queue for immediate polling
        $this->write_to_file_queue($channel, $payload, $message_id);

        // Fire action for external integrations
        do_action('aevov_ws_broadcast', $data, $channel, $message_id);

        // Log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Aevov WebSocket] Broadcast to channel "%s": %s',
                $channel,
                substr($payload, 0, 200)
            ));
        }

        return $this->get_channel_client_count($channel);
    }

    /**
     * Send message to specific client
     *
     * @param string $client_id Target client
     * @param mixed $data Data to send
     * @return bool Success status
     */
    public function send($client_id, $data) {
        $table_name = $this->wpdb->prefix . 'aevov_ws_messages';

        $payload = is_array($data) || is_object($data) ? json_encode($data) : $data;

        $result = $this->wpdb->insert(
            $table_name,
            [
                'channel' => $this->get_client_channel($client_id),
                'client_id' => $client_id,
                'message_type' => 'direct',
                'payload' => $payload,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600)
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        if ($result !== false) {
            do_action('aevov_ws_direct_message', $client_id, $data);
            return true;
        }

        return false;
    }

    /**
     * Get pending messages for a client (polling endpoint)
     *
     * @param string $client_id Client identifier
     * @param int $since_id Get messages after this ID
     * @return array Messages
     */
    public function get_messages($client_id, $since_id = 0) {
        $table_name = $this->wpdb->prefix . 'aevov_ws_messages';
        $channel = $this->get_client_channel($client_id);

        if (!$channel) {
            return [];
        }

        // Update heartbeat
        $this->heartbeat($client_id);

        // Get messages for this client (broadcasts + direct messages)
        $messages = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT id, message_type, payload, created_at
             FROM {$table_name}
             WHERE channel = %s
             AND id > %d
             AND (client_id IS NULL OR client_id = %s)
             AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY id ASC
             LIMIT 100",
            $channel,
            $since_id,
            $client_id
        ), ARRAY_A);

        // Mark direct messages as delivered
        if (!empty($messages)) {
            $message_ids = array_column($messages, 'id');
            $ids_placeholder = implode(',', array_fill(0, count($message_ids), '%d'));

            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$table_name} SET delivered = 1
                 WHERE id IN ({$ids_placeholder}) AND client_id = %s",
                array_merge($message_ids, [$client_id])
            ));
        }

        // Decode payloads
        foreach ($messages as &$message) {
            $decoded = json_decode($message['payload'], true);
            $message['payload'] = $decoded !== null ? $decoded : $message['payload'];
        }

        return $messages;
    }

    /**
     * Write message to file queue for immediate availability
     *
     * @param string $channel Channel name
     * @param string $payload Message payload
     * @param int $message_id Database message ID
     * @return bool Success status
     */
    private function write_to_file_queue($channel, $payload, $message_id) {
        $channel_dir = $this->queue_dir . '/' . sanitize_file_name($channel);

        if (!file_exists($channel_dir)) {
            wp_mkdir_p($channel_dir);
        }

        $filename = $channel_dir . '/' . $message_id . '.json';

        return file_put_contents($filename, json_encode([
            'id' => $message_id,
            'payload' => $payload,
            'timestamp' => time()
        ])) !== false;
    }

    /**
     * Get client's subscribed channel
     *
     * @param string $client_id Client identifier
     * @return string|null Channel name
     */
    private function get_client_channel($client_id) {
        $conn_table = $this->wpdb->prefix . 'aevov_ws_connections';

        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT channel FROM {$conn_table} WHERE client_id = %s",
            $client_id
        ));
    }

    /**
     * Get number of clients connected to a channel
     *
     * @param string $channel Channel name
     * @return int Client count
     */
    public function get_channel_client_count($channel) {
        $conn_table = $this->wpdb->prefix . 'aevov_ws_connections';

        // Only count clients seen in last 5 minutes
        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$conn_table}
             WHERE channel = %s
             AND last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            $channel
        ));
    }

    /**
     * Get all clients on a channel
     *
     * @param string $channel Channel name
     * @return array Client IDs
     */
    public function get_channel_clients($channel) {
        $conn_table = $this->wpdb->prefix . 'aevov_ws_connections';

        return $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT client_id FROM {$conn_table}
             WHERE channel = %s
             AND last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            $channel
        ));
    }

    /**
     * Clean up expired messages and stale connections
     *
     * @return array Cleanup statistics
     */
    public function cleanup() {
        $msg_table = $this->wpdb->prefix . 'aevov_ws_messages';
        $conn_table = $this->wpdb->prefix . 'aevov_ws_connections';

        // Delete expired messages
        $expired_messages = $this->wpdb->query(
            "DELETE FROM {$msg_table} WHERE expires_at < NOW()"
        );

        // Delete stale connections (no heartbeat in 10 minutes)
        $stale_connections = $this->wpdb->query(
            "DELETE FROM {$conn_table} WHERE last_seen < DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
        );

        // Clean up file queue
        $this->cleanup_file_queue();

        return [
            'expired_messages' => $expired_messages,
            'stale_connections' => $stale_connections
        ];
    }

    /**
     * Clean up old files from the file queue
     *
     * @return int Number of files deleted
     */
    private function cleanup_file_queue() {
        $deleted = 0;
        $max_age = 3600; // 1 hour

        $dirs = glob($this->queue_dir . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $files = glob($dir . '/*.json');

            foreach ($files as $file) {
                if (filemtime($file) < time() - $max_age) {
                    if (unlink($file)) {
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }

    /**
     * Get server status information
     *
     * @return array Status information
     */
    public function get_status() {
        $conn_table = $this->wpdb->prefix . 'aevov_ws_connections';
        $msg_table = $this->wpdb->prefix . 'aevov_ws_messages';

        $active_connections = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$conn_table}
             WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );

        $pending_messages = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$msg_table}
             WHERE delivered = 0 AND (expires_at IS NULL OR expires_at > NOW())"
        );

        $channels = $this->wpdb->get_results(
            "SELECT channel, COUNT(*) as clients
             FROM {$conn_table}
             WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             GROUP BY channel",
            ARRAY_A
        );

        return [
            'active_connections' => (int) $active_connections,
            'pending_messages' => (int) $pending_messages,
            'channels' => $channels,
            'queue_directory' => $this->queue_dir,
            'port' => $this->port
        ];
    }

    /**
     * Get WebSocket connection URL for clients
     *
     * @param string $channel Channel to connect to
     * @return string Connection URL
     */
    public function get_connection_url($channel = 'default') {
        // Return REST API polling endpoint as fallback
        // In production, this could return a real WebSocket URL if running Ratchet
        return rest_url('aevov-forge/v1/ws/poll') . '?channel=' . urlencode($channel);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        }
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /**
     * Send progress update for a job
     *
     * @param string $job_id Job identifier
     * @param int $progress Progress percentage (0-100)
     * @param string $status Status message
     * @param array $data Additional data
     * @return int Number of clients notified
     */
    public function send_progress($job_id, $progress, $status, $data = []) {
        return $this->broadcast([
            'type' => 'progress',
            'job_id' => $job_id,
            'progress' => min(100, max(0, $progress)),
            'status' => $status,
            'data' => $data,
            'timestamp' => current_time('mysql')
        ], 'job_' . $job_id);
    }

    /**
     * Send completion notification for a job
     *
     * @param string $job_id Job identifier
     * @param mixed $result Job result
     * @return int Number of clients notified
     */
    public function send_complete($job_id, $result) {
        return $this->broadcast([
            'type' => 'complete',
            'job_id' => $job_id,
            'progress' => 100,
            'status' => 'completed',
            'result' => $result,
            'timestamp' => current_time('mysql')
        ], 'job_' . $job_id);
    }

    /**
     * Send error notification for a job
     *
     * @param string $job_id Job identifier
     * @param string $error Error message
     * @param array $details Error details
     * @return int Number of clients notified
     */
    public function send_error($job_id, $error, $details = []) {
        return $this->broadcast([
            'type' => 'error',
            'job_id' => $job_id,
            'error' => $error,
            'details' => $details,
            'timestamp' => current_time('mysql')
        ], 'job_' . $job_id);
    }
}
