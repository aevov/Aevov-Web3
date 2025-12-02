<?php
/**
 * Manages database migrations and schema changes
 * 
 * @package APS
 * @subpackage DB
 */

namespace APS\DB;

class MigrationManager {
    private $migrations = [];

    public function register_migration($version, $callback) {
        $this->migrations[$version] = $callback;
    }

    public function run_migrations() {
        $current_version = get_option('aps_db_version', '1.0.0');
        
        foreach ($this->migrations as $version => $callback) {
            if (version_compare($current_version, $version, '<')) {
                call_user_func($callback);
                update_option('aps_db_version', $version);
            }
        }
    }
}