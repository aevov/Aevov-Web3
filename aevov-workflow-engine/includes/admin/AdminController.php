<?php

namespace AevovWorkflowEngine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class AdminController {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_menu(): void {
        // Main menu
        add_menu_page(
            __('Workflow Engine', 'aevov-workflow-engine'),
            __('Workflow Engine', 'aevov-workflow-engine'),
            'edit_posts',
            'aevov-workflow-engine',
            [$this, 'render_workflow_builder'],
            'dashicons-networking',
            30
        );

        // Workflow Builder (same as main)
        add_submenu_page(
            'aevov-workflow-engine',
            __('Workflow Builder', 'aevov-workflow-engine'),
            __('Builder', 'aevov-workflow-engine'),
            'edit_posts',
            'aevov-workflow-engine',
            [$this, 'render_workflow_builder']
        );

        // My Workflows
        add_submenu_page(
            'aevov-workflow-engine',
            __('My Workflows', 'aevov-workflow-engine'),
            __('My Workflows', 'aevov-workflow-engine'),
            'edit_posts',
            'aevov-workflows',
            [$this, 'render_workflows_list']
        );

        // Execution History
        add_submenu_page(
            'aevov-workflow-engine',
            __('Execution History', 'aevov-workflow-engine'),
            __('History', 'aevov-workflow-engine'),
            'edit_posts',
            'aevov-workflow-history',
            [$this, 'render_history']
        );

        // Templates
        add_submenu_page(
            'aevov-workflow-engine',
            __('Templates', 'aevov-workflow-engine'),
            __('Templates', 'aevov-workflow-engine'),
            'edit_posts',
            'aevov-workflow-templates',
            [$this, 'render_templates']
        );

        // Settings
        add_submenu_page(
            'aevov-workflow-engine',
            __('Settings', 'aevov-workflow-engine'),
            __('Settings', 'aevov-workflow-engine'),
            'manage_options',
            'aevov-workflow-settings',
            [$this, 'render_settings']
        );
    }

