<?php
namespace AevovNeuroArchitect\Core;

use AevovPatternSyncProtocol\Comparison\APS_Comparator;
use AevovSimulationEngine\Core\SimulationWorker;

class BlueprintEvolver {

    private $comparator;
    private $simulation_worker;
    private $logger;

    public function __construct() {
        $this->comparator = new APS_Comparator();
        $this->simulation_worker = new SimulationWorker();
        $this->logger = new class {
            public function info($message, $context = []) { error_log('[INFO] ' . $message . ' ' . json_encode($context)); }
            public function error($message, $context = []) { error_log('[ERROR] ' . $message . ' ' . json_encode($context)); }
        };
    }

    public function evolve_blueprint( $task_description, $population_size, $generations ) {
        $this->logger->info('Starting blueprint evolution', ['task' => $task_description]);

        $population = $this->initialize_population( $population_size );

        for ( $i = 0; $i < $generations; $i++ ) {
            $population = $this->evaluate_population( $population, $task_description );
            $population = $this->select_fittest( $population );
            $population = $this->crossover_and_mutate( $population, $population_size );
            $this->logger->info('Generation complete', ['generation' => $i + 1]);
        }

        return $population[0];
    }

    private function initialize_population( $size ) {
        $population = [];
        for ( $i = 0; $i < $size; $i++ ) {
            $population[] = $this->generate_random_blueprint();
        }
        return $population;
    }

    private function generate_random_blueprint() {
        $layer_types = ['attention', 'feed_forward', 'conv', 'recurrent', 'pooling', 'normalization'];
        $num_layers = mt_rand(2, 8);

        $layers = [];
        for ($i = 0; $i < $num_layers; $i++) {
            $type = $layer_types[mt_rand(0, count($layer_types) - 1)];

            $layers[] = [
                'type' => $type,
                'pattern_type' => $type,
                'count' => mt_rand(1, 4),
                'size' => mt_rand(32, 512),
                'activation' => ['relu', 'tanh', 'sigmoid', 'gelu'][mt_rand(0, 3)],
                'dropout' => mt_rand(0, 30) / 100, // 0-0.3
                'params' => $this->generate_layer_params($type),
            ];
        }

        return [
            'name' => 'evolved-blueprint-' . uniqid(),
            'layers' => $layers,
            'genome' => $this->blueprint_to_genome($layers), // Genetic encoding
        ];
    }

    private function generate_layer_params($type) {
        switch ($type) {
            case 'attention':
                return [
                    'heads' => [1, 2, 4, 8][mt_rand(0, 3)],
                    'key_dim' => [32, 64, 128][mt_rand(0, 2)],
                ];
            case 'conv':
                return [
                    'kernel_size' => [3, 5, 7][mt_rand(0, 2)],
                    'stride' => [1, 2][mt_rand(0, 1)],
                ];
            case 'recurrent':
                return [
                    'cell_type' => ['lstm', 'gru'][mt_rand(0, 1)],
                    'bidirectional' => mt_rand(0, 1) === 1,
                ];
            default:
                return [];
        }
    }

    private function blueprint_to_genome($layers) {
        // Convert blueprint to genetic encoding for crossover
        $genome = [];
        foreach ($layers as $layer) {
            $genome[] = [
                'type_id' => crc32($layer['type']) % 100,
                'size' => $layer['size'],
                'count' => $layer['count'],
            ];
        }
        return $genome;
    }

    private function evaluate_population( $population, $task_description ) {
        foreach ( $population as &$individual ) {
            // Individual IS the blueprint, no need to access ['blueprint']
            $model = $this->comparator->compose_model( $individual );
            $output = $this->simulation_worker->run_simulation( $model, $task_description['input'] );
            $individual['fitness'] = $this->calculate_fitness( $output, $task_description['expected_output'] );
        }
        return $population;
    }

    private function calculate_fitness( $output, $expected_output ) {
        // Fitness based on similarity - higher similarity = higher fitness
        // Cosine similarity ranges from -1 to 1, normalize to 0-1
        $similarity = $this->cosine_similarity( $output, $expected_output );
        return ($similarity + 1) / 2; // Normalize to 0-1 range
    }

    private function select_fittest( $population ) {
        usort($population, function($a, $b) {
            return $b['fitness'] <=> $a['fitness'];
        });
        return array_slice( $population, 0, count($population) / 2 );
    }

    private function crossover_and_mutate( $population, $target_size ) {
        $new_population = $population;
        while ( count( $new_population ) < $target_size ) {
            $parent1 = $population[array_rand($population)];
            $parent2 = $population[array_rand($population)];
            $child = $this->crossover( $parent1, $parent2 );
            $child = $this->mutate( $child );
            $new_population[] = $child;
        }
        return $new_population;
    }

