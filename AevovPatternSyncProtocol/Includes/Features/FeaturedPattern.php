<?php

namespace Aevov\Features;

class FeaturedPattern
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_meta_box']);
        add_action('widgets_init', [$this, 'register_widget']);
    }

    /**
     * Registers the widget.
     */
    public function register_widget()
    {
        require_once __DIR__ . '/FeaturedPatternWidget.php';
        register_widget('\Aevov\Features\FeaturedPatternWidget');
    }

    /**
     * Adds the meta box.
     */
    public function add_meta_box()
    {
        add_meta_box(
            'featured_pattern',
            'Featured Pattern',
            [$this, 'render_meta_box'],
            'aps_pattern',
            'side'
        );
    }

    /**
     * Renders the meta box.
     *
     * @param \WP_Post $post
     */
    public function render_meta_box(\WP_Post $post)
    {
        $featured_pattern = get_option('featured_pattern');
        wp_nonce_field('featured_pattern_nonce', 'featured_pattern_nonce');
        ?>
        <p>
            <label for="featured_pattern">
                <input type="checkbox" name="featured_pattern" id="featured_pattern" value="1" <?php checked($featured_pattern, $post->ID); ?>>
                <?php _e('Feature this pattern', 'aps'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Saves the meta box.
     *
     * @param int $post_id
     */
    public function save_meta_box(int $post_id)
    {
        if (!isset($_POST['featured_pattern_nonce']) || !wp_verify_nonce($_POST['featured_pattern_nonce'], 'featured_pattern_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['featured_pattern'])) {
            update_option('featured_pattern', $post_id);
        } else {
            delete_option('featured_pattern');
        }
    }
}
