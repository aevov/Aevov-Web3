<?php
/**
 * Handles plugin settings page and options management
 */
class BLOOM_Settings_Page {
    private $option_group = 'bloom_settings';
    private $option_name = 'bloom_options';
    
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            [$this, 'validate_settings']
        );

        $this->add_settings_sections();
    }

    public function validate_settings($input) {
        $sanitized = [];

        foreach ($input as $key => $value) {
            switch ($key) {
                case 'api_enabled':
                case 'debug_mode':
                    $sanitized[$key] = (bool) $value;
                    break;

                case 'chunk_size':
                case 'sites_per_chunk':
                case 'processing_batch_size':
                case 'sync_interval':
                case 'threshold_queue_size':
                case 'retention_patterns_days':
                case 'retention_metrics_days':
                case 'retention_logs_days':
                case 'multisite_master_site_id':
                    $sanitized[$key] = absint($value);
                    break;

                case 'threshold_cpu':
                case 'threshold_memory':
                case 'threshold_disk':
                case 'threshold_error_rate':
                    $sanitized[$key] = floatval($value);
                    break;

                case 'api_key':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;

                case 'multisite_sync_partners':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;

                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }

        return $sanitized;
    }

    private function add_settings_sections() {
        // Network Settings
        add_settings_section(
            'network_settings',
            __('Network Settings', 'bloom-pattern-system'),
            [$this, 'render_network_section'],
            $this->option_group
        );
 
        // Processing Settings
        add_settings_section(
            'processing_settings',
            __('Processing Settings', 'bloom-pattern-system'),
            [$this, 'render_processing_section'],
            $this->option_group
        );
 
        // API Settings
        add_settings_section(
            'api_settings',
            __('API Settings', 'bloom-pattern-system'),
            [$this, 'render_api_section'],
            $this->option_group
        );

        // Monitoring Settings
        add_settings_section(
            'monitoring_settings',
            __('Monitoring Settings', 'bloom-pattern-system'),
            [$this, 'render_monitoring_section'],
            $this->option_group
        );

        // Data Retention Settings
        add_settings_section(
            'data_retention_settings',
            __('Data Retention Settings', 'bloom-pattern-system'),
            [$this, 'render_data_retention_section'],
            $this->option_group
        );

        // Multisite Settings
        if (is_multisite()) {
            add_settings_section(
                'multisite_settings',
                __('Multisite Settings', 'bloom-pattern-system'),
                [$this, 'render_multisite_section'],
                $this->option_group
            );
        }

        $this->add_settings_fields();
    }
 
    private function add_settings_fields() {
        // Network Fields
        add_settings_field(
            'chunk_size',
            __('Chunk Size (MB)', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'network_settings',
            [
                'name' => 'chunk_size',
                'min' => 1,
                'max' => 50,
                'default' => 7
            ]
        );
 
        add_settings_field(
            'sites_per_chunk',
            __('Sites Per Chunk', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'network_settings',
            [
                'name' => 'sites_per_chunk',
                'min' => 2,
                'max' => 10,
                'default' => 3
            ]
        );

        // Processing Fields
        add_settings_field(
            'processing_batch_size',
            __('Processing Batch Size', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'processing_settings',
            [
                'name' => 'processing_batch_size',
                'min' => 10,
                'max' => 1000,
                'default' => 100
            ]
        );

        add_settings_field(
            'sync_interval',
            __('Sync Interval (seconds)', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'processing_settings',
            [
                'name' => 'sync_interval',
                'min' => 60,
                'max' => 3600,
                'default' => 300
            ]
        );

        // API Fields
        add_settings_field(
            'api_enabled',
            __('Enable API', 'bloom-pattern-system'),
            [$this, 'render_checkbox_field'],
            $this->option_group,
            'api_settings',
            [
                'name' => 'api_enabled',
                'description' => __('Enable or disable the BLOOM REST API.', 'bloom-pattern-system'),
                'default' => true
            ]
        );

        add_settings_field(
            'api_key',
            __('API Key', 'bloom-pattern-system'),
            [$this, 'render_password_field'],
            $this->option_group,
            'api_settings',
            [
                'name' => 'api_key',
                'description' => __('API key for external access. Leave empty to auto-generate.', 'bloom-pattern-system'),
                'default' => ''
            ]
        );

        // Monitoring Fields
        add_settings_field(
            'threshold_cpu',
            __('CPU Usage Threshold (%)', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'monitoring_settings',
            [
                'name' => 'threshold_cpu',
                'min' => 0,
                'max' => 1,
                'step' => 0.01,
                'default' => 0.85
            ]
        );

        add_settings_field(
            'threshold_memory',
            __('Memory Usage Threshold (%)', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'monitoring_settings',
            [
                'name' => 'threshold_memory',
                'min' => 0,
                'max' => 1,
                'step' => 0.01,
                'default' => 0.85
            ]
        );

        add_settings_field(
            'threshold_disk',
            __('Disk Usage Threshold (%)', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'monitoring_settings',
            [
                'name' => 'threshold_disk',
                'min' => 0,
                'max' => 1,
                'step' => 0.01,
                'default' => 0.90
            ]
        );

        add_settings_field(
            'threshold_queue_size',
            __('Queue Size Threshold', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'monitoring_settings',
            [
                'name' => 'threshold_queue_size',
                'min' => 1,
                'default' => 1000
            ]
        );

        add_settings_field(
            'threshold_error_rate',
            __('Error Rate Threshold (%)', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'monitoring_settings',
            [
                'name' => 'threshold_error_rate',
                'min' => 0,
                'max' => 1,
                'step' => 0.01,
                'default' => 0.05
            ]
        );

        add_settings_field(
            'debug_mode',
            __('Enable Debug Mode', 'bloom-pattern-system'),
            [$this, 'render_checkbox_field'],
            $this->option_group,
            'monitoring_settings',
            [
                'name' => 'debug_mode',
                'description' => __('Enable verbose logging for debugging purposes. Only enable this if you are experiencing issues.', 'bloom-pattern-system'),
                'default' => false
            ]
        );

        // Data Retention Fields
        add_settings_field(
            'retention_patterns_days',
            __('Retain Patterns (days)', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'data_retention_settings',
            [
                'name' => 'retention_patterns_days',
                'min' => 1,
                'default' => 90
            ]
        );

        add_settings_field(
            'retention_metrics_days',
            __('Retain Metrics (days)', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'data_retention_settings',
            [
                'name' => 'retention_metrics_days',
                'min' => 1,
                'default' => 30
            ]
        );

        add_settings_field(
            'retention_logs_days',
            __('Retain Logs (days)', 'bloom-pattern-system'),
            [$this, 'render_number_field'],
            $this->option_group,
            'data_retention_settings',
            [
                'name' => 'retention_logs_days',
                'min' => 1,
                'default' => 7
            ]
        );

        // Multisite Fields
        if (is_multisite()) {
            add_settings_field(
                'multisite_master_site_id',
                __('Master Site ID', 'bloom-pattern-system'),
                [$this, 'render_number_field'],
                $this->option_group,
                'multisite_settings',
                [
                    'name' => 'multisite_master_site_id',
                    'min' => 1,
                    'default' => 1
                ]
            );

            add_settings_field(
                'multisite_sync_partners',
                __('Sync Partner Site IDs (comma-separated)', 'bloom-pattern-system'),
                [$this, 'render_text_field'],
                $this->option_group,
                'multisite_settings',
                [
                    'name' => 'multisite_sync_partners',
                    'description' => __('Enter comma-separated IDs of sites to synchronize patterns with.', 'bloom-pattern-system'),
                    'default' => ''
                ]
            );
        }
    }
 
    public function render_network_section() {
        echo '<p>' . __('Configure network-related settings for pattern distribution.', 'bloom-pattern-system') . '</p>';
    }

    public function render_processing_section() {
        echo '<p>' . __('Configure settings related to pattern processing and analysis.', 'bloom-pattern-system') . '</p>';
    }

    public function render_api_section() {
        echo '<p>' . __('Manage API access and security settings.', 'bloom-pattern-system') . '</p>';
    }

    public function render_monitoring_section() {
        echo '<p>' . __('Set thresholds for system monitoring and alerts.', 'bloom-pattern-system') . '</p>';
    }

    public function render_data_retention_section() {
        echo '<p>' . __('Configure how long pattern data, metrics, and logs are retained.', 'bloom-pattern-system') . '</p>';
    }

    public function render_multisite_section() {
        echo '<p>' . __('Configure multisite-specific synchronization and integration settings.', 'bloom-pattern-system') . '</p>';
    }
 
    public function render_number_field($args) {
        $options = get_option($this->option_name);
        $value = $options[$args['name']] ?? $args['default'];
        
        printf(
            '<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$s" max="%5$s" step="%6$s" class="regular-text">',
            esc_attr($args['name']),
            esc_attr($this->option_name),
            esc_attr($value),
            esc_attr($args['min']),
            esc_attr($args['max'] ?? ''), // Max might not be set for all number fields
            esc_attr($args['step'] ?? 1) // Default step to 1
        );
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
 
    // Helper function to render a checkbox field
    public function render_checkbox_field($args) {
        $options = get_option($this->option_name);
        $checked = isset($options[$args['name']]) ? checked(1, $options[$args['name']], false) : '';
        printf(
            '<input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s>',
            esc_attr($args['name']),
            esc_attr($this->option_name),
            $checked
        );
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
 
    // Helper function to render a text field
    public function render_text_field($args) {
        $options = get_option($this->option_name);
        $value = $options[$args['name']] ?? $args['default'];
        printf(
            '<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text">',
            esc_attr($args['name']),
            esc_attr($this->option_name),
            esc_attr($value)
        );
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    public function render_password_field($args) {
        $options = get_option($this->option_name);
        $value = $options[$args['name']] ?? $args['default'];
        printf(
            '<input type="password" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text">',
            esc_attr($args['name']),
            esc_attr($this->option_name),
            esc_attr($value)
        );
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
}
