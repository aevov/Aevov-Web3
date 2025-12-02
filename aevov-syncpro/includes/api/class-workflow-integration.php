<?php
/**
 * Workflow Engine Integration
 *
 * Integrates AevSyncPro as a capability within the Aevov Workflow Engine.
 * Registers the 'syncpro' node type and handles execution.
 *
 * @package AevovSyncPro
 */

namespace AevovSyncPro\Api;

use AevovSyncPro\Providers\SystemContextProvider;
use AevovSyncPro\Providers\AIOrchestrator;
use AevovSyncPro\Sync\DatabaseSyncController;
use AevovSyncPro\Generators\ConfigurationGenerator;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Workflow Integration Class
 */
class WorkflowIntegration {

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

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Register as workflow capability
        add_filter('aevov_workflow_capabilities', [$this, 'register_capability']);

        // Handle node execution
        add_filter('aevov_workflow_execute_node_syncpro', [$this, 'execute_node'], 10, 3);

        // Hook into workflow lifecycle
        add_action('aevov_workflow_before_execute', [$this, 'before_workflow_execute'], 10, 2);
        add_action('aevov_workflow_after_execute', [$this, 'after_workflow_execute'], 10, 2);
    }

    /**
     * Register AevSyncPro as a workflow capability
     */
    public function register_capability(array $capabilities): array {
        $capabilities['syncpro'] = [
            'type' => 'syncpro',
            'label' => 'AevSyncPro',
            'category' => 'capability',
            'description' => 'Intelligent configuration generation and system orchestration',
            'icon' => 'Sparkles',
            'color' => '#8B5CF6',
            'inputs' => [
                [
                    'id' => 'prompt',
                    'type' => 'string',
                    'label' => 'Configuration Prompt',
                    'description' => 'Natural language description of what to configure',
                ],
                [
                    'id' => 'context',
                    'type' => 'object',
                    'label' => 'Additional Context',
                    'description' => 'Extra context from previous nodes',
                ],
            ],
            'outputs' => [
                [
                    'id' => 'configuration',
                    'type' => 'object',
                    'label' => 'Generated Configuration',
                ],
                [
                    'id' => 'sync_operations',
                    'type' => 'array',
                    'label' => 'Sync Operations',
                ],
                [
                    'id' => 'analysis',
                    'type' => 'object',
                    'label' => 'Analysis Results',
                ],
            ],
            'configFields' => [
                [
                    'name' => 'mode',
                    'label' => 'Operation Mode',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'analyze', 'label' => 'Analyze System'],
                        ['value' => 'generate', 'label' => 'Generate Configuration'],
                        ['value' => 'modify', 'label' => 'Modify Existing'],
                        ['value' => 'apply', 'label' => 'Apply Configuration'],
                    ],
                    'default' => 'generate',
                    'required' => true,
                ],
                [
                    'name' => 'target',
                    'label' => 'Target System',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'all', 'label' => 'All Systems'],
                        ['value' => 'ai_engines', 'label' => 'AI Engines'],
                        ['value' => 'storage', 'label' => 'Storage Systems'],
                        ['value' => 'workflows', 'label' => 'Workflows'],
                        ['value' => 'patterns', 'label' => 'Pattern Recognition'],
                        ['value' => 'memory', 'label' => 'Memory Core'],
                        ['value' => 'security', 'label' => 'Security'],
                        ['value' => 'network', 'label' => 'Network/Meshcore'],
                    ],
                    'default' => 'all',
                ],
                [
                    'name' => 'auto_apply',
                    'label' => 'Auto-Apply Changes',
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Automatically apply configuration changes to the system',
                ],
                [
                    'name' => 'performance',
                    'label' => 'Performance Mode',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'standard', 'label' => 'Standard'],
                        ['value' => 'high', 'label' => 'High Performance'],
                        ['value' => 'economy', 'label' => 'Economy (Cost Optimized)'],
                    ],
                    'default' => 'standard',
                ],
                [
                    'name' => 'security_level',
                    'label' => 'Security Level',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'standard', 'label' => 'Standard'],
                        ['value' => 'strict', 'label' => 'Strict'],
                        ['value' => 'maximum', 'label' => 'Maximum'],
                    ],
                    'default' => 'standard',
                ],
            ],
            'available' => true,
            'handler' => [$this, 'execute_node'],
        ];

        return $capabilities;
    }

    /**
     * Execute AevSyncPro node
     */
    public function execute_node(array $result, array $node_data, array $inputs): array {
        $mode = $node_data['mode'] ?? 'generate';
        $target = $node_data['target'] ?? 'all';
        $auto_apply = $node_data['auto_apply'] ?? false;

        $prompt = $inputs['prompt'] ?? '';
        $additional_context = $inputs['context'] ?? [];

        try {
            switch ($mode) {
                case 'analyze':
                    return $this->execute_analyze($target, $prompt, $additional_context);

                case 'generate':
                    return $this->execute_generate($target, $prompt, $additional_context, $node_data);

                case 'modify':
                    return $this->execute_modify($target, $prompt, $additional_context);

                case 'apply':
                    return $this->execute_apply($inputs, $auto_apply);

                default:
                    throw new \Exception("Unknown mode: {$mode}");
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute analyze mode
     */
    private function execute_analyze(string $target, string $prompt, array $context): array {
        $system_context = $this->context_provider->get_full_context();

        $analysis = [
            'system_overview' => [
                'active_plugins' => count(array_filter($system_context['plugins'], fn($p) => $p['is_active'])),
                'total_capabilities' => count($system_context['capabilities']),
                'workflow_stats' => $system_context['workflows'],
                'storage_status' => $system_context['storage'],
            ],
            'recommendations' => $system_context['recommendations'],
            'statistics' => $system_context['statistics'],
        ];

        if ($target !== 'all') {
            $analysis['target_details'] = $this->context_provider->get_component_context($target);
        }

        // If a prompt was provided, use AI to analyze
        if (!empty($prompt)) {
            $ai_analysis = $this->ai_orchestrator->generate_configuration($target, [
                'prompt' => $prompt,
                'mode' => 'analyze',
            ]);
            $analysis['ai_insights'] = $ai_analysis;
        }

        return [
            'success' => true,
            'analysis' => $analysis,
            'ai_context' => $this->context_provider->get_ai_context_prompt(),
        ];
    }

    /**
     * Execute generate mode
     */
    private function execute_generate(string $target, string $prompt, array $context, array $node_data): array {
        $requirements = [
            'prompt' => $prompt,
            'context' => $context,
            'performance' => $node_data['performance'] ?? 'standard',
            'security' => $node_data['security_level'] ?? 'standard',
        ];

        $options = [
            'target' => $target,
        ];

        // Generate configuration bundle
        $bundle_result = $this->config_generator->generate_bundle($requirements, $options);

        if (!$bundle_result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to generate configuration',
                'validation' => $bundle_result['validation'],
            ];
        }

        // Generate sync operations
        $sync_operations = [];
        foreach ($bundle_result['bundle'] as $bundle_target => $config) {
            $sync_operations[] = [
                'sync_type' => 'plugin_config',
                'plugin' => $this->target_to_plugin($bundle_target),
                'config' => $config,
                'merge' => true,
            ];
        }

        // Auto-apply if enabled
        if ($node_data['auto_apply'] ?? false) {
            $this->db_sync->apply_configuration_bundle($bundle_result['bundle']);
        }

        return [
            'success' => true,
            'configuration' => $bundle_result['bundle'],
            'sync_operations' => $sync_operations,
            'validation' => $bundle_result['validation'],
            'apply_instructions' => $bundle_result['apply_instructions'],
            'rollback_plan' => $bundle_result['rollback_plan'],
        ];
    }

    /**
     * Execute modify mode
     */
    private function execute_modify(string $target, string $prompt, array $context): array {
        // Get current configuration
        $current_configs = $this->context_provider->get_current_configurations();

        // Use AI to determine modifications
        $modifications = $this->ai_orchestrator->generate_configuration($target, [
            'prompt' => $prompt,
            'current_config' => $current_configs[$target] ?? [],
            'mode' => 'modify',
        ]);

        return [
            'success' => true,
            'configuration' => $modifications['configuration'] ?? [],
            'changes' => $modifications['changes'] ?? [],
            'sync_operations' => $modifications['sync_operations'] ?? [],
        ];
    }

    /**
     * Execute apply mode
     */
    private function execute_apply(array $inputs, bool $auto_apply): array {
        $configuration = $inputs['configuration'] ?? null;

        if (!$configuration) {
            return [
                'success' => false,
                'error' => 'No configuration provided to apply',
            ];
        }

        if (!$auto_apply) {
            // Just validate without applying
            return [
                'success' => true,
                'status' => 'ready_to_apply',
                'configuration' => $configuration,
                'message' => 'Configuration validated. Set auto_apply to true or manually apply.',
            ];
        }

        // Apply the configuration
        $results = $this->db_sync->apply_configuration_bundle($configuration);

        return [
            'success' => true,
            'status' => 'applied',
            'results' => $results,
        ];
    }

    /**
     * Hook: Before workflow execution
     */
    public function before_workflow_execute(string $workflow_id, array $inputs): void {
        // Start a sync session for tracking
        $this->db_sync->start_session(time());
    }

    /**
     * Hook: After workflow execution
     */
    public function after_workflow_execute(string $workflow_id, array $result): void {
        // End sync session and collect summary
        $summary = $this->db_sync->end_session();

        // Log execution summary
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[AevSyncPro] Workflow {$workflow_id} completed with {$summary['total_operations']} sync operations");
        }
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
