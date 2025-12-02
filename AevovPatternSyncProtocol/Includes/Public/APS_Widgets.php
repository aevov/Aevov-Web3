<?php


/**
 * includes/public/class-aps-widgets.php
 */
class APS_Widgets {
    public function register() {
        register_widget('APS_Comparison_Widget');
        register_widget('APS_Pattern_Display_Widget');
        register_widget('APS_Recent_Comparisons_Widget');
    }
}

/**
 * Comparison Widget Class
 */
class APS_Comparison_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'aps_comparison_widget',
            __('APS Pattern Comparison', 'aps'),
            ['description' => __('Display pattern comparisons', 'aps')]
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . 
                 apply_filters('widget_title', $instance['title']) . 
                 $args['after_title'];
        }

        $shortcode = '[aps_compare ';
        $shortcode .= 'items="' . esc_attr($instance['items']) . '" ';
        $shortcode .= 'engine="' . esc_attr($instance['engine']) . '" ';
        $shortcode .= 'template="widget"]';

        echo do_shortcode($shortcode);
        
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : '';
        $items = isset($instance['items']) ? $instance['items'] : '';
        $engine = isset($instance['engine']) ? $instance['engine'] : 'auto';
        
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">
                <?php _e('Title:', 'aps'); ?>
            </label>
            <input class="widefat" 
                   id="<?php echo $this->get_field_id('title'); ?>" 
                   name="<?php echo $this->get_field_name('title'); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('items'); ?>">
                <?php _e('Items to Compare (comma-separated):', 'aps'); ?>
            </label>
            <input class="widefat" 
                   id="<?php echo $this->get_field_id('items'); ?>" 
                   name="<?php echo $this->get_field_name('items'); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($items); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('engine'); ?>">
                <?php _e('Comparison Engine:', 'aps'); ?>
            </label>
            <select class="widefat" 
                    id="<?php echo $this->get_field_id('engine'); ?>" 
                    name="<?php echo $this->get_field_name('engine'); ?>">
                <option value="auto" <?php selected($engine, 'auto'); ?>>
                    <?php _e('Auto', 'aps'); ?>
                </option>
                <option value="pattern" <?php selected($engine, 'pattern'); ?>>
                    <?php _e('Pattern', 'aps'); ?>
                </option>
                <option value="tensor" <?php selected($engine, 'tensor'); ?>>
                    <?php _e('Tensor', 'aps'); ?>
                </option>
                <option value="hybrid" <?php selected($engine, 'hybrid'); ?>>
                    <?php _e('Hybrid', 'aps'); ?>
                </option>
            </select>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) 
            ? strip_tags($new_instance['title']) 
            : '';
        $instance['items'] = (!empty($new_instance['items'])) 
            ? strip_tags($new_instance['items']) 
            : '';
        $instance['engine'] = (!empty($new_instance['engine'])) 
            ? strip_tags($new_instance['engine']) 
            : 'auto';

        return $instance;
    }
}