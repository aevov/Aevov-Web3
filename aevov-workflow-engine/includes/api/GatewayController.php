<?php

namespace AevovWorkflowEngine\API;

if (!defined('ABSPATH')) {
    exit;
}

class GatewayController {

    private const NAMESPACE = 'aevov-workflow/v1';
    private array $capabilities = [];
    private ?WorkflowExecutor $executor = null;

    public function __construct() {
        $this->discover_capabilities();
        $this->executor = new WorkflowExecutor($this->capabilities);
    }

    public function register_routes(): void {
        // Capabilities
        register_rest_route(self::NAMESPACE, '/capabilities', [
            'methods' => 'GET',
            'callback' => [$this, 'get_capabilities'],
            'permission_callback' => [$this, 'check_read_permission'],
        ]);

        // Workflow CRUD
        register_rest_route(self::NAMESPACE, '/workflows', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list_workflows'],
                'permission_callback' => [$this, 'check_read_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_workflow'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/workflows/(?P<id>[a-zA-Z0-9-]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_workflow'],
                'permission_callback' => [$this, 'check_read_permission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_workflow'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'delete_workflow'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
        ]);

        // Workflow execution
        register_rest_route(self::NAMESPACE, '/execute', [
            'methods' => 'POST',
            'callback' => [$this, 'execute_workflow'],
            'permission_callback' => [$this, 'check_write_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/workflows/(?P<id>[a-zA-Z0-9-]+)/execute', [
            'methods' => 'POST',
            'callback' => [$this, 'execute_saved_workflow'],
            'permission_callback' => [$this, 'check_write_permission'],
        ]);

        // Execution history
        register_rest_route(self::NAMESPACE, '/executions', [
            'methods' => 'GET',
            'callback' => [$this, 'list_executions'],
            'permission_callback' => [$this, 'check_read_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/executions/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_execution'],
            'permission_callback' => [$this, 'check_read_permission'],
        ]);

        // Templates
        register_rest_route(self::NAMESPACE, '/templates', [
            'methods' => 'GET',
            'callback' => [$this, 'list_templates'],
            'permission_callback' => [$this, 'check_read_permission'],
        ]);

        // Proxy to Aevov capabilities
        register_rest_route(self::NAMESPACE, '/proxy/(?P<capability>[a-z_]+)/(?P<endpoint>.+)', [
            'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
            'callback' => [$this, 'proxy_request'],
            'permission_callback' => [$this, 'check_write_permission'],
        ]);

        // Authentication
        register_rest_route(self::NAMESPACE, '/auth/token', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_token'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/verify', [
            'methods' => 'GET',
            'callback' => [$this, 'verify_token'],
            'permission_callback' => '__return_true',
        ]);

        // Health check
        register_rest_route(self::NAMESPACE, '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'health_check'],
            'permission_callback' => '__return_true',
        ]);

        // Node types (for UI)
        register_rest_route(self::NAMESPACE, '/node-types', [
            'methods' => 'GET',
            'callback' => [$this, 'get_node_types'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function check_read_permission(\WP_REST_Request $request): bool {
        return $this->check_auth($request);
    }

    public function check_write_permission(\WP_REST_Request $request): bool {
        return $this->check_auth($request) && current_user_can('edit_posts');
    }

    private function check_auth(\WP_REST_Request $request): bool {
        // Check token
        $token = $request->get_header('X-Aevov-Token');
        if ($token && $this->validate_token($token)) {
            return true;
        }

        // Check nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return is_user_logged_in();
        }

        // Check if logged in
        return is_user_logged_in();
    }

    private function validate_token(string $full_token): bool {
        $parts = explode('.', $full_token);
        if (count($parts) !== 2) {
            return false;
        }

        [$token, $signature] = $parts;
        $expected_signature = hash_hmac('sha256', $token, wp_salt('auth'));

        if (!hash_equals($expected_signature, $signature)) {
            return false;
        }

        $token_data = json_decode(base64_decode($token), true);
        if (!$token_data || ($token_data['expires'] ?? 0) < time()) {
            return false;
        }

        wp_set_current_user($token_data['user_id']);
        return true;
    }

    public function generate_token(\WP_REST_Request $request): \WP_REST_Response {
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        $user = wp_authenticate($username, $password);
        if (is_wp_error($user)) {
            return new \WP_REST_Response(['error' => 'Invalid credentials'], 401);
        }

        if (!user_can($user, 'edit_posts')) {
            return new \WP_REST_Response(['error' => 'Insufficient permissions'], 403);
        }

        $token_data = [
            'user_id' => $user->ID,
            'issued' => time(),
            'expires' => time() + (7 * DAY_IN_SECONDS),
            'nonce' => wp_generate_password(32, false),
        ];

        $token = base64_encode(json_encode($token_data));
        $signature = hash_hmac('sha256', $token, wp_salt('auth'));
        $full_token = $token . '.' . $signature;

        return new \WP_REST_Response([
            'token' => $full_token,
            'expires' => $token_data['expires'],
            'user' => [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
            ],
        ]);
    }

    public function verify_token(\WP_REST_Request $request): \WP_REST_Response {
        $token = $request->get_header('X-Aevov-Token');
        if (!$token) {
            $auth = $request->get_header('Authorization');
            $token = str_replace('Bearer ', '', $auth);
        }

        $valid = $token && $this->validate_token($token);
        return new \WP_REST_Response(['valid' => $valid], $valid ? 200 : 401);
    }

    public function get_capabilities(\WP_REST_Request $request): \WP_REST_Response {
        $include_unavailable = $request->get_param('include_unavailable') === 'true';

        $capabilities = $this->capabilities;
        if (!$include_unavailable) {
            $capabilities = array_filter($capabilities, fn($cap) => $cap['available']);
        }

        return new \WP_REST_Response([
            'capabilities' => $capabilities,
            'version' => AEVOV_WORKFLOW_ENGINE_VERSION,
        ]);
    }

    public function list_workflows(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflows';

        $page = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(100, max(1, intval($request->get_param('per_page') ?? 20)));
        $offset = ($page - 1) * $per_page;

        $where = 'WHERE is_template = 0';
        $user_id = get_current_user_id();
        if (!current_user_can('manage_options')) {
            $where .= $wpdb->prepare(' AND user_id = %d', $user_id);
        }

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");
        $workflows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, description, is_published, version, created_at, updated_at
             FROM {$table} {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));

        return new \WP_REST_Response([
            'workflows' => $workflows,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ]);
    }

    public function get_workflow(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflows';
        $id = $request->get_param('id');

        $workflow = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %s",
            $id
        ));

        if (!$workflow) {
            return new \WP_REST_Response(['error' => 'Workflow not found'], 404);
        }

        return new \WP_REST_Response([
            'id' => $workflow->id,
            'name' => $workflow->name,
            'description' => $workflow->description,
            'workflow' => json_decode($workflow->workflow_data, true),
            'is_published' => (bool)$workflow->is_published,
            'version' => (int)$workflow->version,
            'created_at' => $workflow->created_at,
            'updated_at' => $workflow->updated_at,
        ]);
    }

    public function create_workflow(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflows';

        $id = wp_generate_uuid4();
        $now = current_time('mysql');

        $result = $wpdb->insert($table, [
            'id' => $id,
            'name' => $request->get_param('name') ?? 'Untitled Workflow',
            'description' => $request->get_param('description') ?? '',
            'workflow_data' => wp_json_encode($request->get_param('workflow') ?? ['nodes' => [], 'edges' => []]),
            'user_id' => get_current_user_id(),
            'is_template' => (int)($request->get_param('is_template') ?? false),
            'is_published' => 0,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($result === false) {
            return new \WP_REST_Response(['error' => 'Failed to create workflow'], 500);
        }

        return new \WP_REST_Response([
            'id' => $id,
            'created' => true,
        ], 201);
    }

    public function update_workflow(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflows';
        $id = $request->get_param('id');

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %s",
            $id
        ));

        if (!$existing) {
            return new \WP_REST_Response(['error' => 'Workflow not found'], 404);
        }

        $data = ['updated_at' => current_time('mysql')];

        if ($request->has_param('name')) {
            $data['name'] = $request->get_param('name');
        }
        if ($request->has_param('description')) {
            $data['description'] = $request->get_param('description');
        }
        if ($request->has_param('workflow')) {
            $data['workflow_data'] = wp_json_encode($request->get_param('workflow'));
            $data['version'] = $existing->version + 1;
        }
        if ($request->has_param('is_published')) {
            $data['is_published'] = (int)$request->get_param('is_published');
        }

        $wpdb->update($table, $data, ['id' => $id]);

        return new \WP_REST_Response([
            'id' => $id,
            'updated' => true,
            'version' => $data['version'] ?? $existing->version,
        ]);
    }

    public function delete_workflow(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflows';
        $id = $request->get_param('id');

        $deleted = $wpdb->delete($table, ['id' => $id]);

        return new \WP_REST_Response([
            'deleted' => $deleted > 0,
        ]);
    }

    public function execute_workflow(\WP_REST_Request $request): \WP_REST_Response {
        $workflow = $request->get_param('workflow');
        $inputs = $request->get_param('inputs') ?? [];

        if (!$workflow || !isset($workflow['nodes'])) {
            return new \WP_REST_Response(['error' => 'Invalid workflow structure'], 400);
        }

        $execution_id = $this->log_execution_start(null, $inputs);
        $result = $this->executor->execute($workflow, $inputs);
        $this->log_execution_end($execution_id, $result);

        $result['execution_id'] = $execution_id;
        return new \WP_REST_Response($result);
    }

    public function execute_saved_workflow(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflows';
        $id = $request->get_param('id');

        $workflow_record = $wpdb->get_row($wpdb->prepare(
            "SELECT workflow_data FROM {$table} WHERE id = %s",
            $id
        ));

        if (!$workflow_record) {
            return new \WP_REST_Response(['error' => 'Workflow not found'], 404);
        }

        $workflow = json_decode($workflow_record->workflow_data, true);
        $inputs = $request->get_param('inputs') ?? [];

        $execution_id = $this->log_execution_start($id, $inputs);
        $result = $this->executor->execute($workflow, $inputs);
        $this->log_execution_end($execution_id, $result);

        $result['execution_id'] = $execution_id;
        return new \WP_REST_Response($result);
    }

    private function log_execution_start(?string $workflow_id, array $inputs): int {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflow_executions';

        $wpdb->insert($table, [
            'workflow_id' => $workflow_id ?? '',
            'user_id' => get_current_user_id(),
            'status' => 'running',
            'inputs' => wp_json_encode($inputs),
            'started_at' => current_time('mysql'),
            'created_at' => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    private function log_execution_end(int $execution_id, array $result): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflow_executions';

        $wpdb->update($table, [
            'status' => $result['success'] ? 'completed' : 'failed',
            'outputs' => wp_json_encode($result['outputs'] ?? $result['error'] ?? null),
            'execution_log' => wp_json_encode($result['log'] ?? []),
            'completed_at' => current_time('mysql'),
        ], ['id' => $execution_id]);
    }

    public function list_executions(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflow_executions';

        $page = max(1, intval($request->get_param('page') ?? 1));
        $per_page = min(100, max(1, intval($request->get_param('per_page') ?? 20)));
        $offset = ($page - 1) * $per_page;

        $where = '1=1';
        $params = [];

        if ($workflow_id = $request->get_param('workflow_id')) {
            $where .= ' AND workflow_id = %s';
            $params[] = $workflow_id;
        }

        if (!current_user_can('manage_options')) {
            $where .= ' AND user_id = %d';
            $params[] = get_current_user_id();
        }

        $params[] = $per_page;
        $params[] = $offset;

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$where}",
            array_slice($params, 0, -2)
        ));

        $executions = $wpdb->get_results($wpdb->prepare(
            "SELECT id, workflow_id, status, started_at, completed_at, created_at
             FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $params
        ));

        return new \WP_REST_Response([
            'executions' => $executions,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
        ]);
    }

    public function get_execution(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflow_executions';
        $id = $request->get_param('id');

        $execution = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));

        if (!$execution) {
            return new \WP_REST_Response(['error' => 'Execution not found'], 404);
        }

        return new \WP_REST_Response([
            'id' => (int)$execution->id,
            'workflow_id' => $execution->workflow_id,
            'status' => $execution->status,
            'inputs' => json_decode($execution->inputs, true),
            'outputs' => json_decode($execution->outputs, true),
            'log' => json_decode($execution->execution_log, true),
            'started_at' => $execution->started_at,
            'completed_at' => $execution->completed_at,
        ]);
    }

