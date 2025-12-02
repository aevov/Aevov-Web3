<?php
/**
 * Manages sync log database operations
 * 
 * @package APS
 * @subpackage DB
 */

namespace APS\DB;

class SyncLogDB {
    private $wpdb;
    private $table_name;
    
    public function __construct($wpdb = null) {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
        $this->table_name = $this->wpdb->prefix . 'aps_sync_log';
    }
    
    public function create_table() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sync_type varchar(32) NOT NULL,
            sync_data longtext NOT NULL,
            status varchar(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY sync_type (sync_type),
            KEY status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    public function log_sync_event($type, $data, $status) {
        return $this->wpdb->insert(
            $this->table_name,
            [
                'sync_type' => $type,
                'sync_data' => json_encode($data),
                'status' => $status,
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    public function log_sync_error($error, $context = []) {
        return $this->log_sync_event(
            'error',
            [
                'message' => $error->getMessage(),
                'trace' => $error->getTraceAsString(),
                'context' => $context
            ],
            'error'
        );
    }
    
    public function cleanup_old_logs($days = 30) {
        return $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    public function optimize_table() {
        return $this->wpdb->query("OPTIMIZE TABLE {$this->table_name}");
    }
}