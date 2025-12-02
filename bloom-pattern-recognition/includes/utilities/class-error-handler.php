<?php
namespace BLOOM\Utilities;

/**
 * Error handling and logging for BLOOM Pattern Recognition
 */
class ErrorHandler {
    private $table = 'bloom_error_log';
    private $db;
    private $log_levels = [
        'debug' => 1,
        'info' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5
    ];
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . $this->table;
    }

    public function log_error($error, $context = []) {
        $error_data = [
            'error_type' => $this->get_error_type($error),
            'error_message' => $this->get_error_message($error),
            'error_trace' => $this->get_error_trace($error),
            'error_context' => json_encode($context),
            'site_id' => get_current_blog_id(),
            'created_at' => current_time('mysql')
        ];

        $this->db->insert($this->table, $error_data);
        
        // Also log to WordPress error log
        $this->log_to_wordpress($error, $context);
        
        // Send critical errors to admin
        if ($this->is_critical_error($error)) {
            $this->notify_admin($error, $context);
        }
    }

    public function log_info($message, $context = []) {
        $this->log_custom('info', $message, $context);
    }

    public function log_warning($message, $context = []) {
        $this->log_custom('warning', $message, $context);
    }

    public function log_debug($message, $context = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log_custom('debug', $message, $context);
        }
    }

    private function log_custom($level, $message, $context = []) {
        $error_data = [
            'error_type' => $level,
            'error_message' => $message,
            'error_trace' => json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)),
            'error_context' => json_encode($context),
            'site_id' => get_current_blog_id(),
            'created_at' => current_time('mysql')
        ];

        $this->db->insert($this->table, $error_data);
    }

    private function get_error_type($error) {
        if ($error instanceof \Exception) {
            return get_class($error);
        } elseif (is_array($error) && isset($error['type'])) {
            return $error['type'];
        } else {
            return 'unknown';
        }
    }

    private function get_error_message($error) {
        if ($error instanceof \Exception) {
            return $error->getMessage();
        } elseif (is_array($error) && isset($error['message'])) {
            return $error['message'];
        } elseif (is_string($error)) {
            return $error;
        } else {
            return 'Unknown error occurred';
        }
    }

    private function get_error_trace($error) {
        if ($error instanceof \Exception) {
            return $error->getTraceAsString();
        } else {
            return json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10));
        }
    }

    private function log_to_wordpress($error, $context) {
        $message = sprintf(
            'BLOOM Error: %s - %s',
            $this->get_error_type($error),
            $this->get_error_message($error)
        );
        
        if (!empty($context)) {
            $message .= ' | Context: ' . json_encode($context);
        }
        
        error_log($message);
    }

    private function is_critical_error($error) {
        $critical_types = [
            'Fatal Error',
            'Parse Error',
            'Database Error',
            'Memory Error'
        ];
        
        $error_type = $this->get_error_type($error);
        $error_message = $this->get_error_message($error);
        
        foreach ($critical_types as $critical_type) {
            if (stripos($error_type, $critical_type) !== false || 
                stripos($error_message, $critical_type) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function notify_admin($error, $context) {
        $admin_email = get_option('admin_email');
        
        if (!$admin_email) {
            return;
        }
        
        $subject = 'BLOOM Critical Error - ' . get_bloginfo('name');
        $message = $this->format_error_email($error, $context);
        
        wp_mail($admin_email, $subject, $message);
    }

    private function format_error_email($error, $context) {
        $site_url = get_site_url();
        $site_name = get_bloginfo('name');
        $timestamp = current_time('mysql');
        
        $message = "A critical error occurred in BLOOM Pattern Recognition:\n\n";
        $message .= "Site: {$site_name} ({$site_url})\n";
        $message .= "Time: {$timestamp}\n";
        $message .= "Error Type: " . $this->get_error_type($error) . "\n";
        $message .= "Error Message: " . $this->get_error_message($error) . "\n\n";
        
        if (!empty($context)) {
            $message .= "Context:\n" . print_r($context, true) . "\n\n";
        }
        
        $message .= "Error Trace:\n" . $this->get_error_trace($error) . "\n";
        
        return $message;
    }

    public function get_recent_errors($limit = 50, $level = null) {
        $where_clause = "WHERE site_id = " . get_current_blog_id();
        
        if ($level && isset($this->log_levels[$level])) {
            $where_clause .= " AND error_type = '" . esc_sql($level) . "'";
        }
        
        return $this->db->get_results(
            "SELECT * FROM {$this->table} 
             {$where_clause}
             ORDER BY created_at DESC 
             LIMIT " . intval($limit),
            ARRAY_A
        );
    }

    public function get_error_statistics($days = 7) {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT 
                    error_type,
                    COUNT(*) as count,
                    DATE(created_at) as error_date
                 FROM {$this->table} 
                 WHERE site_id = %d 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY error_type, DATE(created_at)
                 ORDER BY error_date DESC, count DESC",
                get_current_blog_id(),
                $days
            ),
            ARRAY_A
        );
    }

    public function clear_old_errors($days = 30) {
        return $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$this->table} 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    public function get_error_summary() {
        $total_errors = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE site_id = %d",
                get_current_blog_id()
            )
        );
        
        $recent_errors = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table} 
                 WHERE site_id = %d 
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                get_current_blog_id()
            )
        );
        
        $critical_errors = $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->table} 
                 WHERE site_id = %d 
                 AND error_type IN ('Fatal Error', 'Parse Error', 'Database Error', 'Memory Error')
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                get_current_blog_id()
            )
        );
        
        return [
            'total_errors' => intval($total_errors),
            'recent_errors' => intval($recent_errors),
            'critical_errors' => intval($critical_errors),
            'error_rate' => $recent_errors > 0 ? round($recent_errors / 24, 2) : 0
        ];
    }

    public function create_table() {
        $charset_collate = $this->db->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            error_type varchar(50) NOT NULL,
            error_message text NOT NULL,
            error_trace text,
            error_context text,
            site_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY error_type (error_type),
            KEY site_id (site_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}