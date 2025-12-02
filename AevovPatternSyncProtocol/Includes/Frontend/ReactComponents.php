<?php
/**
 * React component management and rendering
 * 
 * @package APS
 * @subpackage Frontend
 */

namespace APS\Frontend;

use APS\Core\Logger;
use APS\DB\MetricsDB;

class ReactComponents {
    private $logger;
    private $metrics;
    private $root_element = 'aps-react-root';
    private $default_props = [];

    public function __construct() {
        $this->logger = new Logger();
        $this->metrics = new MetricsDB();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render_root_element']);
        add_action('admin_footer', [$this, 'render_root_element']);
    }

    public function enqueue_assets($hook) {
        // React and ReactDOM from CDN
        wp_enqueue_script(
            'react',
            'https://cdnjs.cloudflare.com/ajax/libs/react/18.2.0/umd/react.production.min.js',
            [],
            '18.2.0'
        );

        wp_enqueue_script(
            'react-dom',
            'https://cdnjs.cloudflare.com/ajax/libs/react-dom/18.2.0/umd/react-dom.production.min.js',
            ['react'],
            '18.2.0'
        );

        // Custom React components and utilities
        wp_enqueue_script(
            'aps-react-components',
            APS_URL . 'assets/js/react-components.js',
            ['react', 'react-dom'],
            APS_VERSION,
            true
        );

        wp_localize_script('aps-react-components', 'apsReact', [
            'rootElement' => $this->root_element,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aps-react'),
            'assets_url' => APS_URL . 'assets/',
            'defaultProps' => $this->default_props
        ]);

        // Tailwind CSS for styling
        wp_enqueue_style(
            'aps-react-styles',
            APS_URL . 'assets/css/react-components.css',
            [],
            APS_VERSION
        );
    }

    public function render_root_element() {
        echo '<div id="' . esc_attr($this->root_element) . '"></div>';
    }

    public function render_component($name, $props = [], $container_id = null) {
        $container_id = $container_id ?? 'aps-react-' . wp_generate_uuid4();
        $props = array_merge($this->default_props, $props);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($container_id); ?>" class="aps-react-container"></div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof APS.React !== 'undefined') {
                    APS.React.renderComponent(
                        '<?php echo esc_js($name); ?>',
                        <?php echo wp_json_encode($props); ?>,
                        '<?php echo esc_js($container_id); ?>'
                    );
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function register_component($name, $component) {
        add_filter('aps_react_components', function($components) use ($name, $component) {
            $components[$name] = $component;
            return $components;
        });
    }

    public function get_component_definition($name) {
        $components = apply_filters('aps_react_components', []);
        return $components[$name] ?? null;
    }

    public function set_default_props($props) {
        $this->default_props = array_merge($this->default_props, $props);
    }

    public function render_pattern_visualization($pattern_data, $options = []) {
        return $this->render_component('PatternVisualization', [
            'pattern' => $pattern_data,
            'options' => $options
        ]);
    }

    public function render_metrics_dashboard($metrics_data, $options = []) {
        return $this->render_component('MetricsDashboard', [
            'metrics' => $metrics_data,
            'options' => $options
        ]);
    }

    public function render_pattern_comparison($patterns, $options = []) {
        return $this->render_component('PatternComparison', [
            'patterns' => $patterns,
            'options' => $options
        ]);
    }

    public function render_network_topology($topology_data, $options = []) {
        return $this->render_component('NetworkTopology', [
            'topology' => $topology_data,
            'options' => $options
        ]);
    }

    /**
     * Register built-in React components
     */
    public function register_built_in_components() {
        // Pattern Visualization Component
        $this->register_component('PatternVisualization', [
            'name' => 'PatternVisualization',
            'props' => ['pattern', 'options'],
            'file' => 'components/pattern-visualization.jsx'
        ]);

        // Metrics Dashboard Component
        $this->register_component('MetricsDashboard', [
            'name' => 'MetricsDashboard',
            'props' => ['metrics', 'options'],
            'file' => 'components/metrics-dashboard.jsx'
        ]);

        // Pattern Comparison Component
        $this->register_component('PatternComparison', [
            'name' => 'PatternComparison',
            'props' => ['patterns', 'options'],
            'file' => 'components/pattern-comparison.jsx'
        ]);

        // Network Topology Component
        $this->register_component('NetworkTopology', [
            'name' => 'NetworkTopology',
            'props' => ['topology', 'options'],
            'file' => 'components/network-topology.jsx'
        ]);
        
         // Add the new DJCLM Pattern Sync component
    $this->register_component('DJCLMPatternSync', [
        'name' => 'DJCLMPatternSync',
        'props' => ['options'],
        'file' => 'components/pattern-sync.jsx'
    ]);

    }

    /**
     * Helper method to get component props schema
     */
    public function get_component_props_schema($name) {
        $component = $this->get_component_definition($name);
        if (!$component) {
            return null;
        }

        $schema = [];
        foreach ($component['props'] as $prop) {
            $schema[$prop] = [
                'type' => 'any',
                'required' => false
            ];
        }

        return $schema;
    }

    /**
     * Validate component props against schema
     */
    public function validate_component_props($name, $props) {
        $schema = $this->get_component_props_schema($name);
        if (!$schema) {
            return false;
        }

        foreach ($schema as $prop => $rules) {
            if ($rules['required'] && !isset($props[$prop])) {
                $this->logger->error("Missing required prop: {$prop} for component: {$name}");
                return false;
            }
        }

        return true;
    }
}