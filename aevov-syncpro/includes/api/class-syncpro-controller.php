<?php
/**
 * SyncPro REST API Controller
 *
 * Provides REST API endpoints for AevSyncPro functionality.
 *
 * @package AevovSyncPro
 */

namespace AevovSyncPro\Api;

use AevovSyncPro\Providers\SystemContextProvider;
use AevovSyncPro\Providers\AIOrchestrator;
use AevovSyncPro\Sync\DatabaseSyncController;
use AevovSyncPro\Generators\ConfigurationGenerator;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SyncPro Controller Class
 */
class SyncProController extends WP_REST_Controller {

    protected $namespace = 'aevov-syncpro/v1';

    private SystemContextProvider $context_provider;
    private AIOrchestrator $ai_orchestrator;
    private DatabaseSyncController $db_sync;
    private ConfigurationGenerator $config_generator;

    public function __construct(
        SystemContextProvider $context_provider,
        AIOrchestrator $ai_orchestrator,
        DatabaseSyncController $db_sync,
        ConfigurationGenerator $config_generator
    ) {
        $this->context_provider = $context_provider;
        $this->ai_orchestrator = $ai_orchestrator;
        $this->db_sync = $db_sync;
        $this->config_generator = $config_generator;
    }

    /**
     * Register REST routes
     */
    public function register_routes(): void {
        // System Context
        register_rest_route($this->namespace, '/context', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_context'],
                'permission_callback' => [$this, 'check_read_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/context/(?P<component>[a-z_]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_component_context'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'component' => [
                        'required' => true,
                        'validate_callback' => [$this, 'validate_component'],
                    ],
                ],
            ],
        ]);

        // Workflow Generation
        register_rest_route($this->namespace, '/generate/workflow', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate_workflow'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => [
                    'prompt' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'options' => [
                        'required' => false,
                        'type' => 'object',
                        'default' => [],
                    ],
                ],
            ],
        ]);

        // Configuration Generation
        register_rest_route($this->namespace, '/generate/config', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate_config'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => [
                    'target' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'all',
                    ],
                    'requirements' => [
                        'required' => true,
                        'type' => 'object',
                    ],
                    'options' => [
                        'required' => false,
                        'type' => 'object',
                        'default' => [],
                    ],
                ],
            ],
        ]);

        // Configuration Bundle
        register_rest_route($this->namespace, '/bundle', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate_bundle'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => [
                    'requirements' => [
                        'required' => true,
                        'type' => 'object',
                    ],
                    'options' => [
                        'required' => false,
                        'type' => 'object',
                        'default' => [],
                    ],
                ],
            ],
        ]);

        // Apply Configuration
        register_rest_route($this->namespace, '/apply', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'apply_configuration'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'bundle' => [
                        'required' => true,
                        'type' => 'object',
                    ],
                    'dry_run' => [
                        'required' => false,
                        'type' => 'boolean',
                        'default' => false,
                    ],
                ],
            ],
        ]);

        // Sync Operations
        register_rest_route($this->namespace, '/sync/start', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'start_sync_session'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/sync/status', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_sync_status'],
                'permission_callback' => [$this, 'check_read_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/sync/rollback/(?P<execution_id>\d+)', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'rollback_execution'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'execution_id' => [
                        'required' => true,
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        },
                    ],
                ],
            ],
        ]);

        // Templates
        register_rest_route($this->namespace, '/templates', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_templates'],
                'permission_callback' => [$this, 'check_read_permission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_template'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/templates/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_template'],
                'permission_callback' => [$this, 'check_read_permission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_template'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_template'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);

        // Configurations History
        register_rest_route($this->namespace, '/history', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_config_history'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'limit' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 20,
                    ],
                    'offset' => [
                        'required' => false,
                        'type' => 'integer',
                        'default' => 0,
                    ],
                ],
            ],
        ]);

        // Execute SyncPro node (for workflow engine integration)
        register_rest_route($this->namespace, '/execute', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'execute_syncpro'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => [
                    'mode' => [
                        'required' => true,
                        'type' => 'string',
                        'enum' => ['analyze', 'generate', 'modify'],
                    ],
                    'target' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'all',
                    ],
                    'input' => [
                        'required' => true,
                        'type' => 'object',
                    ],
                ],
            ],
        ]);

        // Health check
        register_rest_route($this->namespace, '/health', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'health_check'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // Export/Import
        register_rest_route($this->namespace, '/export', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'export_configuration'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'bundle' => [
                        'required' => true,
                        'type' => 'object',
                    ],
                    'format' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'json',
                        'enum' => ['json', 'yaml', 'php'],
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/import', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'import_configuration'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'content' => [
                        'required' => true,
                        'type' => 'string',
                    ],
                    'format' => [
                        'required' => false,
                        'type' => 'string',
                        'default' => 'json',
                    ],
                    'apply' => [
                        'required' => false,
                        'type' => 'boolean',
                        'default' => false,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Permission callbacks
     */
    public function check_read_permission(): bool {
        return is_user_logged_in();
    }

    public function check_write_permission(): bool {
        return current_user_can('edit_posts');
    }

    public function check_admin_permission(): bool {
        return current_user_can('manage_options');
    }

    public function validate_component($param): bool {
        $valid_components = ['ai_engines', 'storage', 'workflows', 'patterns', 'memory', 'network', 'security'];
        return in_array($param, $valid_components, true);
    }

    /**
     * Get full system context
     */
    public function get_context(WP_REST_Request $request): WP_REST_Response {
        $context = $this->context_provider->get_full_context();

        return new WP_REST_Response([
            'success' => true,
            'context' => $context,
        ]);
    }

    /**
     * Get specific component context
     */
    public function get_component_context(WP_REST_Request $request): WP_REST_Response {
        $component = $request->get_param('component');
        $context = $this->context_provider->get_component_context($component);

        return new WP_REST_Response([
            'success' => true,
            'component' => $component,
            'context' => $context,
        ]);
    }

    /**
     * Generate workflow from prompt
     */
    public function generate_workflow(WP_REST_Request $request): WP_REST_Response {
        $prompt = $request->get_param('prompt');
        $options = $request->get_param('options');

        $result = $this->ai_orchestrator->generate_workflow($prompt, $options);

        return new WP_REST_Response($result);
    }

    /**
     * Generate configuration
     */
    public function generate_config(WP_REST_Request $request): WP_REST_Response {
        $target = $request->get_param('target');
        $requirements = $request->get_param('requirements');
        $options = $request->get_param('options');

        $result = $this->ai_orchestrator->generate_configuration($target, $requirements, $options);

        return new WP_REST_Response($result);
    }

    /**
     * Generate configuration bundle
     */
    public function generate_bundle(WP_REST_Request $request): WP_REST_Response {
        $requirements = $request->get_param('requirements');
        $options = $request->get_param('options');

        $result = $this->config_generator->generate_bundle($requirements, $options);

        return new WP_REST_Response($result);
    }

    /**
     * Apply configuration bundle
     */
    public function apply_configuration(WP_REST_Request $request): WP_REST_Response {
        $bundle = $request->get_param('bundle');
        $dry_run = $request->get_param('dry_run');

        if ($dry_run) {
            // Validate without applying
            $context = $this->context_provider->get_full_context();
            $validation = [];

            foreach ($bundle as $target => $config) {
                $plugin = $this->target_to_plugin($target);
                $option_key = str_replace('-', '_', $plugin) . '_settings';
                $current = get_option($option_key, []);

                $validation[$target] = [
                    'would_change' => array_diff_assoc($config, $current),
                    'option_key' => $option_key,
                ];
            }

            return new WP_REST_Response([
                'success' => true,
                'dry_run' => true,
                'validation' => $validation,
            ]);
        }

        // Start sync session
        $this->db_sync->start_session(0);

        // Apply configurations
        $results = $this->db_sync->apply_configuration_bundle($bundle);

        // End session
        $summary = $this->db_sync->end_session();

        return new WP_REST_Response([
            'success' => $summary['failed'] === 0,
            'summary' => $summary,
            'results' => $results,
        ]);
    }

    /**
     * Start sync session
     */
    public function start_sync_session(WP_REST_Request $request): WP_REST_Response {
        $execution_id = time(); // Use timestamp as simple execution ID
        $this->db_sync->start_session($execution_id);

        return new WP_REST_Response([
            'success' => true,
            'execution_id' => $execution_id,
        ]);
    }

    /**
     * Get sync status
     */
    public function get_sync_status(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $recent = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}aevsync_operations ORDER BY created_at DESC LIMIT 20",
            ARRAY_A
        );

        return new WP_REST_Response([
            'success' => true,
            'operations' => $recent ?: [],
        ]);
    }

    /**
     * Rollback execution
     */
    public function rollback_execution(WP_REST_Request $request): WP_REST_Response {
        $execution_id = (int) $request->get_param('execution_id');

        $result = $this->db_sync->rollback_execution($execution_id);

        return new WP_REST_Response([
            'success' => $result['total_rolled_back'] > 0,
            'result' => $result,
        ]);
    }

    /**
     * Get templates
     */
    public function get_templates(WP_REST_Request $request): WP_REST_Response {
        $templates = get_posts([
            'post_type' => 'aevsync_template',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        $result = [];
        foreach ($templates as $template) {
            $result[] = [
                'id' => $template->ID,
                'title' => $template->post_title,
                'description' => get_post_meta($template->ID, 'description', true),
                'workflow_data' => json_decode(get_post_meta($template->ID, 'workflow_data', true), true),
                'is_default' => get_post_meta($template->ID, 'is_default', true),
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'templates' => $result,
        ]);
    }

    /**
     * Get single template
     */
    public function get_template(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $template = get_post($id);

        if (!$template || $template->post_type !== 'aevsync_template') {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Template not found',
            ], 404);
        }

        return new WP_REST_Response([
            'success' => true,
            'template' => [
                'id' => $template->ID,
                'title' => $template->post_title,
                'description' => get_post_meta($template->ID, 'description', true),
                'workflow_data' => json_decode(get_post_meta($template->ID, 'workflow_data', true), true),
            ],
        ]);
    }

    /**
     * Create template
     */
    public function create_template(WP_REST_Request $request): WP_REST_Response {
        $title = $request->get_param('title');
        $description = $request->get_param('description');
        $workflow_data = $request->get_param('workflow_data');

        $post_id = wp_insert_post([
            'post_type' => 'aevsync_template',
            'post_title' => sanitize_text_field($title),
            'post_status' => 'publish',
            'meta_input' => [
                'description' => sanitize_text_field($description),
                'workflow_data' => wp_json_encode($workflow_data),
            ],
        ]);

        if (is_wp_error($post_id)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => $post_id->get_error_message(),
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'id' => $post_id,
        ]);
    }

    /**
     * Update template
     */
    public function update_template(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $updates = [];

        if ($request->has_param('title')) {
            $updates['post_title'] = sanitize_text_field($request->get_param('title'));
        }

        if (!empty($updates)) {
            $updates['ID'] = $id;
            wp_update_post($updates);
        }

        if ($request->has_param('description')) {
            update_post_meta($id, 'description', sanitize_text_field($request->get_param('description')));
        }

        if ($request->has_param('workflow_data')) {
            update_post_meta($id, 'workflow_data', wp_json_encode($request->get_param('workflow_data')));
        }

        return new WP_REST_Response([
            'success' => true,
            'id' => $id,
        ]);
    }

    /**
     * Delete template
     */
    public function delete_template(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $result = wp_delete_post($id, true);

        return new WP_REST_Response([
            'success' => $result !== false,
        ]);
    }

    /**
     * Get configuration history
     */
    public function get_config_history(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $limit = (int) $request->get_param('limit');
        $offset = (int) $request->get_param('offset');

        $history = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aevsync_config_history ORDER BY applied_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aevsync_config_history");

        return new WP_REST_Response([
            'success' => true,
            'history' => $history ?: [],
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Execute SyncPro node (for workflow integration)
     */
    public function execute_syncpro(WP_REST_Request $request): WP_REST_Response {
        $mode = $request->get_param('mode');
        $target = $request->get_param('target');
        $input = $request->get_param('input');

        $result = match ($mode) {
            'analyze' => $this->execute_analyze($input, $target),
            'generate' => $this->execute_generate($input, $target),
            'modify' => $this->execute_modify($input, $target),
            default => ['success' => false, 'error' => 'Unknown mode'],
        };

        return new WP_REST_Response($result);
    }

    /**
     * Execute analyze mode
     */
    private function execute_analyze(array $input, string $target): array {
        $context = $this->context_provider->get_full_context();
        $prompt = $input['prompt'] ?? wp_json_encode($input);

        // Analyze using AI orchestrator
        $analysis = [
            'system_status' => $context['statistics'],
            'recommendations' => $context['recommendations'],
            'capabilities' => array_keys($context['capabilities']),
            'active_plugins' => count(array_filter($context['plugins'], fn($p) => $p['is_active'])),
        ];

        if ($target !== 'all') {
            $analysis['target_context'] = $this->context_provider->get_component_context($target);
        }

        return [
            'success' => true,
            'analysis' => $analysis,
            'ai_prompt' => $this->context_provider->get_ai_context_prompt(),
        ];
    }

    /**
     * Execute generate mode
     */
    private function execute_generate(array $input, string $target): array {
        $requirements = $input['requirements'] ?? $input;
        $options = ['target' => $target];

        $result = $this->config_generator->generate_bundle($requirements, $options);

        return [
            'success' => $result['success'],
            'configuration' => $result['bundle'],
            'validation' => $result['validation'],
            'sync_operations' => $this->generate_sync_operations_from_bundle($result['bundle']),
        ];
    }

    /**
     * Execute modify mode
     */
    private function execute_modify(array $input, string $target): array {
        $modifications = $input['modifications'] ?? $input;
        $current = $this->context_provider->get_current_configurations();

        $modified = [];
        foreach ($modifications as $key => $value) {
            if (isset($current[$key])) {
                $modified[$key] = array_replace_recursive($current[$key], $value);
            } else {
                $modified[$key] = $value;
            }
        }

        return [
            'success' => true,
            'configuration' => $modified,
            'changes' => $modifications,
            'sync_operations' => $this->generate_sync_operations_from_bundle($modified),
        ];
    }

    /**
     * Generate sync operations from bundle
     */
    private function generate_sync_operations_from_bundle(array $bundle): array {
        $operations = [];

        foreach ($bundle as $target => $config) {
            $operations[] = [
                'sync_type' => 'plugin_config',
                'plugin' => $this->target_to_plugin($target),
                'config' => $config,
                'merge' => true,
            ];
        }

        return $operations;
    }

    /**
     * Health check
     */
    public function health_check(WP_REST_Request $request): WP_REST_Response {
        $context = $this->context_provider->get_full_context();

        return new WP_REST_Response([
            'status' => 'healthy',
            'version' => AEVOV_SYNCPRO_VERSION,
            'active_plugins' => count(array_filter($context['plugins'], fn($p) => $p['is_active'])),
            'capabilities' => count($context['capabilities']),
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Export configuration
     */
    public function export_configuration(WP_REST_Request $request): WP_REST_Response {
        $bundle = $request->get_param('bundle');
        $format = $request->get_param('format');

        $content = $this->config_generator->export_bundle($bundle, $format);

        return new WP_REST_Response([
            'success' => true,
            'format' => $format,
            'content' => $content,
        ]);
    }

    /**
     * Import configuration
     */
    public function import_configuration(WP_REST_Request $request): WP_REST_Response {
        $content = $request->get_param('content');
        $format = $request->get_param('format');
        $apply = $request->get_param('apply');

        $bundle = $this->config_generator->import_bundle($content, $format);

        if (empty($bundle)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Failed to parse configuration',
            ], 400);
        }

        $result = [
            'success' => true,
            'bundle' => $bundle,
        ];

        if ($apply) {
            $this->db_sync->start_session(time());
            $apply_results = $this->db_sync->apply_configuration_bundle($bundle);
            $summary = $this->db_sync->end_session();

            $result['applied'] = true;
            $result['summary'] = $summary;
        }

        return new WP_REST_Response($result);
    }

    /**
     * Helper: Convert target to plugin slug
     */
    private function target_to_plugin(string $target): string {
        $map = [
            'ai_engines' => 'aevov-ai-core',
            'storage' => 'aevov-memory-core',
            'workflows' => 'aevov-workflow-engine',
            'patterns' => 'bloom-pattern-recognition',
            'memory' => 'aevov-memory-core',
            'security' => 'aevov-security',
            'network' => 'aevov-meshcore',
        ];
        return $map[$target] ?? $target;
    }
}
