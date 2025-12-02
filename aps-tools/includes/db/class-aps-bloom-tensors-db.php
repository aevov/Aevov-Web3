<?php
/**
 * Database Handler for Bloom Tensors
 *
 * Provides database abstraction layer for tensor metadata and data operations
 * in the APS Tools plugin. Handles CRUD operations for tensor storage.
 *
 * @package APSTools
 * @subpackage Database
 * @since 1.0.0
 */

namespace APSTools\DB;

class APS_Bloom_Tensors_DB {

    /**
     * WordPress database object
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Bloom tensors table name
     *
     * @var string
     */
    private $tensors_table;

    /**
     * Tensor data table name
     *
     * @var string
     */
    private $tensor_data_table;

    /**
     * Tensor chunks table name
     *
     * @var string
     */
    private $chunks_table;

    /**
     * Tensor metadata table name
     *
     * @var string
     */
    private $metadata_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;

        // Define table names
        $this->tensors_table = $wpdb->prefix . 'aps_bloom_tensors';
        $this->tensor_data_table = $wpdb->prefix . 'aps_tensor_data';
        $this->chunks_table = $wpdb->prefix . 'aps_tensor_chunks';
        $this->metadata_table = $wpdb->prefix . 'aps_tensor_metadata';

        // Create tables on initialization
        $this->create_tables();
    }

    /**
     * Create all required database tables
     *
     * @return void
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        // Bloom tensors table - stores tensor references and URLs
        $sql_tensors = "CREATE TABLE IF NOT EXISTS {$this->tensors_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            chunk_id BIGINT UNSIGNED NOT NULL,
            tensor_sku VARCHAR(255) NOT NULL,
            tensor_data_url VARCHAR(500) NULL,
            storage_type ENUM('database', 'file', 'cubbit', 's3') DEFAULT 'database',
            file_path VARCHAR(500) NULL,
            file_size BIGINT UNSIGNED NULL,
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY chunk_id (chunk_id),
            INDEX tensor_sku (tensor_sku),
            INDEX storage_type (storage_type),
            INDEX status (status)
        ) {$charset_collate};";

        // Tensor data table - stores actual tensor data when using database storage
        $sql_data = "CREATE TABLE IF NOT EXISTS {$this->tensor_data_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            chunk_id BIGINT UNSIGNED NOT NULL,
            tensor_data LONGTEXT NOT NULL,
            tensor_shape VARCHAR(255) NULL,
            tensor_type VARCHAR(50) NULL,
            tensor_dtype VARCHAR(50) NULL,
            compression_type VARCHAR(50) DEFAULT 'none',
            processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY chunk_id (chunk_id),
            INDEX tensor_type (tensor_type),
            INDEX processed_at (processed_at)
        ) {$charset_collate};";

        // Tensor chunks table - tracks chunk processing
        $sql_chunks = "CREATE TABLE IF NOT EXISTS {$this->chunks_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tensor_id BIGINT UNSIGNED NOT NULL,
            chunk_index INT NOT NULL,
            chunk_size BIGINT UNSIGNED NULL,
            chunk_hash VARCHAR(64) NULL,
            chunk_data LONGTEXT NULL,
            processed TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX tensor_id (tensor_id),
            INDEX processed (processed),
            UNIQUE KEY tensor_chunk (tensor_id, chunk_index)
        ) {$charset_collate};";

        // Tensor metadata table - stores additional metadata
        $sql_metadata = "CREATE TABLE IF NOT EXISTS {$this->metadata_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tensor_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(255) NOT NULL,
            meta_value LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX tensor_id (tensor_id),
            INDEX meta_key (meta_key),
            UNIQUE KEY tensor_meta (tensor_id, meta_key)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_tensors);
        dbDelta($sql_data);
        dbDelta($sql_chunks);
        dbDelta($sql_metadata);
    }

    /**
     * Insert new tensor record
     *
     * @param array $data Tensor data
     * @return int|false Tensor ID or false on failure
     */
    public function insert_tensor($data) {
        $defaults = [
            'chunk_id' => 0,
            'tensor_sku' => '',
            'tensor_data_url' => null,
            'storage_type' => 'database',
            'file_path' => null,
            'file_size' => null,
            'status' => 'pending'
        ];

        $data = wp_parse_args($data, $defaults);

        $result = $this->wpdb->insert(
            $this->tensors_table,
            [
                'chunk_id' => $data['chunk_id'],
                'tensor_sku' => $data['tensor_sku'],
                'tensor_data_url' => $data['tensor_data_url'],
                'storage_type' => $data['storage_type'],
                'file_path' => $data['file_path'],
                'file_size' => $data['file_size'],
                'status' => $data['status']
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get tensor by ID
     *
     * @param int $tensor_id Tensor ID
     * @return object|null Tensor record
     */
    public function get_tensor($tensor_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tensors_table} WHERE id = %d",
            $tensor_id
        ));
    }

    /**
     * Get tensor by chunk ID
     *
     * @param int $chunk_id Chunk ID
     * @return object|null Tensor record
     */
    public function get_tensor_by_chunk($chunk_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tensors_table} WHERE chunk_id = %d",
            $chunk_id
        ));
    }

    /**
     * Get tensor by SKU
     *
     * @param string $tensor_sku Tensor SKU
     * @return object|null Tensor record
     */
    public function get_tensor_by_sku($tensor_sku) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tensors_table} WHERE tensor_sku = %s LIMIT 1",
            $tensor_sku
        ));
    }

    /**
     * Update tensor
     *
     * @param int $tensor_id Tensor ID
     * @param array $data Data to update
     * @return bool Success status
     */
    public function update_tensor($tensor_id, $data) {
        $allowed_fields = ['tensor_data_url', 'storage_type', 'file_path', 'file_size', 'status'];
        $update_data = [];
        $format = [];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
                $format[] = in_array($field, ['file_size']) ? '%d' : '%s';
            }
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->tensors_table,
            $update_data,
            ['id' => $tensor_id],
            $format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete tensor
     *
     * @param int $tensor_id Tensor ID
     * @return bool Success status
     */
    public function delete_tensor($tensor_id) {
        // Delete related data first
        $this->delete_tensor_data($tensor_id);
        $this->delete_tensor_chunks($tensor_id);
        $this->delete_tensor_metadata($tensor_id);

        // Delete tensor record
        $result = $this->wpdb->delete(
            $this->tensors_table,
            ['id' => $tensor_id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Insert tensor data
     *
     * @param array $data Tensor data
     * @return int|false Data ID or false on failure
     */
    public function insert_tensor_data($data) {
        $defaults = [
            'chunk_id' => 0,
            'tensor_data' => '',
            'tensor_shape' => null,
            'tensor_type' => null,
            'tensor_dtype' => null,
            'compression_type' => 'none'
        ];

        $data = wp_parse_args($data, $defaults);

        $result = $this->wpdb->insert(
            $this->tensor_data_table,
            [
                'chunk_id' => $data['chunk_id'],
                'tensor_data' => $data['tensor_data'],
                'tensor_shape' => $data['tensor_shape'],
                'tensor_type' => $data['tensor_type'],
                'tensor_dtype' => $data['tensor_dtype'],
                'compression_type' => $data['compression_type']
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get tensor data by chunk ID
     *
     * @param int $chunk_id Chunk ID
     * @return object|null Tensor data record
     */
    public function get_tensor_data($chunk_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->tensor_data_table} WHERE chunk_id = %d",
            $chunk_id
        ));
    }

    /**
     * Update tensor data
     *
     * @param int $chunk_id Chunk ID
     * @param array $data Data to update
     * @return bool Success status
     */
    public function update_tensor_data($chunk_id, $data) {
        $allowed_fields = ['tensor_data', 'tensor_shape', 'tensor_type', 'tensor_dtype', 'compression_type'];
        $update_data = [];
        $format = [];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
                $format[] = '%s';
            }
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $this->wpdb->update(
            $this->tensor_data_table,
            $update_data,
            ['chunk_id' => $chunk_id],
            $format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete tensor data
     *
     * @param int $tensor_id Tensor ID
     * @return bool Success status
     */
    public function delete_tensor_data($tensor_id) {
        // Get chunk_id from tensor
        $tensor = $this->get_tensor($tensor_id);
        if (!$tensor) {
            return false;
        }

        $result = $this->wpdb->delete(
            $this->tensor_data_table,
            ['chunk_id' => $tensor->chunk_id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Insert tensor chunk
     *
     * @param array $data Chunk data
     * @return int|false Chunk ID or false on failure
     */
    public function insert_chunk($data) {
        $defaults = [
            'tensor_id' => 0,
            'chunk_index' => 0,
            'chunk_size' => null,
            'chunk_hash' => null,
            'chunk_data' => null,
            'processed' => 0
        ];

        $data = wp_parse_args($data, $defaults);

        $result = $this->wpdb->insert(
            $this->chunks_table,
            [
                'tensor_id' => $data['tensor_id'],
                'chunk_index' => $data['chunk_index'],
                'chunk_size' => $data['chunk_size'],
                'chunk_hash' => $data['chunk_hash'],
                'chunk_data' => $data['chunk_data'],
                'processed' => $data['processed']
            ],
            ['%d', '%d', '%d', '%s', '%s', '%d']
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get chunks for a tensor
     *
     * @param int $tensor_id Tensor ID
     * @return array Array of chunk records
     */
    public function get_tensor_chunks($tensor_id) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->chunks_table} WHERE tensor_id = %d ORDER BY chunk_index ASC",
            $tensor_id
        ), ARRAY_A);
    }

    /**
     * Update chunk processed status
     *
     * @param int $chunk_id Chunk ID
     * @param bool $processed Processed status
     * @return bool Success status
     */
    public function update_chunk_status($chunk_id, $processed) {
        $result = $this->wpdb->update(
            $this->chunks_table,
            ['processed' => $processed ? 1 : 0],
            ['id' => $chunk_id],
            ['%d'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete tensor chunks
     *
     * @param int $tensor_id Tensor ID
     * @return bool Success status
     */
    public function delete_tensor_chunks($tensor_id) {
        $result = $this->wpdb->delete(
            $this->chunks_table,
            ['tensor_id' => $tensor_id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Insert or update tensor metadata
     *
     * @param int $tensor_id Tensor ID
     * @param string $meta_key Metadata key
     * @param mixed $meta_value Metadata value
     * @return bool Success status
     */
    public function update_metadata($tensor_id, $meta_key, $meta_value) {
        // Check if metadata exists
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->metadata_table} WHERE tensor_id = %d AND meta_key = %s",
            $tensor_id,
            $meta_key
        ));

        $value = is_array($meta_value) || is_object($meta_value)
            ? json_encode($meta_value)
            : $meta_value;

        if ($existing) {
            // Update existing metadata
            $result = $this->wpdb->update(
                $this->metadata_table,
                ['meta_value' => $value],
                ['tensor_id' => $tensor_id, 'meta_key' => $meta_key],
                ['%s'],
                ['%d', '%s']
            );
        } else {
            // Insert new metadata
            $result = $this->wpdb->insert(
                $this->metadata_table,
                [
                    'tensor_id' => $tensor_id,
                    'meta_key' => $meta_key,
                    'meta_value' => $value
                ],
                ['%d', '%s', '%s']
            );
        }

        return $result !== false;
    }

    /**
     * Get tensor metadata
     *
     * @param int $tensor_id Tensor ID
     * @param string $meta_key Metadata key (optional)
     * @return mixed Metadata value or array of all metadata
     */
    public function get_metadata($tensor_id, $meta_key = null) {
        if ($meta_key) {
            $value = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT meta_value FROM {$this->metadata_table} WHERE tensor_id = %d AND meta_key = %s",
                $tensor_id,
                $meta_key
            ));

            // Try to decode JSON
            if ($value && is_string($value)) {
                $decoded = json_decode($value, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            }

            return $value;
        }

        // Get all metadata
        $results = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$this->metadata_table} WHERE tensor_id = %d",
            $tensor_id
        ), ARRAY_A);

        $metadata = [];
        foreach ($results as $row) {
            $value = $row['meta_value'];
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            }
            $metadata[$row['meta_key']] = $value;
        }

        return $metadata;
    }

    /**
     * Delete tensor metadata
     *
     * @param int $tensor_id Tensor ID
     * @param string $meta_key Metadata key (optional)
     * @return bool Success status
     */
    public function delete_tensor_metadata($tensor_id, $meta_key = null) {
        if ($meta_key) {
            $result = $this->wpdb->delete(
                $this->metadata_table,
                ['tensor_id' => $tensor_id, 'meta_key' => $meta_key],
                ['%d', '%s']
            );
        } else {
            $result = $this->wpdb->delete(
                $this->metadata_table,
                ['tensor_id' => $tensor_id],
                ['%d']
            );
        }

        return $result !== false;
    }

    /**
     * Get tensors by status
     *
     * @param string $status Status filter
     * @param int $limit Limit results
     * @return array Array of tensor records
     */
    public function get_tensors_by_status($status, $limit = 100) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tensors_table} WHERE status = %s LIMIT %d",
            $status,
            $limit
        ), ARRAY_A);
    }

    /**
     * Get tensors by storage type
     *
     * @param string $storage_type Storage type
     * @param int $limit Limit results
     * @return array Array of tensor records
     */
    public function get_tensors_by_storage($storage_type, $limit = 100) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->tensors_table} WHERE storage_type = %s LIMIT %d",
            $storage_type,
            $limit
        ), ARRAY_A);
    }

    /**
     * Count tensors by status
     *
     * @param string $status Status filter (optional)
     * @return int Count of tensors
     */
    public function count_tensors($status = null) {
        if ($status) {
            return (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tensors_table} WHERE status = %s",
                $status
            ));
        }

        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->tensors_table}");
    }

    /**
     * Get database statistics
     *
     * @return array Database statistics
     */
    public function get_statistics() {
        $total_tensors = $this->count_tensors();
        $pending = $this->count_tensors('pending');
        $processing = $this->count_tensors('processing');
        $completed = $this->count_tensors('completed');
        $failed = $this->count_tensors('failed');

        $total_size = $this->wpdb->get_var(
            "SELECT SUM(file_size) FROM {$this->tensors_table}"
        );

        $storage_breakdown = $this->wpdb->get_results(
            "SELECT storage_type, COUNT(*) as count, SUM(file_size) as total_size
             FROM {$this->tensors_table}
             GROUP BY storage_type",
            ARRAY_A
        );

        return [
            'total_tensors' => $total_tensors,
            'status_counts' => [
                'pending' => $pending,
                'processing' => $processing,
                'completed' => $completed,
                'failed' => $failed
            ],
            'total_size_bytes' => intval($total_size),
            'total_size_mb' => round(intval($total_size) / (1024 * 1024), 2),
            'storage_breakdown' => $storage_breakdown
        ];
    }
}
