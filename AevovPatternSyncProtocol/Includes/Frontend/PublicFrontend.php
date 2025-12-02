<?php

namespace APS\Frontend;

class PublicFrontend {
    private $plugin_name;
    private $version;

    public function __construct() {
        $this->plugin_name = 'aps';
        $this->version = APS_VERSION;
        $this->init_hooks();
    }

    private function init_hooks() {
        if (function_exists('add_action')) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
            add_action('init', [$this, 'register_shortcodes']);
        }
    }

    public function enqueue_styles() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'aps_pattern' ) || has_shortcode( $post->post_content, 'aps_comparison' ) ) ) {
            if (function_exists('wp_enqueue_style')) {
                wp_enqueue_style(
                    $this->plugin_name,
                    APS_ASSETS . 'css/aps-public.css',
                    [],
                    $this->version,
                    'all'
                );
            }
        }
    }

    public function enqueue_scripts() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'aps_pattern' ) || has_shortcode( $post->post_content, 'aps_comparison' ) ) ) {
            if (function_exists('wp_enqueue_script')) {
                wp_enqueue_script(
                    $this->plugin_name,
                    APS_ASSETS . 'js/aps-public.js',
                    ['jquery'],
                    $this->version,
                    true
                );
            }
        }
    }

    public function register_shortcodes() {
        if (function_exists('add_shortcode')) {
            add_shortcode('aps_pattern', [$this, 'render_pattern_shortcode']);
            add_shortcode('aps_comparison', [$this, 'render_comparison_shortcode']);
        }
    }

    public function render_pattern_shortcode($atts) {
        $atts = function_exists('shortcode_atts') ? shortcode_atts([
            'id' => '',
            'type' => 'default'
        ], $atts) : $atts;

        ob_start();
        include APS_PATH . 'templates/pattern.php';
        return ob_get_clean();
    }

    public function render_comparison_shortcode($atts) {
        $atts = function_exists('shortcode_atts') ? shortcode_atts([
            'ids' => '',
            'type' => 'default'
        ], $atts) : $atts;

        ob_start();
        include APS_PATH . 'templates/comparison.php';
        return ob_get_clean();
    }
}