<?php
namespace BLOOM\Models;

/**
 * Data model for pattern operations and storage
 */
/**
 * Data model for pattern operations and storage.
 *
 * @since 1.0.0
 */
class Pattern_Model {
    /**
     * The name of the database table.
     *
     * @since 1.0.0
     * @var   string
     */
    private $table = 'bloom_patterns';

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
     * Create a new pattern.
     *
     * @since  1.0.0
     * @param  array $pattern_data The data for the new pattern.
     * @return int|false The ID of the new pattern, or false on failure.
     */
    public function create($pattern_data) {
        $pattern_hash = $this->generate_pattern_hash($pattern_data);
        $this->error_handler->log_debug('PatternModel: Generated hash for creation: ' . $pattern_hash);

        $data = [
            'pattern_hash' => $pattern_hash,
            'pattern_type' => $pattern_data['type'],
            'features' => json_encode($pattern_data['features']),
            'confidence' => $pattern_data['confidence'],
            'metadata' => json_encode($pattern_data['metadata'] ?? []),
            'tensor_sku' => $pattern_data['tensor_sku'] ?? null,
            'cluster_id' => $pattern_data['metadata']['cluster_id'] ?? null,
            'site_id' => get_current_blog_id(),
            'status' => 'active',
            'created_at' => current_time('mysql')
        ];

        $this->error_handler->log_debug('PatternModel: Inserting data: ', $data);
        $result = $this->db->insert($this->table, $data);

        if (false === $result) {
            $this->error_handler->log_error('Database error while creating pattern', ['error' => $this->db->last_error]);
            return false;
        }
        
        $this->clear_pattern_statistics_cache();
        return $this->db->insert_id;
    }

