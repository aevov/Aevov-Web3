<?php
namespace AevovSimulationEngine\Core;

use AevovMemoryCore\MemoryManager;

require_once dirname(__FILE__) . '/../../../aevov-memory-core/includes/class-memory-manager.php';

class SimulationWorker {

    private $logger;
    private $memory_manager;

    public function __construct() {
        $this->logger = new class {
            public function info($message, $context = []) { error_log('[INFO] ' . $message . ' ' . json_encode($context)); }
            public function error($message, $context = []) { error_log('[ERROR] ' . $message . ' ' . json_encode($context)); }
        };
        $this->memory_manager = new MemoryManager();
    }

    public function run_simulation( $model, $input, $training_data = null ) {
        $this->logger->info('Running simulation', ['model_id' => $model['id']]);

        $output = $input;
        $history = [];

        for ( $i = 0; $i < ($training_data ? $training_data['epochs'] : 1); $i++ ) {
            $output = $input;
            foreach ( $model['layers'] as $layer ) {
                $history[] = ['input' => $output];
                $output = $this->process_layer( $layer, $output, $model );
                $history[count($history) - 1]['output'] = $output;
            }

            if ( $training_data ) {
                $this->backpropagate( $model, $history, $training_data );
            }
        }

        return $output;
    }

    private function process_layer( $layer, $input, $model ) {
        $output = [];
        foreach ( $layer as $pattern ) {
            $output[] = $this->apply_pattern( $pattern, $input, $model );
        }

        return $this->combine_outputs($output);
    }

    private function apply_pattern( $pattern, $input, $model ) {
        $pattern_type = $pattern->pattern_type;
        $method_name = 'apply_' . str_replace('-', '_', $pattern_type) . '_pattern';

        if (method_exists($this, $method_name)) {
            return $this->$method_name( $pattern, $input, $model );
        } else {
            $this->logger->error('Unknown pattern type', ['pattern_type' => $pattern_type]);
            return $input; // Or throw an exception
        }
    }

    /**
     * Apply physics pattern (Aevov Physics Engine integration)
     */
    private function apply_physics_pattern( $pattern, $input, $model ) {
        // Check if physics engine is available
        if (!class_exists('\\Aevov\\PhysicsEngine\\Core\\PhysicsCore')) {
            $this->logger->error('Physics engine not available');
            return $input;
        }

        $physics_core = new \Aevov\PhysicsEngine\Core\PhysicsCore();

        // Extract simulation state from input
        $simulation_state = is_array($input) ? $input : ['entities' => []];
        $simulation_id = $model['id'] ?? uniqid('sim_');

        // Run physics simulation
        $updated_state = $physics_core->simulate_physics($simulation_state, $simulation_id);

        return $updated_state;
    }

    /**
     * Apply spatial pattern (Spatial world generation)
     */
    private function apply_spatial_pattern( $pattern, $input, $model ) {
        if (!class_exists('\\Aevov\\PhysicsEngine\\World\\WorldGenerator')) {
            $this->logger->error('World generator not available');
            return $input;
        }

        $world_generator = new \Aevov\PhysicsEngine\World\WorldGenerator();

        // Extract world config from pattern metadata
        $world_config = $pattern->metadata['world_config'] ?? [
            'terrain' => ['enabled' => true],
            'biomes' => ['enabled' => true],
            'structures' => ['enabled' => true]
        ];

        // Generate world
        $world = $world_generator->generate_world($world_config);

        return $world;
    }

    private function apply_attention_pattern( $pattern, $input, $model ) {
        $memory_system = $model->memory_system;
        // Construct composite addresses for memory system components
        $query = $this->memory_manager->read_from_memory( $memory_system['id'] . '_query' );
        $keys = $this->memory_manager->read_from_memory( $memory_system['id'] . '_keys' );
        $values = $this->memory_manager->read_from_memory( $memory_system['id'] . '_values' );

        $scores = [];
        foreach ( $keys as $i => $key ) {
            $scores[$i] = $this->cosine_similarity( $query, $key );
        }

        $attention_weights = $this->softmax( $scores );

        $output = array_fill(0, count($values[0]), 0);
        foreach ( $values as $i => $value ) {
            for ( $j = 0; $j < count($value); $j++ ) {
                $output[$j] += $value[$j] * $attention_weights[$i];
            }
        }

        return $output;
    }

    private function apply_feed_forward_pattern( $pattern, $input, $model ) {
        $weights = $pattern->metadata['weights'];
        $biases = $pattern->metadata['biases'];
        $activation = $pattern->metadata['activation'] ?? 'relu';

        $output = [];
        for ( $i = 0; $i < count($weights); $i++ ) {
            $sum = 0;
            for ( $j = 0; $j < count($input); $j++ ) {
                $sum += $input[$j] * $weights[$i][$j];
            }
            $output[$i] = $this->apply_activation( $activation, $sum + $biases[$i] );
        }

        return $output;
    }

    private function apply_convolutional_pattern( $pattern, $input, $model ) {
        $filters = $pattern->metadata['filters'];
        $biases = $pattern->metadata['biases'];
        $stride = $pattern->metadata['stride'] ?? 1;
        $padding = $pattern->metadata['padding'] ?? 'valid';

        $input_padded = $this->pad_input( $input, $padding, count($filters[0]) );
        $output = [];

        for ( $i = 0; $i <= count($input_padded) - count($filters[0]); $i += $stride ) {
            $receptive_field = array_slice( $input_padded, $i, count($filters[0]) );
            $sum = 0;
            for ( $j = 0; $j < count($filters); $j++ ) {
                for ( $k = 0; $k < count($filters[$j]); $k++ ) {
                    $sum += $receptive_field[$k] * $filters[$j][$k];
                }
            }
            $output[] = $this->relu( $sum + $biases[0] );
        }

        return $output;
    }