    public function list_templates(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflows';

        $templates = $wpdb->get_results(
            "SELECT id, name, description, created_at FROM {$table}
             WHERE is_template = 1 ORDER BY name ASC"
        );

        return new \WP_REST_Response(['templates' => $templates]);
    }

    public function proxy_request(\WP_REST_Request $request): \WP_REST_Response {
        $capability = $request->get_param('capability');
        $endpoint = '/' . $request->get_param('endpoint');

        if (!isset($this->capabilities[$capability])) {
            return new \WP_REST_Response(['error' => 'Unknown capability'], 404);
        }

        $cap_config = $this->capabilities[$capability];
        if (!$cap_config['available']) {
            return new \WP_REST_Response(['error' => 'Capability not available'], 503);
        }

        $internal_request = new \WP_REST_Request(
            $request->get_method(),
            '/' . $cap_config['namespace'] . $endpoint
        );

        $internal_request->set_body($request->get_body());
        $internal_request->set_body_params($request->get_body_params());
        $internal_request->set_query_params($request->get_query_params());

        $response = rest_do_request($internal_request);
        return new \WP_REST_Response(
            $response->get_data(),
            $response->get_status()
        );
    }

    public function health_check(\WP_REST_Request $request): \WP_REST_Response {
        $available = count(array_filter($this->capabilities, fn($c) => $c['available']));

        return new \WP_REST_Response([
            'status' => 'healthy',
            'version' => AEVOV_WORKFLOW_ENGINE_VERSION,
            'timestamp' => time(),
            'capabilities_available' => $available,
            'capabilities_total' => count($this->capabilities),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
        ]);
    }

