<?php
/**
 * APS Tools Frontend Class
 */
namespace APSTools;

class APSToolsFrontend {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Only add WordPress hooks if WordPress is loaded
        if (function_exists('add_action')) {
            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_ajax_aps_analyze_pattern', [$this, 'ajax_analyze_pattern']);
            add_action('wp_ajax_nopriv_aps_analyze_pattern', [$this, 'ajax_analyze_pattern']);
            add_action('wp_ajax_aps_compare_patterns', [$this, 'ajax_compare_patterns']);
            add_action('wp_ajax_nopriv_aps_compare_patterns', [$this, 'ajax_compare_patterns']);
        }
        
        if (function_exists('add_shortcode')) {
            add_shortcode('aps_analysis_form', [$this, 'render_analysis_form']);
            add_shortcode('aps_comparison_form', [$this, 'render_comparison_form']);
        }
    }

    public function enqueue_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'aps_analysis_form' ) || has_shortcode( $post->post_content, 'aps_comparison_form' ) ) ) {
            wp_enqueue_script(
                'aps-tools-frontend',
                APSTOOLS_URL . 'assets/js/frontend.js',
                ['jquery'],
                APSTOOLS_VERSION,
                true
            );

            wp_localize_script('aps-tools-frontend', 'apsToolsFrontend', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aps-tools-frontend')
            ]);
        }
    }

    public function render_analysis_form() {
        ob_start();
        ?>
        <form id="aps-analysis-form">
            <div>
                <label for="pattern_type"><?php _e('Pattern Type', 'aps-tools'); ?></label>
                <select id="pattern_type" name="pattern_type" required>
                    <option value="sequential"><?php _e('Sequential', 'aps-tools'); ?></option>
                    <option value="structural"><?php _e('Structural', 'aps-tools'); ?></option>
                    <option value="statistical"><?php _e('Statistical', 'aps-tools'); ?></option>
                </select>
            </div>
            <div>
                <label for="pattern_data"><?php _e('Pattern Data', 'aps-tools'); ?></label>
                <textarea id="pattern_data" name="pattern_data" rows="10" required></textarea>
            </div>
            <button type="submit"><?php _e('Analyze', 'aps-tools'); ?></button>
        </form>
        <div id="aps-analysis-result"></div>
        <?php
        return ob_get_clean();
    }

    public function render_comparison_form() {
        $available_patterns = aps_tools_get_available_patterns();
        ob_start();
        ?>
        <form id="aps-comparison-form">
            <div>
                <label><?php _e('Select Patterns', 'aps-tools'); ?></label>
                <select id="pattern1" name="pattern1" required>
                    <?php foreach ($available_patterns as $pattern): ?>
                        <option value="<?php echo esc_attr($pattern->id); ?>">
                            <?php echo esc_html($pattern->sku); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="pattern2" name="pattern2" required>
                    <?php foreach ($available_patterns as $pattern): ?>
                        <option value="<?php echo esc_attr($pattern->id); ?>">
                            <?php echo esc_html($pattern->sku); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="comparison_type"><?php _e('Comparison Type', 'aps-tools'); ?></label>
                <select id="comparison_type" name="comparison_type" required>
                    <option value="similarity"><?php _e('Similarity', 'aps-tools'); ?></option>
                    <option value="difference"><?php _e('Difference', 'aps-tools'); ?></option>
                    <option value="structural"><?php _e('Structural', 'aps-tools'); ?></option>
                </select>
            </div>
            <button type="submit"><?php _e('Compare', 'aps-tools'); ?></button>
        </form>
        <div id="aps-comparison-result"></div>
        <?php
        return ob_get_clean();
    }

    public function ajax_analyze_pattern() {
        check_ajax_referer('aps-tools-frontend', 'nonce');

        $pattern_data = [
            'pattern_type' => sanitize_text_field($_POST['pattern_type']),
            'pattern_data' => sanitize_textarea_field($_POST['pattern_data'])
        ];

        $result = aps_tools_analyze_pattern($pattern_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function ajax_compare_patterns() {
        check_ajax_referer('aps-tools-frontend', 'nonce');

        $patterns = [
            aps_tools_get_pattern_by_id(absint($_POST['pattern1'])),
            aps_tools_get_pattern_by_id(absint($_POST['pattern2']))
        ];

        $comparison_type = sanitize_text_field($_POST['comparison_type']);

        $result = aps_tools_compare_patterns($patterns, $comparison_type);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

}

// Initialize the class - only if WordPress is loaded
if (function_exists('add_action')) {
    add_action('plugins_loaded', function() {
        APSToolsFrontend::instance();
    });
}