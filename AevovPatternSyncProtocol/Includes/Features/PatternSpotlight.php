<?php

namespace Aevov\Features;

class PatternSpotlight
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_meta_box']);
    }

    /**
     * Registers the post type.
     */
    public function register_post_type()
    {
        register_post_type('aps_spotlight', [
            'labels' => [
                'name' => __('Pattern Spotlights', 'aps'),
                'singular_name' => __('Pattern Spotlight', 'aps'),
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
        ]);
    }

    /**
     * Adds the meta box.
     */
    public function add_meta_box()
    {
        add_meta_box(
            'pattern_spotlight',
            'Pattern Spotlight',
            [$this, 'render_meta_box'],
            'aps_spotlight',
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
        $pattern_id = get_post_meta($post->ID, 'pattern_id', true);
        wp_nonce_field('pattern_spotlight_nonce', 'pattern_spotlight_nonce');
        ?>
        <p>
            <label for="pattern_id"><?php _e('Pattern', 'aps'); ?></label>
            <br>
            <select name="pattern_id" id="pattern_id">
                <option value=""><?php _e('Select a pattern', 'aps'); ?></option>
                <?php
                $patterns = $this->get_patterns();
                foreach ($patterns as $pattern) {
                    ?>
                    <option value="<?php echo esc_attr($pattern->ID); ?>" <?php selected($pattern_id, $pattern->ID); ?>>
                        <?php echo esc_html($pattern->post_title); ?>
                    </option>
                    <?php
                }
                ?>
            </select>
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
        if (!isset($_POST['pattern_spotlight_nonce']) || !wp_verify_nonce($_POST['pattern_spotlight_nonce'], 'pattern_spotlight_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['pattern_id'])) {
            update_post_meta($post_id, 'pattern_id', absint($_POST['pattern_id']));
        }
    }

    /**
     * Gets the patterns.
     *
     * @return array
     */
    private function get_patterns()
    {
        $patterns = get_posts([
            'post_type' => 'aps_pattern',
            'posts_per_page' => -1,
        ]);

        return $patterns;
    }
}
