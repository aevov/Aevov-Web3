<?php

namespace APS\DB;
use APS\Core\Logger;
use APS\Integration\CubbitIntegration; // Import CubbitIntegration

class APS_Pattern_DB {
    private $wpdb;
    private $table_name;
    private $logger;
    private $cubbit_integration; // New property for CubbitIntegration
    
    public function __construct($wpdb = null) {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
        $this->table_name = $this->wpdb->prefix . 'aps_patterns';
        $this->logger = Logger::get_instance();
        $this->cubbit_integration = new CubbitIntegration(); // Instantiate CubbitIntegration
    }


    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->wpdb->get_charset_collate();
    
        $this->create_patterns_table($charset_collate);
        $this->create_chunks_table($charset_collate);
        $this->create_relationships_table($charset_collate);
        $this->create_symbolic_patterns_table($charset_collate);
        $this->create_comparison_results_table($charset_collate); // New table
        // create_blocks_table is now in APS_Block_DB
        $reward_db = new \APS\DB\APS_Reward_DB($this->wpdb);
        $reward_db->create_table();
    }
    
    private function create_patterns_table($charset_collate) {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            pattern_hash varchar(64) NOT NULL,
            pattern_type varchar(32) NOT NULL,
            pattern_data longtext NOT NULL,
            confidence float NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            sync_status varchar(20) DEFAULT 'pending',
            distribution_count int DEFAULT 0,
            last_accessed datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY pattern_hash (pattern_hash),
            KEY pattern_type (pattern_type),
            KEY sync_status (sync_status),
            cubbit_key varchar(255) DEFAULT NULL,
            contributor_id bigint(20) UNSIGNED DEFAULT NULL,
            useful_inference_count int(11) DEFAULT 0,
            KEY contributor_id (contributor_id)
        ) $charset_collate;";
    
        dbDelta($sql);
    }
    
    private function create_chunks_table($charset_collate) {
        $table_name = $this->wpdb->prefix . 'aps_pattern_chunks';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            pattern_id BIGINT(20) NOT NULL,
            chunk_id INT UNSIGNED NOT NULL,
            sequence LONGTEXT NOT NULL,
            attention_mask LONGTEXT NOT NULL,
            position_ids LONGTEXT NOT NULL,
            chunk_size INT UNSIGNED NOT NULL,
            overlap INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY pattern_id (pattern_id),
            KEY chunk_id (chunk_id),
            FOREIGN KEY (pattern_id) REFERENCES {$this->table_name}(id) ON DELETE CASCADE
        ) $charset_collate;";
    
        dbDelta($sql);
    }
    
    private function create_relationships_table($charset_collate) {
        $table_name = $this->wpdb->prefix . 'aps_pattern_relationships';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            pattern_id_a BIGINT(20) NOT NULL,
            pattern_id_b BIGINT(20) NOT NULL,
            relationship_type VARCHAR(32) NOT NULL,
            similarity_score FLOAT NOT NULL,
            metadata JSON,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY pattern_id_a (pattern_id_a),
            KEY pattern_id_b (pattern_id_b),
            KEY relationship_type (relationship_type),
            FOREIGN KEY (pattern_id_a) REFERENCES {$this->table_name}(id) ON DELETE CASCADE ON UPDATE CASCADE,
            FOREIGN KEY (pattern_id_b) REFERENCES {$this->table_name}(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) $charset_collate;";
    
        dbDelta($sql);
    }
    
    private function create_symbolic_patterns_table($charset_collate) {
        $table_name = $this->wpdb->prefix . 'aps_symbolic_patterns';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            pattern_id bigint(20) NOT NULL,
            symbols longtext NOT NULL,
            relations longtext NOT NULL,
            rules longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY pattern_id (pattern_id),
            FOREIGN KEY (pattern_id) REFERENCES {$this->table_name}(id) ON DELETE CASCADE
        ) $charset_collate;";
    
        dbDelta($sql);
    }

    public function insert_pattern($pattern_data) {
        try {
            // Validate input data
            if (!isset($pattern_data['hash']) || !isset($pattern_data['type']) || !isset($pattern_data['data']) || !isset($pattern_data['confidence'])) {
                $this->logger->log('error', 'Missing required pattern data fields', ['pattern_data' => $pattern_data]);
                return false;
            }
            
            // Upload to Cubbit
            $cubbit_key = null;
            if ($this->cubbit_integration->is_configured()) {
                $cubbit_key = 'patterns/' . $pattern_data['hash'] . '.json';
                $upload_success = $this->cubbit_integration->upload_object($cubbit_key, json_encode($pattern_data['data']), 'application/json', 'private');
                if (!$upload_success) {
                    $this->logger->log('error', 'Failed to upload pattern data to Cubbit', ['pattern_hash' => $pattern_data['hash']]);
                    $cubbit_key = null; // Don't store key if upload failed
                }
            }
            
            $result = $this->wpdb->insert(
                $this->table_name,
                [
                    'pattern_hash' => $pattern_data['hash'],
                    'pattern_type' => $pattern_data['type'],
                    'pattern_data' => json_encode($pattern_data['data']),
                    'confidence' => $pattern_data['confidence'],
                    'cubbit_key' => $cubbit_key,
                    'contributor_id' => $pattern_data['contributor_id'] ?? null,
                    'useful_inference_count' => $pattern_data['useful_inference_count'] ?? 0
                ],
                ['%s', '%s', '%s', '%f', '%s', '%d', '%d']
            );
            
            if ($result === false) {
                $this->logger->log('error', 'Failed to insert pattern into database', [
                    'pattern_hash' => $pattern_data['hash'],
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            
            $this->logger->log('info', 'Pattern inserted successfully', [
                'pattern_hash' => $pattern_data['hash'],
                'pattern_type' => $pattern_data['type']
            ]);
            
            return $this->wpdb->insert_id;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while inserting pattern', [
                'pattern_hash' => $pattern_data['hash'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function update_pattern($pattern_hash, $pattern_data) {
        try {
            // Validate input data
            if (empty($pattern_hash) || !isset($pattern_data['data']) || !isset($pattern_data['confidence'])) {
                $this->logger->log('error', 'Missing required pattern data fields for update', [
                    'pattern_hash' => $pattern_hash,
                    'pattern_data' => $pattern_data
                ]);
                return false;
            }
            
            $result = $this->wpdb->update(
                $this->table_name,
                [
                    'pattern_data' => json_encode($pattern_data['data']),
                    'confidence' => $pattern_data['confidence'],
                    'updated_at' => current_time('mysql')
                ],
                ['pattern_hash' => $pattern_hash],
                ['%s', '%f', '%s'],
                ['%s']
            );
            
            if ($result === false) {
                $this->logger->log('error', 'Failed to update pattern in database', [
                    'pattern_hash' => $pattern_hash,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            
            $this->logger->log('info', 'Pattern updated successfully', [
                'pattern_hash' => $pattern_hash,
                'rows_affected' => $result
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while updating pattern', [
                'pattern_hash' => $pattern_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function get_pattern($pattern_hash) {
        try {
            // Validate input
            if (empty($pattern_hash)) {
                $this->logger->log('error', 'Empty pattern hash provided for get_pattern');
                return null;
            }
            
            $pattern = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE pattern_hash = %s",
                    $pattern_hash
                ),
                ARRAY_A
            );
            
            if ($pattern === null) {
                // No error logging for "not found" as this is a normal case
                return null;
            }
            
            if ($pattern === false) {
                $this->logger->log('error', 'Failed to retrieve pattern from database', [
                    'pattern_hash' => $pattern_hash,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            
            // Decode pattern data or download from Cubbit
            $decoded_data = null;
            if (!empty($pattern['pattern_data'])) {
                $decoded_data = json_decode($pattern['pattern_data'], true);
                if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->log('error', 'Failed to decode pattern data from DB', [
                        'pattern_hash' => $pattern_hash,
                        'json_error' => json_last_error_msg()
                    ]);
                }
            } elseif (!empty($pattern['cubbit_key']) && $this->cubbit_integration->is_configured()) {
                $cubbit_data = $this->cubbit_integration->download_object($pattern['cubbit_key']);
                if ($cubbit_data !== false) {
                    $decoded_data = json_decode($cubbit_data, true);
                    if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                        $this->logger->log('error', 'Failed to decode pattern data from Cubbit', [
                            'pattern_hash' => $pattern_hash,
                            'cubbit_key' => $pattern['cubbit_key'],
                            'json_error' => json_last_error_msg()
                        ]);
                    }
                } else {
                    $this->logger->log('error', 'Failed to download pattern data from Cubbit', [
                        'pattern_hash' => $pattern_hash,
                        'cubbit_key' => $pattern['cubbit_key']
                    ]);
                }
            }

            $pattern['pattern_data'] = $decoded_data;
            
            // Update access time
            $this->update_access_time($pattern_hash);
            
            $this->logger->log('info', 'Pattern retrieved successfully', [
                'pattern_hash' => $pattern_hash,
                'pattern_type' => $pattern['pattern_type']
            ]);
            
            return $pattern;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving pattern', [
                'pattern_hash' => $pattern_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function get_pattern_by_id($pattern_id) {
        try {
            // Validate input
            if (empty($pattern_id)) {
                $this->logger->log('error', 'Empty pattern ID provided for get_pattern_by_id');
                return null;
            }
            
            $pattern = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE id = %d",
                    $pattern_id
                ),
                ARRAY_A
            );
            
            if ($pattern === null) {
                return null;
            }
            
            if ($pattern === false) {
                $this->logger->log('error', 'Failed to retrieve pattern by ID from database', [
                    'pattern_id' => $pattern_id,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            
            // Decode pattern data or download from Cubbit
            $decoded_data = null;
            if (!empty($pattern['pattern_data'])) {
                $decoded_data = json_decode($pattern['pattern_data'], true);
                if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->log('error', 'Failed to decode pattern data from DB by ID', [
                        'pattern_id' => $pattern_id,
                        'json_error' => json_last_error_msg()
                    ]);
                }
            } elseif (!empty($pattern['cubbit_key']) && $this->cubbit_integration->is_configured()) {
                $cubbit_data = $this->cubbit_integration->download_object($pattern['cubbit_key']);
                if ($cubbit_data !== false) {
                    $decoded_data = json_decode($cubbit_data, true);
                    if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                        $this->logger->log('error', 'Failed to decode pattern data from Cubbit by ID', [
                            'pattern_id' => $pattern_id,
                            'cubbit_key' => $pattern['cubbit_key'],
                            'json_error' => json_last_error_msg()
                        ]);
                    }
                } else {
                    $this->logger->log('error', 'Failed to download pattern data from Cubbit by ID', [
                        'pattern_id' => $pattern_id,
                        'cubbit_key' => $pattern['cubbit_key']
                    ]);
                }
            }
            $pattern['pattern_data'] = $decoded_data;
            
            // Update access time (optional, if you want to track access by ID)
            // $this->update_access_time_by_id($pattern_id);
            
            $this->logger->log('info', 'Pattern retrieved by ID successfully', [
                'pattern_id' => $pattern_id,
                'pattern_type' => $pattern['pattern_type']
            ]);
            
            return $pattern;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving pattern by ID', [
                'pattern_id' => $pattern_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function get_patterns_by_type($type, $limit = 100, $offset = 0) {
        try {
            // Validate input
            if (empty($type)) {
                $this->logger->log('error', 'Empty pattern type provided for get_patterns_by_type');
                return [];
            }
            
            // Validate limit and offset
            $limit = max(1, min($limit, 1000)); // Ensure limit is between 1 and 1000
            $offset = max(0, $offset); // Ensure offset is non-negative
            
            $patterns = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name}
                     WHERE pattern_type = %s
                     ORDER BY confidence DESC
                     LIMIT %d OFFSET %d",
                    $type, $limit, $offset
                ),
                ARRAY_A
            );
            
            if ($patterns === false) {
                $this->logger->log('error', 'Failed to retrieve patterns by type from database', [
                    'pattern_type' => $type,
                    'error' => $this->wpdb->last_error
                ]);
                return [];
            }
            
            // Decode pattern data for each pattern
            foreach ($patterns as &$pattern) {
                $decoded_data = json_decode($pattern['pattern_data'], true);
                if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->log('warning', 'Failed to decode pattern data for pattern', [
                        'pattern_id' => $pattern['id'],
                        'pattern_hash' => $pattern['pattern_hash'],
                        'json_error' => json_last_error_msg()
                    ]);
                    // Continue with undecoded data rather than failing completely
                } else {
                    $pattern['pattern_data'] = $decoded_data;
                }
            }
            
            $this->logger->log('info', 'Patterns retrieved by type successfully', [
                'pattern_type' => $type,
                'count' => count($patterns),
                'limit' => $limit,
                'offset' => $offset
            ]);
            
            return $patterns;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving patterns by type', [
                'pattern_type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function get_pending_sync_patterns($limit = 50) {
        try {
            // Validate limit
            $limit = max(1, min($limit, 1000)); // Ensure limit is between 1 and 1000
            
            $patterns = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name}
                     WHERE sync_status = 'pending'
                     ORDER BY updated_at ASC
                     LIMIT %d",
                    $limit
                ),
                ARRAY_A
            );
            
            if ($patterns === false) {
                $this->logger->log('error', 'Failed to retrieve pending sync patterns from database', [
                    'error' => $this->wpdb->last_error
                ]);
                return [];
            }
            
            // Decode pattern data for each pattern
            foreach ($patterns as &$pattern) {
                $decoded_data = json_decode($pattern['pattern_data'], true);
                if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->log('error', 'Failed to decode pattern data for pending sync pattern', [
                        'pattern_id' => $pattern['id'],
                        'pattern_hash' => $pattern['pattern_hash'],
                        'json_error' => json_last_error_msg()
                    ]);
                    // Continue with undecoded data rather than failing completely
                } else {
                    $pattern['pattern_data'] = $decoded_data;
                }
            }
            
            $this->logger->log('info', 'Pending sync patterns retrieved successfully', [
                'count' => count($patterns),
                'limit' => $limit
            ]);
            
            return $patterns;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving pending sync patterns', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function update_sync_status($pattern_hash, $status) {
        try {
            // Validate input
            if (empty($pattern_hash) || empty($status)) {
                $this->logger->log('error', 'Empty pattern hash or status provided for update_sync_status', [
                    'pattern_hash' => $pattern_hash,
                    'status' => $status
                ]);
                return false;
            }
            
            // Validate status is one of the allowed values
            $allowed_statuses = ['pending', 'synced', 'failed', 'processing'];
            if (!in_array($status, $allowed_statuses)) {
                $this->logger->log('error', 'Invalid sync status provided', [
                    'pattern_hash' => $pattern_hash,
                    'status' => $status,
                    'allowed_statuses' => $allowed_statuses
                ]);
                return false;
            }
            
            $result = $this->wpdb->update(
                $this->table_name,
                ['sync_status' => $status],
                ['pattern_hash' => $pattern_hash],
                ['%s'],
                ['%s']
            );
            
            if ($result === false) {
                $this->logger->log('error', 'Failed to update sync status in database', [
                    'pattern_hash' => $pattern_hash,
                    'status' => $status,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            
            $this->logger->log('info', 'Pattern sync status updated successfully', [
                'pattern_hash' => $pattern_hash,
                'status' => $status,
                'rows_affected' => $result
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while updating sync status', [
                'pattern_hash' => $pattern_hash,
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function increment_distribution($pattern_hash) {
        try {
            // Validate input
            if (empty($pattern_hash)) {
                $this->logger->log('error', 'Empty pattern hash provided for increment_distribution');
                return false;
            }
            
            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->table_name}
                     SET distribution_count = distribution_count + 1
                     WHERE pattern_hash = %s",
                    $pattern_hash
                )
            );
            
            if ($result === false) {
                $this->logger->log('error', 'Failed to increment distribution count in database', [
                    'pattern_hash' => $pattern_hash,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            
            $this->logger->log('info', 'Pattern distribution count incremented successfully', [
                'pattern_hash' => $pattern_hash
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while incrementing distribution count', [
                'pattern_hash' => $pattern_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function delete_pattern($pattern_hash) {
        try {
            // Validate input
            if (empty($pattern_hash)) {
                $this->logger->log('error', 'Empty pattern hash provided for delete_pattern');
                return false;
            }
            
            $result = $this->wpdb->delete(
                $this->table_name,
                ['pattern_hash' => $pattern_hash],
                ['%s']
            );
            
            if ($result === false) {
                $this->logger->log('error', 'Failed to delete pattern from database', [
                    'pattern_hash' => $pattern_hash,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            
            // Delete from Cubbit
            if ($this->cubbit_integration->is_configured()) {
                $cubbit_key = 'patterns/' . $pattern_hash . '.json';
                $delete_success = $this->cubbit_integration->delete_object($cubbit_key);
                if (!$delete_success) {
                    $this->logger->log('error', 'Failed to delete pattern data from Cubbit', ['pattern_hash' => $pattern_hash]);
                }
            }

            $this->logger->log('info', 'Pattern deleted successfully', [
                'pattern_hash' => $pattern_hash,
                'rows_affected' => $result
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while deleting pattern', [
                'pattern_hash' => $pattern_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function cleanup_old_patterns($days = 30) {
        try {
            // Validate input
            $days = max(1, $days); // Ensure days is at least 1
            
            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->table_name}
                     WHERE last_accessed < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $days
                )
            );
            
            if ($result === false) {
                $this->logger->log('error', 'Failed to cleanup old patterns from database', [
                    'days' => $days,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            
            $this->logger->log('info', 'Old patterns cleaned up successfully', [
                'days' => $days,
                'patterns_deleted' => $result
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while cleaning up old patterns', [
                'days' => $days,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    private function update_access_time($pattern_hash) {
        $this->wpdb->update(
            $this->table_name,
            ['last_accessed' => current_time('mysql')],
            ['pattern_hash' => $pattern_hash],
            ['%s'],
            ['%s']
        );
    }

    public function get_pattern_stats() {
        try {
            $total = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
            if ($total === false) {
                $this->logger->log('error', 'Failed to get total pattern count', [
                    'error' => $this->wpdb->last_error
                ]);
                $total = 0;
            }
            
            $by_type = $this->wpdb->get_results(
                "SELECT pattern_type, COUNT(*) as count
                 FROM {$this->table_name}
                 GROUP BY pattern_type",
                ARRAY_A
            );
            if ($by_type === false) {
                $this->logger->log('error', 'Failed to get pattern count by type', [
                    'error' => $this->wpdb->last_error
                ]);
                $by_type = [];
            }
            
            $avg_confidence = $this->wpdb->get_var(
                "SELECT AVG(confidence) FROM {$this->table_name}"
            );
            if ($avg_confidence === false) {
                $this->logger->log('error', 'Failed to get average confidence', [
                    'error' => $this->wpdb->last_error
                ]);
                $avg_confidence = 0;
            }
            
            $stats = [
                'total' => (int)$total,
                'by_type' => $by_type,
                'avg_confidence' => (float)$avg_confidence
            ];
            
            $this->logger->log('info', 'Pattern stats retrieved successfully', $stats);
            
            return $stats;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving pattern stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'total' => 0,
                'by_type' => [],
                'avg_confidence' => 0
            ];
        }
    }
    
    public function insert_symbolic_pattern($pattern_data) {
        try {
            // Validate input data
            if (!isset($pattern_data['pattern_hash']) || !isset($pattern_data['features']) || !isset($pattern_data['confidence'])) {
                $this->logger->log('error', 'Missing required symbolic pattern data fields', ['pattern_data' => $pattern_data]);
                return false;
            }
            
            // First insert the base pattern
            $pattern_id = $this->insert_pattern([
                'hash' => $pattern_data['pattern_hash'],
                'type' => 'symbolic_pattern',
                'data' => [
                    'features' => $pattern_data['features'],
                    'metrics' => $pattern_data['metrics'] ?? []
                ],
                'confidence' => $pattern_data['confidence']
            ]);
            
            if (!$pattern_id) {
                $this->logger->log('error', 'Failed to insert base pattern for symbolic pattern', [
                    'pattern_hash' => $pattern_data['pattern_hash'] ?? 'unknown'
                ]);
                return false;
            }
            
            // Then insert the symbolic pattern details
            $table_name = $this->wpdb->prefix . 'aps_symbolic_patterns';
            $result = $this->wpdb->insert(
                $table_name,
                [
                    'pattern_id' => $pattern_id,
                    'symbols' => json_encode($pattern_data['symbols']),
                    'relations' => json_encode($pattern_data['relations']),
                    'rules' => json_encode($pattern_data['rules'])
                ],
                ['%d', '%s', '%s', '%s']
            );
            
            if ($result === false) {
                $this->logger->log('error', 'Failed to insert symbolic pattern details', [
                    'pattern_id' => $pattern_id,
                    'error' => $this->wpdb->last_error
                ]);
                // Attempt to rollback the base pattern insertion
                $this->delete_pattern($pattern_data['pattern_hash']);
                return false;
            }
            
            $this->logger->log('info', 'Symbolic pattern inserted successfully', [
                'pattern_hash' => $pattern_data['pattern_hash'],
                'pattern_id' => $pattern_id
            ]);
            
            return $pattern_id;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while inserting symbolic pattern', [
                'pattern_hash' => $pattern_data['pattern_hash'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    public function get_symbolic_pattern($pattern_hash) {
        try {
            // Validate input
            if (empty($pattern_hash)) {
                $this->logger->log('error', 'Empty pattern hash provided for get_symbolic_pattern');
                return null;
            }
            
            // First get the base pattern
            $pattern = $this->get_pattern($pattern_hash);
            
            if (!$pattern) {
                // get_pattern already logged the error if there was one
                return null;
            }
            
            if ($pattern['pattern_type'] !== 'symbolic_pattern') {
                $this->logger->log('warning', 'Pattern is not a symbolic pattern', [
                    'pattern_hash' => $pattern_hash,
                    'pattern_type' => $pattern['pattern_type']
                ]);
                return $pattern;
            }
            
            // Then get the symbolic pattern details
            $table_name = $this->wpdb->prefix . 'aps_symbolic_patterns';
            $symbolic_data = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE pattern_id = %d",
                    $pattern['id']
                ),
                ARRAY_A
            );
            
            if ($symbolic_data === false) {
                $this->logger->log('error', 'Failed to retrieve symbolic pattern details', [
                    'pattern_hash' => $pattern_hash,
                    'pattern_id' => $pattern['id'],
                    'error' => $this->wpdb->last_error
                ]);
                return $pattern;
            }
            
            if (!$symbolic_data) {
                $this->logger->log('warning', 'No symbolic pattern details found', [
                    'pattern_hash' => $pattern_hash,
                    'pattern_id' => $pattern['id']
                ]);
                return $pattern;
            }
            
            // Merge the data
            $decoded_symbols = json_decode($symbolic_data['symbols'], true);
            if ($decoded_symbols === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->log('error', 'Failed to decode symbolic pattern symbols', [
                    'pattern_hash' => $pattern_hash,
                    'json_error' => json_last_error_msg()
                ]);
            } else {
                $pattern['symbols'] = $decoded_symbols;
            }
            
            $decoded_relations = json_decode($symbolic_data['relations'], true);
            if ($decoded_relations === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->log('error', 'Failed to decode symbolic pattern relations', [
                    'pattern_hash' => $pattern_hash,
                    'json_error' => json_last_error_msg()
                ]);
            } else {
                $pattern['relations'] = $decoded_relations;
            }
            
            $decoded_rules = json_decode($symbolic_data['rules'], true);
            if ($decoded_rules === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->log('error', 'Failed to decode symbolic pattern rules', [
                    'pattern_hash' => $pattern_hash,
                    'json_error' => json_last_error_msg()
                ]);
            } else {
                $pattern['rules'] = $decoded_rules;
            }
            
            $this->logger->log('info', 'Symbolic pattern retrieved successfully', [
                'pattern_hash' => $pattern_hash,
                'pattern_id' => $pattern['id']
            ]);
            
            return $pattern;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving symbolic pattern', [
                'pattern_hash' => $pattern_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    public function update_symbolic_pattern($pattern_hash, $pattern_data) {
        try {
            // Validate input data
            if (empty($pattern_hash) || !isset($pattern_data['features']) || !isset($pattern_data['confidence'])) {
                $this->logger->log('error', 'Missing required symbolic pattern data fields for update', [
                    'pattern_hash' => $pattern_hash,
                    'pattern_data' => $pattern_data
                ]);
                return false;
            }
            
            // First update the base pattern
            $result = $this->update_pattern($pattern_hash, [
                'data' => [
                    'features' => $pattern_data['features'],
                    'metrics' => $pattern_data['metrics'] ?? []
                ],
                'confidence' => $pattern_data['confidence']
            ]);
            
            if (!$result) {
                $this->logger->log('error', 'Failed to update base pattern for symbolic pattern', [
                    'pattern_hash' => $pattern_hash
                ]);
                return false;
            }
            
            // Get the pattern ID
            $pattern = $this->get_pattern($pattern_hash);
            if (!$pattern) {
                $this->logger->log('error', 'Failed to retrieve pattern after base update', [
                    'pattern_hash' => $pattern_hash
                ]);
                return false;
            }
            
            // Then update the symbolic pattern details
            $table_name = $this->wpdb->prefix . 'aps_symbolic_patterns';
            $result = $this->wpdb->update(
                $table_name,
                [
                    'symbols' => json_encode($pattern_data['symbols']),
                    'relations' => json_encode($pattern_data['relations']),
                    'rules' => json_encode($pattern_data['rules']),
                    'updated_at' => current_time('mysql')
                ],
                ['pattern_id' => $pattern['id']],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
            
            if ($result === false) {
                $this->logger->log('error', 'Failed to update symbolic pattern details', [
                    'pattern_hash' => $pattern_hash,
                    'pattern_id' => $pattern['id'],
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            
            $this->logger->log('info', 'Symbolic pattern updated successfully', [
                'pattern_hash' => $pattern_hash,
                'pattern_id' => $pattern['id'],
                'rows_affected' => $result
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while updating symbolic pattern', [
                'pattern_hash' => $pattern_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    public function delete_symbolic_pattern($pattern_hash) {
        try {
            // Validate input
            if (empty($pattern_hash)) {
                $this->logger->log('error', 'Empty pattern hash provided for delete_symbolic_pattern');
                return false;
            }
            
            // The symbolic pattern will be deleted automatically due to the foreign key constraint
            $result = $this->delete_pattern($pattern_hash);
            
            if ($result === false) {
                $this->logger->log('error', 'Failed to delete symbolic pattern', [
                    'pattern_hash' => $pattern_hash
                ]);
                return false;
            }
            
            $this->logger->log('info', 'Symbolic pattern deleted successfully', [
                'pattern_hash' => $pattern_hash,
                'rows_affected' => $result
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while deleting symbolic pattern', [
                'pattern_hash' => $pattern_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    public function get_pattern_collection($args) {
        $sql = "SELECT * FROM {$this->table_name} WHERE 1=1";
        $where_clauses = [];
        $sql_params = [];

        // Filter by type
        if (!empty($args['type'])) {
            $where_clauses[] = "pattern_type = %s";
            $sql_params[] = $args['type'];
        }

        // Filter by confidence
        if (isset($args['confidence']) && is_numeric($args['confidence'])) {
            $where_clauses[] = "confidence >= %f";
            $sql_params[] = $args['confidence'];
        }

        // Filter by tensor_sku
        if (!empty($args['tensor_sku'])) {
            $where_clauses[] = "tensor_sku = %s";
            $sql_params[] = $args['tensor_sku'];
        }

        // Filter by site_id
        if (!empty($args['site_id'])) {
            $where_clauses[] = "site_id = %d";
            $sql_params[] = $args['site_id'];
        }

        // Filter by status
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $sql_params[] = $args['status'];
        }

        // Filter by date range
        if (!empty($args['start_date'])) {
            $where_clauses[] = "created_at >= %s";
            $sql_params[] = $args['start_date'] . ' 00:00:00';
        }
        if (!empty($args['end_date'])) {
            $where_clauses[] = "created_at <= %s";
            $sql_params[] = $args['end_date'] . ' 23:59:59';
        }

        // Search
        if (!empty($args['search'])) {
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where_clauses[] = "(pattern_hash LIKE %s OR pattern_type LIKE %s OR pattern_data LIKE %s OR metadata LIKE %s)";
            $sql_params[] = $search_term;
            $sql_params[] = $search_term;
            $sql_params[] = $search_term;
            $sql_params[] = $search_term;
        }

        if (!empty($where_clauses)) {
            $sql .= " AND " . implode(" AND ", $where_clauses);
        }

        // Order by
        $orderby = in_array($args['orderby'], ['id', 'pattern_hash', 'pattern_type', 'confidence', 'created_at', 'updated_at']) ? $args['orderby'] : 'created_at';
        $order = in_array(strtoupper($args['order']), ['ASC', 'DESC']) ? strtoupper($args['order']) : 'DESC';
        $sql .= " ORDER BY {$orderby} {$order}";

        // Pagination
        $sql .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $args['per_page'], ($args['page'] - 1) * $args['per_page']);

        try {
            $patterns = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, ...$sql_params),
                ARRAY_A
            );

            if ($patterns === false) {
                $this->logger->log('error', 'Failed to retrieve pattern collection from database', [
                    'args' => $args,
                    'error' => $this->wpdb->last_error
                ]);
                return [];
            }

            // Decode pattern data for each pattern
            foreach ($patterns as &$pattern) {
                $decoded_data = json_decode($pattern['pattern_data'], true);
                if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->log('warning', 'Failed to decode pattern data for pattern in collection', [
                        'pattern_id' => $pattern['id'],
                        'pattern_hash' => $pattern['pattern_hash'],
                        'json_error' => json_last_error_msg()
                    ]);
                } else {
                    $pattern['pattern_data'] = $decoded_data;
                }
                $decoded_metadata = json_decode($pattern['metadata'], true);
                if ($decoded_metadata === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->log('warning', 'Failed to decode metadata for pattern in collection', [
                        'pattern_id' => $pattern['id'],
                        'pattern_hash' => $pattern['pattern_hash'],
                        'json_error' => json_last_error_msg()
                    ]);
                } else {
                    $pattern['metadata'] = $decoded_metadata;
                }
            }

            $this->logger->log('info', 'Pattern collection retrieved successfully', [
                'count' => count($patterns),
                'args' => $args
            ]);

            return $patterns;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving pattern collection', [
                'args' => $args,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function get_patterns_count($args) {
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1";
        $where_clauses = [];
        $sql_params = [];

        // Filter by type
        if (!empty($args['type'])) {
            $where_clauses[] = "pattern_type = %s";
            $sql_params[] = $args['type'];
        }

        // Filter by confidence
        if (isset($args['confidence']) && is_numeric($args['confidence'])) {
            $where_clauses[] = "confidence >= %f";
            $sql_params[] = $args['confidence'];
        }

        // Filter by tensor_sku
        if (!empty($args['tensor_sku'])) {
            $where_clauses[] = "tensor_sku = %s";
            $sql_params[] = $args['tensor_sku'];
        }

        // Filter by site_id
        if (!empty($args['site_id'])) {
            $where_clauses[] = "site_id = %d";
            $sql_params[] = $args['site_id'];
        }

        // Filter by status
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $sql_params[] = $args['status'];
        }

        // Filter by date range
        if (!empty($args['start_date'])) {
            $where_clauses[] = "created_at >= %s";
            $sql_params[] = $args['start_date'] . ' 00:00:00';
        }
        if (!empty($args['end_date'])) {
            $where_clauses[] = "created_at <= %s";
            $sql_params[] = $args['end_date'] . ' 23:59:59';
        }

        // Search
        if (!empty($args['search'])) {
            $search_term = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $where_clauses[] = "(pattern_hash LIKE %s OR pattern_type LIKE %s OR pattern_data LIKE %s OR metadata LIKE %s)";
            $sql_params[] = $search_term;
            $sql_params[] = $search_term;
            $sql_params[] = $search_term;
            $sql_params[] = $search_term;
        }

        if (!empty($where_clauses)) {
            $sql .= " AND " . implode(" AND ", $where_clauses);
        }

        try {
            $count = $this->wpdb->get_var(
                $this->wpdb->prepare($sql, ...$sql_params)
            );

            if ($count === false) {
                $this->logger->log('error', 'Failed to retrieve pattern count from database', [
                    'args' => $args,
                    'error' => $this->wpdb->last_error
                ]);
                return 0;
            }

            $this->logger->log('info', 'Pattern count retrieved successfully', [
                'count' => $count,
                'args' => $args
            ]);

            return (int)$count;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving pattern count', [
                'args' => $args,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    public function get_patterns_by_contributor($contributor_id) {
        try {
            if (empty($contributor_id)) {
                $this->logger->log('error', 'Empty contributor ID provided for get_patterns_by_contributor');
                return [];
            }

            $patterns = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE contributor_id = %d",
                    $contributor_id
                ),
                ARRAY_A
            );

            if ($patterns === false) {
                $this->logger->log('error', 'Failed to retrieve patterns by contributor from database', [
                    'contributor_id' => $contributor_id,
                    'error' => $this->wpdb->last_error
                ]);
                return [];
            }

            // Decode pattern data for each pattern
            foreach ($patterns as &$pattern) {
                $decoded_data = json_decode($pattern['pattern_data'], true);
                if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->log('warning', 'Failed to decode pattern data for contributor pattern', [
                        'pattern_id' => $pattern['id'],
                        'pattern_hash' => $pattern['pattern_hash'],
                        'json_error' => json_last_error_msg()
                    ]);
                } else {
                    $pattern['pattern_data'] = $decoded_data;
                }
            }

            $this->logger->log('info', 'Patterns retrieved by contributor successfully', [
                'contributor_id' => $contributor_id,
                'count' => count($patterns)
            ]);

            return $patterns;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving patterns by contributor', [
                'contributor_id' => $contributor_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function increment_useful_inference_count($pattern_hash) {
        try {
            if (empty($pattern_hash)) {
                $this->logger->log('error', 'Empty pattern hash provided for increment_useful_inference_count');
                return false;
            }

            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->table_name}
                     SET useful_inference_count = useful_inference_count + 1
                     WHERE pattern_hash = %s",
                    $pattern_hash
                )
            );

            if ($result === false) {
                $this->logger->log('error', 'Failed to increment useful inference count in database', [
                    'pattern_hash' => $pattern_hash,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }

            $this->logger->log('info', 'Useful inference count incremented successfully', [
                'pattern_hash' => $pattern_hash
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while incrementing useful inference count', [
                'pattern_hash' => $pattern_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    protected function create_comparison_results_table($charset_collate) {
        $table_name = $this->wpdb->prefix . 'aps_comparison_results';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            comparison_id bigint(20) UNSIGNED NOT NULL,
            result_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY comparison_id (comparison_id),
            FOREIGN KEY (comparison_id) REFERENCES {$this->wpdb->prefix}aps_comparisons(id) ON DELETE CASCADE
        ) $charset_collate;";
        dbDelta($sql);
    }
}