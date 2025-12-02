<?php
namespace BLOOM\Network;

/**
 * Message Queue for inter-site communication in BLOOM Pattern Recognition
 */
class MessageQueue {
    private $table = 'bloom_message_queue';
    private $db;
    private $max_retries = 3;
    private $retry_delay = 300; // 5 minutes
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . $this->table;
        $this->setup_hooks();
    }

    public function enqueue_message($message) {
        $data = [
            'message_type' => $message['type'],
            'message_data' => json_encode($message['data']),
            'source_site' => get_current_blog_id(),
            'target_site' => $message['target_site'] ?? null,
            'priority' => $message['priority'] ?? 5,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => current_time('mysql'),
            'scheduled_at' => $message['scheduled_at'] ?? current_time('mysql')
        ];

        $result = $this->db->insert($this->table, $data);
        
        if ($result) {
            return $this->db->insert_id;
        }
        
        return false;
    }

    public function process_queue($limit = 10) {
        $messages = $this->get_pending_messages($limit);
        $processed = 0;
        
        foreach ($messages as $message) {
            try {
                $result = $this->process_message($message);
                
                if ($result) {
                    $this->mark_message_completed($message['id']);
                    $processed++;
                } else {
                    $this->handle_message_failure($message);
                }
                
            } catch (\Exception $e) {
                $this->handle_message_error($message, $e);
            }
        }
        
        return $processed;
    }

    private function get_pending_messages($limit) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} 
                 WHERE status = 'pending' 
                 AND scheduled_at <= %s
                 AND attempts < %d
                 ORDER BY priority ASC, created_at ASC
                 LIMIT %d",
                current_time('mysql'),
                $this->max_retries,
                $limit
            ),
            ARRAY_A
        );
    }

    private function process_message($message) {
        $message_data = json_decode($message['message_data'], true);
        
        switch ($message['message_type']) {
            case 'pattern_distribution':
                return $this->process_pattern_distribution($message_data, $message);
                
            case 'tensor_sync':
                return $this->process_tensor_sync($message_data, $message);
                
            case 'health_check':
                return $this->process_health_check($message_data, $message);
                
            case 'pattern_update':
                return $this->process_pattern_update($message_data, $message);
                
            default:
                // Allow custom message types via filter
                return apply_filters('bloom_process_custom_message', false, $message['message_type'], $message_data, $message);
        }
    }

    private function process_pattern_distribution($data, $message) {
        if (!isset($data['pattern']) || !$message['target_site']) {
            return false;
        }

        $current_site = get_current_blog_id();
        
        switch_to_blog($message['target_site']);
        
        try {
            $pattern_model = new \BLOOM\Models\PatternModel();
            $pattern_data = $data['pattern'];
            $pattern_data['site_id'] = $message['target_site'];
            
            $result = $pattern_model->create($pattern_data);
            
            restore_current_blog();
            
            return (bool)$result;
            
        } catch (\Exception $e) {
            restore_current_blog();
            throw $e;
        }
    }

    private function process_tensor_sync($data, $message) {
        if (!isset($data['tensor_sku']) || !$message['target_site']) {
            return false;
        }

        $current_site = get_current_blog_id();
        
        // Get tensor data from source site
        $tensor_model = new \BLOOM\Models\TensorModel();
        $tensor_data = $tensor_model->get($data['tensor_sku']);
        
        if (!$tensor_data) {
            return false;
        }

        switch_to_blog($message['target_site']);
        
        try {
            $target_tensor_model = new \BLOOM\Models\TensorModel();
            $result = $target_tensor_model->create($tensor_data);
            
            restore_current_blog();
            
            return (bool)$result;
            
        } catch (\Exception $e) {
            restore_current_blog();
            throw $e;
        }
    }

    private function process_health_check($data, $message) {
        if (!$message['target_site']) {
            return false;
        }

        switch_to_blog($message['target_site']);
        
        try {
            $health_data = [
                'site_id' => $message['target_site'],
                'status' => 'healthy',
                'timestamp' => time(),
                'pattern_count' => $this->get_site_pattern_count(),
                'memory_usage' => memory_get_usage(true),
                'disk_space' => $this->get_available_disk_space()
            ];
            
            update_option('bloom_health_status', $health_data);
            
            restore_current_blog();
            
            return true;
            
        } catch (\Exception $e) {
            restore_current_blog();
            throw $e;
        }
    }

    private function process_pattern_update($data, $message) {
        if (!isset($data['pattern_id']) || !isset($data['updates'])) {
            return false;
        }

        $target_site = $message['target_site'] ?? get_current_blog_id();
        
        if ($target_site != get_current_blog_id()) {
            switch_to_blog($target_site);
        }
        
        try {
            $pattern_model = new \BLOOM\Models\PatternModel();
            
            // Update pattern confidence or other fields
            if (isset($data['updates']['confidence'])) {
                $result = $pattern_model->update_confidence(
                    $data['pattern_id'], 
                    $data['updates']['confidence']
                );
            } else {
                $result = true; // Other updates can be added here
            }
            
            if ($target_site != get_current_blog_id()) {
                restore_current_blog();
            }
            
            return $result;
            
        } catch (\Exception $e) {
            if ($target_site != get_current_blog_id()) {
                restore_current_blog();
            }
            throw $e;
        }
    }

    private function get_site_pattern_count() {
        $pattern_model = new \BLOOM\Models\PatternModel();
        $stats = $pattern_model->get_pattern_statistics();
        return array_sum(array_column($stats, 'count'));
    }

    private function get_available_disk_space() {
        $upload_dir = wp_upload_dir();
        return disk_free_space($upload_dir['basedir']);
    }

    private function mark_message_completed($message_id) {
        return $this->db->update(
            $this->table,
            [
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ],
            ['id' => $message_id]
        );
    }

    private function handle_message_failure($message) {
        $attempts = intval($message['attempts']) + 1;
        
        if ($attempts >= $this->max_retries) {
            // Mark as failed
            $this->db->update(
                $this->table,
                [
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'failed_at' => current_time('mysql')
                ],
                ['id' => $message['id']]
            );
        } else {
            // Schedule retry
            $next_attempt = date('Y-m-d H:i:s', time() + ($this->retry_delay * $attempts));
            
            $this->db->update(
                $this->table,
                [
                    'attempts' => $attempts,
                    'scheduled_at' => $next_attempt,
                    'last_error' => 'Processing failed, scheduled for retry'
                ],
                ['id' => $message['id']]
            );
        }
    }

    private function handle_message_error($message, $error) {
        $attempts = intval($message['attempts']) + 1;
        
        $this->db->update(
            $this->table,
            [
                'attempts' => $attempts,
                'last_error' => $error->getMessage(),
                'scheduled_at' => date('Y-m-d H:i:s', time() + ($this->retry_delay * $attempts))
            ],
            ['id' => $message['id']]
        );
    }

    public function get_queue_status() {
        return $this->db->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM {$this->table}",
            ARRAY_A
        );
    }

    public function cleanup_old_messages($days = 7) {
        return $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$this->table} 
                 WHERE status IN ('completed', 'failed') 
                 AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    public function create_table() {
        $charset_collate = $this->db->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message_type varchar(50) NOT NULL,
            message_data longtext NOT NULL,
            source_site bigint(20) NOT NULL,
            target_site bigint(20),
            priority int NOT NULL DEFAULT 5,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int NOT NULL DEFAULT 0,
            last_error text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            failed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY message_type (message_type),
            KEY source_site (source_site),
            KEY target_site (target_site),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY priority (priority)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function setup_hooks() {
        // Only setup hooks if WordPress functions are available
        if (function_exists('add_action')) {
            // Process queue on cron
            add_action('bloom_process_queue', [$this, 'process_queue']);
            
            // Cleanup old messages
            add_action('bloom_cleanup_messages', [$this, 'cleanup_old_messages']);
        }
    }
}