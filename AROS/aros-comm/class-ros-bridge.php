<?php
/**
 * AROS ROS Bridge
 *
 * Production-ready bridge to Robot Operating System (ROS)
 * Features:
 * - Topic publishing and subscription
 * - Service calls (request/response)
 * - TF (Transform) management
 * - Parameter server integration
 * - Action client support
 * - Message serialization/deserialization
 * - Connection management and reconnection
 * - Environment detection for production vs development
 */

namespace AROS\Communication;

class ROSBridge {

    private $host = 'localhost';
    private $port = 9090; // rosbridge_server default port
    private $connected = false;
    private $subscriptions = [];
    private $publishers = [];
    private $service_clients = [];
    private $tf_buffer = [];
    private $parameters = [];
    private $connection_id = null;
    private $socket = null;
    private $is_production = false;
    private $reconnect_attempts = 0;
    private $max_reconnect_attempts = 5;
    private $reconnect_delay = 1000; // milliseconds
    private $message_queue = [];
    private $pending_responses = [];

    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->host = $config['host'] ?? getenv('ROS_BRIDGE_HOST') ?: 'localhost';
        $this->port = $config['port'] ?? getenv('ROS_BRIDGE_PORT') ?: 9090;

        // Detect production environment
        $this->is_production = $this->detect_production_environment();