    /**
     * Get a pattern by its ID.
     *
     * @since  1.0.0
     * @param  int $pattern_id The ID of the pattern to get.
     * @return array|null The pattern, or null if not found.
     */
    public function get($pattern_id) {
        $pattern = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $pattern_id
            ),
            ARRAY_A
        );

        if (null === $pattern && $this->db->last_error) {
            $this->error_handler->log_error('Database error in get', ['error' => $this->db->last_error]);
        }

        if ($pattern) {
            // Decode JSON fields
            if (isset($pattern['features'])) {
                $pattern['features'] = json_decode($pattern['features'], true);
            }
            if (isset($pattern['metadata'])) {
                $pattern['metadata'] = json_decode($pattern['metadata'], true);
            }
        }
        return $pattern;
    }

    /**
     * Get a pattern by its hash.
     *
     * @since  1.0.0
     * @param  string $pattern_hash The hash of the pattern to get.
     * @return array|null The pattern, or null if not found.
     */
    public function get_by_hash($pattern_hash) {
        $pattern = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE pattern_hash = %s",
                $pattern_hash
            ),
            ARRAY_A
        );

        if (null === $pattern && $this->db->last_error) {
            $this->error_handler->log_error('Database error in get_by_hash', ['error' => $this->db->last_error]);
        }

        return $pattern;
    }

    /**
     * Get a list of patterns.
     *
     * @since  1.0.0
     * @param  array $params The parameters to filter the patterns by.
     * @return array The list of patterns.
     */
    public function get_patterns($params) {
        $page = $params['page'] ?? 1;
        $per_page = $params['per_page'] ?? 20;
        $offset = ($page - 1) * $per_page;

        $sql = "SELECT * FROM {$this->table} WHERE status = 'active'";
        $where_clauses = [];
        $sql_args = [];

        if (isset($params['type']) && !empty($params['type'])) {
            $where_clauses[] = "pattern_type = %s";
            $sql_args[] = $params['type'];
        }

        if (isset($params['confidence']) && is_numeric($params['confidence'])) {
            $where_clauses[] = "confidence >= %f";
            $sql_args[] = $params['confidence'];
        }

        if (isset($params['filter']) && $params['filter'] === 'clustered') {
            // Assuming clustered patterns have a 'cluster_id' in their metadata
            $where_clauses[] = "cluster_id IS NOT NULL";
        }

        if (!empty($where_clauses)) {
            $sql .= " AND " . implode(" AND ", $where_clauses);
        }

        $sql .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $sql_args_for_patterns = array_merge($sql_args, [$per_page, $offset]);

        $patterns = $this->db->get_results(
            $this->db->prepare($sql, ...$sql_args_for_patterns),
            ARRAY_A
        );

        if (null === $patterns && $this->db->last_error) {
            $this->error_handler->log_error('Database error in get_patterns', ['error' => $this->db->last_error]);
        }

        // Decode JSON fields for each pattern
        if ($patterns) {
            foreach ($patterns as &$pattern) {
                if (isset($pattern['features'])) {
                    $pattern['features'] = json_decode($pattern['features'], true);
                }
                if (isset($pattern['metadata'])) {
                    $pattern['metadata'] = json_decode($pattern['metadata'], true);
                }
            }
        }

        $total_patterns_sql = "SELECT COUNT(*) FROM {$this->table} WHERE status = 'active'" . (!empty($where_clauses) ? " AND " . implode(" AND ", $where_clauses) : "");
        $total_patterns = $this->db->get_var($this->db->prepare($total_patterns_sql, ...$sql_args)); // Pass args to prepare for count query

        return [
            'patterns' => $patterns,
            'total_patterns' => (int) $total_patterns,
            'total_pages' => ceil($total_patterns / $per_page)
        ];
    }

    /**
     * Update the confidence of a pattern.
     *
     * @since  1.0.0
     * @param  int   $pattern_id The ID of the pattern to update.
     * @param  float $confidence The new confidence score.
     * @return bool True on success, false on failure.
     */
    public function update_confidence($pattern_id, $confidence) {
        $result = $this->db->update(
            $this->table,
            [
                'confidence' => $confidence,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $pattern_id]
        );

        if (false === $result) {
            $this->error_handler->log_error('Database error while updating confidence', ['error' => $this->db->last_error]);
        } else {
            $this->clear_pattern_statistics_cache();
        }

        return $result;
    }

    /**
     * Generate a hash for a pattern.
     *
     * @since  1.0.0
     * @param  array $pattern_data The data for the pattern.
     * @return string The hash of the pattern.
     */
    private function generate_pattern_hash($pattern_data) {
        $hash_data = [
            'type' => $pattern_data['type'],
            'features' => $pattern_data['features']
        ];
        $hash = hash('sha256', json_encode($hash_data)); // Encode once for hashing
        $this->error_handler->log_debug('PatternModel: generate_pattern_hash input: ' . json_encode($hash_data) . ' -> hash: ' . $hash);
        return $hash;
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
            pattern_hash varchar(64) NOT NULL,
            pattern_type varchar(50) NOT NULL,
            features text NOT NULL,
            confidence decimal(5,4) NOT NULL,
            metadata text,
            tensor_sku varchar(64),
            cluster_id bigint(20) DEFAULT NULL,
            site_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY pattern_hash (pattern_hash),
            KEY pattern_type (pattern_type),
            KEY confidence (confidence),
            KEY site_id (site_id),
            KEY status (status),
            KEY tensor_sku (tensor_sku),
            KEY cluster_id (cluster_id),
            FULLTEXT KEY features_search (features)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get patterns by site ID.
     *
     * @since  1.0.0
     * @param  int $site_id The ID of the site to get patterns for.
     * @param  int $limit   The maximum number of patterns to get.
     * @return array The list of patterns.
     */
    public function get_patterns_by_site($site_id, $limit = 100) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} 
                 WHERE site_id = %d 
                 AND status = 'active'
                 ORDER BY confidence DESC, created_at DESC
                 LIMIT %d",
                $site_id,
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Get pattern statistics.
     *
     * @since  1.0.0
     * @return array The pattern statistics.
     */
    public function get_pattern_statistics() {
        $transient_key = 'bloom_pattern_stats';
        $stats = get_transient($transient_key);

        if (false === $stats) {
            $stats = $this->db->get_results(
                $this->db->prepare(
                    "SELECT 
                        pattern_type,
                        COUNT(*) as count,
                        AVG(confidence) as avg_confidence,
                        MAX(confidence) as max_confidence,
                        MIN(confidence) as min_confidence
                     FROM {$this->table} 
                     WHERE status = %s
                     GROUP BY pattern_type
                     ORDER BY count DESC",
                     'active'
                ),
                ARRAY_A
            );
            set_transient($transient_key, $stats, HOUR_IN_SECONDS);
        }

        return $stats;
    }

    /**
     * Clear the pattern statistics cache.
     *
     * @since 1.0.0
     */
    public function clear_pattern_statistics_cache() {
        delete_transient('bloom_pattern_stats');
    }

    /**
     * Get patterns by tensor SKU.
     *
     * @since  1.0.0
     * @param  string $tensor_sku The tensor SKU to get patterns for.
     * @return array The list of patterns.
     */
    public function get_by_tensor_sku($tensor_sku) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE tensor_sku = %s ORDER BY created_at DESC",
                $tensor_sku
            ),
            ARRAY_A
        );
    }
}