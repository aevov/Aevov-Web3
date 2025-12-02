<?php

/**
 * includes/public/class-aps-shortcodes.php
 */
class APS_Shortcodes {
    private $comparison_handler;
    private $cache;
    
    public function __construct() {
        $this->comparison_handler = new APS_Comparator();
        $this->cache = new APS_Cache();
    }

    public function register() {
        add_shortcode('aps_compare', [$this, 'render_comparison']);
        add_shortcode('aps_pattern_view', [$this, 'render_pattern_view']);
        add_shortcode('aps_results', [$this, 'render_results']);
        add_shortcode('aps_comparison_form', [$this, 'render_comparison_form']);
    }

    public function render_comparison($atts) {
        $atts = shortcode_atts([
            'items' => '',
            'engine' => 'auto',
            'template' => 'default',
            'cache' => true
        ], $atts);

        // Parse items
        $items = array_filter(array_map('trim', explode(',', $atts['items'])));
        
        if (empty($items)) {
            return '<div class="aps-error">' . 
                   __('No items specified for comparison', 'aps') . 
                   '</div>';
        }

        try {
            $cache_key = 'comparison_' . md5(serialize($atts));
            
            if ($atts['cache'] && ($cached = $this->cache->get($cache_key))) {
                return $cached;
            }

            $comparison = $this->comparison_handler->compare_patterns($items, [
                'engine' => $atts['engine']
            ]);

            $output = $this->render_template(
                'comparison/' . $atts['template'],
                ['comparison' => $comparison]
            );

            if ($atts['cache']) {
                $this->cache->set($cache_key, $output);
            }

            return $output;

        } catch (Exception $e) {
            return '<div class="aps-error">' . 
                   esc_html($e->getMessage()) . 
                   '</div>';
        }
    }

    public function render_pattern_view($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'template' => 'default',
            'show_details' => true
        ], $atts);

        if (empty($atts['id'])) {
            return '<div class="aps-error">' . 
                   __('No pattern ID specified', 'aps') . 
                   '</div>';
        }

        try {
            $pattern = $this->comparison_handler->get_pattern($atts['id']);
            
            return $this->render_template(
                'shortcodes/pattern-view',
                [
                    'pattern' => $pattern,
                    'show_details' => $atts['show_details']
                ]
            );

        } catch (Exception $e) {
            return '<div class="aps-error">' . 
                   esc_html($e->getMessage()) . 
                   '</div>';
        }
    }

    public function render_results($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'template' => 'default',
            'limit' => 10
        ], $atts);

        if (empty($atts['id'])) {
            return '<div class="aps-error">' . 
                   __('No comparison ID specified', 'aps') . 
                   '</div>';
        }

        try {
            $results = $this->comparison_handler->get_comparison_results(
                $atts['id'],
                $atts['limit']
            );
            
            return $this->render_template(
                'shortcodes/results-display',
                [
                    'results' => $results,
                    'comparison_id' => $atts['id']
                ]
            );

        } catch (Exception $e) {
            return '<div class="aps-error">' . 
                   esc_html($e->getMessage()) . 
                   '</div>';
        }
    }

    public function render_comparison_form($atts) {
        $atts = shortcode_atts([
            'template' => 'default',
            'engine' => 'auto',
            'max_items' => 5
        ], $atts);

        return $this->render_template(
            'shortcodes/comparison-form',
            [
                'engine' => $atts['engine'],
                'max_items' => $atts['max_items']
            ]
        );
    }

    private function render_template($template, $data) {
        $template_path = APS_PATH . 'templates/' . $template . '.php';
        
        if (!file_exists($template_path)) {
            return '<div class="aps-error">' . 
                   __('Template not found', 'aps') . 
                   '</div>';
        }

        ob_start();
        extract($data);
        include $template_path;
        return ob_get_clean();
    }
}
