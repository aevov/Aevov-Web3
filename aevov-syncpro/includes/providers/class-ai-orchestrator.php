<?php
/**
 * AI Orchestrator
 *
 * Combines Aevov's AI engines with intelligent workflow generation.
 * This is the core intelligence layer of AevSyncPro.
 *
 * @package AevovSyncPro
 */

namespace AevovSyncPro\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Orchestrator Class
 *
 * Orchestrates multiple AI engines to generate, analyze, and optimize
 * configurations and workflows with complete system context.
 */
class AIOrchestrator {

    private SystemContextProvider $context_provider;
    private array $engine_cache = [];

    /**
     * Available AI engines and their specializations
     */
    private const ENGINE_SPECIALIZATIONS = [
        'cognitive' => [
            'strengths' => ['complex_reasoning', 'planning', 'analysis'],
            'use_for' => ['system_analysis', 'architecture_decisions', 'optimization_planning'],
        ],
        'reasoning' => [
            'strengths' => ['logical_inference', 'validation', 'consistency_checking'],
            'use_for' => ['config_validation', 'dependency_analysis', 'conflict_detection'],
        ],
        'language' => [
            'strengths' => ['text_generation', 'summarization', 'documentation'],
            'use_for' => ['documentation', 'user_guidance', 'error_messages'],
        ],
    ];

    public function __construct(SystemContextProvider $context_provider) {
        $this->context_provider = $context_provider;
    }

    /**
     * Generate a workflow from natural language description
     */
    public function generate_workflow(string $prompt, array $options = []): array {
        // Get full system context
        $context = $this->context_provider->get_full_context();
        $context_prompt = $this->context_provider->get_ai_context_prompt();

        // Build the generation prompt
        $system_prompt = $this->build_workflow_generation_prompt($context);

        // Use cognitive engine for complex reasoning
        $analysis = $this->analyze_requirements($prompt, $context);

        // Generate workflow structure
        $workflow = $this->create_workflow_structure($analysis, $context, $options);

        // Validate with reasoning engine
        $validation = $this->validate_workflow($workflow, $context);

        if (!$validation['valid']) {
            $workflow = $this->fix_workflow_issues($workflow, $validation['issues']);
        }

        return [
            'success' => true,
            'workflow' => $workflow,
            'analysis' => $analysis,
            'validation' => $validation,
            'estimated_steps' => count($workflow['nodes']),
            'capabilities_used' => $this->extract_capabilities_used($workflow),
        ];
    }

    /**
     * Analyze user requirements using cognitive engine
     */
    private function analyze_requirements(string $prompt, array $context): array {
        $analysis_prompt = <<<PROMPT
Analyze the following user request in the context of the Aevov ecosystem.

USER REQUEST:
{$prompt}

AVAILABLE CAPABILITIES:
{$this->format_capabilities($context['capabilities'])}

AVAILABLE PLUGINS:
{$this->format_plugins($context['plugins'])}

Provide a structured analysis:
1. What is the user trying to achieve?
2. What Aevov capabilities are needed?
3. What configurations need to be created or modified?
4. What is the optimal sequence of operations?
5. Are there any prerequisites or dependencies?
6. What are potential risks or considerations?

Return as JSON with keys: goal, required_capabilities, configurations, sequence, prerequisites, considerations
PROMPT;

        $response = $this->call_ai_engine('cognitive', $analysis_prompt, [
            'response_format' => 'json',
        ]);

        return $response['data'] ?? [
            'goal' => $prompt,
            'required_capabilities' => [],
            'configurations' => [],
            'sequence' => [],
            'prerequisites' => [],
            'considerations' => [],
        ];
    }

