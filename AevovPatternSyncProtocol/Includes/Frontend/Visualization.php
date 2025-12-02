<?php
/**
 * Pattern visualization and data representation
 * 
 * @package APS
 * @subpackage Frontend
 */

namespace APS\Frontend;

use APS\DB\PatternDB;
use APS\DB\MetricsDB;

class Visualization {
    private $pattern_db;
    private $metrics;
    private $chart_defaults = [
        'width' => 800,
        'height' => 400,
        'margin' => [
            'top' => 20,
            'right' => 30,
            'bottom' => 30,
            'left' => 40
        ]
    ];

    public function __construct() {
        $this->pattern_db = new PatternDB();
        $this->metrics = new MetricsDB();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('aps_pattern_visualization', [$this, 'render_pattern_visualization']);
        add_shortcode('aps_metrics_chart', [$this, 'render_metrics_chart']);
    }

    public function enqueue_assets($hook) {
        // D3.js for visualizations
        wp_enqueue_script(
            'd3',
            'https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js',
            [],
            '7.8.5'
        );

        // Custom visualization scripts
        wp_enqueue_script(
            'aps-visualization',
            APS_URL . 'assets/js/visualization.js',
            ['d3'],
            APS_VERSION,
            true
        );

        wp_enqueue_style(
            'aps-visualization',
            APS_URL . 'assets/css/visualization.css',
            [],
            APS_VERSION
        );
    }

