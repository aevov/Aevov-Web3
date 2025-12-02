<?php

namespace AevovStream;

/**
 * Edge Side Includes (ESI) Handler for Aevov Stream
 *
 * Implements ESI rendering for dynamic content caching with
 * LiteSpeed Cache and other edge caching systems.
 */
class ESI {

    private $block_registry = [];
    private $cache_ttl = 3600; // Default 1 hour
    private $esi_enabled = true;

    /**
     * Initialize ESI handler
     */
    public function __construct() {
        $this->esi_enabled = $this->is_esi_supported();
        $this->register_default_blocks();

        // Register hooks
        add_action('init', [$this, 'init_esi_support']);
        add_filter('aevov_stream_esi_output', [$this, 'process_esi_tags'], 10, 2);
    }

    /**
     * Initialize ESI support
     */
    public function init_esi_support() {
        // Check if LiteSpeed Cache is available
        if (defined('LSCWP_V')) {
            add_action('litespeed_init', [$this, 'register_litespeed_esi']);
        }

        // Add ESI header if supported
        if ($this->esi_enabled && !is_admin()) {
            header('Surrogate-Control: content="ESI/1.0"');
        }
    }

    /**
     * Render an ESI block
     *
     * @param string $block_name Block identifier.
     * @param array $atts Block attributes.
     * @param bool $inline Render inline or return ESI tag.
     * @return string ESI tag or rendered content.
     */
    public function render_esi_block( $block_name, $atts = [], $inline = false ) {
        // Validate block name
        if (!$this->is_block_registered($block_name)) {
            error_log("ESI block not registered: {$block_name}");
            return "<!-- ESI Error: Block '{$block_name}' not registered -->";
        }

        // If ESI is disabled or inline requested, render directly
        if (!$this->esi_enabled || $inline || is_admin()) {
            return $this->render_block_content($block_name, $atts);
        }

        // Generate ESI tag
        $esi_url = $this->generate_esi_url($block_name, $atts);
        $ttl = $this->get_block_ttl($block_name);

        // Return ESI include tag
        $esi_tag = sprintf(
            '<esi:include src="%s" maxage="%d" onerror="continue"/>',
            esc_url($esi_url),
            $ttl
        );

        // Add HTML comment for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $esi_tag = "<!-- ESI Block: {$block_name} -->\n{$esi_tag}\n<!-- /ESI Block -->";
        }