    private function pad_input( $input, $padding, $filter_size ) {
        if ($padding === 'same') {
            $pad_size = (int)(($filter_size - 1) / 2);
            return array_merge(
                array_fill(0, $pad_size, 0),
                $input,
                array_fill(0, $pad_size, 0)
            );
        }
        return $input;
    }

    private function combine_outputs( $outputs, $strategy = 'average' ) {
        if (empty($outputs)) {
            return [];
        }

        switch ($strategy) {
            case 'concatenate':
                return array_merge(...$outputs);
            case 'add':
                $combined = array_fill(0, count($outputs[0]), 0);
                foreach ($outputs as $output) {
                    for ($i = 0; $i < count($output); $i++) {
                        $combined[$i] += $output[$i];
                    }
                }
                return $combined;
            case 'average':
            default:
                $combined = array_fill(0, count($outputs[0]), 0);
                foreach ($outputs as $output) {
                    for ($i = 0; $i < count($output); $i++) {
                        $combined[$i] += $output[$i];
                    }
                }
                $count = count($outputs);
                if ($count > 0) {
                    for ($i = 0; $i < count($combined); $i++) {
                        $combined[$i] /= $count;
                    }
                }
                return $combined;
        }
    }

    private function backpropagate( $model, $history, $training_data ) {
        $error = $this->calculate_error( $history[count($history) - 1]['output'], $training_data['expected_output'] );

        for ( $i = count($history) - 1; $i >= 0; $i-- ) {
            $layer = $model['layers'][$i];
            $input = $history[$i]['input'];
            $output = $history[$i]['output'];

            $error = $this->backpropagate_layer( $layer, $input, $output, $error, $training_data['learning_rate'] );
        }
    }

    private function backpropagate_layer( $layer, $input, $output, $error, $learning_rate ) {
        $new_error = array_fill(0, count($input), 0);

        foreach ( $layer as $pattern ) {
            $new_error = $this->update_pattern_weights( $pattern, $input, $output, $error, $learning_rate, $new_error );
        }

        return $new_error;
    }

    private function update_pattern_weights( $pattern, $input, $output, $error, $learning_rate, $new_error ) {
        $pattern_type = $pattern->pattern_type;
        $method_name = 'update_' . str_replace('-', '_', $pattern_type) . '_pattern_weights';

        if (method_exists($this, $method_name)) {
            return $this->$method_name( $pattern, $input, $output, $error, $learning_rate, $new_error );
        } else {
            return $new_error;
        }
    }

    private function update_feed_forward_pattern_weights( $pattern, $input, $output, $error, $learning_rate, $new_error ) {
        $weights = $pattern->metadata['weights'];
        $biases = $pattern->metadata['biases'];
        $activation = $pattern->metadata['activation'] ?? 'relu';

        $delta = [];
        for ($i = 0; $i < count($output); $i++) {
            $delta[$i] = $error[$i] * $this->apply_activation_derivative( $activation, $output[$i] );
        }

        for ($i = 0; $i < count($weights); $i++) {
            for ($j = 0; $j < count($weights[$i]); $j++) {
                $weights[$i][$j] -= $learning_rate * $delta[$i] * $input[$j];
                $new_error[$j] += $weights[$i][$j] * $delta[$i];
            }
            $biases[$i] -= $learning_rate * $delta[$i];
        }

        $pattern->metadata['weights'] = $weights;
        $pattern->metadata['biases'] = $biases;

        return $new_error;
    }

    private function calculate_error( $output, $expected_output ) {
        $error = [];
        for ($i = 0; $i < count($output); $i++) {
            $error[$i] = $output[$i] - $expected_output[$i];
        }
        return $error;
    }

    private function cosine_similarity( $vec1, $vec2 ) {
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;
        for ( $i = 0; $i < count($vec1); $i++ ) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }
        $norm1 = sqrt( $norm1 );
        $norm2 = sqrt( $norm2 );

        if ( $norm1 == 0 || $norm2 == 0 ) {
            return 0;
        }

        return $dot_product / ( $norm1 * $norm2 );
    }

    private function softmax( $values ) {
        $exps = array_map('exp', $values);
        $sum = array_sum($exps);
        return array_map(function($x) use ($sum) { return $x / $sum; }, $exps);
    }

    private function relu( $x ) {
        return max(0, $x);
    }

    private function apply_activation( $activation, $x ) {
        switch ($activation) {
            case 'relu':
                return $this->relu($x);
            case 'sigmoid':
                return 1 / (1 + exp(-$x));
            case 'tanh':
                return tanh($x);
            default:
                return $x;
        }
    }

    private function apply_activation_derivative( $activation, $x ) {
        switch ($activation) {
            case 'relu':
                return $x > 0 ? 1 : 0;
            case 'sigmoid':
                return $x * (1 - $x);
            case 'tanh':
                return 1 - $x * $x;
            default:
                return 1;
        }
    }

    public function render_virtual_hippocampus( $memory_system ) {
        $nodes = [];
        $edges = [];

        foreach ( $memory_system['components'] as $component_address ) {
            // Construct composite address for component
            $component = $this->memory_manager->read_from_memory( $memory_system['id'] . '_' . $component_address );
            $nodes[] = [
                'id' => $component_address,
                'label' => $component['type'],
                'capacity' => $component['capacity'],
                'decay_rate' => $component['decay_rate'],
            ];

            if (!empty($component['connections'])) {
                foreach ($component['connections'] as $connection) {
                    $edges[] = [
                        'source' => $component_address,
                        'target' => $connection,
                    ];
                }
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
