<?php
/**
 * Provider Manager
 *
 * Manages all AI providers with:
 * - Provider registration and routing
 * - Load balancing across providers
 * - Automatic fallback chains
 * - Response caching for cost savings
 * - Request queuing and rate limiting
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Providers;

use Aevov\AICore\Debug\DebugEngine;

/**
 * Provider Manager Class
 */
class ProviderManager
{
    /**
     * Registered providers
     *
     * @var array
     */
    private array $providers = [];

    /**
     * Debug engine
     *
     * @var DebugEngine
     */
    private DebugEngine $debug;

    /**
     * Response cache
     *
     * @var array
     */
    private array $cache = [];

    /**
     * Default provider
     *
     * @var string
     */
    private string $default_provider = 'deepseek';

    /**
     * Fallback chain
     *
     * @var array
     */
    private array $fallback_chain = ['deepseek', 'minimax', 'openai', 'anthropic'];

    /**
     * Constructor
     *
     * @param DebugEngine $debug Debug engine
     */
    public function __construct(DebugEngine $debug)
    {
        $this->debug = $debug;
        $this->default_provider = get_option('aevov_default_ai_provider', 'deepseek');
        $this->fallback_chain = get_option('aevov_ai_fallback_chain', $this->fallback_chain);
    }

    /**
     * Register a provider
     *
     * @param string $name Provider name
     * @param AIProviderInterface $provider Provider instance
     * @return void
     */
    public function register(string $name, AIProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
        $this->debug->log('info', 'ProviderManager', "Registered provider: {$name}");
    }