        return $esi_tag;
    }

    /**
     * Render block content directly
     *
     * @param string $block_name Block name.
     * @param array $atts Block attributes.
     * @return string Rendered content.
     */
    public function render_block_content( $block_name, $atts = [] ) {
        if (!isset($this->block_registry[$block_name])) {
            return '';
        }

        $block = $this->block_registry[$block_name];

        // Call the render callback
        if (is_callable($block['callback'])) {
            $content = call_user_func($block['callback'], $atts);
        } else {
            $content = $this->get_default_block_content($block_name, $atts);
        }

        // Wrap in container if needed
        if ($block['wrap'] ?? true) {
            $content = sprintf(
                '<div class="aevov-esi-block" data-block="%s">%s</div>',
                esc_attr($block_name),
                $content
            );
        }

        return $content;
    }

    /**
     * Register an ESI block
     *
     * @param string $block_name Block identifier.
     * @param array $args Block configuration.
     * @return bool Success status.
     */
    public function register_block( $block_name, $args = [] ) {
        $defaults = [
            'callback' => null,
            'ttl' => $this->cache_ttl,
            'wrap' => true,
            'public' => false,
            'vary' => []
        ];

        $this->block_registry[$block_name] = array_merge($defaults, $args);

        return true;
    }

    /**
     * Unregister an ESI block
     *
     * @param string $block_name Block identifier.
     * @return bool Success status.
     */
    public function unregister_block( $block_name ) {
        if (isset($this->block_registry[$block_name])) {
            unset($this->block_registry[$block_name]);
            return true;
        }

        return false;
    }

    /**
     * Check if block is registered
     *
     * @param string $block_name Block name.
     * @return bool Registered status.
     */
    public function is_block_registered( $block_name ) {
        return isset($this->block_registry[$block_name]);
    }

    /**
     * Register default ESI blocks
     */
    private function register_default_blocks() {
        // User info block
        $this->register_block('user_info', [
            'callback' => [$this, 'render_user_info_block'],
            'ttl' => 300, // 5 minutes
            'public' => false,
            'vary' => ['user_id']
        ]);

        // Cart block
        $this->register_block('cart', [
            'callback' => [$this, 'render_cart_block'],
            'ttl' => 60, // 1 minute
            'public' => false,
            'vary' => ['session_id']
        ]);

        // Dynamic content block
        $this->register_block('dynamic_content', [
            'callback' => [$this, 'render_dynamic_content_block'],
            'ttl' => 600, // 10 minutes
            'public' => true
        ]);

        // Recent posts block
        $this->register_block('recent_posts', [
            'callback' => [$this, 'render_recent_posts_block'],
            'ttl' => 3600, // 1 hour
            'public' => true
        ]);

        // Video player block
        $this->register_block('video_player', [
            'callback' => [$this, 'render_video_player_block'],
            'ttl' => 7200, // 2 hours
            'public' => true
        ]);
    }

    /**
     * Generate ESI URL for a block
     *
     * @param string $block_name Block name.
     * @param array $atts Attributes.
     * @return string ESI URL.
     */
    private function generate_esi_url( $block_name, $atts ) {
        $base_url = rest_url('aevov-stream/v1/esi/' . $block_name);

        // Add attributes as query parameters
        if (!empty($atts)) {
            $base_url = add_query_arg($atts, $base_url);
        }

        // Add nonce for non-public blocks
        $block = $this->block_registry[$block_name];
        if (!($block['public'] ?? false)) {
            $base_url = add_query_arg('_wpnonce', wp_create_nonce('esi_' . $block_name), $base_url);
        }

        return $base_url;
    }

    /**
     * Get block cache TTL
     *
     * @param string $block_name Block name.
     * @return int TTL in seconds.
     */
    private function get_block_ttl( $block_name ) {
        return $this->block_registry[$block_name]['ttl'] ?? $this->cache_ttl;
    }

    /**
     * Check if ESI is supported
     *
     * @return bool Support status.
     */
    private function is_esi_supported() {
        // Check for LiteSpeed
        if (defined('LSCWP_V') || isset($_SERVER['HTTP_X_LSCACHE'])) {
            return true;
        }

        // Check for Varnish
        if (isset($_SERVER['HTTP_X_VARNISH'])) {
            return true;
        }

        // Check for Cloudflare or other edge caches
        if (isset($_SERVER['HTTP_CF_RAY'])) {
            return true;
        }

        return false;
    }

    /**
     * Process ESI tags in content
     *
     * @param string $content Content with ESI tags.
     * @param bool $force Force processing.
     * @return string Processed content.
     */
    public function process_esi_tags( $content, $force = false ) {
        if (!$force && $this->esi_enabled) {
            // Let edge cache handle ESI processing
            return $content;
        }

        // Manual ESI processing (for testing or when ESI not available)
        $pattern = '/<esi:include\s+src="([^"]+)"[^>]*>/';

        return preg_replace_callback($pattern, function($matches) {
            $url = $matches[1];

            // Parse URL to get block name and attributes
            $url_parts = parse_url($url);
            $path_parts = explode('/', trim($url_parts['path'], '/'));
            $block_name = end($path_parts);

            parse_str($url_parts['query'] ?? '', $atts);

            // Render block inline
            return $this->render_block_content($block_name, $atts);
        }, $content);
    }

    /**
     * Register with LiteSpeed Cache
     */
    public function register_litespeed_esi() {
        if (!defined('LSCWP_V')) {
            return;
        }

        // Register ESI blocks with LiteSpeed
        foreach ($this->block_registry as $block_name => $block) {
            do_action('litespeed_esi_load-' . $block_name);
        }
    }

    /**
     * Default block renderers
     */

    public function render_user_info_block( $atts ) {
        if (!is_user_logged_in()) {
            return '<div class="user-info">Guest</div>';
        }

        $current_user = wp_get_current_user();
        return sprintf(
            '<div class="user-info">Welcome, %s</div>',
            esc_html($current_user->display_name)
        );
    }

    public function render_cart_block( $atts ) {
        // Placeholder for cart rendering
        $cart_count = 0; // Would integrate with WooCommerce or other cart

        return sprintf(
            '<div class="cart-block">Cart: <span class="cart-count">%d</span> items</div>',
            $cart_count
        );
    }

    public function render_dynamic_content_block( $atts ) {
        $content_id = $atts['content_id'] ?? '';

        if (empty($content_id)) {
            return '';
        }

        // Fetch dynamic content
        $content = get_option('aevov_dynamic_content_' . $content_id, '');

        return sprintf(
            '<div class="dynamic-content">%s</div>',
            wp_kses_post($content)
        );
    }

    public function render_recent_posts_block( $atts ) {
        $count = $atts['count'] ?? 5;

        $posts = get_posts([
            'numberposts' => $count,
            'post_status' => 'publish'
        ]);

        if (empty($posts)) {
            return '<div class="recent-posts">No recent posts</div>';
        }

        $output = '<ul class="recent-posts-list">';
        foreach ($posts as $post) {
            $output .= sprintf(
                '<li><a href="%s">%s</a></li>',
                get_permalink($post),
                esc_html($post->post_title)
            );
        }
        $output .= '</ul>';

        return $output;
    }

    public function render_video_player_block( $atts ) {
        $video_id = $atts['video_id'] ?? '';
        $video_url = $atts['video_url'] ?? '';

        if (empty($video_id) && empty($video_url)) {
            return '<!-- No video specified -->';
        }

        // Render video player
        return sprintf(
            '<div class="aevov-video-player" data-video-id="%s">
                <video controls>
                    <source src="%s" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>',
            esc_attr($video_id),
            esc_url($video_url)
        );
    }

    /**
     * Get default block content
     *
     * @param string $block_name Block name.
     * @param array $atts Attributes.
     * @return string Default content.
     */
    private function get_default_block_content( $block_name, $atts ) {
        return sprintf(
            '<div class="esi-block-placeholder">ESI Block: %s</div>',
            esc_html($block_name)
        );
    }

    /**
     * Get all registered blocks
     *
     * @return array Blocks.
     */
    public function get_registered_blocks() {
        return $this->block_registry;
    }
}
