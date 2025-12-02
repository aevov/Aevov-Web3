<?php
namespace BLOOM\Core;

use BLOOM\Models\TensorModel;
use BLOOM\Models\ChunkModel;
use BLOOM\Models\PatternModel;
use BLOOM\Network\MessageQueue;
use BLOOM\Utilities\ErrorHandler;

/**
 * Handles plugin activation and setup
 */
class PluginActivator {
    public function activate() {
        if (!$this->check_requirements()) {
            $this->deactivate();
            return;
        }

        $this->create_tables();
        $this->initialize_options();
        $this->setup_scheduled_tasks();
        
        // For network activation
        if (is_multisite()) {
            $this->network_activate();
        }

        flush_rewrite_rules();
    }

    private function check_requirements() {
        if (version_compare(PHP_VERSION, BLOOM_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', function() {
                $message = sprintf(
                    'BLOOM Pattern System requires PHP %s or higher. Your current version is %s',
                    BLOOM_MIN_PHP_VERSION,
                    PHP_VERSION
                );
                echo '<div class="error"><p>' . esc_html($message) . '</p></div>';
            });
            return false;
        }

        if (!is_multisite()) {
            add_action('admin_notices', function() {
                $message = 'BLOOM Pattern System requires WordPress Multisite to be enabled.';
                echo '<div class="error"><p>' . esc_html($message) . '</p></div>';
            });
            return false;
        }

        return true;
    }

    public function create_tables() {
        try {
            // Create model tables
            $models = [
                new TensorModel(),
                new ChunkModel(),
                new PatternModel()
            ];

            foreach ($models as $model) {
                $model->create_table();
            }

            // Create utility tables
            $error_handler = new ErrorHandler();
            $error_handler->create_table();
            
            // Create message queue table
            $message_queue = new MessageQueue();
            $message_queue->create_table();
            
        } catch (\Exception $e) {
            error_log('BLOOM Plugin Activation Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function initialize_options() {
        $default_options = [
            'bloom_version' => BLOOM_VERSION,
            'chunk_size' => BLOOM_CHUNK_SIZE,
            'sites_per_pattern' => 3,
            'sync_interval' => 300,
            'processing_batch_size' => 100
        ];

        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    private function setup_scheduled_tasks() {
        if (!wp_next_scheduled('bloom_process_queue')) {
            wp_schedule_event(time(), 'minute', 'bloom_process_queue');
        }

        if (!wp_next_scheduled('bloom_sync_network')) {
            wp_schedule_event(time(), 'five_minutes', 'bloom_sync_network');
        }

        if (!wp_next_scheduled('bloom_system_check')) {
            wp_schedule_event(time(), 'minute', 'bloom_system_check');
        }
    }

    private function network_activate() {
        $sites = get_sites(['fields' => 'ids']);
        
        foreach ($sites as $site_id) {
            switch_to_blog($site_id);
            $this->create_tables();
            $this->initialize_options();
            restore_current_blog();
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('bloom_process_queue');
        wp_clear_scheduled_hook('bloom_sync_network');
        wp_clear_scheduled_hook('bloom_system_check');
    }
}