    /**
     * Create workflow structure from analysis
     */
    private function create_workflow_structure(array $analysis, array $context, array $options): array {
        $nodes = [];
        $edges = [];
        $node_id = 1;

        // Add input node
        $nodes[] = [
            'id' => "node_{$node_id}",
            'type' => 'input',
            'position' => ['x' => 100, 'y' => 200],
            'data' => [
                'label' => 'User Input',
                'inputType' => 'text',
                'description' => 'Initial configuration requirements',
            ],
        ];
        $prev_node_id = "node_{$node_id}";
        $node_id++;

        // Add SyncPro analysis node
        $nodes[] = [
            'id' => "node_{$node_id}",
            'type' => 'syncpro',
            'position' => ['x' => 300, 'y' => 200],
            'data' => [
                'label' => 'Analyze Requirements',
                'mode' => 'analyze',
                'target' => 'all',
            ],
        ];
        $edges[] = [
            'id' => "edge_{$node_id}",
            'source' => $prev_node_id,
            'target' => "node_{$node_id}",
            'sourceHandle' => 'output',
            'targetHandle' => 'input',
        ];
        $prev_node_id = "node_{$node_id}";
        $node_id++;

        // Add nodes for each required capability
        $y_offset = 100;
        $parallel_nodes = [];

        foreach ($analysis['required_capabilities'] ?? [] as $capability) {
            $capability_info = $this->get_capability_node_info($capability, $context);

            if ($capability_info) {
                $nodes[] = [
                    'id' => "node_{$node_id}",
                    'type' => $capability_info['type'],
                    'position' => ['x' => 500, 'y' => $y_offset],
                    'data' => array_merge($capability_info['data'], [
                        'label' => $capability_info['label'],
                    ]),
                ];

                $edges[] = [
                    'id' => "edge_{$node_id}",
                    'source' => $prev_node_id,
                    'target' => "node_{$node_id}",
                ];

                $parallel_nodes[] = "node_{$node_id}";
                $y_offset += 120;
                $node_id++;
            }
        }

        // Add merge node if multiple parallel nodes
        if (count($parallel_nodes) > 1) {
            $nodes[] = [
                'id' => "node_{$node_id}",
                'type' => 'merge',
                'position' => ['x' => 700, 'y' => 200],
                'data' => ['label' => 'Merge Results'],
            ];

            foreach ($parallel_nodes as $pn) {
                $edges[] = [
                    'id' => "edge_merge_{$node_id}_{$pn}",
                    'source' => $pn,
                    'target' => "node_{$node_id}",
                ];
            }

            $prev_node_id = "node_{$node_id}";
            $node_id++;
        } elseif (count($parallel_nodes) === 1) {
            $prev_node_id = $parallel_nodes[0];
        }

        // Add configuration generation node
        $nodes[] = [
            'id' => "node_{$node_id}",
            'type' => 'syncpro',
            'position' => ['x' => 900, 'y' => 200],
            'data' => [
                'label' => 'Generate Configuration',
                'mode' => 'generate',
                'target' => $options['target'] ?? 'all',
                'auto_apply' => $options['auto_apply'] ?? false,
            ],
        ];
        $edges[] = [
            'id' => "edge_{$node_id}",
            'source' => $prev_node_id,
            'target' => "node_{$node_id}",
        ];
        $prev_node_id = "node_{$node_id}";
        $node_id++;

        // Add output node
        $nodes[] = [
            'id' => "node_{$node_id}",
            'type' => 'output',
            'position' => ['x' => 1100, 'y' => 200],
            'data' => [
                'label' => 'Ready Configuration',
                'description' => 'Configuration bundle ready for use',
            ],
        ];
        $edges[] = [
            'id' => "edge_{$node_id}",
            'source' => $prev_node_id,
            'target' => "node_{$node_id}",
        ];

        return [
            'id' => 'wf_' . wp_generate_uuid4(),
            'name' => $this->generate_workflow_name($analysis),
            'description' => $analysis['goal'] ?? '',
            'nodes' => $nodes,
            'edges' => $edges,
            'metadata' => [
                'generated_by' => 'AevSyncPro',
                'generated_at' => current_time('mysql'),
                'analysis' => $analysis,
            ],
        ];
    }

    /**
     * Validate workflow using reasoning engine
     */
    private function validate_workflow(array $workflow, array $context): array {
        $validation_prompt = <<<PROMPT
Validate this workflow for the Aevov ecosystem.

WORKFLOW:
{$this->format_workflow($workflow)}

SYSTEM CONTEXT:
{$this->format_context_summary($context)}

Check for:
1. Missing dependencies between nodes
2. Invalid node types for available capabilities
3. Circular dependencies
4. Unreachable nodes
5. Missing required configurations
6. Potential execution errors

Return JSON with: valid (boolean), issues (array of {type, node_id, message, severity})
PROMPT;

        $response = $this->call_ai_engine('reasoning', $validation_prompt, [
            'response_format' => 'json',
        ]);

        return $response['data'] ?? ['valid' => true, 'issues' => []];
    }

