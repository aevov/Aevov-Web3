<?php
namespace BLOOM\Models;

/**
 * Data model for tensor chunks
 */
/**
 * Data model for tensor chunks.
 *
 * @since 1.0.0
 */
class Chunk_Model {
    /**
     * The name of the database table.
     *
     * @since 1.0.0
     * @var   string
     */
    private $table = 'bloom_chunks';

    /**
     * The WordPress database object.
     *
     * @since 1.0.0
     * @var   wpdb
     */
    private $db;

    /**
     * The error handler object.
     *
     * @since 1.0.0
     * @var   \BLOOM\Utilities\ErrorHandler
     */
    private $error_handler;
    
    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . $this->table;
        $this->error_handler = bloom()->get_error_handler();
    }

    /**
     * Store a new chunk.
     *
     * @since  1.0.0
     * @param  array $chunk_data The data for the new chunk.
     * @return int|false The number of rows inserted, or false on failure.
     */
    public function store_chunk($chunk_data) {
        $data = [
            'tensor_sku' => $chunk_data['tensor_sku'],
            'chunk_index' => $chunk_data['chunk_index'],
            'chunk_data' => $chunk_data['data'],
            'chunk_size' => strlen($chunk_data['data']),
            'checksum' => hash('sha256', $chunk_data['data']),
            'site_id' => get_current_blog_id(),
            'status' => $chunk_data['status'] ?? 'active',
            'original_pattern_id' => $chunk_data['original_pattern_id'] ?? null, // New field
            'created_at' => current_time('mysql')
        ];

        $format = ['%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s']; // Added %d for original_pattern_id
        $result = $this->db->insert($this->table, $data, $format);

        if (false === $result) {
            $this->error_handler->log_error('Database error while storing chunk', ['error' => $this->db->last_error]);
        }

        return $result;
    }

    /**
     * Get all chunks for a tensor.
     *
     * @since  1.0.0
     * @param  string $tensor_sku The tensor SKU.
     * @return array The list of chunks.
     */
    public function get_chunks($tensor_sku) {
        $chunks = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table}
                 WHERE tensor_sku = %s
                 ORDER BY chunk_index ASC",
                $tensor_sku
            ),
            ARRAY_A
        );

        if (null === $chunks && $this->db->last_error) {
            $this->error_handler->log_error('Database error in get_chunks', ['error' => $this->db->last_error]);
        }

        return $chunks;
    }

    /**
     * Get all chunks for an original pattern ID.
     *
     * @since  1.0.0
     * @param  int $original_pattern_id The original pattern ID.
     * @return array The list of chunks.
     */
    public function get_chunks_by_original_pattern_id($original_pattern_id) {
        $chunks = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table}
                 WHERE original_pattern_id = %d
                 ORDER BY chunk_index ASC",
                $original_pattern_id
            ),
            ARRAY_A
        );

        if (null === $chunks && $this->db->last_error) {
            $this->error_handler->log_error('Database error in get_chunks_by_original_pattern_id', ['error' => $this->db->last_error]);
        }

        return $chunks;
    }

    /**
     * Verify the checksum of a chunk.
     *
     * @since  1.0.0
     * @param  int    $chunk_id The ID of the chunk to verify.
     * @param  string $checksum The checksum to verify against.
     * @return bool True if the checksum is valid, false otherwise.
     */
    public function verify_chunk($chunk_id, $checksum) {
        $stored_chunk = $this->db->get_row(
            $this->db->prepare(
                "SELECT checksum FROM {$this->table} WHERE id = %d",
                $chunk_id
            )
        );

        if (null === $stored_chunk && $this->db->last_error) {
            $this->error_handler->log_error('Database error in verify_chunk', ['error' => $this->db->last_error]);
        }

        return $stored_chunk && hash_equals($stored_chunk->checksum, $checksum);
    }

    /**
     * Create the database table.
     *
     * @since 1.0.0
     */
    public function create_table() {
        $charset_collate = $this->db->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tensor_sku varchar(64) NOT NULL,
            chunk_index int NOT NULL,
            chunk_data longtext NOT NULL,
            chunk_size bigint(20) NOT NULL,
            checksum varchar(64) NOT NULL,
            site_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            original_pattern_id bigint(20) DEFAULT NULL, -- New column
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_accessed datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tensor_chunk (tensor_sku, chunk_index),
            KEY site_id (site_id),
            KEY status (status),
            KEY original_pattern_id (original_pattern_id) -- New index
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}