    public function get_node_types(\WP_REST_Request $request): \WP_REST_Response {
        return new \WP_REST_Response([
            'node_types' => $this->get_node_type_definitions(),
        ]);
    }

    private function discover_capabilities(): void {
        $this->capabilities = [
            'language' => [
                'name' => 'Language Engine',
                'description' => 'Natural language processing and generation',
                'namespace' => 'aevov-language/v1',
                'icon' => 'MessageSquare',
                'color' => '#0ea5e9',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/generate', 'description' => 'Generate text'],
                    ['method' => 'POST', 'route' => '/analyze', 'description' => 'Analyze text'],
                ],
                'available' => class_exists('AevovLanguageEngine\\LanguageEngine'),
            ],
            'image' => [
                'name' => 'Image Engine',
                'description' => 'AI image generation and manipulation',
                'namespace' => 'aevov-image/v1',
                'icon' => 'Image',
                'color' => '#ec4899',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/generate', 'description' => 'Generate image'],
                    ['method' => 'POST', 'route' => '/edit', 'description' => 'Edit image'],
                ],
                'available' => class_exists('AevovImageEngine\\ImageWeaver'),
            ],
            'music' => [
                'name' => 'Music Forge',
                'description' => 'AI music composition',
                'namespace' => 'aevov-music/v1',
                'icon' => 'Music',
                'color' => '#8b5cf6',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/compose', 'description' => 'Compose music'],
                ],
                'available' => class_exists('AevovMusicForge\\MusicWeaver'),
            ],
            'cognitive' => [
                'name' => 'Cognitive Engine',
                'description' => 'Complex reasoning and problem solving',
                'namespace' => 'aevov-cognitive/v1',
                'icon' => 'Brain',
                'color' => '#f97316',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/solve', 'description' => 'Solve problems'],
                ],
                'available' => class_exists('AevovCognitiveEngine\\CognitiveEngine'),
            ],
            'reasoning' => [
                'name' => 'Reasoning Engine',
                'description' => 'Logical inference',
                'namespace' => 'aevov-reasoning/v1',
                'icon' => 'Lightbulb',
                'color' => '#eab308',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/infer', 'description' => 'Make inferences'],
                ],
                'available' => class_exists('AevovReasoningEngine\\ReasoningEngine'),
            ],
            'memory' => [
                'name' => 'Memory Core',
                'description' => 'Persistent memory storage',
                'namespace' => 'aevov-memory/v1',
                'icon' => 'Database',
                'color' => '#14b8a6',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/memory', 'description' => 'Store memory'],
                    ['method' => 'GET', 'route' => '/memory/{address}', 'description' => 'Retrieve memory'],
                ],
                'available' => class_exists('AevovMemoryCore\\MemoryCore'),
            ],
            'embedding' => [
                'name' => 'Embedding Engine',
                'description' => 'Vector embeddings',
                'namespace' => 'aevov-embedding/v1',
                'icon' => 'Layers',
                'color' => '#6366f1',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/embed', 'description' => 'Generate embeddings'],
                ],
                'available' => class_exists('AevovEmbeddingEngine\\EmbeddingEngine'),
            ],
            'transcription' => [
                'name' => 'Transcription',
                'description' => 'Speech to text',
                'namespace' => 'aevov-transcription/v1',
                'icon' => 'Mic',
                'color' => '#f59e0b',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/transcribe', 'description' => 'Transcribe audio'],
                ],
                'available' => class_exists('AevovTranscriptionEngine\\TranscriptionEngine'),
            ],
            'stream' => [
                'name' => 'Stream Engine',
                'description' => 'Video streaming',
                'namespace' => 'aevov-stream/v1',
                'icon' => 'Video',
                'color' => '#ef4444',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/start-session', 'description' => 'Start stream'],
                ],
                'available' => class_exists('AevovStream\\StreamEngine'),
            ],
            'pattern' => [
                'name' => 'Pattern Recognition',
                'description' => 'Pattern detection',
                'namespace' => 'bloom/v1',
                'icon' => 'Scan',
                'color' => '#84cc16',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/process', 'description' => 'Process patterns'],
                ],
                'available' => class_exists('Bloom\\PatternRecognition\\PatternRecognition'),
            ],
            'super_app' => [
                'name' => 'App Generator',
                'description' => 'Generate applications',
                'namespace' => 'aevov-super-app/v1',
                'icon' => 'Rocket',
                'color' => '#f43f5e',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/spawn', 'description' => 'Spawn app'],
                ],
                'available' => class_exists('AevovSuperAppForge\\SuperAppWeaver'),
            ],
            'vision' => [
                'name' => 'Web Scraper',
                'description' => 'Extract web data',
                'namespace' => 'vision-depth/v1',
                'icon' => 'Eye',
                'color' => '#06b6d4',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/scrape', 'description' => 'Scrape web'],
                ],
                'available' => class_exists('AevovVisionDepth\\VisionDepth'),
            ],
            'simulation' => [
                'name' => 'Simulation Engine',
                'description' => 'Physics simulation',
                'namespace' => 'aevov-simulation/v1',
                'icon' => 'Atom',
                'color' => '#a855f7',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/simulate', 'description' => 'Run simulation'],
                ],
                'available' => class_exists('AevovSimulationEngine\\SimulationEngine'),
            ],
            'runtime' => [
                'name' => 'Runtime',
                'description' => 'Workflow scheduling',
                'namespace' => 'aevov-runtime/v1',
                'icon' => 'Clock',
                'color' => '#64748b',
                'endpoints' => [
                    ['method' => 'POST', 'route' => '/execute', 'description' => 'Execute'],
                ],
                'available' => class_exists('AevovRuntime\\Runtime'),
            ],
        ];

        $this->capabilities = apply_filters('aevov_workflow_capabilities', $this->capabilities);
    }

    private function get_node_type_definitions(): array {
        return [
            'input' => [
                'type' => 'input',
                'label' => 'Input',
                'category' => 'input',
                'description' => 'Starting point for workflow data',
                'icon' => 'ArrowRightCircle',
                'color' => '#22c55e',
                'inputs' => [],
                'outputs' => [['id' => 'output', 'label' => 'Output', 'type' => 'any']],
                'configFields' => [
                    ['key' => 'defaultValue', 'label' => 'Default Value', 'type' => 'textarea'],
                ],
            ],
            'output' => [
                'type' => 'output',
                'label' => 'Output',
                'category' => 'output',
                'description' => 'Final result of the workflow',
                'icon' => 'ArrowLeftCircle',
                'color' => '#ef4444',
                'inputs' => [['id' => 'input', 'label' => 'Input', 'type' => 'any']],
                'outputs' => [],
                'configFields' => [],
            ],
            'transform' => [
                'type' => 'transform',
                'label' => 'Transform',
                'category' => 'transform',
                'description' => 'Transform data between nodes',
                'icon' => 'Wand2',
                'color' => '#a855f7',
                'inputs' => [['id' => 'input', 'label' => 'Input', 'type' => 'any']],
                'outputs' => [['id' => 'output', 'label' => 'Output', 'type' => 'any']],
                'configFields' => [
                    ['key' => 'type', 'label' => 'Transform Type', 'type' => 'select', 'options' => [
                        ['value' => 'passthrough', 'label' => 'Passthrough'],
                        ['value' => 'json_parse', 'label' => 'Parse JSON'],
                        ['value' => 'json_stringify', 'label' => 'Stringify JSON'],
                        ['value' => 'extract', 'label' => 'Extract Path'],
                        ['value' => 'template', 'label' => 'Template'],
                    ]],
                ],
            ],
            'condition' => [
                'type' => 'condition',
                'label' => 'Condition',
                'category' => 'control',
                'description' => 'Branch based on condition',
                'icon' => 'GitBranch',
                'color' => '#f59e0b',
                'inputs' => [['id' => 'input', 'label' => 'Input', 'type' => 'any']],
                'outputs' => [
                    ['id' => 'true', 'label' => 'True', 'type' => 'any'],
                    ['id' => 'false', 'label' => 'False', 'type' => 'any'],
                ],
                'configFields' => [
                    ['key' => 'condition', 'label' => 'Condition', 'type' => 'text'],
                ],
            ],
            'loop' => [
                'type' => 'loop',
                'label' => 'Loop',
                'category' => 'control',
                'description' => 'Iterate over array',
                'icon' => 'Repeat',
                'color' => '#f59e0b',
                'inputs' => [['id' => 'items', 'label' => 'Items', 'type' => 'array']],
                'outputs' => [['id' => 'output', 'label' => 'Results', 'type' => 'array']],
                'configFields' => [],
            ],
            'http' => [
                'type' => 'http',
                'label' => 'HTTP Request',
                'category' => 'utility',
                'description' => 'Make HTTP requests',
                'icon' => 'Globe',
                'color' => '#3b82f6',
                'inputs' => [['id' => 'body', 'label' => 'Body', 'type' => 'any']],
                'outputs' => [['id' => 'output', 'label' => 'Response', 'type' => 'any']],
                'configFields' => [
                    ['key' => 'url', 'label' => 'URL', 'type' => 'text'],
                    ['key' => 'method', 'label' => 'Method', 'type' => 'select', 'options' => [
                        ['value' => 'GET', 'label' => 'GET'],
                        ['value' => 'POST', 'label' => 'POST'],
                    ]],
                ],
            ],
            // Capability nodes are dynamically added from $this->capabilities
        ];
    }

    public function get_capabilities_array(): array {
        return $this->capabilities;
    }
}
