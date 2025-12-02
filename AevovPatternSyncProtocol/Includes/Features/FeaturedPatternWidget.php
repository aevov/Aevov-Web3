<?php

namespace Aevov\Features;

class FeaturedPatternWidget extends \WP_Widget
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(
            'featured_pattern_widget',
            'Featured Pattern',
            ['description' => __('Displays the featured pattern.', 'aps')]
        );
    }

    /**
     * Renders the widget.
     *
     * @param array $args
     * @param array $instance
     */
    public function widget($args, $instance)
    {
        $featured_pattern_id = get_option('featured_pattern');

        if (!$featured_pattern_id) {
            return;
        }

        $pattern = get_post($featured_pattern_id);

        if (!$pattern) {
            return;
        }

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html($pattern->post_title) . $args['after_title'];
        echo '<p>' . esc_html($pattern->post_content) . '</p>';
        echo $args['after_widget'];
    }
}
