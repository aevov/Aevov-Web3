<?php
/**
 * Performance Benchmarking Suite
 * Comprehensive performance tests for all Aevov systems
 */

require_once dirname(__FILE__) . '/../Infrastructure/BaseAevovTestCase.php';
require_once dirname(__FILE__) . '/../Infrastructure/TestDataFactory.php';
require_once dirname(__FILE__) . '/../Infrastructure/PerformanceProfiler.php';

use AevovTesting\Infrastructure\BaseAevovTestCase;
use AevovTesting\Infrastructure\TestDataFactory;
use AevovTesting\Infrastructure\PerformanceProfiler;

class PerformanceBenchmarks extends BaseAevovTestCase {

    private $profiler;

    public function setUp(): void {
        parent::setUp();
        $this->profiler = PerformanceProfiler::getInstance();
    }

    /**
     * Benchmark blueprint generation
     */
    public function test_benchmark_blueprint_generation() {
        $result = $this->profiler->benchmark(function() {
            TestDataFactory::createBlueprint();
        }, 1000);

        $this->assertLessThan(10, $result['time']['avg']); // < 10ms average
        echo "\nBlueprint Generation: {$result['time']['avg']}ms avg\n";
    }

    /**
     * Benchmark physics calculations
     */
    public function test_benchmark_physics_calculations() {
        $params = TestDataFactory::createPhysicsParams();

        $result = $this->profiler->benchmark(function() use ($params) {
            // Simulate physics step
            foreach ($params['bodies'] as $body) {
                $force = [0, -9.81 * $body['mass'], 0];
            }
        }, 10000);

        $this->assertLessThan(1, $result['time']['avg']); // < 1ms
        echo "\nPhysics Step: {$result['time']['avg']}ms avg\n";
    }

    /**
     * Benchmark embedding generation
     */
    public function test_benchmark_embedding_generation() {
        $result = $this->profiler->benchmark(function() {
            TestDataFactory::createVector(768);
        }, 1000);

        $this->assertLessThan(5, $result['time']['avg']);
        echo "\nEmbedding Generation: {$result['time']['avg']}ms avg\n";
    }

    /**
     * Benchmark memory operations
     */
    public function test_benchmark_memory_operations() {
        $result = $this->profiler->benchmark(function() {
            $data = TestDataFactory::createMemoryData();
            $serialized = json_encode($data);
            $deserialized = json_decode($serialized, true);
        }, 1000);

        $this->assertLessThan(5, $result['time']['avg']);
        echo "\nMemory Operations: {$result['time']['avg']}ms avg\n";
    }

    /**
     * Benchmark database queries
     */
    public function test_benchmark_database_queries() {
        global $wpdb;

        $this->profiler->start('db_query');

        for ($i = 0; $i < 100; $i++) {
            $wpdb->get_results("SELECT * FROM {$wpdb->users} LIMIT 10");
        }

        $result = $this->profiler->end('db_query');

        $avg_per_query = $result['duration'] * 1000 / 100;

        $this->assertLessThan(10, $avg_per_query); // < 10ms per query
        echo "\nDatabase Query: {$avg_per_query}ms avg\n";
    }

    /**
     * Benchmark API endpoint response time
     */
    public function test_benchmark_api_endpoints() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $endpoints = [
            '/aevov-physics/v1/simulations',
            '/aevov-simulation/v1/simulations',
            '/aevov-embedding/v1/embed',
        ];