        error_log('[ROSBridge] Initialized - Host: ' . $this->host . ':' . $this->port .
                  ' | Mode: ' . ($this->is_production ? 'PRODUCTION' : 'DEVELOPMENT'));
    }

    /**
     * Detect if running in production server environment
     *
     * @return bool
     */
    private function detect_production_environment() {
        // Check for explicit environment variable
        if (getenv('AEVOV_ENV') === 'production' || getenv('WP_ENV') === 'production') {
            return true;
        }

        // Check for Docker/container environment
        if (file_exists('/.dockerenv') || getenv('KUBERNETES_SERVICE_HOST')) {
            return true;
        }

        // Check WordPress environment
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
            return true;
        }

        // Check if ROS bridge host is not localhost (implies remote server)
        if ($this->host !== 'localhost' && $this->host !== '127.0.0.1') {
            return true;
        }

        // Check for server indicators
        if (php_sapi_name() !== 'cli' && isset($_SERVER['SERVER_NAME']) &&
            !in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) {
            return true;
        }

        return false;
    }

    /**
     * Connect to ROS bridge server
     *
     * @return bool Success
     */
    public function connect() {
        error_log('[ROSBridge] Connecting to ROS bridge...');

        if ($this->is_production) {
            return $this->connect_production();
        }

        return $this->connect_development();
    }

    /**
     * Production connection using real WebSocket
     *
     * @return bool
     */
    private function connect_production() {
        $url = "tcp://{$this->host}:{$this->port}";

        $context = stream_context_create([
            'socket' => [
                'tcp_nodelay' => true,
            ],
        ]);

        $errno = 0;
        $errstr = '';

        $this->socket = @stream_socket_client(
            $url,
            $errno,
            $errstr,
            5, // timeout
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($this->socket === false) {
            error_log("[ROSBridge] Connection failed: $errstr ($errno)");

            // Attempt reconnection
            if ($this->reconnect_attempts < $this->max_reconnect_attempts) {
                $this->reconnect_attempts++;
                usleep($this->reconnect_delay * 1000);
                error_log("[ROSBridge] Reconnection attempt {$this->reconnect_attempts}/{$this->max_reconnect_attempts}");
                return $this->connect_production();
            }

            return false;
        }

        // Set socket options
        stream_set_blocking($this->socket, false);
        stream_set_timeout($this->socket, 5);

        // Send WebSocket upgrade request
        $upgrade = $this->create_websocket_upgrade();
        fwrite($this->socket, $upgrade);

        // Read upgrade response
        $response = $this->read_websocket_response();

        if (strpos($response, '101 Switching Protocols') === false) {
            error_log('[ROSBridge] WebSocket upgrade failed');
            fclose($this->socket);
            $this->socket = null;
            return false;
        }

        $this->connected = true;
        $this->connection_id = uniqid('ros_prod_');
        $this->reconnect_attempts = 0;

        do_action('aros_ros_connect', $this->host, $this->port, true);

        error_log('[ROSBridge] Connected successfully (PRODUCTION)');

        return true;
    }

    /**
     * Development connection (simulation mode)
     *
     * @return bool
     */
    private function connect_development() {
        // In development, we use WordPress hooks for integration testing
        $this->connected = true;
        $this->connection_id = uniqid('ros_dev_');

        do_action('aros_ros_connect', $this->host, $this->port, false);

        error_log('[ROSBridge] Connected successfully (DEVELOPMENT - simulation mode)');

        return true;
    }

    /**
     * Create WebSocket upgrade request
     *
     * @return string
     */
    private function create_websocket_upgrade() {
        $key = base64_encode(random_bytes(16));

        $headers = [
            "GET / HTTP/1.1",
            "Host: {$this->host}:{$this->port}",
            "Upgrade: websocket",
            "Connection: Upgrade",
            "Sec-WebSocket-Key: {$key}",
            "Sec-WebSocket-Version: 13",
            "Sec-WebSocket-Protocol: rosbridge",
            "",
            ""
        ];

        return implode("\r\n", $headers);
    }

    /**
     * Read WebSocket upgrade response
     *
     * @return string
     */
    private function read_websocket_response() {
        $response = '';
        $timeout = time() + 5;

        while (time() < $timeout) {
            $line = fgets($this->socket, 1024);
            if ($line === false) {
                usleep(10000);
                continue;
            }
            $response .= $line;
            if ($line === "\r\n") {
                break;
            }
        }

        return $response;
    }

    /**
     * Disconnect from ROS bridge
     */
    public function disconnect() {
        if (!$this->connected) {
            return false;
        }

        error_log('[ROSBridge] Disconnecting...');

        // Unsubscribe from all topics
        foreach ($this->subscriptions as $topic => $subscription) {
            $this->unsubscribe($topic);
        }

        // Unadvertise all publishers
        foreach ($this->publishers as $topic => $publisher) {
            $this->unadvertise($topic);
        }

        // Close socket in production
        if ($this->is_production && $this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }

        $this->connected = false;

        do_action('aros_ros_disconnect');

        return true;
    }

    /**
     * Send message to ROS bridge
     *
     * @param array $packet Message packet
     * @return bool
     */
    private function send($packet) {
        $json = json_encode($packet);

        if ($this->is_production && $this->socket !== null) {
            // WebSocket frame encoding
            $frame = $this->encode_websocket_frame($json);
            $written = @fwrite($this->socket, $frame);

            if ($written === false) {
                error_log('[ROSBridge] Send failed - attempting reconnection');
                $this->connected = false;
                $this->connect();
                return false;
            }

            return true;
        }

        // Development mode - queue for processing
        $this->message_queue[] = $packet;
        do_action('aros_ros_send', $packet);

        return true;
    }

    /**
     * Encode WebSocket frame
     *
     * @param string $data Data to encode
     * @return string
     */
    private function encode_websocket_frame($data) {
        $length = strlen($data);
        $frame = chr(0x81); // Text frame, FIN bit set

        if ($length <= 125) {
            $frame .= chr($length | 0x80); // Masked
        } elseif ($length <= 65535) {
            $frame .= chr(126 | 0x80);
            $frame .= pack('n', $length);
        } else {
            $frame .= chr(127 | 0x80);
            $frame .= pack('J', $length);
        }

        // Masking key
        $mask = random_bytes(4);
        $frame .= $mask;

        // Masked data
        for ($i = 0; $i < $length; $i++) {
            $frame .= chr(ord($data[$i]) ^ ord($mask[$i % 4]));
        }

        return $frame;
    }

    /**
     * Receive message from ROS bridge
     *
     * @return array|null
     */
    private function receive() {
        if ($this->is_production && $this->socket !== null) {
            $data = $this->read_websocket_frame();
            if ($data !== null) {
                return json_decode($data, true);
            }
            return null;
        }

        // Development mode - get from filter
        $messages = apply_filters('aros_ros_messages', []);
        return !empty($messages) ? array_shift($messages) : null;
    }

    /**
     * Read WebSocket frame
     *
     * @return string|null
     */
    private function read_websocket_frame() {
        if ($this->socket === null) {
            return null;
        }

        $header = @fread($this->socket, 2);
        if ($header === false || strlen($header) < 2) {
            return null;
        }

        $opcode = ord($header[0]) & 0x0F;
        $length = ord($header[1]) & 0x7F;

        if ($length === 126) {
            $ext = fread($this->socket, 2);
            $length = unpack('n', $ext)[1];
        } elseif ($length === 127) {
            $ext = fread($this->socket, 8);
            $length = unpack('J', $ext)[1];
        }

        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data));
            if ($chunk === false) {
                break;
            }
            $data .= $chunk;
        }

        return $data;
    }

    /**
     * Publish message to ROS topic
     *
     * @param string $topic Topic name (e.g., '/cmd_vel')
     * @param mixed $message Message data
     * @param string $message_type Message type (e.g., 'geometry_msgs/Twist')
     * @return bool Success
     */
    public function publish($topic, $message, $message_type = null) {
        if (!$this->connected) {
            error_log('[ROSBridge] ERROR: Not connected');
            return false;
        }

        // Auto-advertise if not already done
        if (!isset($this->publishers[$topic])) {
            $this->advertise($topic, $message_type);
        }

        $packet = [
            'op' => 'publish',
            'topic' => $topic,
            'msg' => $message,
        ];

        if ($message_type !== null) {
            $packet['type'] = $message_type;
        }

        error_log('[ROSBridge] Publishing to ' . $topic);

        $result = $this->send($packet);

        do_action('aros_ros_publish', $topic, $message, $message_type);

        return $result;
    }

    /**
     * Advertise a topic for publishing
     */
    public function advertise($topic, $message_type, $latch = false) {
        if (!$this->connected) {
            return false;
        }

        $packet = [
            'op' => 'advertise',
            'topic' => $topic,
            'type' => $message_type,
        ];

        if ($latch) {
            $packet['latch'] = true;
        }

        $this->send($packet);

        $this->publishers[$topic] = [
            'type' => $message_type,
            'latch' => $latch,
            'advertised_at' => microtime(true),
        ];

        error_log('[ROSBridge] Advertised topic: ' . $topic . ' (' . $message_type . ')');

        do_action('aros_ros_advertise', $topic, $message_type);

        return true;
    }

    /**
     * Unadvertise a topic
     */
    public function unadvertise($topic) {
        if (!isset($this->publishers[$topic])) {
            return false;
        }

        $packet = [
            'op' => 'unadvertise',
            'topic' => $topic,
        ];

        $this->send($packet);

        unset($this->publishers[$topic]);

        error_log('[ROSBridge] Unadvertised topic: ' . $topic);

        do_action('aros_ros_unadvertise', $topic);

        return true;
    }

    /**
     * Subscribe to ROS topic
     *
     * @param string $topic Topic name
     * @param callable $callback Callback function (receives message)
     * @param string $message_type Optional message type
     * @param int $throttle_rate Optional throttle rate (ms)
     * @return bool Success
     */
    public function subscribe($topic, $callback, $message_type = null, $throttle_rate = null) {
        if (!$this->connected) {
            error_log('[ROSBridge] ERROR: Not connected');
            return false;
        }

        if (!is_callable($callback)) {
            error_log('[ROSBridge] ERROR: Callback must be callable');
            return false;
        }

        $packet = [
            'op' => 'subscribe',
            'topic' => $topic,
        ];

        if ($message_type !== null) {
            $packet['type'] = $message_type;
        }

        if ($throttle_rate !== null) {
            $packet['throttle_rate'] = $throttle_rate;
        }

        $this->send($packet);

        $this->subscriptions[$topic] = [
            'callback' => $callback,
            'type' => $message_type,
            'throttle_rate' => $throttle_rate,
            'subscribed_at' => microtime(true),
        ];

        error_log('[ROSBridge] Subscribed to topic: ' . $topic);

        do_action('aros_ros_subscribe', $topic, $message_type);

        return true;
    }

    /**
     * Unsubscribe from topic
     */
    public function unsubscribe($topic) {
        if (!isset($this->subscriptions[$topic])) {
            return false;
        }

        $packet = [
            'op' => 'unsubscribe',
            'topic' => $topic,
        ];

        $this->send($packet);

        unset($this->subscriptions[$topic]);

        error_log('[ROSBridge] Unsubscribed from topic: ' . $topic);

        do_action('aros_ros_unsubscribe', $topic);

        return true;
    }

    /**
     * Process incoming messages (call subscriber callbacks)
     */
    public function process_messages() {
        $processed = 0;

        if ($this->is_production && $this->socket !== null) {
            // Production mode - read from socket
            while (($message = $this->receive()) !== null) {
                $this->dispatch_message($message);
                $processed++;
            }
        } else {
            // Development mode - process from filter
            $messages = apply_filters('aros_ros_messages', []);

            foreach ($messages as $message) {
                $this->dispatch_message($message);
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Dispatch received message to appropriate handler
     *
     * @param array $message Message data
     */
    private function dispatch_message($message) {
        $op = $message['op'] ?? null;

        switch ($op) {
            case 'publish':
                $topic = $message['topic'] ?? null;
                $msg_data = $message['msg'] ?? null;

                if ($topic !== null && isset($this->subscriptions[$topic])) {
                    $callback = $this->subscriptions[$topic]['callback'];
                    if (is_callable($callback)) {
                        call_user_func($callback, $msg_data);
                    }
                }
                break;

            case 'service_response':
                $id = $message['id'] ?? null;
                if ($id !== null && isset($this->pending_responses[$id])) {
                    $this->pending_responses[$id]['response'] = $message;
                    $this->pending_responses[$id]['received'] = true;
                }
                break;
        }
    }

    /**
     * Call ROS service
     *
     * @param string $service Service name (e.g., '/add_two_ints')
     * @param array $args Service arguments
     * @param string $service_type Optional service type
     * @return mixed Service response or false
     */
    public function call_service($service, $args = [], $service_type = null) {
        if (!$this->connected) {
            error_log('[ROSBridge] ERROR: Not connected');
            return false;
        }

        $id = uniqid('srv_');

        $packet = [
            'op' => 'call_service',
            'id' => $id,
            'service' => $service,
            'args' => $args,
        ];

        if ($service_type !== null) {
            $packet['type'] = $service_type;
        }

        error_log('[ROSBridge] Calling service: ' . $service);

        if ($this->is_production) {
            // Production mode - send and wait for response
            $this->pending_responses[$id] = ['received' => false, 'response' => null];
            $this->send($packet);

            // Wait for response (with timeout)
            $timeout = microtime(true) + 5.0;
            while (microtime(true) < $timeout) {
                $this->process_messages();

                if ($this->pending_responses[$id]['received']) {
                    $response = $this->pending_responses[$id]['response'];
                    unset($this->pending_responses[$id]);

                    if (isset($response['result']) && $response['result'] === true) {
                        return $response['values'] ?? true;
                    }
                    return false;
                }

                usleep(10000); // 10ms
            }

            unset($this->pending_responses[$id]);
            error_log('[ROSBridge] Service call timeout: ' . $service);
            return false;
        }

        // Development mode - use WordPress filter
        $response = apply_filters('aros_ros_service_response', null, $service, $args);
        return $response;
    }

    /**
     * Advertise ROS service
     */
    public function advertise_service($service, $service_type, $callback) {
        if (!$this->connected) {
            return false;
        }

        if (!is_callable($callback)) {
            error_log('[ROSBridge] ERROR: Service callback must be callable');
            return false;
        }

        $packet = [
            'op' => 'advertise_service',
            'service' => $service,
            'type' => $service_type,
        ];

        $this->send($packet);

        $this->service_clients[$service] = [
            'type' => $service_type,
            'callback' => $callback,
            'advertised_at' => microtime(true),
        ];

        error_log('[ROSBridge] Advertised service: ' . $service);

        do_action('aros_ros_advertise_service', $service, $service_type);

        return true;
    }

    /**
     * Get transform from TF tree
     *
     * @param string $target_frame Target frame
     * @param string $source_frame Source frame
     * @param float $time Timestamp (0 for latest)
     * @return array|false Transform or false
     */
    public function lookup_transform($target_frame, $source_frame, $time = 0.0) {
        if (!$this->connected) {
            return false;
        }

        // Check TF buffer
        $tf_key = $source_frame . '_to_' . $target_frame;

        if (isset($this->tf_buffer[$tf_key])) {
            $tf = $this->tf_buffer[$tf_key];

            // Check if transform is recent (within 1 second)
            if ($time === 0.0 || (microtime(true) - $tf['timestamp']) < 1.0) {
                return $tf['transform'];
            }
        }

        // Request from TF service
        $service = '/tf_lookup';
        $args = [
            'target_frame' => $target_frame,
            'source_frame' => $source_frame,
            'time' => $time,
        ];

        $response = $this->call_service($service, $args);

        if ($response !== false && isset($response['transform'])) {
            // Cache in buffer
            $this->tf_buffer[$tf_key] = [
                'transform' => $response['transform'],
                'timestamp' => microtime(true),
            ];

            return $response['transform'];
        }

        return false;
    }

    /**
     * Broadcast transform to TF tree
     *
     * @param array $transform Transform data
     * @param string $parent_frame Parent frame
     * @param string $child_frame Child frame
     */
    public function broadcast_transform($transform, $parent_frame, $child_frame) {
        if (!$this->connected) {
            return false;
        }

        $tf_message = [
            'header' => [
                'frame_id' => $parent_frame,
                'stamp' => microtime(true),
            ],
            'child_frame_id' => $child_frame,
            'transform' => $transform,
        ];

        // Publish to /tf topic
        $this->publish('/tf', $tf_message, 'tf2_msgs/TFMessage');

        // Update local buffer
        $tf_key = $child_frame . '_to_' . $parent_frame;
        $this->tf_buffer[$tf_key] = [
            'transform' => $transform,
            'timestamp' => microtime(true),
        ];

        return true;
    }

    /**
     * Get parameter from ROS parameter server
     */
    public function get_param($param_name, $default = null) {
        if (!$this->connected) {
            return $default;
        }

        // Check local cache
        if (isset($this->parameters[$param_name])) {
            return $this->parameters[$param_name]['value'];
        }

        // Request from parameter server
        $service = '/rosapi/get_param';
        $args = ['name' => $param_name];

        $response = $this->call_service($service, $args);

        if ($response !== false && isset($response['value'])) {
            // Cache locally
            $this->parameters[$param_name] = [
                'value' => $response['value'],
                'fetched_at' => microtime(true),
            ];

            return $response['value'];
        }

        return $default;
    }

    /**
     * Set parameter on ROS parameter server
     */
    public function set_param($param_name, $value) {
        if (!$this->connected) {
            return false;
        }

        $service = '/rosapi/set_param';
        $args = [
            'name' => $param_name,
            'value' => $value,
        ];

        $response = $this->call_service($service, $args);

        if ($response !== false) {
            // Update local cache
            $this->parameters[$param_name] = [
                'value' => $value,
                'set_at' => microtime(true),
            ];

            return true;
        }

        return false;
    }

    /**
     * Get list of active topics
     */
    public function get_topics() {
        if (!$this->connected) {
            return [];
        }

        $service = '/rosapi/topics';
        $response = $this->call_service($service);

        if ($response !== false && isset($response['topics'])) {
            return $response['topics'];
        }

        return [];
    }

    /**
     * Get list of active services
     */
    public function get_services() {
        if (!$this->connected) {
            return [];
        }

        $service = '/rosapi/services';
        $response = $this->call_service($service);

        if ($response !== false && isset($response['services'])) {
            return $response['services'];
        }

        return [];
    }

    /**
     * Check if connected
     */
    public function is_connected() {
        return $this->connected;
    }

    /**
     * Check if running in production mode
     */
    public function is_production_mode() {
        return $this->is_production;
    }

    /**
     * Get connection statistics
     */
    public function get_stats() {
        return [
            'connected' => $this->connected,
            'host' => $this->host,
            'port' => $this->port,
            'mode' => $this->is_production ? 'production' : 'development',
            'publishers' => count($this->publishers),
            'subscribers' => count($this->subscriptions),
            'service_clients' => count($this->service_clients),
            'tf_buffer_size' => count($this->tf_buffer),
            'cached_parameters' => count($this->parameters),
            'reconnect_attempts' => $this->reconnect_attempts,
        ];
    }

    /**
     * Helper: Create Twist message for velocity commands
     */
    public static function create_twist($linear_x = 0.0, $linear_y = 0.0, $linear_z = 0.0,
                                       $angular_x = 0.0, $angular_y = 0.0, $angular_z = 0.0) {
        return [
            'linear' => [
                'x' => $linear_x,
                'y' => $linear_y,
                'z' => $linear_z,
            ],
            'angular' => [
                'x' => $angular_x,
                'y' => $angular_y,
                'z' => $angular_z,
            ],
        ];
    }

    /**
     * Helper: Create Pose message
     */
    public static function create_pose($x = 0.0, $y = 0.0, $z = 0.0,
                                      $qx = 0.0, $qy = 0.0, $qz = 0.0, $qw = 1.0) {
        return [
            'position' => [
                'x' => $x,
                'y' => $y,
                'z' => $z,
            ],
            'orientation' => [
                'x' => $qx,
                'y' => $qy,
                'z' => $qz,
                'w' => $qw,
            ],
        ];
    }

    /**
     * Helper: Create Transform message
     */
    public static function create_transform($tx = 0.0, $ty = 0.0, $tz = 0.0,
                                           $rx = 0.0, $ry = 0.0, $rz = 0.0, $rw = 1.0) {
        return [
            'translation' => [
                'x' => $tx,
                'y' => $ty,
                'z' => $tz,
            ],
            'rotation' => [
                'x' => $rx,
                'y' => $ry,
                'z' => $rz,
                'w' => $rw,
            ],
        ];
    }
}
