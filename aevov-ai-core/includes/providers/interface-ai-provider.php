<?php
/**
 * AI Provider Interface
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Providers;

/**
 * AI Provider Interface
 *
 * All AI providers must implement this interface
 */
interface AIProviderInterface
{
    /**
     * Get provider name
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Get supported capabilities
     *
     * @return array
     */
    public function get_capabilities(): array;

    /**
     * Get available models
     *
     * @return array
     */
    public function get_models(): array;

    /**
     * Complete text
     *
     * @param array $params Parameters
     * @return array Response
     */
    public function complete(array $params): array;

    /**
     * Stream completion
     *
     * @param array $params Parameters
     * @param callable $callback Callback for each chunk
     * @return void
     */
    public function stream(array $params, callable $callback): void;

    /**
     * Get API endpoint
     *
     * @return string
     */
    public function get_endpoint(): string;

    /**
     * Get API key
     *
     * @return string
     */
    public function get_api_key(): string;

    /**
     * Set API key
     *
     * @param string $api_key API key
     * @return void
     */
    public function set_api_key(string $api_key): void;

    /**
     * Validate configuration
     *
     * @return bool
     */
    public function validate_config(): bool;

    /**
     * Get cost per token
     *
     * @param string $model Model name
     * @return array [input_cost, output_cost]
     */
    public function get_token_cost(string $model): array;
}