        foreach ($endpoints as $endpoint) {
            $this->profiler->start($endpoint);

            $response = $this->simulateRestRequest(
                $endpoint,
                'POST',
                TestDataFactory::createPhysicsParams()
            );

            $result = $this->profiler->end($endpoint);

            $time_ms = $result['duration'] * 1000;
            $this->assertLessThan(100, $time_ms); // < 100ms
            echo "\n{$endpoint}: {$time_ms}ms\n";
        }
    }

    /**
     * Benchmark text processing
     */
    public function test_benchmark_text_processing() {
        $text = str_repeat('This is a test sentence. ', 100);

        $result = $this->profiler->benchmark(function() use ($text) {
            $words = str_word_count($text);
            $chars = strlen($text);
            $sentences = substr_count($text, '.');
        }, 1000);

        $this->assertLessThan(1, $result['time']['avg']);
        echo "\nText Processing: {$result['time']['avg']}ms avg\n";
    }

    /**
     * Benchmark vector operations
     */
    public function test_benchmark_vector_operations() {
        $vec1 = TestDataFactory::createVector(512);
        $vec2 = TestDataFactory::createVector(512);

        $result = $this->profiler->benchmark(function() use ($vec1, $vec2) {
            $dot = 0;
            for ($i = 0; $i < 512; $i++) {
                $dot += $vec1[$i] * $vec2[$i];
            }
        }, 10000);

        $this->assertLessThan(0.5, $result['time']['avg']);
        echo "\nVector Dot Product: {$result['time']['avg']}ms avg\n";
    }

    /**
     * Benchmark JSON serialization
     */
    public function test_benchmark_json_serialization() {
        $data = [
            'blueprint' => TestDataFactory::createBlueprint(),
            'params' => TestDataFactory::createPhysicsParams(),
            'memory' => TestDataFactory::createMemoryData(),
        ];

        $result = $this->profiler->benchmark(function() use ($data) {
            $json = json_encode($data);
            $decoded = json_decode($json, true);
        }, 1000);

        $this->assertLessThan(2, $result['time']['avg']);
        echo "\nJSON Serialization: {$result['time']['avg']}ms avg\n";
    }

    /**
     * Benchmark cache operations
     */
    public function test_benchmark_cache_operations() {
        $key = 'test_cache_' . uniqid();
        $value = TestDataFactory::createBlueprint();

        $result = $this->profiler->benchmark(function() use ($key, $value) {
            set_transient($key, $value, 3600);
            $retrieved = get_transient($key);
            delete_transient($key);
        }, 100);

        $this->assertLessThan(5, $result['time']['avg']);
        echo "\nCache Operations: {$result['time']['avg']}ms avg\n";
    }

    /**
     * Benchmark collision detection
     */
    public function test_benchmark_collision_detection() {
        $bodies = [];
        for ($i = 0; $i < 100; $i++) {
            $bodies[] = [
                'position' => [rand(-100, 100), rand(-100, 100), rand(-100, 100)],
                'radius' => 1.0,
            ];
        }

        $result = $this->profiler->benchmark(function() use ($bodies) {
            $collisions = 0;
            for ($i = 0; $i < count($bodies); $i++) {
                for ($j = $i + 1; $j < count($bodies); $j++) {
                    $dx = $bodies[$i]['position'][0] - $bodies[$j]['position'][0];
                    $dy = $bodies[$i]['position'][1] - $bodies[$j]['position'][1];
                    $dz = $bodies[$i]['position'][2] - $bodies[$j]['position'][2];

                    $distance = sqrt($dx*$dx + $dy*$dy + $dz*$dz);

                    if ($distance < 2.0) {
                        $collisions++;
                    }
                }
            }
        }, 100);

        $this->assertLessThan(50, $result['time']['avg']);
        echo "\nCollision Detection (100 bodies): {$result['time']['avg']}ms avg\n";
    }

    /**
     * Benchmark pattern matching
     */
    public function test_benchmark_pattern_matching() {
        $patterns = [];
        for ($i = 0; $i < 100; $i++) {
            $patterns[] = TestDataFactory::createPatternData();
        }

        $query = TestDataFactory::createPatternData();

        $result = $this->profiler->benchmark(function() use ($patterns, $query) {
            $matches = array_filter($patterns, function($p) use ($query) {
                return $p['pattern_type'] === $query['pattern_type'];
            });
        }, 1000);

        $this->assertLessThan(5, $result['time']['avg']);
        echo "\nPattern Matching: {$result['time']['avg']}ms avg\n";
    }

    /**
     * Benchmark full workflow performance
     */
    public function test_benchmark_full_workflow() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $this->profiler->start('full_workflow');

        // 1. Create blueprint
        $blueprint = TestDataFactory::createBlueprint();

        // 2. Create simulation
        $sim_params = TestDataFactory::createSimulationParams();

        // 3. Store in memory
        $memory_data = TestDataFactory::createMemoryData([
            'data' => ['blueprint' => $blueprint],
        ]);

        // 4. Create embedding
        $embedding = TestDataFactory::createVector(768);

        // 5. Create pattern
        $pattern = TestDataFactory::createPatternData();

        $result = $this->profiler->end('full_workflow');

        $time_ms = $result['duration'] * 1000;

        $this->assertLessThan(50, $time_ms);
        echo "\nFull Workflow: {$time_ms}ms\n";
    }

    /**
     * Benchmark concurrent operations
     */
    public function test_benchmark_concurrent_operations() {
        $operations = 100;

        $this->profiler->start('concurrent');

        for ($i = 0; $i < $operations; $i++) {
            $blueprint = TestDataFactory::createBlueprint();
            $params = TestDataFactory::createPhysicsParams();
            $memory = TestDataFactory::createMemoryData();
        }

        $result = $this->profiler->end('concurrent');

        $avg_per_op = ($result['duration'] * 1000) / $operations;

        $this->assertLessThan(5, $avg_per_op);
        echo "\nConcurrent Operations ({$operations}): {$avg_per_op}ms per operation\n";
    }

    /**
     * Generate comprehensive performance report
     */
    public function test_generate_performance_report() {
        $report = $this->profiler->generateReport();

        $this->assertArrayHasKeys(['total_profiles', 'profiles', 'summary'], $report);

        echo "\n\n=== PERFORMANCE REPORT ===\n";
        echo "Total Profiles: {$report['total_profiles']}\n";
        echo "Total Duration: {$report['summary']['total_duration_ms']}ms\n";
        echo "Total Memory: {$report['summary']['total_memory_mb']}MB\n";
        echo "Total Queries: {$report['summary']['total_queries']}\n";

        if ($report['summary']['slowest_operation']) {
            echo "Slowest Operation: {$report['summary']['slowest_operation']}\n";
        }

        if ($report['summary']['most_memory_intensive']) {
            echo "Most Memory Intensive: {$report['summary']['most_memory_intensive']}\n";
        }
    }
}