    /**
     * Render pattern visualization
     */
    public function render_pattern_visualization($atts) {
        $atts = shortcode_atts([
            'pattern_id' => '',
            'type' => 'heatmap',
            'width' => $this->chart_defaults['width'],
            'height' => $this->chart_defaults['height']
        ], $atts);

        if (empty($atts['pattern_id'])) {
            return '';
        }

        try {
            $pattern = $this->pattern_db->get_pattern($atts['pattern_id']);
            if (!$pattern) {
                return '';
            }

            $visualization_data = $this->prepare_pattern_data($pattern, $atts['type']);
            return $this->generate_visualization($visualization_data, $atts);

        } catch (\Exception $e) {
            error_log('Pattern visualization error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Render metrics chart
     */
    public function render_metrics_chart($atts) {
        $atts = shortcode_atts([
            'metric' => '',
            'type' => 'line',
            'duration' => '24h',
            'width' => $this->chart_defaults['width'],
            'height' => $this->chart_defaults['height']
        ], $atts);

        if (empty($atts['metric'])) {
            return '';
        }

        try {
            $metrics_data = $this->get_metrics_data($atts['metric'], $atts['duration']);
            return $this->generate_chart($metrics_data, $atts);

        } catch (\Exception $e) {
            error_log('Metrics chart error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Generate pattern visualization
     */
    public function generate_visualization($data, $options = []) {
        $container_id = 'aps-visualization-' . wp_generate_uuid4();
        $json_data = json_encode($data);
        $json_options = json_encode($options);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>" class="aps-visualization-container">
            <svg class="aps-visualization" width="<?php echo esc_attr($options['width']); ?>" height="<?php echo esc_attr($options['height']); ?>">
            </svg>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                APS.Visualization.create(
                    '<?php echo esc_js($container_id); ?>',
                    <?php echo $json_data; ?>,
                    <?php echo $json_options; ?>
                );
            });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate metrics chart
     */
    public function generate_chart($data, $options = []) {
        $container_id = 'aps-chart-' . wp_generate_uuid4();
        $json_data = json_encode($data);
        $json_options = json_encode($options);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>" class="aps-chart-container">
            <svg class="aps-chart" width="<?php echo esc_attr($options['width']); ?>" height="<?php echo esc_attr($options['height']); ?>">
            </svg>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                APS.Chart.create(
                    '<?php echo esc_js($container_id); ?>',
                    <?php echo $json_data; ?>,
                    <?php echo $json_options; ?>
                );
            });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Prepare pattern data for visualization
     */
    private function prepare_pattern_data($pattern, $type) {
        $pattern_data = json_decode($pattern['pattern_data'], true);

        switch ($type) {
            case 'heatmap':
                return $this->prepare_heatmap_data($pattern_data);
            case 'network':
                return $this->prepare_network_data($pattern_data);
            case 'matrix':
                return $this->prepare_matrix_data($pattern_data);
            default:
                return $pattern_data;
        }
    }

    /**
     * Prepare heatmap visualization data
     */
    private function prepare_heatmap_data($pattern_data) {
        $features = $pattern_data['features'] ?? [];
        $matrix = [];
        
        foreach ($features as $i => $row) {
            foreach ($row as $j => $value) {
                $matrix[] = [
                    'x' => $i,
                    'y' => $j,
                    'value' => $value
                ];
            }
        }

        return [
            'type' => 'heatmap',
            'data' => $matrix,
            'dimensions' => [
                'x' => count($features),
                'y' => count($features[0] ?? [])
            ]
        ];
    }

    /**
     * Prepare network visualization data
     */
    private function prepare_network_data($pattern_data) {
        $nodes = [];
        $links = [];
        
        foreach ($pattern_data['relationships'] ?? [] as $rel) {
            $nodes[$rel['source']] = [
                'id' => $rel['source'],
                'type' => $rel['source_type'] ?? 'feature'
            ];
            
            $nodes[$rel['target']] = [
                'id' => $rel['target'],
                'type' => $rel['target_type'] ?? 'feature'
            ];
            
            $links[] = [
                'source' => $rel['source'],
                'target' => $rel['target'],
                'value' => $rel['weight'] ?? 1
            ];
        }

        return [
            'type' => 'network',
            'nodes' => array_values($nodes),
            'links' => $links
        ];
    }

    /**
     * Prepare matrix visualization data
     */
    private function prepare_matrix_data($pattern_data) {
        $matrix = $pattern_data['similarity_matrix'] ?? [];
        $labels = $pattern_data['labels'] ?? [];
        
        return [
            'type' => 'matrix',
            'matrix' => $matrix,
            'labels' => $labels
        ];
    }

    /**
     * Get metrics data for charting
     */
    private function get_metrics_data($metric, $duration) {
        $end_time = time();
        $start_time = strtotime("-{$duration}");
        
        $metrics = $this->metrics->get_metric_history(
            $metric,
            $start_time,
            $end_time
        );

        return array_map(function($m) {
            return [
                'timestamp' => strtotime($m['timestamp']),
                'value' => $m['value']
            ];
        }, $metrics);
    }

    /**
     * Generate SVG from pattern
     */
    public function generate_pattern_svg($pattern_data, $options = []) {
        $width = $options['width'] ?? 400;
        $height = $options['height'] ?? 400;
        $scale = $options['scale'] ?? 1;

        ob_start();
        ?>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 <?php echo $width; ?> <?php echo $height; ?>">
            <?php $this->render_pattern_elements($pattern_data, $width, $height, $scale); ?>
        </svg>
        <?php
        return ob_get_clean();
    }

    /**
     * Render SVG pattern elements
     */
    private function render_pattern_elements($pattern_data, $width, $height, $scale) {
        $features = $pattern_data['features'] ?? [];
        $normalized = $this->normalize_features($features);

        foreach ($normalized as $feature) {
            $this->render_feature_element($feature, $width, $height, $scale);
        }
    }

    /**
     * Normalize features for visualization
     */
    private function normalize_features($features) {
        $normalized = [];
        $max_value = max(array_map('max', $features));
        
        foreach ($features as $i => $row) {
            foreach ($row as $j => $value) {
                $normalized[] = [
                    'x' => $i / count($features) * 100,
                    'y' => $j / count($row) * 100,
                    'value' => $value / $max_value
                ];
            }
        }

        return $normalized;
    }

    /**
     * Render individual feature element
     */
    private function render_feature_element($feature, $width, $height, $scale) {
        $x = $feature['x'] * $width / 100;
        $y = $feature['y'] * $height / 100;
        $size = $feature['value'] * 10 * $scale;
        $opacity = $feature['value'];
        
        printf(
            '<circle cx="%f" cy="%f" r="%f" fill="blue" opacity="%f" />',
            $x,
            $y,
            $size,
            $opacity
        );
    }
}