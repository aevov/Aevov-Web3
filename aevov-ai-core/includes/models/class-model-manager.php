<?php
/**
 * Model Manager
 *
 * Central management for .aev models with:
 * - Model CRUD operations
 * - Database persistence
 * - Model extraction and conversion
 * - Version control
 * - Model library management
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Models;

use Aevov\AICore\Debug\DebugEngine;

/**
 * Model Manager Class
 */
class ModelManager
{
    /**
     * Debug engine
     *
     * @var DebugEngine
     */
    private DebugEngine $debug;

    /**
     * Model extractor
     *
     * @var ModelExtractor
     */
    private ModelExtractor $extractor;

    /**
     * Model converter
     *
     * @var ModelConverter
     */
    private ModelConverter $converter;

    /**
     * Models directory
     *
     * @var string
     */
    private string $models_dir;

    /**
     * Loaded models cache
     *
     * @var array
     */
    private array $loaded_models = [];

    /**
     * Constructor
     *
     * @param DebugEngine $debug Debug engine
     */
    public function __construct(DebugEngine $debug)
    {
        $this->debug = $debug;
        $this->extractor = new ModelExtractor($debug);
        $this->converter = new ModelConverter($debug);

        $upload_dir = wp_upload_dir();
        $this->models_dir = $upload_dir['basedir'] . '/aevov-models';

        // Create models directory if it doesn't exist
        if (!file_exists($this->models_dir)) {
            wp_mkdir_p($this->models_dir);
        }
    }

    /**
     * Create new model
     *
     * @param array $data Model data
     * @return AevModel
     */
    public function create_model(array $data): AevModel
    {
        $model = new AevModel($data);

        $this->debug->log('info', 'ModelManager', 'Model created', [
            'model_id' => $model->get_id(),
            'name' => $model->get_name()
        ]);

        return $model;
    }

    /**
     * Save model to database
     *
     * @param AevModel $model Model to save
     * @return bool
     */
    public function save_model(AevModel $model): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_models';
        $data = $model->to_array();

