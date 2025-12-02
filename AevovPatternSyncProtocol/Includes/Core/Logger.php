<?php
/**
 * Core Logger Class for AevovPatternSyncProtocol
 */

namespace APS\Core;

class Logger {
    private static $instance = null;
    private $log_file;
    private $log_level;
    
    const LEVEL_DEBUG = 1;
    const LEVEL_INFO = 2;
    const LEVEL_WARNING = 3;
    const LEVEL_ERROR = 4;
    const LEVEL_CRITICAL = 5;
    
    private function __construct() {
        $this->log_level = self::LEVEL_INFO;
        $this->log_file = defined('APS_PATH') ? APS_PATH . 'logs/aps.log' : '/tmp/aps.log';
        
        // Ensure log directory exists
        $log_dir = dirname($this->log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
    }
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function debug($message, $context = []) {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }
    
    public function info($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
    
    public function critical($message, $context = []) {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
    }
    
    private function log($level, $message, $context = []) {
        if ($level < $this->log_level) {
            return;
        }
        
        $level_names = [
            self::LEVEL_DEBUG => 'DEBUG',
            self::LEVEL_INFO => 'INFO',
            self::LEVEL_WARNING => 'WARNING',
            self::LEVEL_ERROR => 'ERROR',
            self::LEVEL_CRITICAL => 'CRITICAL'
        ];
        
        $timestamp = function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s');
        $level_name = $level_names[$level] ?? 'UNKNOWN';
        
        $log_entry = sprintf(
            "[%s] %s: %s",
            $timestamp,
            $level_name,
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= ' Context: ' . json_encode($context);
        }
        
        $log_entry .= PHP_EOL;
        
        // Write to file if possible, otherwise use error_log
        if (is_writable(dirname($this->log_file))) {
            file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        } else {
            error_log($log_entry);
        }
    }
    
    public function set_log_level($level) {
        $this->log_level = $level;
    }
    
    public function get_log_level() {
        return $this->log_level;
    }
    
    public function clear_log() {
        if (file_exists($this->log_file)) {
            file_put_contents($this->log_file, '');
        }
    }
    
    public function get_recent_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $file = file($this->log_file);
        return array_slice($file, -$lines);
    }
}