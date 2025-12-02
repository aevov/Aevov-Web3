<?php

namespace APS\Pattern;

use APS\Core\Logger;

class PatternStorage {
    private $wpdb;
    private $pattern_table;
    private $logger;
    
    public function __construct($wpdb_instance = null) {
        $this->wpdb = $wpdb_instance ?? $GLOBALS['wpdb'];
        $this->pattern_table = $this->wpdb->prefix . 'aps_patterns';
        $this->logger = Logger::get_instance();
    }

    public function store_patterns($patterns) {
        $stored_patterns = [];
        $errors = [];
        
        if (!is_array($patterns) || empty($patterns)) {
            $this->logger->warning('No patterns provided to store_patterns');
            return [];
        }
        
        foreach ($patterns as $pattern) {
            $stored_id = $this->store_single_pattern($pattern);
            if ($stored_id) {
                $pattern['stored_id'] = $stored_id;
                $stored_patterns[] = $pattern;
            } else {
                $errors[] = $pattern;
            }
        }
        
        if (!empty($errors)) {
            $this->logger->error('Failed to store some patterns', ['failed_patterns' => $errors]);
        }
        
        $this->logger->info('Batch pattern storage complete', [
            'total_patterns' => count($patterns),
            'stored_successfully' => count($stored_patterns),
            'failed_to_store' => count($errors)
        ]);
        
        return $stored_patterns;
    }

    private function store_single_pattern($pattern) {
        try {
            // Validate input
            if (!isset($pattern['type'])) {
                $this->logger->error('Pattern missing required type field', ['pattern' => $pattern]);
                return false;
            }
            
            $data = [
                'pattern_hash' => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('pattern_', true),
                'pattern_type' => $pattern['type'],
                'pattern_data' => json_encode($pattern),
                'confidence' => $pattern['confidence'] ?? 1.0,
                'created_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                'updated_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
            ];
            
            $result = $this->wpdb->insert($this->pattern_table, $data);
            
            if ($result === false) {
                $this->logger->error('Failed to insert pattern into database', [
                    'pattern_type' => $pattern['type'],
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            
            $this->logger->info('Pattern stored successfully', [
                'pattern_type' => $pattern['type'],
                'pattern_hash' => $data['pattern_hash']
            ]);
            
            return $this->wpdb->insert_id;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while storing pattern', [
                'pattern_type' => $pattern['type'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function get_pattern($pattern_id) {
        try {
            // Validate input
            if (empty($pattern_id)) {
                $this->logger->error('Empty pattern ID provided for retrieval');
                return null;
            }
            
            $pattern = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->pattern_table} WHERE id = %d",
                    $pattern_id
                ),
                ARRAY_A
            );
            
            if ($pattern === null) {
                // No error logging for "not found" as this is a normal case
                return null;
            }
            
            if ($pattern === false) {
                $this->logger->error('Failed to retrieve pattern from database', [
                    'pattern_id' => $pattern_id,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }
            
            // Decode pattern data
            $decoded_data = json_decode($pattern['pattern_data'], true);
            if ($decoded_data === null && json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Failed to decode pattern data', [
                    'pattern_id' => $pattern_id,
                    'json_error' => json_last_error_msg()
                ]);
                return false;
            }
            $pattern['pattern_data'] = $decoded_data;
            
            $this->logger->info('Pattern retrieved successfully', [
                'pattern_id' => $pattern_id,
                'pattern_type' => $pattern['pattern_type']
            ]);
            
            return $pattern;
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while retrieving pattern', [
                'pattern_id' => $pattern_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}