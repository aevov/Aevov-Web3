<?php
/**
 * Database Sync Controller
 *
 * Provides real-time database synchronization as workflows execute,
 * enabling BIDC (Bidirectional Incremental Data Connectivity) between
 * workflow actions and the Aevov ecosystem.
 *
 * @package AevovSyncPro
 */

namespace AevovSyncPro\Sync;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Sync Controller Class
 *
 * Handles real-time synchronization of database properties as workflows
 * implement new configurations. Tracks all changes for rollback capability.
 */
class DatabaseSyncController {

    private const SYNC_TYPES = [
        'option' => 'WordPress Options',
        'post_meta' => 'Post Metadata',
        'user_meta' => 'User Metadata',
        'custom_table' => 'Custom Tables',
        'transient' => 'Transients',
        'plugin_config' => 'Plugin Configuration',
    ];

    private array $pending_operations = [];
    private array $completed_operations = [];
    private ?int $current_execution_id = null;

    /**
     * Start a sync session for a workflow execution
     */
    public function start_session(int $execution_id): void {
        $this->current_execution_id = $execution_id;
        $this->pending_operations = [];
        $this->completed_operations = [];

        $this->log_operation([
            'type' => 'session_start',
            'execution_id' => $execution_id,
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * End the sync session
     */
    public function end_session(): array {
        $summary = [
            'execution_id' => $this->current_execution_id,
            'total_operations' => count($this->completed_operations),
            'successful' => count(array_filter($this->completed_operations, fn($op) => $op['status'] === 'completed')),
            'failed' => count(array_filter($this->completed_operations, fn($op) => $op['status'] === 'failed')),
            'operations' => $this->completed_operations,
        ];

        $this->log_operation([
            'type' => 'session_end',
            'summary' => $summary,
            'timestamp' => current_time('mysql'),
        ]);

        $this->current_execution_id = null;
        return $summary;
    }

    /**
     * Hook: Called when a workflow node is executed
     */
    public function on_node_executed(string $node_id, array $node_data, array $result): void {
        // Check if this node produces database changes
        if (!$this->node_produces_changes($node_data)) {
            return;
        }

        // Extract sync operations from the node result
        $operations = $this->extract_sync_operations($node_data, $result);

        foreach ($operations as $operation) {
            $this->queue_operation($operation);
        }

        // Process queued operations
        $this->process_pending_operations();
    }

    /**
     * Hook: Called when a workflow completes
     */
    public function on_workflow_completed(string $workflow_id, array $execution_result): void {
        // Finalize any pending operations
        $this->process_pending_operations(true);

        // Generate configuration snapshot
        $this->create_configuration_snapshot($workflow_id, $execution_result);
    }

    /**
     * Queue a sync operation
     */
    public function queue_operation(array $operation): string {
        $operation_id = wp_generate_uuid4();

        $operation = array_merge($operation, [
            'id' => $operation_id,
            'queued_at' => current_time('mysql'),
            'status' => 'pending',
            'execution_id' => $this->current_execution_id,
        ]);

        // Store rollback data before making changes
        $operation['rollback_data'] = $this->capture_rollback_data($operation);

        $this->pending_operations[$operation_id] = $operation;

        return $operation_id;
    }

    /**
     * Process pending operations
     */
    public function process_pending_operations(bool $force = false): array {
        $results = [];

        foreach ($this->pending_operations as $id => $operation) {
            if (!$force && isset($operation['defer_until']) && time() < $operation['defer_until']) {
                continue;
            }

            $result = $this->execute_operation($operation);
            $results[$id] = $result;

            // Move to completed
            $this->completed_operations[$id] = array_merge($operation, [
                'status' => $result['success'] ? 'completed' : 'failed',
                'completed_at' => current_time('mysql'),
                'result' => $result,
            ]);

            unset($this->pending_operations[$id]);

            // Log to database
            $this->save_operation_to_db($this->completed_operations[$id]);
        }

        return $results;
    }

    /**
     * Execute a single sync operation
     */
    private function execute_operation(array $operation): array {
        try {
            return match ($operation['sync_type']) {
                'option' => $this->sync_option($operation),
                'post_meta' => $this->sync_post_meta($operation),
                'user_meta' => $this->sync_user_meta($operation),
                'custom_table' => $this->sync_custom_table($operation),
                'transient' => $this->sync_transient($operation),
                'plugin_config' => $this->sync_plugin_config($operation),
                'bulk' => $this->sync_bulk($operation),
                default => throw new \Exception("Unknown sync type: {$operation['sync_type']}"),
            };
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync WordPress option
     */
    private function sync_option(array $operation): array {
        $key = $operation['key'];
        $value = $operation['value'];
        $action = $operation['action'] ?? 'update';

        switch ($action) {
            case 'update':
                $result = update_option($key, $value, $operation['autoload'] ?? true);
                break;
            case 'delete':
                $result = delete_option($key);
                break;
            case 'add':
                $result = add_option($key, $value, '', $operation['autoload'] ?? true);
                break;
            default:
                throw new \Exception("Unknown option action: {$action}");
        }

        return [
            'success' => $result !== false,
            'key' => $key,
            'action' => $action,
        ];
    }

    /**
     * Sync post metadata
     */
    private function sync_post_meta(array $operation): array {
        $post_id = $operation['post_id'];
        $key = $operation['key'];
        $value = $operation['value'];
        $action = $operation['action'] ?? 'update';

        switch ($action) {
            case 'update':
                $result = update_post_meta($post_id, $key, $value);
                break;
            case 'delete':
                $result = delete_post_meta($post_id, $key);
                break;
            case 'add':
                $result = add_post_meta($post_id, $key, $value, $operation['unique'] ?? false);
                break;
            default:
                throw new \Exception("Unknown post_meta action: {$action}");
        }

        return [
            'success' => $result !== false,
            'post_id' => $post_id,
            'key' => $key,
            'action' => $action,
        ];
    }

    /**
     * Sync user metadata
     */
    private function sync_user_meta(array $operation): array {
        $user_id = $operation['user_id'];
        $key = $operation['key'];
        $value = $operation['value'];
        $action = $operation['action'] ?? 'update';

        switch ($action) {
            case 'update':
                $result = update_user_meta($user_id, $key, $value);
                break;
            case 'delete':
                $result = delete_user_meta($user_id, $key);
                break;
            case 'add':
                $result = add_user_meta($user_id, $key, $value, $operation['unique'] ?? false);
                break;
            default:
                throw new \Exception("Unknown user_meta action: {$action}");
        }

        return [
            'success' => $result !== false,
            'user_id' => $user_id,
            'key' => $key,
            'action' => $action,
        ];
    }

    /**
     * Sync custom table data
     */
    private function sync_custom_table(array $operation): array {
        global $wpdb;

        $table = $wpdb->prefix . $operation['table'];
        $action = $operation['action'] ?? 'insert';
        $data = $operation['data'];

        switch ($action) {
            case 'insert':
                $result = $wpdb->insert($table, $data, $this->get_format($data));
                $insert_id = $wpdb->insert_id;
                break;
            case 'update':
                $where = $operation['where'];
                $result = $wpdb->update($table, $data, $where, $this->get_format($data), $this->get_format($where));
                $insert_id = null;
                break;
            case 'delete':
                $where = $operation['where'];
                $result = $wpdb->delete($table, $where, $this->get_format($where));
                $insert_id = null;
                break;
            case 'replace':
                $result = $wpdb->replace($table, $data, $this->get_format($data));
                $insert_id = $wpdb->insert_id;
                break;
            default:
                throw new \Exception("Unknown custom_table action: {$action}");
        }

        return [
            'success' => $result !== false,
            'table' => $table,
            'action' => $action,
            'insert_id' => $insert_id ?? null,
            'rows_affected' => $wpdb->rows_affected,
        ];
    }

    /**
     * Sync transient data
     */
    private function sync_transient(array $operation): array {
        $key = $operation['key'];
        $value = $operation['value'] ?? null;
        $action = $operation['action'] ?? 'set';
        $expiration = $operation['expiration'] ?? HOUR_IN_SECONDS;

        switch ($action) {
            case 'set':
                $result = set_transient($key, $value, $expiration);
                break;
            case 'delete':
                $result = delete_transient($key);
                break;
            default:
                throw new \Exception("Unknown transient action: {$action}");
        }

        return [
            'success' => $result,
            'key' => $key,
            'action' => $action,
        ];
    }

    /**
     * Sync plugin configuration
     */
    private function sync_plugin_config(array $operation): array {
        $plugin = $operation['plugin'];
        $config = $operation['config'];
        $merge = $operation['merge'] ?? true;

        $option_key = str_replace('-', '_', $plugin) . '_settings';
        $current = get_option($option_key, []);

        if ($merge) {
            $new_config = array_merge($current, $config);
        } else {
            $new_config = $config;
        }

        $result = update_option($option_key, $new_config);

        // Trigger plugin-specific hooks
        do_action("aevov_syncpro_config_updated_{$plugin}", $new_config, $current);
        do_action('aevov_syncpro_config_updated', $plugin, $new_config, $current);

        return [
            'success' => $result !== false,
            'plugin' => $plugin,
            'option_key' => $option_key,
            'changes' => array_diff_assoc($new_config, $current),
        ];
    }

    /**
     * Sync bulk operations
     */
    private function sync_bulk(array $operation): array {
        $operations = $operation['operations'];
        $results = [];
        $all_success = true;

        foreach ($operations as $sub_operation) {
            $result = $this->execute_operation($sub_operation);
            $results[] = $result;
            if (!$result['success']) {
                $all_success = false;
            }
        }

        return [
            'success' => $all_success,
            'total' => count($operations),
            'successful' => count(array_filter($results, fn($r) => $r['success'])),
            'results' => $results,
        ];
    }

    /**
     * Rollback an operation
     */
    public function rollback_operation(string $operation_id): array {
        global $wpdb;

        // Get operation from database
        $operation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aevsync_operations WHERE id = %s",
                $operation_id
            ),
            ARRAY_A
        );

        if (!$operation) {
            return ['success' => false, 'error' => 'Operation not found'];
        }

        $rollback_data = json_decode($operation['rollback_data'], true);

        if (!$rollback_data) {
            return ['success' => false, 'error' => 'No rollback data available'];
        }

        // Create rollback operation
        $rollback_operation = array_merge(
            json_decode($operation['operation_data'], true),
            ['value' => $rollback_data['original_value']]
        );

        $result = $this->execute_operation($rollback_operation);

        // Update status
        $wpdb->update(
            $wpdb->prefix . 'aevsync_operations',
            ['status' => 'rolled_back'],
            ['id' => $operation_id]
        );

        return $result;
    }

    /**
     * Rollback entire workflow execution
     */
    public function rollback_execution(int $execution_id): array {
        global $wpdb;

        $operations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aevsync_operations
                 WHERE workflow_execution_id = %d AND status = 'completed'
                 ORDER BY completed_at DESC",
                $execution_id
            ),
            ARRAY_A
        );

        $results = [];
        foreach ($operations as $operation) {
            $results[] = $this->rollback_operation($operation['id']);
        }

        return [
            'execution_id' => $execution_id,
            'total_rolled_back' => count(array_filter($results, fn($r) => $r['success'])),
            'results' => $results,
        ];
    }

    /**
     * Create a configuration snapshot
     */
    private function create_configuration_snapshot(string $workflow_id, array $execution_result): void {
        global $wpdb;

        $snapshot = [
            'workflow_id' => $workflow_id,
            'timestamp' => current_time('mysql'),
            'configurations' => $this->capture_all_configurations(),
            'execution_summary' => [
                'operations' => count($this->completed_operations),
                'status' => $execution_result['status'] ?? 'unknown',
            ],
        ];

        $wpdb->insert(
            $wpdb->prefix . 'aevsync_config_history',
            [
                'config_id' => 0,
                'config_type' => 'snapshot',
                'config_data' => wp_json_encode($snapshot),
                'workflow_id' => $workflow_id,
                'execution_id' => $this->current_execution_id,
                'status' => 'applied',
                'applied_by' => get_current_user_id(),
            ]
        );
    }

    /**
     * Capture all current configurations
     */
    public function capture_all_configurations(): array {
        $configs = [];

        // Aevov plugin configurations
        $aevov_plugins = [
            'aevov-ai-core', 'aevov-language-engine', 'aevov-image-engine',
            'aevov-music-forge', 'aevov-cognitive-engine', 'aevov-memory-core',
            'aevov-workflow-engine', 'aevov-meshcore', 'aevov-security',
        ];

        foreach ($aevov_plugins as $plugin) {
            $option_key = str_replace('-', '_', $plugin) . '_settings';
            $config = get_option($option_key);
            if ($config) {
                $configs[$plugin] = $config;
            }
        }

        return $configs;
    }

    /**
     * Apply a configuration bundle
     */
    public function apply_configuration_bundle(array $bundle): array {
        $results = [];

        foreach ($bundle as $target => $config) {
            $operation = [
                'sync_type' => 'plugin_config',
                'plugin' => $target,
                'config' => $config,
                'merge' => true,
            ];

            $this->queue_operation($operation);
        }

        return $this->process_pending_operations(true);
    }

    /**
     * Helper: Check if node produces database changes
     */
    private function node_produces_changes(array $node_data): bool {
        $change_types = ['syncpro', 'memory', 'config', 'database'];
        return in_array($node_data['type'] ?? '', $change_types, true);
    }

    /**
     * Helper: Extract sync operations from node result
     */
    private function extract_sync_operations(array $node_data, array $result): array {
        $operations = [];

        // Check for explicit sync operations in result
        if (isset($result['sync_operations'])) {
            $operations = array_merge($operations, $result['sync_operations']);
        }

        // Check for configuration updates
        if (isset($result['configuration'])) {
            foreach ($result['configuration'] as $target => $config) {
                $operations[] = [
                    'sync_type' => 'plugin_config',
                    'plugin' => $target,
                    'config' => $config,
                ];
            }
        }

        // Check for memory operations
        if ($node_data['type'] === 'memory' && isset($result['memory_address'])) {
            $operations[] = [
                'sync_type' => 'post_meta',
                'post_id' => $result['memory_post_id'],
                'key' => 'memory_data',
                'value' => $result['memory_data'],
            ];
        }

        return $operations;
    }

    /**
     * Helper: Capture rollback data
     */
    private function capture_rollback_data(array $operation): array {
        return match ($operation['sync_type']) {
            'option' => ['original_value' => get_option($operation['key'])],
            'post_meta' => ['original_value' => get_post_meta($operation['post_id'], $operation['key'], true)],
            'user_meta' => ['original_value' => get_user_meta($operation['user_id'], $operation['key'], true)],
            'plugin_config' => ['original_value' => get_option(str_replace('-', '_', $operation['plugin']) . '_settings', [])],
            default => [],
        };
    }

    /**
     * Helper: Get format array for wpdb operations
     */
    private function get_format(array $data): array {
        $formats = [];
        foreach ($data as $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        return $formats;
    }

    /**
     * Helper: Save operation to database
     */
    private function save_operation_to_db(array $operation): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'aevsync_operations',
            [
                'operation_type' => $operation['sync_type'],
                'target_system' => $operation['plugin'] ?? $operation['key'] ?? 'unknown',
                'target_entity' => $operation['key'] ?? null,
                'operation_data' => wp_json_encode($operation),
                'result_data' => wp_json_encode($operation['result'] ?? []),
                'status' => $operation['status'],
                'workflow_execution_id' => $operation['execution_id'],
                'completed_at' => $operation['completed_at'] ?? null,
                'error_message' => $operation['result']['error'] ?? null,
            ]
        );
    }

    /**
     * Helper: Log operation
     */
    private function log_operation(array $data): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[AevSyncPro] ' . wp_json_encode($data));
        }
    }
}
