<?php
/**
 * Debug Logger
 *
 * Handles logging to database and file
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Debug;

/**
 * Debug Logger Class
 */
class DebugLogger
{
    /**
     * Debug engine
     *
     * @var DebugEngine
     */
    private DebugEngine $debug_engine;

    /**
     * Log file path
     *
     * @var string
     */
    private string $log_file;

    /**
     * Constructor
     *
     * @param DebugEngine $debug_engine Debug engine
     */
    public function __construct(DebugEngine $debug_engine)
    {
        $this->debug_engine = $debug_engine;

        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aevov-logs';

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $this->log_file = $log_dir . '/debug-' . date('Y-m-d') . '.log';
    }

    /**
     * Log message
     *
     * @param string $level Log level
     * @param string $component Component name
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    public function log(string $level, string $component, string $message, array $context = []): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_debug_logs';

        // Insert into database
        $wpdb->insert($table, [
            'level' => $level,
            'component' => $component,
            'message' => $message,
            'context' => wp_json_encode($context),
            'created_at' => current_time('mysql')
        ]);

        // Write to file
        $this->write_to_file($level, $component, $message, $context);
    }

    /**
     * Write to log file
     *
     * @param string $level Log level
     * @param string $component Component name
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function write_to_file(string $level, string $component, string $message, array $context): void
    {
        $timestamp = current_time('mysql');
        $level_upper = strtoupper($level);

        $log_line = "[{$timestamp}] [{$level_upper}] [{$component}] {$message}";

        if (!empty($context)) {
            $log_line .= ' ' . wp_json_encode($context);
        }

        $log_line .= "\n";

        // Append to file
        file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get recent logs
     *
     * @param int $limit Limit
     * @param array $filters Filters
     * @return array
     */
    public function get_recent(int $limit = 100, array $filters = []): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aev_debug_logs';
        $where = ['1=1'];
        $params = [];

        if (isset($filters['level'])) {
            $where[] = 'level = %s';
            $params[] = $filters['level'];
        }

        if (isset($filters['component'])) {
            $where[] = 'component = %s';
            $params[] = $filters['component'];
        }

        if (isset($filters['start_date'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['start_date'];
        }

        $where_clause = implode(' AND ', $where);

        if (!empty($params)) {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d",
                ...array_merge($params, [$limit])
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d",
                $limit
            );
        }

        $results = $wpdb->get_results($query, ARRAY_A);

        // Decode context JSON
        foreach ($results as &$result) {
            $result['context'] = json_decode($result['context'], true);
        }

        return $results;
    }

    /**
     * Get log file path
     *
     * @return string
     */
    public function get_log_file(): string
    {
        return $this->log_file;
    }

    /**
     * Read log file
     *
     * @param int $lines Number of lines to read from end
     * @return string
     */
    public function read_log_file(int $lines = 100): string
    {
        if (!file_exists($this->log_file)) {
            return '';
        }

        $file = new \SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);

        $last_line = $file->key();
        $start_line = max(0, $last_line - $lines);

        $output = [];
        $file->seek($start_line);

        while (!$file->eof()) {
            $output[] = $file->current();
            $file->next();
        }

        return implode('', $output);
    }
}
