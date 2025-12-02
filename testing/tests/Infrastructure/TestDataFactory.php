<?php
/**
 * Test Data Factory
 * Creates realistic test data for Aevov systems
 */

namespace AevovTesting\Infrastructure;

class TestDataFactory {

    /**
     * Generate realistic blueprint data for NeuroArchitect
     */
    public static function createBlueprint($overrides = []) {
        $defaults = [
            'id' => 'blueprint_' . uniqid(),
            'config' => [
                'layer_1' => [
                    'type' => 'dense',
                    'units' => 128,
                    'activation' => 'relu',
                ],
                'layer_2' => [
                    'type' => 'dense',
                    'units' => 64,
                    'activation' => 'relu',
                ],
                'output' => [
                    'type' => 'dense',
                    'units' => 10,
                    'activation' => 'softmax',
                ],
            ],
            'performance_score' => 0.85,
            'created_at' => current_time('mysql'),
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate physics simulation parameters
     */
    public static function createPhysicsParams($overrides = []) {
        $defaults = [
            'solver_type' => 'newtonian',
            'time_step' => 0.016,
            'max_iterations' => 100,
            'bodies' => [
                [
                    'mass' => 1.0,
                    'position' => [0, 0, 0],
                    'velocity' => [1, 0, 0],
                    'radius' => 0.5,
                ],
                [
                    'mass' => 2.0,
                    'position' => [5, 0, 0],
                    'velocity' => [-0.5, 0, 0],
                    'radius' => 0.7,
                ],
            ],
            'gravity' => [0, -9.81, 0],
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate image generation parameters
     */
    public static function createImageParams($overrides = []) {
        $defaults = [
            'prompt' => 'A serene mountain landscape at sunset',
            'width' => 512,
            'height' => 512,
            'steps' => 50,
            'guidance_scale' => 7.5,
            'seed' => random_int(1, 999999),
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate music composition parameters
     */
    public static function createMusicParams($overrides = []) {
        $defaults = [
            'style' => 'ambient',
            'tempo' => 120,
            'duration' => 30,
            'key' => 'C',
            'scale' => 'major',
            'instruments' => ['piano', 'strings'],
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate transcription job data
     */
    public static function createTranscriptionJob($overrides = []) {
        $defaults = [
            'job_id' => 'transcription_' . uniqid(),
            'audio_file' => '/tmp/test_audio.wav',
            'language' => 'en',
            'model' => 'base',
            'status' => 'pending',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate memory system data
     */
    public static function createMemoryData($overrides = []) {
        $defaults = [
            'address' => 'mem_' . uniqid(),
            'data' => [
                'type' => 'simulation_state',
                'content' => [
                    'entities' => 10,
                    'timestamp' => time(),
                    'state_vector' => array_fill(0, 64, 0.5),
                ],
            ],
            'metadata' => [
                'source' => 'test',
                'created_at' => current_time('mysql'),
            ],
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate embedding data
     */
    public static function createEmbeddingData($overrides = []) {
        $defaults = [
            'text' => 'This is a test sentence for embedding generation.',
            'model' => 'text-embedding-ada-002',
            'dimensions' => 1536,
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate APS pattern data
     */
    public static function createPatternData($overrides = []) {
        $defaults = [
            'pattern_hash' => hash('sha256', uniqid()),
            'pattern_type' => 'text',
            'features' => [
                'embedding' => array_fill(0, 64, rand(0, 100) / 100),
                'metadata' => ['source' => 'test'],
            ],
            'confidence' => 0.92,
            'status' => 'active',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate chunk data
     */
    public static function createChunkData($overrides = []) {
        $defaults = [
            'id' => 'chunk_' . uniqid(),
            'type' => 'text',
            'cubbit_key' => 'test/chunk_' . uniqid() . '.json',
            'metadata' => [
                'size' => 1024,
                'created_at' => current_time('mysql'),
                'source' => 'test',
            ],
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate simulation parameters
     */
    public static function createSimulationParams($overrides = []) {
        $defaults = [
            'simulation_type' => 'neural',
            'duration' => 100,
            'time_step' => 0.01,
            'initial_conditions' => [
                'population_size' => 50,
                'mutation_rate' => 0.1,
                'crossover_rate' => 0.7,
            ],
            'fitness_function' => 'mse',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate application configuration
     */
    public static function createApplicationConfig($overrides = []) {
        $defaults = [
            'app_name' => 'Test Application ' . uniqid(),
            'app_type' => 'web',
            'features' => [
                'authentication' => true,
                'api_integration' => true,
                'data_storage' => true,
            ],
            'blueprint' => self::createBlueprint(),
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate realistic test array data
     */
    public static function createVector($dimensions = 64, $range_min = 0.0, $range_max = 1.0) {
        $vector = [];
        for ($i = 0; $i < $dimensions; $i++) {
            $vector[] = $range_min + (mt_rand() / mt_getrandmax()) * ($range_max - $range_min);
        }
        return $vector;
    }

    /**
     * Generate matrix data
     */
    public static function createMatrix($rows = 10, $cols = 10, $range_min = 0.0, $range_max = 1.0) {
        $matrix = [];
        for ($i = 0; $i < $rows; $i++) {
            $matrix[] = self::createVector($cols, $range_min, $range_max);
        }
        return $matrix;
    }

    /**
     * Generate world generation parameters
     */
    public static function createWorldParams($overrides = []) {
        $defaults = [
            'size' => [100, 100, 100],
            'seed' => random_int(1, 999999),
            'terrain_type' => 'procedural',
            'biomes' => ['plains', 'mountains', 'desert'],
            'structures' => true,
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate cognitive task parameters
     */
    public static function createCognitiveTask($overrides = []) {
        $defaults = [
            'task_type' => 'reasoning',
            'input_data' => [
                'query' => 'What is the relationship between A and B?',
                'context' => ['A is larger than B', 'B is faster than C'],
            ],
            'expected_output' => 'inference',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Generate language processing parameters
     */
    public static function createLanguageParams($overrides = []) {
        $defaults = [
            'text' => 'The quick brown fox jumps over the lazy dog.',
            'task' => 'analysis',
            'language' => 'en',
            'options' => [
                'sentiment' => true,
                'entities' => true,
                'keywords' => true,
            ],
        ];

        return array_merge($defaults, $overrides);
    }
}
