<?php
namespace APS\DB;

class APS_Chunk_DB {
    private $table_name = 'aps_chunks';
    private $db;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_name = $wpdb->prefix . $this->table_name;
    }

    public function create_table() {
        $charset_collate = $this->db->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            original_pattern_id bigint(20) NOT NULL,
            tensor_sku varchar(64) NOT NULL,
            chunk_index int NOT NULL,
            chunk_data longtext NOT NULL,
            chunk_size bigint(20) NOT NULL,
            checksum varchar(64) NOT NULL,
            site_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'partial',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY original_pattern_chunk (original_pattern_id, chunk_index),
            KEY tensor_sku (tensor_sku),
            KEY site_id (site_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function store_chunk($chunk_data) {
        $decoded_data = base64_decode($chunk_data['data']);
        $data = [
            'original_pattern_id' => $chunk_data['original_pattern_id'],
            'tensor_sku' => $chunk_data['tensor_sku'],
            'chunk_index' => $chunk_data['chunk_index'],
            'chunk_data' => $chunk_data['data'],
            'chunk_size' => strlen($decoded_data),
            'checksum' => hash('sha256', $decoded_data),
            'site_id' => get_current_blog_id(),
            'status' => $chunk_data['status'] ?? 'partial',
            'created_at' => current_time('mysql')
        ];

        $format = ['%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s'];
        return $this->db->insert($this->table_name, $data, $format);
    }

    public function get_chunks_by_original_pattern_id($original_pattern_id) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table_name} 
                 WHERE original_pattern_id = %d 
                 ORDER BY chunk_index ASC",
                $original_pattern_id
            ),
            ARRAY_A
        );
    }

    public function reassemble_pattern($original_pattern_id) {
        $chunks = $this->get_chunks_by_original_pattern_id($original_pattern_id);

        if (empty($chunks)) {
            return null;
        }

        $full_data = '';
        $expected_chunks = $chunks[0]['total_parts'] ?? count($chunks); // Assuming total_parts is in the first chunk's metadata

        // Check if all chunks are present
        if (count($chunks) !== $expected_chunks) {
            // Not all chunks received yet, or some are missing
            return null;
        }

        foreach ($chunks as $chunk) {
            // Verify checksum before reassembly
            if (hash('sha256', base64_decode($chunk['chunk_data'])) !== $chunk['checksum']) {
                // Checksum mismatch, data corruption
                return new \WP_Error('chunk_checksum_mismatch', 'Chunk data corrupted for chunk_id: ' . $chunk['id']);
            }
            $full_data .= base64_decode($chunk['chunk_data']);
        }

        // Assuming the original pattern data structure can be reconstructed
        // This part might need more specific logic based on how the original pattern was structured
        $reassembled_pattern = [
            'id' => $original_pattern_id,
            'tensor_data' => $full_data,
            'type' => 'reassembled_large_pattern', // Or infer from metadata
            'metadata' => [
                'source' => 'bloom_chunk_reassembly',
                'total_size' => strlen($full_data),
                'num_chunks' => count($chunks),
                'tensor_sku' => $chunks[0]['tensor_sku'] // Assuming consistent SKU
            ]
        ];

        // Optionally, delete chunks after successful reassembly
        $this->delete_chunks($original_pattern_id);

        return $reassembled_pattern;
    }

    public function delete_chunks($original_pattern_id) {
        return $this->db->delete(
            $this->table_name,
            ['original_pattern_id' => $original_pattern_id],
            ['%d']
        );
    }
}
