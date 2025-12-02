<?php
/**
 * includes/public/class-aps-public.php
 */
class APS_Public {
    private $shortcodes;
    private $widgets;
    private $comparison_handler;
    
    public function __construct() {
        $this->shortcodes = new APS_Shortcodes();
        $this->widgets = new APS_Widgets();
        $this->comparison_handler = new APS_Comparator();
        
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('init', [$this, 'register_shortcodes']);
        add_action('widgets_init', [$this, 'register_widgets']);
        add_action('wp_ajax_aps_fetch_comparison', [$this, 'ajax_fetch_comparison']);
        add_action('wp_ajax_nopriv_aps_fetch_comparison', [$this, 'ajax_fetch_comparison']);
    }

    public function enqueue_styles() {
        wp_enqueue_style(
            'aps-public',
            APS_URL . 'assets/css/aps-public.css',
            [],
            APS_VERSION
        );
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'aps-public',
            APS_URL . 'assets/js/aps-public.js',
            ['jquery'],
            APS_VERSION,
            true
        );

        wp_localize_script('aps-public', 'apsPublic', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aps-public-nonce'),
            'i18n' => [
                'loading' => __('Loading comparison...', 'aps'),
                'error' => __('Error loading comparison', 'aps'),
                'noResults' => __('No comparison results found', 'aps')
            ]
        ]);
    }

    public function register_shortcodes() {
        $this->shortcodes->register();
    }

    public function register_widgets() {
        $this->widgets->register();
    }

    public function ajax_fetch_comparison() {
        check_ajax_referer('aps-public-nonce', 'nonce');

        $comparison_id = isset($_POST['comparison_id']) ? absint($_POST['comparison_id']) : 0;
        
        if (!$comparison_id) {
            wp_send_json_error(['message' => 'Invalid comparison ID']);
        }

        try {
            $comparison = $this->comparison_handler->get_comparison($comparison_id);
            wp_send_json_success($comparison);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}