<?php
/**
 * AEV Model Class
 *
 * Handles .aev model format with:
 * - Model metadata and configuration
 * - Conversation history storage
 * - Model weights and parameters
 * - Version control and tracking
 * - Serialization/deserialization
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Models;

/**
 * AEV Model Class
 */
class AevModel
{
    /**
     * Model ID
     *
     * @var string
     */
    private string $id;

    /**
     * Model name
     *
     * @var string
     */
    private string $name;

    /**
     * Model version
     *
     * @var string
     */
    private string $version;

    /**
     * Base provider
     *
     * @var string
     */
    private string $base_provider;

    /**
     * Base model
     *
     * @var string
     */
    private string $base_model;

    /**
     * Model metadata
     *
     * @var array
     */
    private array $metadata = [];

    /**
     * Training data
     *
     * @var array
     */
    private array $training_data = [];

    /**
     * Model parameters
     *
     * @var array
     */
    private array $parameters = [];

    /**
     * System prompt
     *
     * @var string
     */
    private string $system_prompt = '';

    /**
     * Fine-tuning config
     *
     * @var array
     */
    private array $fine_tuning_config = [];

    /**
     * Performance metrics
     *
     * @var array
     */
    private array $metrics = [];

    /**
     * Constructor
     *
     * @param array $data Model data
     */
    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? uniqid('aev_model_');
        $this->name = $data['name'] ?? '';
        $this->version = $data['version'] ?? '1.0.0';
        $this->base_provider = $data['base_provider'] ?? '';
        $this->base_model = $data['base_model'] ?? '';
        $this->metadata = $data['metadata'] ?? [];
        $this->training_data = $data['training_data'] ?? [];
        $this->parameters = $data['parameters'] ?? [];
        $this->system_prompt = $data['system_prompt'] ?? '';
        $this->fine_tuning_config = $data['fine_tuning_config'] ?? [];
        $this->metrics = $data['metrics'] ?? [];
    }

    /**
     * Create from file
     *
     * @param string $file_path Path to .aev file
     * @return self
     * @throws \Exception
     */
    public static function from_file(string $file_path): self
    {
        if (!file_exists($file_path)) {
            throw new \Exception("Model file not found: {$file_path}");
        }

        $content = file_get_contents($file_path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid .aev file format: ' . json_last_error_msg());
        }

        return new self($data);
    }

    /**
     * Save to file
     *
     * @param string $file_path Path to save .aev file
     * @return bool
     * @throws \Exception
     */
    public function save_to_file(string $file_path): bool
    {
        $data = $this->to_array();
        $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \Exception('Failed to encode model data');
        }

        $result = file_put_contents($file_path, $json);

        if ($result === false) {
            throw new \Exception("Failed to write to file: {$file_path}");
        }

        return true;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function to_array(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'base_provider' => $this->base_provider,
            'base_model' => $this->base_model,
            'metadata' => $this->metadata,
            'training_data' => $this->training_data,
            'parameters' => $this->parameters,
            'system_prompt' => $this->system_prompt,
            'fine_tuning_config' => $this->fine_tuning_config,
            'metrics' => $this->metrics,
            'created_at' => $this->metadata['created_at'] ?? current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
    }

    /**
     * Add training conversation
     *
     * @param array $conversation Conversation data
     * @return void
     */
    public function add_training_conversation(array $conversation): void
    {
        $this->training_data[] = [
            'type' => 'conversation',
            'messages' => $conversation['messages'] ?? [],
            'metadata' => $conversation['metadata'] ?? [],
            'added_at' => current_time('mysql')
        ];
    }

    /**
     * Add training example
     *
     * @param string $input Input text
     * @param string $output Expected output
     * @param array $metadata Optional metadata
     * @return void
     */
    public function add_training_example(string $input, string $output, array $metadata = []): void
    {
        $this->training_data[] = [
            'type' => 'example',
            'input' => $input,
            'output' => $output,
            'metadata' => $metadata,
            'added_at' => current_time('mysql')
        ];
    }

    /**
     * Set system prompt
     *
     * @param string $prompt System prompt
     * @return void
     */
    public function set_system_prompt(string $prompt): void
    {
        $this->system_prompt = $prompt;
    }

    /**
     * Get system prompt
     *
     * @return string
     */
    public function get_system_prompt(): string
    {
        return $this->system_prompt;
    }

    /**
     * Set parameter
     *
     * @param string $key Parameter key
     * @param mixed $value Parameter value
     * @return void
     */
    public function set_parameter(string $key, $value): void
    {
        $this->parameters[$key] = $value;
    }

    /**
     * Get parameter
     *
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get_parameter(string $key, $default = null)
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * Set metadata
     *
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return void
     */
    public function set_metadata(string $key, $value): void
    {
        $this->metadata[$key] = $value;
    }

    /**
     * Get metadata
     *
     * @param string $key Metadata key
     * @param mixed $default Default value
     * @return mixed
     */
    public function get_metadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get training data
     *
     * @return array
     */
    public function get_training_data(): array
    {
        return $this->training_data;
    }

    /**
     * Get training data count
     *
     * @return int
     */
    public function get_training_data_count(): int
    {
        return count($this->training_data);
    }

    /**
     * Set fine-tuning config
     *
     * @param array $config Fine-tuning configuration
     * @return void
     */
    public function set_fine_tuning_config(array $config): void
    {
        $this->fine_tuning_config = array_merge($this->fine_tuning_config, $config);
    }

    /**
     * Get fine-tuning config
     *
     * @return array
     */
    public function get_fine_tuning_config(): array
    {
        return $this->fine_tuning_config;
    }

    /**
     * Update metrics
     *
     * @param array $metrics Metrics data
     * @return void
     */
    public function update_metrics(array $metrics): void
    {
        $this->metrics = array_merge($this->metrics, $metrics);
        $this->metrics['updated_at'] = current_time('mysql');
    }

    /**
     * Get metrics
     *
     * @return array
     */
    public function get_metrics(): array
    {
        return $this->metrics;
    }

    /**
     * Clone model with new ID
     *
     * @param string $new_name New model name
     * @return self
     */
    public function clone_model(string $new_name): self
    {
        $data = $this->to_array();
        $data['id'] = uniqid('aev_model_');
        $data['name'] = $new_name;
        $data['version'] = '1.0.0';
        unset($data['created_at']);
        unset($data['updated_at']);

        return new self($data);
    }

    /**
     * Export for fine-tuning
     *
     * @param string $format Format (jsonl, csv, txt)
     * @return string Formatted data
     */
    public function export_for_fine_tuning(string $format = 'jsonl'): string
    {
        switch ($format) {
            case 'jsonl':
                return $this->export_jsonl();
            case 'csv':
                return $this->export_csv();
            case 'txt':
                return $this->export_txt();
            default:
                throw new \Exception("Unsupported format: {$format}");
        }
    }

    /**
     * Export as JSONL
     *
     * @return string
     */
    private function export_jsonl(): string
    {
        $lines = [];

        foreach ($this->training_data as $item) {
            if ($item['type'] === 'conversation') {
                $lines[] = wp_json_encode([
                    'messages' => $item['messages']
                ]);
            } elseif ($item['type'] === 'example') {
                $lines[] = wp_json_encode([
                    'messages' => [
                        ['role' => 'user', 'content' => $item['input']],
                        ['role' => 'assistant', 'content' => $item['output']]
                    ]
                ]);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Export as CSV
     *
     * @return string
     */
    private function export_csv(): string
    {
        $lines = ['input,output'];

        foreach ($this->training_data as $item) {
            if ($item['type'] === 'example') {
                $input = str_replace('"', '""', $item['input']);
                $output = str_replace('"', '""', $item['output']);
                $lines[] = "\"{$input}\",\"{$output}\"";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Export as text
     *
     * @return string
     */
    private function export_txt(): string
    {
        $lines = [];

        foreach ($this->training_data as $item) {
            if ($item['type'] === 'conversation') {
                foreach ($item['messages'] as $message) {
                    $lines[] = strtoupper($message['role']) . ': ' . $message['content'];
                }
                $lines[] = '---';
            } elseif ($item['type'] === 'example') {
                $lines[] = 'USER: ' . $item['input'];
                $lines[] = 'ASSISTANT: ' . $item['output'];
                $lines[] = '---';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get ID
     *
     * @return string
     */
    public function get_id(): string
    {
        return $this->id;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Get version
     *
     * @return string
     */
    public function get_version(): string
    {
        return $this->version;
    }

    /**
     * Get base provider
     *
     * @return string
     */
    public function get_base_provider(): string
    {
        return $this->base_provider;
    }

    /**
     * Get base model
     *
     * @return string
     */
    public function get_base_model(): string
    {
        return $this->base_model;
    }

    /**
     * Increment version
     *
     * @param string $type Version increment type (major, minor, patch)
     * @return void
     */
    public function increment_version(string $type = 'patch'): void
    {
        $parts = explode('.', $this->version);
        $major = (int) ($parts[0] ?? 1);
        $minor = (int) ($parts[1] ?? 0);
        $patch = (int) ($parts[2] ?? 0);

        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        $this->version = "{$major}.{$minor}.{$patch}";
    }

    /**
     * Validate model data
     *
     * @return array Validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->name)) {
            $errors[] = 'Model name is required';
        }

        if (empty($this->base_provider)) {
            $errors[] = 'Base provider is required';
        }

        if (empty($this->base_model)) {
            $errors[] = 'Base model is required';
        }

        if (count($this->training_data) === 0) {
            $errors[] = 'Model requires at least one training example';
        }

        return $errors;
    }
}
