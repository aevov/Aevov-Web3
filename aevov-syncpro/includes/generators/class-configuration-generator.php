<?php
/**
 * Configuration Generator
 *
 * Generates ready-to-use configuration bundles for the Aevov ecosystem.
 * The output of each workflow is a complete configuration that can be
 * applied immediately.
 *
 * @package AevovSyncPro
 */

namespace AevovSyncPro\Generators;

use AevovSyncPro\Providers\SystemContextProvider;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration Generator Class
 *
 * Creates production-ready configurations based on workflow outputs,
 * user requirements, and system analysis.
 */
class ConfigurationGenerator {

    private SystemContextProvider $context_provider;

    /**
     * Configuration templates for different systems
     */
    private const CONFIG_TEMPLATES = [
        'ai_engines' => [
            'default_provider' => 'openai',
            'fallback_provider' => 'anthropic',
            'rate_limiting' => [
                'enabled' => true,
                'requests_per_minute' => 60,
                'tokens_per_minute' => 100000,
            ],
            'model_preferences' => [
                'text' => 'gpt-4',
                'image' => 'dall-e-3',
                'embedding' => 'text-embedding-3-small',
            ],
            'retry_policy' => [
                'max_retries' => 3,
                'backoff_multiplier' => 2,
            ],
        ],
        'storage' => [
            'primary_backend' => 'wordpress',
            'secondary_backend' => 'cubbit',
            'max_memory_size' => '1GB',
            'chunk_size' => '1MB',
            'compression' => [
                'enabled' => true,
                'algorithm' => 'gzip',
                'level' => 6,
            ],
            'retention_policy' => [
                'default_ttl' => 86400 * 30, // 30 days
                'auto_cleanup' => true,
            ],
        ],
        'workflows' => [
            'max_execution_time' => 300,
            'max_nodes' => 100,
            'max_concurrent_executions' => 5,
            'scheduling' => [
                'enabled' => true,
                'timezone' => 'UTC',
            ],
            'logging' => [
                'level' => 'info',
                'retention_days' => 30,
            ],
        ],
        'security' => [
            'authentication' => [
                'methods' => ['jwt', 'oauth2', 'api_key'],
                'jwt_expiry' => 604800, // 7 days
                'session_duration' => 86400,
            ],
            'encryption' => [
                'algorithm' => 'AES-256-GCM',
                'key_rotation' => true,
                'rotation_interval' => 86400 * 90,
            ],
            'rate_limiting' => [
                'enabled' => true,
                'window' => 60,
                'max_requests' => 100,
            ],
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['*'],
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
            ],
        ],
        'patterns' => [
            'bloom_filter_size' => 1000000,
            'false_positive_rate' => 0.01,
            'sync_interval' => 300,
            'auto_detect' => true,
        ],
        'memory' => [
            'storage_backend' => 'astrocyte',
            'index_strategy' => 'btree',
            'cache_enabled' => true,
            'cache_ttl' => 3600,
        ],
        'network' => [
            'protocol' => 'meshcore',
            'bootstrap_nodes' => [],
            'max_peers' => 50,
            'sync_strategy' => 'eventual',
            'encryption' => true,
        ],
    ];

    public function __construct(SystemContextProvider $context_provider) {
        $this->context_provider = $context_provider;
    }

    /**
     * Generate a complete configuration bundle
     */
    public function generate_bundle(array $requirements, array $options = []): array {
        $context = $this->context_provider->get_full_context();
        $bundle = [];

        // Determine which systems need configuration
        $targets = $this->determine_targets($requirements, $options);

        foreach ($targets as $target) {
            $config = $this->generate_config($target, $requirements, $context);
            if (!empty($config)) {
                $bundle[$target] = $config;
            }
        }

        // Apply cross-system optimizations
        $bundle = $this->optimize_bundle($bundle, $context);

        // Validate the complete bundle
        $validation = $this->validate_bundle($bundle, $context);

        return [
            'success' => $validation['valid'],
            'bundle' => $bundle,
            'validation' => $validation,
            'metadata' => [
                'generated_at' => current_time('mysql'),
                'generated_by' => 'AevSyncPro',
                'version' => AEVOV_SYNCPRO_VERSION,
                'targets' => $targets,
            ],
            'apply_instructions' => $this->generate_apply_instructions($bundle),
            'rollback_plan' => $this->generate_rollback_plan($bundle, $context),
        ];
    }

    /**
     * Generate configuration for a specific target
     */
    public function generate_config(string $target, array $requirements, array $context): array {
        // Start with template
        $config = self::CONFIG_TEMPLATES[$target] ?? [];

        // Apply requirement-based modifications
        $config = $this->apply_requirements($config, $requirements, $target);

        // Apply context-aware optimizations
        $config = $this->apply_context_optimizations($config, $context, $target);

        // Apply any custom configurations
        $config = $this->apply_custom_config($config, $requirements, $target);

        return $config;
    }

    /**
     * Determine which systems need configuration
     */
    private function determine_targets(array $requirements, array $options): array {
        if (!empty($options['target']) && $options['target'] !== 'all') {
            return [$options['target']];
        }

        $targets = [];

        // Analyze requirements to determine targets
        $keywords = $this->extract_keywords($requirements);

        $target_keywords = [
            'ai_engines' => ['ai', 'model', 'gpt', 'claude', 'language', 'image', 'generation'],
            'storage' => ['storage', 'memory', 'database', 'save', 'store', 'persist'],
            'workflows' => ['workflow', 'automation', 'execute', 'schedule', 'process'],
            'security' => ['security', 'auth', 'authentication', 'permission', 'encrypt'],
            'patterns' => ['pattern', 'bloom', 'sync', 'detect'],
            'memory' => ['memory', 'remember', 'recall', 'knowledge'],
            'network' => ['network', 'mesh', 'peer', 'distributed', 'p2p'],
        ];

        foreach ($target_keywords as $target => $kws) {
            if (array_intersect($keywords, $kws)) {
                $targets[] = $target;
            }
        }

        // If no specific targets, configure all
        if (empty($targets)) {
            $targets = array_keys(self::CONFIG_TEMPLATES);
        }

        return $targets;
    }

    /**
     * Extract keywords from requirements
     */
    private function extract_keywords(array $requirements): array {
        $text = '';

        if (is_string($requirements)) {
            $text = $requirements;
        } elseif (isset($requirements['description'])) {
            $text = $requirements['description'];
        } elseif (isset($requirements['prompt'])) {
            $text = $requirements['prompt'];
        } else {
            $text = wp_json_encode($requirements);
        }

        $words = preg_split('/\s+/', strtolower($text));
        return array_unique($words);
    }

    /**
     * Apply requirements to configuration
     */
    private function apply_requirements(array $config, array $requirements, string $target): array {
        // Handle specific requirement patterns
        if (isset($requirements['performance']) && $requirements['performance'] === 'high') {
            $config = $this->apply_high_performance($config, $target);
        }

        if (isset($requirements['security']) && $requirements['security'] === 'strict') {
            $config = $this->apply_strict_security($config, $target);
        }

        if (isset($requirements['storage']) && $requirements['storage'] === 'distributed') {
            $config = $this->apply_distributed_storage($config, $target);
        }

        // Apply direct overrides from requirements
        if (isset($requirements['config'][$target])) {
            $config = array_replace_recursive($config, $requirements['config'][$target]);
        }

        return $config;
    }

    /**
     * Apply context-aware optimizations
     */
    private function apply_context_optimizations(array $config, array $context, string $target): array {
        // Check available plugins and adjust
        $active_plugins = array_filter($context['plugins'], fn($p) => $p['is_active']);

        switch ($target) {
            case 'ai_engines':
                // Check if specific AI providers are available
                if (isset($active_plugins['aevov-ai-core'])) {
                    $providers = $context['ai_engines']['_core']['providers'] ?? [];
                    if (!in_array('openai', $providers) && in_array('anthropic', $providers)) {
                        $config['default_provider'] = 'anthropic';
                    }
                }
                break;

            case 'storage':
                // Check if Cubbit is configured
                if (isset($context['storage']['cdn']) && $context['storage']['cdn']['configured']) {
                    $config['secondary_backend'] = 'cubbit';
                    $config['offload_large_files'] = true;
                }
                break;

            case 'network':
                // Check Meshcore status
                if (isset($context['network']['peers'])) {
                    $config['bootstrap_nodes'] = array_slice($context['network']['peers'], 0, 5);
                }
                break;
        }

        return $config;
    }

    /**
     * Apply custom configuration
     */
    private function apply_custom_config(array $config, array $requirements, string $target): array {
        // Apply any custom settings from hooks
        $config = apply_filters("aevov_syncpro_config_{$target}", $config, $requirements);
        $config = apply_filters('aevov_syncpro_config', $config, $target, $requirements);

        return $config;
    }

    /**
     * Optimize the complete bundle
     */
    private function optimize_bundle(array $bundle, array $context): array {
        // Cross-system optimizations

        // Ensure AI rate limits don't conflict with workflow concurrency
        if (isset($bundle['ai_engines']['rate_limiting']) && isset($bundle['workflows']['max_concurrent_executions'])) {
            $concurrent = $bundle['workflows']['max_concurrent_executions'];
            $ai_rpm = $bundle['ai_engines']['rate_limiting']['requests_per_minute'];

            // Adjust if necessary
            if ($concurrent * 10 > $ai_rpm) {
                $bundle['ai_engines']['rate_limiting']['requests_per_minute'] = $concurrent * 15;
            }
        }

        // Ensure security settings are consistent
        if (isset($bundle['security']) && isset($bundle['network'])) {
            if ($bundle['security']['encryption']['algorithm'] !== null) {
                $bundle['network']['encryption'] = true;
            }
        }

        // Memory and storage consistency
        if (isset($bundle['memory']) && isset($bundle['storage'])) {
            $bundle['memory']['storage_backend'] = $bundle['storage']['primary_backend'] === 'wordpress'
                ? 'astrocyte'
                : 'external';
        }

        return $bundle;
    }

    /**
     * Validate the configuration bundle
     */
    private function validate_bundle(array $bundle, array $context): array {
        $issues = [];

        foreach ($bundle as $target => $config) {
            $target_issues = $this->validate_target_config($target, $config, $context);
            if (!empty($target_issues)) {
                $issues[$target] = $target_issues;
            }
        }

        // Check cross-system dependencies
        $cross_issues = $this->validate_cross_dependencies($bundle, $context);
        if (!empty($cross_issues)) {
            $issues['_cross_system'] = $cross_issues;
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Validate configuration for a specific target
     */
    private function validate_target_config(string $target, array $config, array $context): array {
        $issues = [];

        switch ($target) {
            case 'ai_engines':
                // Check if default provider is available
                $provider = $config['default_provider'] ?? 'openai';
                $available_providers = $context['ai_engines']['_core']['providers'] ?? [];
                if (!in_array($provider, $available_providers)) {
                    $issues[] = [
                        'type' => 'unavailable_provider',
                        'message' => "AI provider '{$provider}' is not configured",
                        'severity' => 'warning',
                    ];
                }
                break;

            case 'storage':
                // Check storage backend availability
                if ($config['secondary_backend'] === 'cubbit') {
                    if (!($context['storage']['cdn']['configured'] ?? false)) {
                        $issues[] = [
                            'type' => 'unconfigured_backend',
                            'message' => 'Cubbit CDN is not configured',
                            'severity' => 'warning',
                        ];
                    }
                }
                break;

            case 'network':
                // Check if Meshcore is active
                if (!($context['network']['meshcore_active'] ?? false)) {
                    $issues[] = [
                        'type' => 'inactive_plugin',
                        'message' => 'Meshcore plugin is not active',
                        'severity' => 'error',
                    ];
                }
                break;
        }

        return $issues;
    }

    /**
     * Validate cross-system dependencies
     */
    private function validate_cross_dependencies(array $bundle, array $context): array {
        $issues = [];

        // Check workflow engine requirements
        if (isset($bundle['workflows'])) {
            if (!($context['plugins']['aevov-workflow-engine']['is_active'] ?? false)) {
                $issues[] = [
                    'type' => 'missing_dependency',
                    'message' => 'Workflow configuration requires aevov-workflow-engine plugin',
                    'severity' => 'error',
                ];
            }
        }

        // Check security requirements for network
        if (isset($bundle['network']) && $bundle['network']['encryption']) {
            if (!isset($bundle['security']) || !$bundle['security']['encryption']['algorithm']) {
                $issues[] = [
                    'type' => 'missing_config',
                    'message' => 'Network encryption requires security encryption configuration',
                    'severity' => 'warning',
                ];
            }
        }

        return $issues;
    }

    /**
     * Generate apply instructions
     */
    private function generate_apply_instructions(array $bundle): array {
        $instructions = [];

        foreach ($bundle as $target => $config) {
            $plugin = $this->target_to_plugin($target);
            $option_key = str_replace('-', '_', $plugin) . '_settings';

            $instructions[] = [
                'step' => count($instructions) + 1,
                'target' => $target,
                'action' => 'update_option',
                'option_key' => $option_key,
                'description' => "Apply {$target} configuration",
            ];
        }

        // Add post-apply actions
        $instructions[] = [
            'step' => count($instructions) + 1,
            'action' => 'flush_cache',
            'description' => 'Flush all caches to apply changes',
        ];

        $instructions[] = [
            'step' => count($instructions) + 1,
            'action' => 'verify',
            'description' => 'Verify configuration was applied correctly',
        ];

        return $instructions;
    }

    /**
     * Generate rollback plan
     */
    private function generate_rollback_plan(array $bundle, array $context): array {
        $plan = [
            'snapshots' => [],
            'steps' => [],
        ];

        foreach ($bundle as $target => $config) {
            $plugin = $this->target_to_plugin($target);
            $option_key = str_replace('-', '_', $plugin) . '_settings';
            $current_value = get_option($option_key, []);

            $plan['snapshots'][$target] = [
                'option_key' => $option_key,
                'original_value' => $current_value,
                'captured_at' => current_time('mysql'),
            ];

            $plan['steps'][] = [
                'target' => $target,
                'action' => 'restore',
                'description' => "Restore {$target} to previous configuration",
            ];
        }

        return $plan;
    }

    /**
     * Apply high performance settings
     */
    private function apply_high_performance(array $config, string $target): array {
        switch ($target) {
            case 'ai_engines':
                $config['rate_limiting']['requests_per_minute'] = 120;
                $config['retry_policy']['max_retries'] = 5;
                break;

            case 'storage':
                $config['compression']['level'] = 1; // Faster
                $config['cache_enabled'] = true;
                break;

            case 'workflows':
                $config['max_execution_time'] = 600;
                $config['max_concurrent_executions'] = 10;
                break;
        }

        return $config;
    }

    /**
     * Apply strict security settings
     */
    private function apply_strict_security(array $config, string $target): array {
        switch ($target) {
            case 'security':
                $config['authentication']['jwt_expiry'] = 3600; // 1 hour
                $config['authentication']['methods'] = ['jwt', 'oauth2'];
                $config['encryption']['key_rotation'] = true;
                $config['encryption']['rotation_interval'] = 86400 * 30;
                $config['rate_limiting']['max_requests'] = 50;
                break;

            case 'network':
                $config['encryption'] = true;
                $config['max_peers'] = 25;
                break;
        }

        return $config;
    }

    /**
     * Apply distributed storage settings
     */
    private function apply_distributed_storage(array $config, string $target): array {
        if ($target === 'storage') {
            $config['secondary_backend'] = 'cubbit';
            $config['replication'] = [
                'enabled' => true,
                'factor' => 3,
            ];
            $config['sharding'] = [
                'enabled' => true,
                'strategy' => 'consistent_hash',
            ];
        }

        return $config;
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

    /**
     * Export configuration as file
     */
    public function export_bundle(array $bundle, string $format = 'json'): string {
        switch ($format) {
            case 'json':
                return wp_json_encode($bundle, JSON_PRETTY_PRINT);

            case 'yaml':
                return $this->to_yaml($bundle);

            case 'php':
                return "<?php\nreturn " . var_export($bundle, true) . ";";

            default:
                return wp_json_encode($bundle);
        }
    }

    /**
     * Convert to YAML format
     */
    private function to_yaml(array $data, int $indent = 0): string {
        $output = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $output .= "{$prefix}{$key}:\n" . $this->to_yaml($value, $indent + 1);
            } else {
                $val = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                $output .= "{$prefix}{$key}: {$val}\n";
            }
        }

        return $output;
    }

    /**
     * Import configuration from file
     */
    public function import_bundle(string $content, string $format = 'json'): array {
        switch ($format) {
            case 'json':
                return json_decode($content, true) ?: [];

            default:
                return json_decode($content, true) ?: [];
        }
    }
}
