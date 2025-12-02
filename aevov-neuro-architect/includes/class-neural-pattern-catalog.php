<?php
namespace AevovNeuroArchitect\Core;

use WP_Error;

class NeuralPatternCatalog {

    private $db;
    private $table_name;
    private $logger;

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table_name = $this->db->prefix . 'aevov_neural_patterns';
        $this->logger = new class {
            public function info($message, $context = []) { error_log('[INFO] ' . $message . ' ' . json_encode($context)); }
            public function error($message, $context = []) { error_log('[ERROR] ' . $message . ' ' . json_encode($context)); }
        };
    }

    public function create_table() {
        $charset_collate = $this->db->get_charset_collate();
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            pattern_id varchar(255) NOT NULL,
            pattern_type varchar(255) NOT NULL,
            version varchar(255) NOT NULL,
            dependencies longtext NOT NULL,
            metadata longtext NOT NULL,
            performance longtext NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY pattern_id_version (pattern_id, version)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function register_pattern($pattern_id, $pattern_type, $version, $dependencies, $metadata) {
        $this->logger->info('Registering new neural pattern', ['pattern_id' => $pattern_id, 'version' => $version]);

        $result = $this->db->insert(
            $this->table_name,
            [
                'pattern_id' => $pattern_id,
                'pattern_type' => $pattern_type,
                'version' => $version,
                'dependencies' => json_encode($dependencies),
                'metadata' => json_encode($metadata),
                'performance' => json_encode([]),
            ]
        );

        if (is_wp_error($result)) {
            $this->logger->error('Failed to register neural pattern', ['error' => $result->get_error_message()]);
            return $result;
        }

        return $this->db->insert_id;
    }

    public function get_pattern($pattern_id, $version = 'latest') {
        $this->logger->info('Fetching neural pattern', ['pattern_id' => $pattern_id, 'version' => $version]);

        if ($version === 'latest') {
            $sql = $this->db->prepare("SELECT * FROM {$this->table_name} WHERE pattern_id = %s ORDER BY version DESC LIMIT 1", $pattern_id);
        } else {
            $sql = $this->db->prepare("SELECT * FROM {$this->table_name} WHERE pattern_id = %s AND version = %s", $pattern_id, $version);
        }

        $result = $this->db->get_row($sql);

        if (is_wp_error($result)) {
            $this->logger->error('Failed to fetch neural pattern', ['error' => $result->get_error_message()]);
            return $result;
        }

        if ($result) {
            $result->dependencies = json_decode($result->dependencies, true);
            $result->metadata = json_decode($result->metadata, true);
            $result->performance = json_decode($result->performance, true);
        }

        return $result;
    }

    public function query_patterns($query_args) {
        $this->logger->info('Querying neural patterns', ['query_args' => $query_args]);

        $sql = "SELECT * FROM {$this->table_name} WHERE 1=1";
        $prepare_args = [];

        if (!empty($query_args['pattern_type'])) {
            $sql .= " AND pattern_type = %s";
            $prepare_args[] = $query_args['pattern_type'];
        }

        if (!empty($query_args['version'])) {
            $sql .= " AND version = %s";
            $prepare_args[] = $query_args['version'];
        }

        if (!empty($query_args['dependencies_include'])) {
            foreach ($query_args['dependencies_include'] as $dependency) {
                $sql .= " AND dependencies LIKE %s";
                $prepare_args[] = '%' . $this->db->esc_like('"' . $dependency . '"') . '%';
            }
        }

        if (!empty($query_args['metadata_contains'])) {
            foreach ($query_args['metadata_contains'] as $key => $value) {
                $sql .= " AND metadata LIKE %s";
                $prepare_args[] = '%' . $this->db->esc_like('"' . $key . '":"' . $value . '"') . '%';
            }
        }

        // Prepare the entire query if there are arguments
        if (!empty($prepare_args)) {
            $sql = $this->db->prepare($sql, ...$prepare_args);
        }

        $results = $this->db->get_results($sql);

        if (is_wp_error($results)) {
            $this->logger->error('Failed to query neural patterns', ['error' => $results->get_error_message()]);
            return $results;
        }

        foreach ($results as &$result) {
            $result->dependencies = json_decode($result->dependencies, true);
            $result->metadata = json_decode($result->metadata, true);
            $result->performance = json_decode($result->performance, true);
        }

        return $results;
    }

    public function update_performance($pattern_id, $version, $performance_data) {
        $this->logger->info('Updating performance data for neural pattern', ['pattern_id' => $pattern_id, 'version' => $version]);

        $existing_pattern = $this->get_pattern($pattern_id, $version);
        if (!$existing_pattern) {
            return new WP_Error('pattern_not_found', 'Neural pattern not found.');
        }

        $new_performance = array_merge($existing_pattern->performance, $performance_data);

        $result = $this->db->update(
            $this->table_name,
            ['performance' => json_encode($new_performance)],
            ['id' => $existing_pattern->id]
        );

        if (is_wp_error($result)) {
            $this->logger->error('Failed to update performance data', ['error' => $result->get_error_message()]);
            return $result;
        }

        return true;
    }
}
