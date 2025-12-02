<?php
/**
 * Model Converter
 *
 * Converts between different model formats:
 * - .aev to provider-specific formats
 * - Provider formats to .aev
 * - Cross-provider model transfer
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Models;

use Aevov\AICore\Debug\DebugEngine;

/**
 * Model Converter Class
 */
class ModelConverter
{
    /**
     * Debug engine
     *
     * @var DebugEngine
     */
    private DebugEngine $debug;

    /**
     * Constructor
     *
     * @param DebugEngine $debug Debug engine
     */
    public function __construct(DebugEngine $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Convert AEV model to OpenAI fine-tuning format
     *
     * @param AevModel $model AEV model
     * @return string JSONL formatted data
     */
    public function to_openai_format(AevModel $model): string
    {
        $this->debug->log('info', 'ModelConverter', 'Converting to OpenAI format');

        $lines = [];
        $system_prompt = $model->get_system_prompt();

        foreach ($model->get_training_data() as $item) {
            $messages = [];

            if (!empty($system_prompt)) {
                $messages[] = ['role' => 'system', 'content' => $system_prompt];
            }

            if ($item['type'] === 'conversation') {
                $messages = array_merge($messages, $item['messages']);
            } elseif ($item['type'] === 'example') {
                $messages[] = ['role' => 'user', 'content' => $item['input']];
                $messages[] = ['role' => 'assistant', 'content' => $item['output']];
            }

            $lines[] = wp_json_encode(['messages' => $messages]);
        }

        return implode("\n", $lines);
    }

    /**
     * Convert AEV model to Anthropic fine-tuning format
     *
     * @param AevModel $model AEV model
     * @return string JSONL formatted data
     */
    public function to_anthropic_format(AevModel $model): string
    {
        $this->debug->log('info', 'ModelConverter', 'Converting to Anthropic format');

        $lines = [];
        $system_prompt = $model->get_system_prompt();

        foreach ($model->get_training_data() as $item) {
            $data = [
                'system' => $system_prompt
            ];

            if ($item['type'] === 'conversation') {
                $data['messages'] = $item['messages'];
            } elseif ($item['type'] === 'example') {
                $data['messages'] = [
                    ['role' => 'user', 'content' => $item['input']],
                    ['role' => 'assistant', 'content' => $item['output']]
                ];
            }

            $lines[] = wp_json_encode($data);
        }

        return implode("\n", $lines);
    }

    /**
     * Convert AEV model to DeepSeek fine-tuning format
     *
     * @param AevModel $model AEV model
     * @return string JSONL formatted data
     */
    public function to_deepseek_format(AevModel $model): string
    {
        $this->debug->log('info', 'ModelConverter', 'Converting to DeepSeek format');

        // DeepSeek uses OpenAI-compatible format
        return $this->to_openai_format($model);
    }

    /**
     * Convert AEV model to MiniMax fine-tuning format
     *
     * @param AevModel $model AEV model
     * @return string JSONL formatted data
     */
    public function to_minimax_format(AevModel $model): string
    {
        $this->debug->log('info', 'ModelConverter', 'Converting to MiniMax format');

        $lines = [];

        foreach ($model->get_training_data() as $item) {
            $data = [
                'prompt' => '',
                'response' => ''
            ];

            if ($item['type'] === 'conversation') {
                $prompt_parts = [];
                $response = '';

                foreach ($item['messages'] as $msg) {
                    if ($msg['role'] === 'user') {
                        $prompt_parts[] = $msg['content'];
                    } elseif ($msg['role'] === 'assistant') {
                        $response = $msg['content'];
                    }
                }

                $data['prompt'] = implode("\n", $prompt_parts);
                $data['response'] = $response;
            } elseif ($item['type'] === 'example') {
                $data['prompt'] = $item['input'];
                $data['response'] = $item['output'];
            }

            if (!empty($data['prompt']) && !empty($data['response'])) {
                $lines[] = wp_json_encode($data);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Convert to provider format
     *
     * @param AevModel $model AEV model
     * @param string $provider Provider name
     * @return string Formatted data
     * @throws \Exception
     */
    public function to_provider_format(AevModel $model, string $provider): string
    {
        switch ($provider) {
            case 'openai':
                return $this->to_openai_format($model);
            case 'anthropic':
                return $this->to_anthropic_format($model);
            case 'deepseek':
                return $this->to_deepseek_format($model);
            case 'minimax':
                return $this->to_minimax_format($model);
            default:
                throw new \Exception("Unsupported provider: {$provider}");
        }
    }

    /**
     * Import from OpenAI format
     *
     * @param string $jsonl_data JSONL formatted data
     * @param array $metadata Model metadata
     * @return AevModel
     */
    public function from_openai_format(string $jsonl_data, array $metadata = []): AevModel
    {
        $this->debug->log('info', 'ModelConverter', 'Importing from OpenAI format');

        $model = new AevModel([
            'name' => $metadata['name'] ?? 'Imported OpenAI Model',
            'base_provider' => 'openai',
            'base_model' => $metadata['base_model'] ?? 'gpt-3.5-turbo',
            'metadata' => array_merge($metadata, [
                'import_source' => 'openai',
                'import_date' => current_time('mysql')
            ])
        ]);

        $lines = explode("\n", trim($jsonl_data));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $data = json_decode($line, true);
            if (!$data || !isset($data['messages'])) {
                continue;
            }

            $messages = $data['messages'];
            $system_prompt = '';

            // Extract system prompt
            if ($messages[0]['role'] === 'system') {
                $system_prompt = array_shift($messages)['content'];
                if (empty($model->get_system_prompt())) {
                    $model->set_system_prompt($system_prompt);
                }
            }

            // Add as conversation
            $model->add_training_conversation(['messages' => $messages]);
        }

        return $model;
    }

    /**
     * Import from Anthropic format
     *
     * @param string $jsonl_data JSONL formatted data
     * @param array $metadata Model metadata
     * @return AevModel
     */
    public function from_anthropic_format(string $jsonl_data, array $metadata = []): AevModel
    {
        $this->debug->log('info', 'ModelConverter', 'Importing from Anthropic format');

        $model = new AevModel([
            'name' => $metadata['name'] ?? 'Imported Anthropic Model',
            'base_provider' => 'anthropic',
            'base_model' => $metadata['base_model'] ?? 'claude-3-sonnet',
            'metadata' => array_merge($metadata, [
                'import_source' => 'anthropic',
                'import_date' => current_time('mysql')
            ])
        ]);

        $lines = explode("\n", trim($jsonl_data));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $data = json_decode($line, true);
            if (!$data) {
                continue;
            }

            if (isset($data['system']) && empty($model->get_system_prompt())) {
                $model->set_system_prompt($data['system']);
            }

            if (isset($data['messages'])) {
                $model->add_training_conversation(['messages' => $data['messages']]);
            }
        }

        return $model;
    }

    /**
     * Import from provider format
     *
     * @param string $data Formatted data
     * @param string $provider Provider name
     * @param array $metadata Model metadata
     * @return AevModel
     * @throws \Exception
     */
    public function from_provider_format(string $data, string $provider, array $metadata = []): AevModel
    {
        switch ($provider) {
            case 'openai':
            case 'deepseek':
                return $this->from_openai_format($data, $metadata);
            case 'anthropic':
                return $this->from_anthropic_format($data, $metadata);
            default:
                throw new \Exception("Import from {$provider} not yet supported");
        }
    }

    /**
     * Merge multiple models
     *
     * @param array $models Array of AevModel instances
     * @param string $new_name Name for merged model
     * @return AevModel
     */
    public function merge_models(array $models, string $new_name): AevModel
    {
        $this->debug->log('info', 'ModelConverter', 'Merging models', [
            'model_count' => count($models)
        ]);

        if (empty($models)) {
            throw new \Exception('No models to merge');
        }

        $first_model = $models[0];

        $merged = new AevModel([
            'name' => $new_name,
            'base_provider' => $first_model->get_base_provider(),
            'base_model' => $first_model->get_base_model(),
            'metadata' => [
                'merged_from' => count($models),
                'merge_date' => current_time('mysql')
            ]
        ]);

        // Use first model's system prompt
        $merged->set_system_prompt($first_model->get_system_prompt());

        // Merge all training data
        foreach ($models as $model) {
            foreach ($model->get_training_data() as $item) {
                if ($item['type'] === 'conversation') {
                    $merged->add_training_conversation($item);
                } elseif ($item['type'] === 'example') {
                    $merged->add_training_example(
                        $item['input'],
                        $item['output'],
                        $item['metadata']
                    );
                }
            }
        }

        return $merged;
    }

    /**
     * Transfer model between providers
     *
     * @param AevModel $model Source model
     * @param string $target_provider Target provider
     * @param string $target_model Target model ID
     * @return AevModel
     */
    public function transfer_between_providers(
        AevModel $model,
        string $target_provider,
        string $target_model
    ): AevModel {
        $this->debug->log('info', 'ModelConverter', 'Transferring between providers', [
            'source' => $model->get_base_provider(),
            'target' => $target_provider
        ]);

        $transferred = $model->clone_model($model->get_name() . " ({$target_provider})");

        // Update provider metadata
        $data = $transferred->to_array();
        $data['base_provider'] = $target_provider;
        $data['base_model'] = $target_model;
        $data['metadata']['transferred_from'] = $model->get_base_provider();
        $data['metadata']['transfer_date'] = current_time('mysql');

        return new AevModel($data);
    }
}