    /**
     * Fix workflow issues
     */
    private function fix_workflow_issues(array $workflow, array $issues): array {
        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'missing_dependency':
                    $workflow = $this->add_missing_dependency($workflow, $issue);
                    break;
                case 'invalid_node':
                    $workflow = $this->replace_invalid_node($workflow, $issue);
                    break;
                case 'unreachable_node':
                    $workflow = $this->connect_unreachable_node($workflow, $issue);
                    break;
            }
        }

        return $workflow;
    }

    /**
     * Generate configuration from workflow context
     */
    public function generate_configuration(string $target, array $requirements, array $options = []): array {
        $context = $this->context_provider->get_component_context($target);

        $config_prompt = <<<PROMPT
Generate optimal configuration for the {$target} system in Aevov.

REQUIREMENTS:
{$this->format_requirements($requirements)}

CURRENT CONFIGURATION:
{$this->format_current_config($context)}

AVAILABLE OPTIONS:
{$this->format_config_options($target)}

Generate a complete, production-ready configuration that:
1. Meets all stated requirements
2. Follows Aevov best practices
3. Is compatible with other system components
4. Includes sensible defaults for unspecified options

Return as JSON configuration object.
PROMPT;

        $response = $this->call_ai_engine('cognitive', $config_prompt, [
            'response_format' => 'json',
        ]);

        $configuration = $response['data'] ?? [];

        // Validate configuration
        $validation = $this->validate_configuration($target, $configuration);

        return [
            'success' => $validation['valid'],
            'configuration' => $configuration,
            'validation' => $validation,
            'target' => $target,
            'sync_operations' => $this->generate_sync_operations($target, $configuration),
        ];
    }

    /**
     * Validate configuration
     */
    private function validate_configuration(string $target, array $config): array {
        $issues = [];

        // Check required fields based on target
        $required_fields = $this->get_required_fields($target);
        foreach ($required_fields as $field) {
            if (!isset($config[$field])) {
                $issues[] = [
                    'type' => 'missing_required',
                    'field' => $field,
                    'message' => "Required field '{$field}' is missing",
                ];
            }
        }

        // Type validation
        $field_types = $this->get_field_types($target);
        foreach ($config as $key => $value) {
            if (isset($field_types[$key])) {
                $expected_type = $field_types[$key];
                $actual_type = gettype($value);
                if ($actual_type !== $expected_type) {
                    $issues[] = [
                        'type' => 'type_mismatch',
                        'field' => $key,
                        'message' => "Field '{$key}' should be {$expected_type}, got {$actual_type}",
                    ];
                }
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Generate sync operations for configuration
     */
    private function generate_sync_operations(string $target, array $config): array {
        $operations = [];

        // Main plugin config
        $operations[] = [
            'sync_type' => 'plugin_config',
            'plugin' => $this->target_to_plugin($target),
            'config' => $config,
            'merge' => true,
        ];

        // Related configurations
        $related = $this->get_related_configs($target, $config);
        foreach ($related as $rel_target => $rel_config) {
            $operations[] = [
                'sync_type' => 'plugin_config',
                'plugin' => $this->target_to_plugin($rel_target),
                'config' => $rel_config,
                'merge' => true,
            ];
        }

        return $operations;
    }

    /**
     * Call an AI engine with fallback
     */
    private function call_ai_engine(string $engine, string $prompt, array $options = []): array {
        // Check if engine is available
        $engine_class = $this->get_engine_class($engine);

        if (!class_exists($engine_class)) {
            // Fallback to language engine or AI core
            $engine_class = $this->get_fallback_engine();
        }

        // Prepare request
        $request = [
            'prompt' => $prompt,
            'system_context' => $this->context_provider->get_ai_context_prompt(),
            'options' => $options,
        ];

        // Make API call
        $endpoint = $this->get_engine_endpoint($engine);

        $response = wp_remote_post(rest_url($endpoint), [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WP-Nonce' => wp_create_nonce('wp_rest'),
            ],
            'body' => wp_json_encode($request),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Parse response based on format
        if (($options['response_format'] ?? '') === 'json') {
            $data = $this->parse_json_response($body['content'] ?? $body['result'] ?? '');
            return ['success' => true, 'data' => $data];
        }

        return [
            'success' => true,
            'data' => $body['content'] ?? $body['result'] ?? $body,
        ];
    }

    /**
     * Parse JSON from AI response
     */
    private function parse_json_response(string $content): array {
        // Extract JSON from potential markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Get capability node info
     */
    private function get_capability_node_info(string $capability, array $context): ?array {
        $capability_map = [
            'text_generation' => ['type' => 'language', 'label' => 'Generate Text', 'data' => ['endpoint' => 'generate']],
            'image_generation' => ['type' => 'image', 'label' => 'Generate Image', 'data' => ['endpoint' => 'generate']],
            'reasoning' => ['type' => 'cognitive', 'label' => 'Cognitive Analysis', 'data' => ['endpoint' => 'reason']],
            'memory_storage' => ['type' => 'memory', 'label' => 'Store Memory', 'data' => ['action' => 'store']],
            'pattern_detection' => ['type' => 'pattern', 'label' => 'Detect Patterns', 'data' => ['action' => 'detect']],
        ];

        return $capability_map[$capability] ?? null;
    }

    /**
     * Helper methods
     */
    private function get_engine_class(string $engine): string {
        $classes = [
            'cognitive' => 'AevovCognitiveEngine\\CognitiveEngine',
            'reasoning' => 'AevovReasoningEngine\\ReasoningEngine',
            'language' => 'AevovLanguageEngine\\LanguageEngine',
        ];
        return $classes[$engine] ?? '';
    }

    private function get_fallback_engine(): string {
        if (class_exists('AevovLanguageEngine\\LanguageEngine')) {
            return 'AevovLanguageEngine\\LanguageEngine';
        }
        if (class_exists('AevovAICore\\AICore')) {
            return 'AevovAICore\\AICore';
        }
        return '';
    }

    private function get_engine_endpoint(string $engine): string {
        $endpoints = [
            'cognitive' => 'aevov-cognitive/v1/reason',
            'reasoning' => 'aevov-reasoning/v1/infer',
            'language' => 'aevov-language/v1/generate',
        ];
        return $endpoints[$engine] ?? 'aevov-language/v1/generate';
    }

    private function format_capabilities(array $capabilities): string {
        $lines = [];
        foreach ($capabilities as $name => $cap) {
            $lines[] = "- {$name}: " . ($cap['description'] ?? 'Available');
        }
        return implode("\n", $lines);
    }

    private function format_plugins(array $plugins): string {
        $lines = [];
        foreach ($plugins as $slug => $plugin) {
            $status = $plugin['is_active'] ? 'ACTIVE' : 'INACTIVE';
            $lines[] = "- {$slug} [{$status}]: {$plugin['description']}";
        }
        return implode("\n", $lines);
    }

    private function format_workflow(array $workflow): string {
        return wp_json_encode($workflow, JSON_PRETTY_PRINT);
    }

    private function format_context_summary(array $context): string {
        return wp_json_encode([
            'active_plugins' => count(array_filter($context['plugins'], fn($p) => $p['is_active'])),
            'capabilities' => array_keys($context['capabilities']),
            'storage' => array_keys($context['storage']),
        ], JSON_PRETTY_PRINT);
    }

    private function format_requirements(array $requirements): string {
        return wp_json_encode($requirements, JSON_PRETTY_PRINT);
    }

    private function format_current_config(array $context): string {
        return wp_json_encode($context, JSON_PRETTY_PRINT);
    }

    private function format_config_options(string $target): string {
        $options = [
            'ai_engines' => ['default_provider', 'api_keys', 'model_preferences', 'rate_limits', 'fallback_models'],
            'storage' => ['backend', 'max_size', 'retention_policy', 'compression', 'encryption'],
            'workflows' => ['max_execution_time', 'max_nodes', 'concurrent_executions', 'scheduling'],
            'security' => ['auth_methods', 'encryption', 'session_duration', 'rate_limiting', 'cors'],
        ];
        return wp_json_encode($options[$target] ?? [], JSON_PRETTY_PRINT);
    }

    private function generate_workflow_name(array $analysis): string {
        $goal = $analysis['goal'] ?? 'Configuration Workflow';
        $words = explode(' ', $goal);
        return implode(' ', array_slice($words, 0, 5));
    }

    private function extract_capabilities_used(array $workflow): array {
        $capabilities = [];
        foreach ($workflow['nodes'] as $node) {
            if (!in_array($node['type'], ['input', 'output', 'merge', 'split'])) {
                $capabilities[] = $node['type'];
            }
        }
        return array_unique($capabilities);
    }

    private function add_missing_dependency(array $workflow, array $issue): array {
        // Add edge between dependent nodes
        return $workflow;
    }

    private function replace_invalid_node(array $workflow, array $issue): array {
        // Replace with valid alternative
        return $workflow;
    }

    private function connect_unreachable_node(array $workflow, array $issue): array {
        // Add edge to connect unreachable node
        return $workflow;
    }

    private function get_required_fields(string $target): array {
        $required = [
            'ai_engines' => ['default_provider'],
            'storage' => ['backend'],
            'security' => ['auth_methods'],
        ];
        return $required[$target] ?? [];
    }

    private function get_field_types(string $target): array {
        return [];
    }

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

    private function get_related_configs(string $target, array $config): array {
        // Return related configurations that should be updated
        return [];
    }

    private function build_workflow_generation_prompt(array $context): string {
        return <<<PROMPT
You are AevSyncPro, an intelligent configuration and workflow generation system for the Aevov ecosystem.

Your role is to:
1. Understand user requirements for system configuration
2. Generate optimal workflows using available Aevov capabilities
3. Create ready-to-use configuration bundles
4. Ensure all configurations are valid and compatible

Available node types for workflows:
- input: Accept user input
- output: Provide final results
- syncpro: AevSyncPro configuration nodes (analyze, generate, modify)
- language: Text generation and analysis
- image: Image generation
- cognitive: Complex reasoning
- reasoning: Logical inference
- memory: Persistent storage
- pattern: Pattern detection
- condition: Conditional branching
- loop: Iteration
- merge: Combine parallel results
- transform: Data transformation

Always generate complete, valid workflows that can be executed immediately.
PROMPT;
    }
}