    private function crossover($parent1, $parent2) {
        $genome1 = $parent1['genome'] ?? $this->blueprint_to_genome($parent1['layers']);
        $genome2 = $parent2['genome'] ?? $this->blueprint_to_genome($parent2['layers']);

        // Two-point crossover
        $len1 = count($genome1);
        $len2 = count($genome2);

        if ($len1 === 0 || $len2 === 0) {
            return $parent1;
        }

        $crossover_point = mt_rand(1, min($len1, $len2) - 1);

        // Create child by combining genomes
        $child_genome = array_merge(
            array_slice($genome1, 0, $crossover_point),
            array_slice($genome2, $crossover_point)
        );

        // Convert back to blueprint layers
        $child_layers = $this->genome_to_layers($child_genome, $parent1['layers'], $parent2['layers']);

        return [
            'name' => 'crossover-' . uniqid(),
            'layers' => $child_layers,
            'genome' => $child_genome,
            'parents' => [$parent1['name'], $parent2['name']],
        ];
    }

    private function genome_to_layers($genome, $layers1, $layers2) {
        $layers = [];
        $source_layers = array_merge($layers1, $layers2);

        foreach ($genome as $gene) {
            // Find matching layer from parents
            foreach ($source_layers as $layer) {
                if ((crc32($layer['type']) % 100) === $gene['type_id']) {
                    $new_layer = $layer;
                    $new_layer['size'] = $gene['size'];
                    $new_layer['count'] = $gene['count'];
                    $layers[] = $new_layer;
                    break;
                }
            }
        }

        return $layers;
    }

    private function mutate($individual) {
        $mutation_rate = 0.15;

        // Multiple mutation types
        if (mt_rand() / mt_getrandmax() < $mutation_rate) {
            $mutation_type = mt_rand(0, 5);

            switch ($mutation_type) {
                case 0:
                    // Add layer
                    $this->mutate_add_layer($individual);
                    break;
                case 1:
                    // Remove layer (if more than 2)
                    if (count($individual['layers']) > 2) {
                        $this->mutate_remove_layer($individual);
                    }
                    break;
                case 2:
                    // Modify layer size
                    $this->mutate_layer_size($individual);
                    break;
                case 3:
                    // Modify activation function
                    $this->mutate_activation($individual);
                    break;
                case 4:
                    // Swap two layers
                    $this->mutate_swap_layers($individual);
                    break;
                case 5:
                    // Modify dropout rate
                    $this->mutate_dropout($individual);
                    break;
            }

            // Update genome
            $individual['genome'] = $this->blueprint_to_genome($individual['layers']);
        }

        return $individual;
    }

    private function mutate_add_layer(&$individual) {
        $layer_types = ['attention', 'feed_forward', 'conv', 'recurrent', 'pooling'];
        $type = $layer_types[mt_rand(0, count($layer_types) - 1)];

        $new_layer = [
            'type' => $type,
            'pattern_type' => $type,
            'count' => mt_rand(1, 4),
            'size' => mt_rand(32, 512),
            'activation' => ['relu', 'tanh', 'sigmoid'][mt_rand(0, 2)],
            'dropout' => mt_rand(0, 30) / 100,
            'params' => $this->generate_layer_params($type),
        ];

        $position = mt_rand(0, count($individual['layers']));
        array_splice($individual['layers'], $position, 0, [$new_layer]);
    }

    private function mutate_remove_layer(&$individual) {
        $index = mt_rand(0, count($individual['layers']) - 1);
        array_splice($individual['layers'], $index, 1);
    }

    private function mutate_layer_size(&$individual) {
        $index = mt_rand(0, count($individual['layers']) - 1);
        $change = mt_rand(-50, 50);
        $individual['layers'][$index]['size'] = max(16, min(1024, $individual['layers'][$index]['size'] + $change));
    }

    private function mutate_activation(&$individual) {
        $index = mt_rand(0, count($individual['layers']) - 1);
        $activations = ['relu', 'tanh', 'sigmoid', 'gelu', 'swish'];
        $individual['layers'][$index]['activation'] = $activations[mt_rand(0, count($activations) - 1)];
    }

    private function mutate_swap_layers(&$individual) {
        if (count($individual['layers']) < 2) return;

        $i = mt_rand(0, count($individual['layers']) - 1);
        $j = mt_rand(0, count($individual['layers']) - 1);

        $temp = $individual['layers'][$i];
        $individual['layers'][$i] = $individual['layers'][$j];
        $individual['layers'][$j] = $temp;
    }

    private function mutate_dropout(&$individual) {
        $index = mt_rand(0, count($individual['layers']) - 1);
        $change = (mt_rand(-10, 10)) / 100;
        $individual['layers'][$index]['dropout'] = max(0, min(0.5, $individual['layers'][$index]['dropout'] + $change));
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
}