        // Check if model exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE model_id = %s",
            $model->get_id()
        ));

        if ($existing) {
            // Update existing
            $result = $wpdb->update(
                $table,
                [
                    'name' => $data['name'],
                    'version' => $data['version'],
                    'base_provider' => $data['base_provider'],
                    'base_model' => $data['base_model'],
                    'metadata' => wp_json_encode($data['metadata']),
                    'training_data' => wp_json_encode($data['training_data']),
                    'parameters' => wp_json_encode($data['parameters']),
                    'system_prompt' => $data['system_prompt'],
                    'fine_tuning_config' => wp_json_encode($data['fine_tuning_config']),
                    'metrics' => wp_json_encode($data['metrics']),
                    'updated_at' => current_time('mysql')
                ],
                ['model_id' => $model->get_id()]
            );
        } else {
            // Insert new
            $result = $wpdb->insert($table, [
                'model_id' => $model->get_id(),
                'name' => $data['name'],
                'version' => $data['version'],
                'base_provider' => $data['base_provider'],
                'base_model' => $data['base_model'],
                'metadata' => wp_json_encode($data['metadata']),
                'training_data' => wp_json_encode($data['training_data']),
                'parameters' => wp_json_encode($data['parameters']),
                'system_prompt' => $data['system_prompt'],
                'fine_tuning_config' => wp_json_encode($data['fine_tuning_config']),
                'metrics' => wp_json_encode($data['metrics']),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]);
        }

        if ($result !== false) {
            // Also save to file
            $file_path = $this->get_model_file_path($model->get_id());
            $model->save_to_file($file_path);

            $this->debug->log('info', 'ModelManager', 'Model saved', [
                'model_id' => $model->get_id(),
                'file_path' => $file_path
            ]);

            return true;
        }

        return false;
    }

    /**
     * Load model from database
     *
     * @param string $model_id Model ID
     * @return AevModel|null
     */
    public function load_model(string $model_id): ?AevModel
    {
        // Check cache first
        if (isset($this->loaded_models[$model_id])) {
            return $this->loaded_models[$model_id];
        }

        global $wpdb;

        $table = $wpdb->prefix . 'aev_models';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE model_id = %s",
            $model_id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        $model = new AevModel([
            'id' => $row['model_id'],
            'name' => $row['name'],
            'version' => $row['version'],
            'base_provider' => $row['base_provider'],
            'base_model' => $row['base_model'],
            'metadata' => json_decode($row['metadata'], true),
            'training_data' => json_decode($row['training_data'], true),
            'parameters' => json_decode($row['parameters'], true),
            'system_prompt' => $row['system_prompt'],
            'fine_tuning_config' => json_decode($row['fine_tuning_config'], true),
            'metrics' => json_decode($row['metrics'], true)
        ]);

        // Cache the model
        $this->loaded_models[$model_id] = $model;

        return $model;
    }

    /**
     * Load model from file
     *
     * @param string $file_path File path
     * @return AevModel
     */
    public function load_model_from_file(string $file_path): AevModel
    {
        $model = AevModel::from_file($file_path);

        // Cache the model
        $this->loaded_models[$model->get_id()] = $model;

        return $model;
    }

    /**
     * Delete model
     *
     * @param string $model_id Model ID
     * @return bool
     */
    public function delete_model(string $model_id): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_models';

        $result = $wpdb->delete($table, ['model_id' => $model_id]);

        if ($result !== false) {
            // Delete file
            $file_path = $this->get_model_file_path($model_id);
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            // Remove from cache
            unset($this->loaded_models[$model_id]);

            $this->debug->log('info', 'ModelManager', 'Model deleted', [
                'model_id' => $model_id
            ]);

            return true;
        }

        return false;
    }

    /**
     * List all models
     *
     * @param array $filters Filters
     * @return array
     */
    public function list_models(array $filters = []): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_models';
        $where = ['1=1'];
        $params = [];

        if (isset($filters['provider'])) {
            $where[] = 'base_provider = %s';
            $params[] = $filters['provider'];
        }

        if (isset($filters['search'])) {
            $where[] = 'name LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['search']) . '%';
        }

        $where_clause = implode(' AND ', $where);

        if (!empty($params)) {
            $query = $wpdb->prepare(
                "SELECT model_id, name, version, base_provider, base_model, created_at, updated_at
                 FROM {$table}
                 WHERE {$where_clause}
                 ORDER BY updated_at DESC",
                ...$params
            );
        } else {
            $query = "SELECT model_id, name, version, base_provider, base_model, created_at, updated_at
                      FROM {$table}
                      WHERE {$where_clause}
                      ORDER BY updated_at DESC";
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Extract model from conversations
     *
     * @param array $conversations Conversation data
     * @param array $config Extraction config
     * @return AevModel
     */
    public function extract_model(array $conversations, array $config = []): AevModel
    {
        return $this->extractor->extract_from_conversations($conversations, $config);
    }

    /**
     * Extract model from database
     *
     * @param array $filters Database filters
     * @param array $config Extraction config
     * @return AevModel
     */
    public function extract_from_database(array $filters = [], array $config = []): AevModel
    {
        return $this->extractor->extract_from_database($filters, $config);
    }

    /**
     * Convert model to provider format
     *
     * @param string $model_id Model ID
     * @param string $provider Provider name
     * @return string Formatted data
     */
    public function convert_to_provider_format(string $model_id, string $provider): string
    {
        $model = $this->load_model($model_id);

        if (!$model) {
            throw new \Exception("Model not found: {$model_id}");
        }

        return $this->converter->to_provider_format($model, $provider);
    }

    /**
     * Import model from provider format
     *
     * @param string $data Formatted data
     * @param string $provider Provider name
     * @param array $metadata Model metadata
     * @return AevModel
     */
    public function import_from_provider(string $data, string $provider, array $metadata = []): AevModel
    {
        $model = $this->converter->from_provider_format($data, $provider, $metadata);
        $this->save_model($model);

        return $model;
    }

    /**
     * Clone model
     *
     * @param string $model_id Model ID
     * @param string $new_name New model name
     * @return AevModel
     */
    public function clone_model(string $model_id, string $new_name): AevModel
    {
        $model = $this->load_model($model_id);

        if (!$model) {
            throw new \Exception("Model not found: {$model_id}");
        }

        $cloned = $model->clone_model($new_name);
        $this->save_model($cloned);

        return $cloned;
    }

    /**
     * Merge models
     *
     * @param array $model_ids Array of model IDs
     * @param string $new_name Name for merged model
     * @return AevModel
     */
    public function merge_models(array $model_ids, string $new_name): AevModel
    {
        $models = [];

        foreach ($model_ids as $model_id) {
            $model = $this->load_model($model_id);
            if ($model) {
                $models[] = $model;
            }
        }

        if (empty($models)) {
            throw new \Exception('No valid models found to merge');
        }

        $merged = $this->converter->merge_models($models, $new_name);
        $this->save_model($merged);

        return $merged;
    }

    /**
     * Export model to file
     *
     * @param string $model_id Model ID
     * @param string $file_path Export file path
     * @return bool
     */
    public function export_model(string $model_id, string $file_path): bool
    {
        $model = $this->load_model($model_id);

        if (!$model) {
            throw new \Exception("Model not found: {$model_id}");
        }

        return $model->save_to_file($file_path);
    }

    /**
     * Get model file path
     *
     * @param string $model_id Model ID
     * @return string
     */
    private function get_model_file_path(string $model_id): string
    {
        return $this->models_dir . '/' . $model_id . '.aev';
    }

    /**
     * Get models directory
     *
     * @return string
     */
    public function get_models_directory(): string
    {
        return $this->models_dir;
    }

    /**
     * Get model statistics
     *
     * @return array
     */
    public function get_statistics(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_models';

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        $by_provider = $wpdb->get_results(
            "SELECT base_provider, COUNT(*) as count FROM {$table} GROUP BY base_provider",
            ARRAY_A
        );

        return [
            'total_models' => (int) $total,
            'by_provider' => $by_provider,
            'storage_path' => $this->models_dir
        ];
    }

    /**
     * Get extractor
     *
     * @return ModelExtractor
     */
    public function get_extractor(): ModelExtractor
    {
        return $this->extractor;
    }

    /**
     * Get converter
     *
     * @return ModelConverter
     */
    public function get_converter(): ModelConverter
    {
        return $this->converter;
    }
}