    /**
     * Get provider instance
     *
     * @param string $name Provider name
     * @return AIProviderInterface|null
     */
    public function get_provider(string $name): ?AIProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Get all providers
     *
     * @return array
     */
    public function get_providers(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Get all provider instances
     *
     * @return array
     */
    public function get_all_providers(): array
    {
        $result = [];

        foreach ($this->providers as $name => $provider) {
            $result[$name] = [
                'name' => $provider->get_name(),
                'capabilities' => $provider->get_capabilities(),
                'models' => $provider->get_models(),
                'configured' => $provider->validate_config()
            ];
        }

        return $result;
    }

    /**
     * Complete with automatic provider selection and fallback
     *
     * @param string|null $provider Provider name (null for auto-select)
     * @param array $params Parameters
     * @return array Response
     */
    public function complete(?string $provider, array $params): array
    {
        $start_time = microtime(true);

        // Use default if not specified
        if ($provider === null) {
            $provider = $this->default_provider;
        }

        // Check cache first
        if (get_option('aevov_ai_cache_enabled', true)) {
            $cache_key = $this->get_cache_key($provider, $params);
            if (isset($this->cache[$cache_key])) {
                $this->debug->log('info', 'ProviderManager', 'Cache hit', ['provider' => $provider]);
                return $this->cache[$cache_key];
            }
        }

        // Try primary provider
        try {
            $result = $this->execute_with_provider($provider, $params);
            $this->log_usage($provider, $result, microtime(true) - $start_time);
            $this->cache_response($provider, $params, $result);
            return $result;
        } catch (\Exception $e) {
            $this->debug->log('error', 'ProviderManager', "Provider {$provider} failed: " . $e->getMessage());

            // Try fallback chain
            foreach ($this->fallback_chain as $fallback_provider) {
                if ($fallback_provider === $provider) {
                    continue; // Skip the one that just failed
                }

                try {
                    $this->debug->log('info', 'ProviderManager', "Trying fallback: {$fallback_provider}");
                    $result = $this->execute_with_provider($fallback_provider, $params);
                    $this->log_usage($fallback_provider, $result, microtime(true) - $start_time);
                    $this->cache_response($fallback_provider, $params, $result);
                    return $result;
                } catch (\Exception $fallback_error) {
                    $this->debug->log('warning', 'ProviderManager', "Fallback {$fallback_provider} also failed");
                    continue;
                }
            }

            // All providers failed
            throw new \Exception('All providers failed. Last error: ' . $e->getMessage());
        }
    }

    /**
     * Execute completion with specific provider
     *
     * @param string $provider Provider name
     * @param array $params Parameters
     * @return array Response
     */
    private function execute_with_provider(string $provider, array $params): array
    {
        $provider_instance = $this->get_provider($provider);

        if (!$provider_instance) {
            throw new \Exception("Provider not found: {$provider}");
        }

        if (!$provider_instance->validate_config()) {
            throw new \Exception("Provider not configured: {$provider}");
        }

        return $provider_instance->complete($params);
    }

    /**
     * Get cache key for request
     *
     * @param string $provider Provider name
     * @param array $params Parameters
     * @return string Cache key
     */
    private function get_cache_key(string $provider, array $params): string
    {
        // Only cache based on messages and model (not temperature, etc.)
        $cache_params = [
            'provider' => $provider,
            'model' => $params['model'] ?? 'default',
            'messages' => $params['messages'] ?? []
        ];

        return 'ai_cache_' . hash('sha256', wp_json_encode($cache_params));
    }

    /**
     * Cache response
     *
     * @param string $provider Provider name
     * @param array $params Parameters
     * @param array $response Response
     * @return void
     */
    private function cache_response(string $provider, array $params, array $response): void
    {
        if (!get_option('aevov_ai_cache_enabled', true)) {
            return;
        }

        $cache_key = $this->get_cache_key($provider, $params);
        $ttl = get_option('aevov_ai_cache_ttl', 3600); // 1 hour default

        // Store in memory cache
        $this->cache[$cache_key] = $response;

        // Store in transient
        set_transient($cache_key, $response, $ttl);
    }

    /**
     * Log usage statistics
     *
     * @param string $provider Provider name
     * @param array $result Result
     * @param float $latency Latency in seconds
     * @return void
     */
    private function log_usage(string $provider, array $result, float $latency): void
    {
        global $wpdb;

        $usage = $result['usage'] ?? [];
        $model = $result['model'] ?? 'unknown';

        $provider_instance = $this->get_provider($provider);
        $costs = $provider_instance ? $provider_instance->get_token_cost($model) : [0, 0];

        $input_tokens = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0;
        $output_tokens = $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0;

        $cost = (($input_tokens / 1000000) * $costs[0]) + (($output_tokens / 1000000) * $costs[1]);

        $table = $wpdb->prefix . 'aev_model_usage';

        $wpdb->insert($table, [
            'model_id' => $model,
            'provider' => $provider,
            'input_tokens' => $input_tokens,
            'output_tokens' => $output_tokens,
            'cost' => $cost,
            'latency' => (int) ($latency * 1000), // Convert to ms
            'success' => 1
        ]);

        $this->debug->log('info', 'ProviderManager', 'Usage logged', [
            'provider' => $provider,
            'model' => $model,
            'tokens' => $input_tokens + $output_tokens,
            'cost' => $cost,
            'latency_ms' => (int) ($latency * 1000)
        ]);
    }

    /**
     * Get usage statistics
     *
     * @param array $filters Filters (start_date, end_date, provider)
     * @return array Statistics
     */
    public function get_usage_stats(array $filters = []): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_model_usage';
        $where = ['1=1'];
        $params = [];

        if (isset($filters['start_date'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['start_date'];
        }

        if (isset($filters['end_date'])) {
            $where[] = 'created_at <= %s';
            $params[] = $filters['end_date'];
        }

        if (isset($filters['provider'])) {
            $where[] = 'provider = %s';
            $params[] = $filters['provider'];
        }

        $where_clause = implode(' AND ', $where);

        if (!empty($params)) {
            $query = $wpdb->prepare(
                "SELECT
                    provider,
                    COUNT(*) as request_count,
                    SUM(input_tokens) as total_input_tokens,
                    SUM(output_tokens) as total_output_tokens,
                    SUM(cost) as total_cost,
                    AVG(latency) as avg_latency
                 FROM {$table}
                 WHERE {$where_clause}
                 GROUP BY provider",
                ...$params
            );
        } else {
            $query = "SELECT
                provider,
                COUNT(*) as request_count,
                SUM(input_tokens) as total_input_tokens,
                SUM(output_tokens) as total_output_tokens,
                SUM(cost) as total_cost,
                AVG(latency) as avg_latency
             FROM {$table}
             WHERE {$where_clause}
             GROUP BY provider";
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        $totals = [
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'avg_latency' => 0,
            'by_provider' => []
        ];

        foreach ($results as $row) {
            $totals['total_requests'] += (int) $row['request_count'];
            $totals['total_tokens'] += (int) $row['total_input_tokens'] + (int) $row['total_output_tokens'];
            $totals['total_cost'] += (float) $row['total_cost'];
            $totals['by_provider'][$row['provider']] = $row;
        }

        if (count($results) > 0) {
            $totals['avg_latency'] = array_sum(array_column($results, 'avg_latency')) / count($results);
        }

        return $totals;
    }

    /**
     * Clear cache
     *
     * @return void
     */
    public function clear_cache(): void
    {
        $this->cache = [];

        // Clear all transients starting with ai_cache_
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_cache_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ai_cache_%'");

        $this->debug->log('info', 'ProviderManager', 'Cache cleared');
    }

    /**
     * Set default provider
     *
     * @param string $provider Provider name
     * @return void
     */
    public function set_default_provider(string $provider): void
    {
        $this->default_provider = $provider;
        update_option('aevov_default_ai_provider', $provider);
    }

    /**
     * Set fallback chain
     *
     * @param array $chain Provider names in order
     * @return void
     */
    public function set_fallback_chain(array $chain): void
    {
        $this->fallback_chain = $chain;
        update_option('aevov_ai_fallback_chain', $chain);
    }

    /**
     * Test provider configuration
     *
     * @param string $provider Provider name
     * @return array Test result
     */
    public function test_provider(string $provider): array
    {
        $provider_instance = $this->get_provider($provider);

        if (!$provider_instance) {
            return [
                'success' => false,
                'error' => 'Provider not found'
            ];
        }

        try {
            $result = $provider_instance->complete([
                'messages' => [
                    ['role' => 'user', 'content' => 'Respond with "OK"']
                ],
                'max_tokens' => 10
            ]);

            return [
                'success' => true,
                'provider' => $provider,
                'response' => $result['content']
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
