<?php
/**
 * Database handler for pattern cache operations
 * 
 * @package APS
 * @subpackage DB
 */

namespace APS\DB;

class PatternCacheDB {
    private $db;
    private $table_name;
    
    public function __construct($wpdb = null) {
        $this->db = $wpdb ?? $GLOBALS['wpdb'];
        $this->table_name = $this->db->prefix . 'aps_patterns_cache';
    }
    
    public function create_table() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->db->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            pattern_hash varchar(64) NOT NULL,
            pattern_data longtext NOT NULL,
            cache_expires datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY pattern_hash (pattern_hash)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    public function store_pattern($pattern_hash, $pattern_data, $expires_in = 3600) {
        $expires = date('Y-m-d H:i:s', time() + $expires_in);
        
        return $this->db->replace(
            $this->table_name,
            [
                'pattern_hash' => $pattern_hash,
                'pattern_data' => is_array($pattern_data) ? json_encode($pattern_data) : $pattern_data,
                'cache_expires' => $expires
            ],
            [
                '%s',
                '%s',
                '%s'
            ]
        );
    }
    
    public function get_pattern($pattern_hash) {
        $result = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table_name} 
                 WHERE pattern_hash = %s 
                 AND cache_expires > NOW()",
                $pattern_hash
            ),
            ARRAY_A
        );
        
        if (!$result) {
            return null;
        }
        
        return json_decode($result['pattern_data'], true);
    }
    
    public function cleanup_expired() {
        return $this->db->query(
            "DELETE FROM {$this->table_name} WHERE cache_expires < NOW()"
        );
    }
    
    public function clear_cache() {
        return $this->db->query("TRUNCATE TABLE {$this->table_name}");
    }
}