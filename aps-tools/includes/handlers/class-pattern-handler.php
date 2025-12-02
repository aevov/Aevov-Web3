<?php
// File: aps-tools/includes/handlers/class-pattern-handler.php
namespace APSTools\Handlers;

class PatternHandler {
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Only add WordPress hooks if WordPress is loaded
        if (function_exists('add_action')) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('wp_ajax_aps_generate_patterns', [$this, 'handle_pattern_generation']);
        }
    }

    public function enqueue_scripts($hook) {
        if (!in_array($hook, ['toplevel_page_aps-stored-chunks', 'pattern-system_page_aps-stored-chunks'])) {
            return;
        }

        wp_enqueue_script(
            'aps-pattern-handler',
            APSTOOLS_URL . 'assets/js/pattern-handler.js',
            ['jquery'],
            APSTOOLS_VERSION,
            true
        );

        wp_localize_script('aps-pattern-handler', 'apsPatterns', [
            'nonce' => wp_create_nonce('aps-patterns-nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiUrl' => rest_url('aps/v1/patterns')
        ]);
    }

    public function handle_pattern_generation() {
        check_ajax_referer('aps-patterns-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $chunk_ids = isset($_POST['chunk_ids']) ? array_map('intval', $_POST['chunk_ids']) : [];
        if (empty($chunk_ids)) {
            wp_send_json_error('No chunks selected');
        }

        // Call the Aevov Pattern Sync-protocol API
        $response = wp_remote_post(rest_url('aps/v1/patterns/generate'), [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WP-Nonce' => wp_create_nonce('wp_rest')
            ],
            'body' => wp_json_encode([
                'chunk_ids' => $chunk_ids
            ])
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['success'])) {
            wp_send_json_success($body['patterns']);
        } else {
            wp_send_json_error($body['message'] ?? 'Pattern generation failed');
        }
    }

    public function render_pattern_controls() {
        ?>
        <div class="pattern-generation-controls">
            <button type="button" class="button button-primary" id="generate-patterns">
                <?php _e('Generate Patterns from Selected', 'aps-tools'); ?>
            </button>
            <div id="pattern-generation-status"></div>
        </div>
        <?php
    }
}