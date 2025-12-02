<?php

namespace APS\DB;
use APS\Core\Logger;
use APS\Integration\CubbitIntegration;

class APS_Block_DB {
    private $wpdb;
    private $table_name;
    private $logger;
    private $cubbit_integration;
    
    public function __construct($wpdb = null) {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
        $this->table_name = $this->wpdb->prefix . 'aps_blocks';
        $this->logger = Logger::get_instance();
        $this->cubbit_integration = new CubbitIntegration();
    }

    public function create_table() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            block_hash varchar(64) NOT NULL,
            block_data longtext NOT NULL,
            cubbit_key varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY block_hash (block_hash)
        ) $charset_collate;";
        dbDelta($sql);
    }

    public function insert_block($block_data) {
        try {
            $block_hash = $block_data['hash'];
            $cubbit_key = null;

            if ($this->cubbit_integration->is_configured()) {
                $cubbit_key = 'blocks/' . $block_hash . '.json';
                $upload_success = $this->cubbit_integration->upload_object($cubbit_key, json_encode($block_data), 'application/json', 'private');
                if (!$upload_success) {
                    $this->logger->log('error', 'Failed to upload block data to Cubbit', ['block_hash' => $block_hash]);
                    $cubbit_key = null;
                }
            }

            $result = $this->wpdb->insert(
                $this->table_name,
                [
                    'block_hash' => $block_hash,
                    'block_data' => json_encode($block_data),
                    'cubbit_key' => $cubbit_key
                ],
                ['%s', '%s', '%s']
            );

            if ($result === false) {
                $this->logger->log('error', 'Failed to insert block into database', [
                    'block_hash' => $block_hash,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }

            $this->logger->log('info', 'Block inserted successfully', ['block_hash' => $block_hash]);
            return $this->wpdb->insert_id;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while inserting block', [
                'block_hash' => $block_data['hash'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function get_block_by_hash($block_hash) {
        try {
            $block = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE block_hash = %s",
                    $block_hash
                ),
                ARRAY_A
            );

            if ($block === null) {
                return null;
            }
            if ($block === false) {
                $this->logger->log('error', 'Failed to retrieve block from database', [
                    'block_hash' => $block_hash,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }

            // Decode block data or download from Cubbit
            $decoded_data = null;
            if (!empty($block['block_data'])) {
                $decoded_data = json_decode($block['block_data'], true);
                if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->log('error', 'Failed to decode block data from DB', [
                        'block_hash' => $block_hash,
                        'json_error' => json_last_error_msg()
                    ]);
                }
            } elseif (!empty($block['cubbit_key']) && $this->cubbit_integration->is_configured()) {
                $cubbit_data = $this->cubbit_integration->download_object($block['cubbit_key']);
                if ($cubbit_data !== false) {
                    $decoded_data = json_decode($cubbit_data, true);
                    if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                        $this->logger->log('error', 'Failed to decode block data from Cubbit', [
                            'block_hash' => $block_hash,
                            'cubbit_key' => $block['cubbit_key'],
                            'json_error' => json_last_error_msg()
                        ]);
                    }
                } else {
                    $this->logger->log('error', 'Failed to download block data from Cubbit', [
                        'block_hash' => $block_hash,
                        'cubbit_key' => $block['cubbit_key']
                    ]);
                }
            }
            $block['block_data'] = $decoded_data;

            return $block;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving block', [
                'block_hash' => $block_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function get_blocks($args) {
        $page = $args['page'] ?? 1;
        $per_page = $args['per_page'] ?? 10;
        $orderby = $args['orderby'] ?? 'created_at';
        $order = strtoupper($args['order'] ?? 'desc');

        $offset = ($page - 1) * $per_page;

        $sql = "SELECT * FROM {$this->table_name} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        
        try {
            $blocks = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $per_page, $offset),
                ARRAY_A
            );

            if ($blocks === false) {
                $this->logger->log('error', 'Failed to retrieve blocks from database', [
                    'args' => $args,
                    'error' => $this->wpdb->last_error
                ]);
                return [];
            }

            foreach ($blocks as &$block) {
                $decoded_data = json_decode($block['block_data'], true);
                if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->log('warning', 'Failed to decode block data for block in collection', [
                        'block_hash' => $block['block_hash'],
                        'json_error' => json_last_error_msg()
                    ]);
                } else {
                    $block['block_data'] = $decoded_data;
                }
            }

            return $blocks;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving blocks', [
                'args' => $args,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public function get_block_count() {
        try {
            $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
            if ($count === false) {
                $this->logger->log('error', 'Failed to retrieve block count from database', [
                    'error' => $this->wpdb->last_error
                ]);
                return 0;
            }
            return (int)$count;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving block count', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    public function get_last_block() {
        try {
            $block = $this->wpdb->get_row(
                "SELECT * FROM {$this->table_name} ORDER BY id DESC LIMIT 1",
                ARRAY_A
            );
            if ($block === false) {
                $this->logger->log('error', 'Failed to retrieve last block from database', [
                    'error' => $this->wpdb->last_error
                ]);
                return null;
            }
            if ($block) {
                $decoded_data = json_decode($block['block_data'], true);
                if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->log('warning', 'Failed to decode block data for last block', [
                        'block_hash' => $block['block_hash'],
                        'json_error' => json_last_error_msg()
                    ]);
                } else {
                    $block['block_data'] = $decoded_data;
                }
            }
            return $block;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving last block', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function delete_block($block_hash) {
        try {
            $result = $this->wpdb->delete(
                $this->table_name,
                ['block_hash' => $block_hash],
                ['%s']
            );
            if ($result === false) {
                $this->logger->log('error', 'Failed to delete block from database', [
                    'block_hash' => $block_hash,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            if ($this->cubbit_integration->is_configured()) {
                $cubbit_key = 'blocks/' . $block_hash . '.json';
                $delete_success = $this->cubbit_integration->delete_object($cubbit_key);
                if (!$delete_success) {
                    $this->logger->log('error', 'Failed to delete block data from Cubbit', ['block_hash' => $block_hash]);
                }
            }
            return $result;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while deleting block', [
                'block_hash' => $block_hash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    public function clear_all_blocks() {
        try {
            // Get all block hashes and cubbit keys before deleting from DB
            $blocks_to_delete = $this->wpdb->get_results(
                "SELECT block_hash, cubbit_key FROM {$this->table_name}",
                ARRAY_A
            );

            // Delete all records from the database table
            $result = $this->wpdb->query("TRUNCATE TABLE {$this->table_name}");

            if ($result === false) {
                $this->logger->log('error', 'Failed to clear all blocks from database', [
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }

            // Delete corresponding objects from Cubbit
            if ($this->cubbit_integration->is_configured()) {
                foreach ($blocks_to_delete as $block) {
                    if (!empty($block['cubbit_key'])) {
                        $delete_success = $this->cubbit_integration->delete_object($block['cubbit_key']);
                        if (!$delete_success) {
                            $this->logger->log('error', 'Failed to delete block data from Cubbit during clear_all_blocks', ['block_hash' => $block['block_hash']]);
                        }
                    }
                }
            }

            $this->logger->log('info', 'All blocks cleared successfully from database and Cubbit.');
            return true;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while clearing all blocks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}