    public function enqueue_assets(string $hook): void {
        // Only load on our pages
        if (strpos($hook, 'aevov-workflow') === false) {
            return;
        }

        $asset_url = AEVOV_WORKFLOW_ENGINE_URL . 'build/';
        $asset_path = AEVOV_WORKFLOW_ENGINE_PATH . 'build/';

        // Check if built assets exist
        if (file_exists($asset_path . 'index.js')) {
            wp_enqueue_script(
                'aevov-workflow-engine',
                $asset_url . 'index.js',
                ['wp-element', 'wp-components', 'wp-api-fetch'],
                AEVOV_WORKFLOW_ENGINE_VERSION,
                true
            );

            wp_enqueue_style(
                'aevov-workflow-engine',
                $asset_url . 'index.css',
                ['wp-components'],
                AEVOV_WORKFLOW_ENGINE_VERSION
            );
        } else {
            // Development mode - load from dev server or fallback
            $this->enqueue_dev_assets();
        }

        // Localize script data
        wp_localize_script('aevov-workflow-engine', 'aevovWorkflowEngine', [
            'apiUrl' => rest_url('aevov-workflow/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'userName' => wp_get_current_user()->display_name,
            'isAdmin' => current_user_can('manage_options'),
            'settings' => [
                'maxExecutionTime' => get_option('aevov_workflow_max_execution_time', 300),
                'maxNodes' => get_option('aevov_workflow_max_nodes', 100),
            ],
            'strings' => [
                'save' => __('Save', 'aevov-workflow-engine'),
                'run' => __('Run', 'aevov-workflow-engine'),
                'newWorkflow' => __('New Workflow', 'aevov-workflow-engine'),
                'deleteConfirm' => __('Are you sure you want to delete this workflow?', 'aevov-workflow-engine'),
            ],
        ]);
    }

    private function enqueue_dev_assets(): void {
        // Fallback CSS for development
        wp_enqueue_style(
            'aevov-workflow-engine-dev',
            AEVOV_WORKFLOW_ENGINE_URL . 'assets/css/admin.css',
            [],
            AEVOV_WORKFLOW_ENGINE_VERSION
        );

        // Inline script to show development notice
        wp_add_inline_script('wp-element', '
            console.info("Aevov Workflow Engine: Running in development mode. Run npm run build to create production assets.");
        ');
    }

    public function register_settings(): void {
        register_setting('aevov_workflow_settings', 'aevov_workflow_max_execution_time', [
            'type' => 'integer',
            'default' => 300,
            'sanitize_callback' => 'absint',
        ]);

        register_setting('aevov_workflow_settings', 'aevov_workflow_max_nodes', [
            'type' => 'integer',
            'default' => 100,
            'sanitize_callback' => 'absint',
        ]);

        register_setting('aevov_workflow_settings', 'aevov_workflow_enable_scheduling', [
            'type' => 'boolean',
            'default' => true,
        ]);

        register_setting('aevov_workflow_settings', 'aevov_workflow_enable_templates', [
            'type' => 'boolean',
            'default' => true,
        ]);

        add_settings_section(
            'aevov_workflow_general',
            __('General Settings', 'aevov-workflow-engine'),
            null,
            'aevov_workflow_settings'
        );

        add_settings_field(
            'aevov_workflow_max_execution_time',
            __('Max Execution Time (seconds)', 'aevov-workflow-engine'),
            [$this, 'render_number_field'],
            'aevov_workflow_settings',
            'aevov_workflow_general',
            ['name' => 'aevov_workflow_max_execution_time', 'min' => 10, 'max' => 3600]
        );

        add_settings_field(
            'aevov_workflow_max_nodes',
            __('Max Nodes Per Workflow', 'aevov-workflow-engine'),
            [$this, 'render_number_field'],
            'aevov_workflow_settings',
            'aevov_workflow_general',
            ['name' => 'aevov_workflow_max_nodes', 'min' => 10, 'max' => 500]
        );
    }

    public function render_number_field(array $args): void {
        $value = get_option($args['name'], 0);
        printf(
            '<input type="number" name="%s" value="%d" min="%d" max="%d" class="regular-text">',
            esc_attr($args['name']),
            esc_attr($value),
            esc_attr($args['min'] ?? 0),
            esc_attr($args['max'] ?? 9999)
        );
    }

    public function render_workflow_builder(): void {
        $workflow_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : null;
        ?>
        <div class="wrap aevov-workflow-wrap">
            <div id="aevov-workflow-builder" data-workflow-id="<?php echo esc_attr($workflow_id); ?>">
                <div class="aevov-loading">
                    <div class="aevov-loading-spinner"></div>
                    <p><?php esc_html_e('Loading Workflow Engine...', 'aevov-workflow-engine'); ?></p>
                </div>
            </div>
        </div>
        <?php
        $this->render_fallback_ui();
    }

    public function render_workflows_list(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflows';

        $workflows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, description, is_published, version, updated_at
             FROM {$table}
             WHERE is_template = 0 AND (user_id = %d OR %d = 1)
             ORDER BY updated_at DESC",
            get_current_user_id(),
            current_user_can('manage_options') ? 1 : 0
        ));
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('My Workflows', 'aevov-workflow-engine'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=aevov-workflow-engine')); ?>" class="page-title-action">
                <?php esc_html_e('Add New', 'aevov-workflow-engine'); ?>
            </a>
            <hr class="wp-header-end">

            <?php if (empty($workflows)): ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e('No workflows yet. Create your first workflow!', 'aevov-workflow-engine'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'aevov-workflow-engine'); ?></th>
                            <th><?php esc_html_e('Description', 'aevov-workflow-engine'); ?></th>
                            <th><?php esc_html_e('Version', 'aevov-workflow-engine'); ?></th>
                            <th><?php esc_html_e('Status', 'aevov-workflow-engine'); ?></th>
                            <th><?php esc_html_e('Last Modified', 'aevov-workflow-engine'); ?></th>
                            <th><?php esc_html_e('Actions', 'aevov-workflow-engine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workflows as $workflow): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=aevov-workflow-engine&id=' . $workflow->id)); ?>">
                                            <?php echo esc_html($workflow->name); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html(wp_trim_words($workflow->description, 10)); ?></td>
                                <td>v<?php echo esc_html($workflow->version); ?></td>
                                <td>
                                    <?php if ($workflow->is_published): ?>
                                        <span class="status-published"><?php esc_html_e('Published', 'aevov-workflow-engine'); ?></span>
                                    <?php else: ?>
                                        <span class="status-draft"><?php esc_html_e('Draft', 'aevov-workflow-engine'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(human_time_diff(strtotime($workflow->updated_at))); ?> ago</td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=aevov-workflow-engine&id=' . $workflow->id)); ?>">
                                        <?php esc_html_e('Edit', 'aevov-workflow-engine'); ?>
                                    </a> |
                                    <a href="#" class="aevov-run-workflow" data-id="<?php echo esc_attr($workflow->id); ?>">
                                        <?php esc_html_e('Run', 'aevov-workflow-engine'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_history(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflow_executions';
        $workflows_table = $wpdb->prefix . 'aevov_workflows';

        $executions = $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, w.name as workflow_name
             FROM {$table} e
             LEFT JOIN {$workflows_table} w ON e.workflow_id = w.id
             WHERE e.user_id = %d OR %d = 1
             ORDER BY e.created_at DESC
             LIMIT 100",
            get_current_user_id(),
            current_user_can('manage_options') ? 1 : 0
        ));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Execution History', 'aevov-workflow-engine'); ?></h1>

            <?php if (empty($executions)): ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e('No workflow executions yet.', 'aevov-workflow-engine'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'aevov-workflow-engine'); ?></th>
                            <th><?php esc_html_e('Workflow', 'aevov-workflow-engine'); ?></th>
                            <th><?php esc_html_e('Status', 'aevov-workflow-engine'); ?></th>
                            <th><?php esc_html_e('Started', 'aevov-workflow-engine'); ?></th>
                            <th><?php esc_html_e('Duration', 'aevov-workflow-engine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($executions as $exec): ?>
                            <tr>
                                <td><?php echo esc_html($exec->id); ?></td>
                                <td><?php echo esc_html($exec->workflow_name ?: 'Ad-hoc'); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($exec->status); ?>">
                                        <?php echo esc_html(ucfirst($exec->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($exec->started_at ?: $exec->created_at); ?></td>
                                <td>
                                    <?php
                                    if ($exec->started_at && $exec->completed_at) {
                                        $start = strtotime($exec->started_at);
                                        $end = strtotime($exec->completed_at);
                                        echo esc_html(($end - $start) . 's');
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_templates(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aevov_workflows';

        $templates = $wpdb->get_results(
            "SELECT id, name, description FROM {$table} WHERE is_template = 1 ORDER BY name ASC"
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Workflow Templates', 'aevov-workflow-engine'); ?></h1>

            <div class="aevov-templates-grid">
                <?php if (empty($templates)): ?>
                    <p><?php esc_html_e('No templates available yet.', 'aevov-workflow-engine'); ?></p>
                <?php else: ?>
                    <?php foreach ($templates as $template): ?>
                        <div class="aevov-template-card">
                            <h3><?php echo esc_html($template->name); ?></h3>
                            <p><?php echo esc_html($template->description); ?></p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=aevov-workflow-engine&template=' . $template->id)); ?>" class="button">
                                <?php esc_html_e('Use Template', 'aevov-workflow-engine'); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_settings(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Workflow Engine Settings', 'aevov-workflow-engine'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('aevov_workflow_settings');
                do_settings_sections('aevov_workflow_settings');
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e('API Information', 'aevov-workflow-engine'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('API Endpoint', 'aevov-workflow-engine'); ?></th>
                    <td><code><?php echo esc_html(rest_url('aevov-workflow/v1')); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Health Check', 'aevov-workflow-engine'); ?></th>
                    <td><code><?php echo esc_html(rest_url('aevov-workflow/v1/health')); ?></code></td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function render_fallback_ui(): void {
        ?>
        <style>
            .aevov-workflow-wrap {
                margin: 0;
                padding: 0;
            }
            #aevov-workflow-builder {
                min-height: calc(100vh - 32px);
                background: #1a1a2e;
            }
            .aevov-loading {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 80vh;
                color: #fff;
            }
            .aevov-loading-spinner {
                width: 48px;
                height: 48px;
                border: 4px solid rgba(255,255,255,0.2);
                border-top-color: #0ea5e9;
                border-radius: 50%;
                animation: aevov-spin 1s linear infinite;
            }
            @keyframes aevov-spin {
                to { transform: rotate(360deg); }
            }
            .status-completed, .status-published { color: #22c55e; }
            .status-failed { color: #ef4444; }
            .status-running, .status-pending { color: #f59e0b; }
            .status-draft { color: #64748b; }
            .aevov-templates-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .aevov-template-card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
            }
            .aevov-template-card h3 {
                margin-top: 0;
            }
        </style>
        <?php
    }
}
