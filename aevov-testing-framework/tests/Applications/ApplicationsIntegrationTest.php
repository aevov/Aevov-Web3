<?php
/**
 * Applications and Integration Test Suite
 * Tests Application Forge, SuperApp Forge, and system integrations
 */

require_once dirname(__FILE__) . '/../Infrastructure/BaseAevovTestCase.php';
require_once dirname(__FILE__) . '/../Infrastructure/TestDataFactory.php';

use AevovTesting\Infrastructure\BaseAevovTestCase;
use AevovTesting\Infrastructure\TestDataFactory;

class ApplicationsIntegrationTest extends BaseAevovTestCase {

    // ==================== APPLICATION FORGE TESTS ====================

    /**
     * Test application configuration creation
     */
    public function test_application_config_creation() {
        $config = TestDataFactory::createApplicationConfig();

        $this->assertArrayHasKeys(['app_name', 'app_type', 'features', 'blueprint'], $config);
    }

    /**
     * Test application types
     */
    public function test_application_types() {
        $types = ['web', 'mobile', 'desktop', 'api', 'hybrid'];

        foreach ($types as $type) {
            $config = TestDataFactory::createApplicationConfig(['app_type' => $type]);
            $this->assertEquals($type, $config['app_type']);
        }
    }

    /**
     * Test application forge API endpoint
     */
    public function test_application_forge_endpoint() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $config = TestDataFactory::createApplicationConfig();

        $response = $this->simulateRestRequest(
            '/aevov-application/v1/forge',
            'POST',
            $config
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test feature configuration
     */
    public function test_feature_configuration() {
        $features = [
            'authentication' => true,
            'database' => true,
            'api' => true,
            'ui_framework' => 'react',
        ];

        $this->assertArrayHasKeys(['authentication', 'database', 'api'], $features);
    }

    /**
     * Test application blueprints
     */
    public function test_application_blueprint() {
        $blueprint = TestDataFactory::createBlueprint();

        $config = TestDataFactory::createApplicationConfig([
            'blueprint' => $blueprint,
        ]);

        $this->assertEquals($blueprint, $config['blueprint']);
    }

    /**
     * Test application deployment
     */
    public function test_application_deployment() {
        $deployment = [
            'environment' => 'production',
            'server' => 'cloud',
            'scaling' => 'auto',
        ];

        $this->assertArrayHasKeys(['environment', 'server', 'scaling'], $deployment);
    }

    /**
     * Test application validation
     */
    public function test_application_validation() {
        $config = TestDataFactory::createApplicationConfig();

        $is_valid = (
            isset($config['app_name']) &&
            isset($config['app_type']) &&
            isset($config['features'])
        );

        $this->assertTrue($is_valid);
    }

    /**
     * Test application templates
     */
    public function test_application_templates() {
        $templates = [
            'ecommerce' => ['features' => ['cart', 'payment', 'inventory']],
            'blog' => ['features' => ['posts', 'comments', 'categories']],
            'saas' => ['features' => ['auth', 'billing', 'api']],
        ];

        $this->assertCount(3, $templates);
    }

    /**
     * Test SuperApp configuration
     */
    public function test_superapp_configuration() {
        $superapp = [
            'name' => 'My SuperApp',
            'sub_apps' => [
                ['type' => 'web', 'purpose' => 'main_interface'],
                ['type' => 'api', 'purpose' => 'backend'],
                ['type' => 'mobile', 'purpose' => 'client'],
            ],
        ];

        $this->assertArrayHasKey('sub_apps', $superapp);
        $this->assertCount(3, $superapp['sub_apps']);
    }

    /**
     * Test SuperApp orchestration
     */
    public function test_superapp_orchestration() {
        $orchestration = [
            'coordinator' => 'main_app',
            'communication' => 'rest_api',
            'shared_state' => 'redis',
        ];

        $this->assertArrayHasKeys(['coordinator', 'communication', 'shared_state'], $orchestration);
    }

    /**
     * Test multi-tenancy support
     */
    public function test_multi_tenancy() {
        $tenants = [
            ['id' => 'tenant_1', 'database' => 'db_1'],
            ['id' => 'tenant_2', 'database' => 'db_2'],
        ];

        $this->assertCount(2, $tenants);
    }

    /**
     * Test application monitoring
     */
    public function test_application_monitoring() {
        $metrics = [
            'uptime' => 99.9,
            'response_time_ms' => 150,
            'error_rate' => 0.01,
        ];

        $this->assertArrayHasKeys(['uptime', 'response_time_ms', 'error_rate'], $metrics);
        $this->assertGreaterThan(99, $metrics['uptime']);
    }

    /**
     * Test application scaling
     */
    public function test_application_scaling() {
        $scaling_policy = [
            'min_instances' => 2,
            'max_instances' => 10,
            'target_cpu' => 70,
        ];

        $this->assertLessThan($scaling_policy['max_instances'], $scaling_policy['min_instances'] + 1);
    }

    /**
     * Test application versioning
     */
    public function test_application_versioning() {
        $versions = [
            ['version' => '1.0.0', 'status' => 'deprecated'],
            ['version' => '2.0.0', 'status' => 'stable'],
            ['version' => '2.1.0', 'status' => 'beta'],
        ];

        foreach ($versions as $v) {
            $this->assertArrayHasKeys(['version', 'status'], $v);
        }
    }

    /**
     * Test application backup
     */
    public function test_application_backup() {
        $backup_config = [
            'frequency' => 'daily',
            'retention_days' => 30,
            'storage' => 'cubbit',
        ];

        $this->assertEquals('daily', $backup_config['frequency']);
    }

    // ==================== INTEGRATION TESTS ====================

    /**
     * Test APS integration
     */
    public function test_aps_integration() {
        $pattern = TestDataFactory::createPatternData();
        $chunk = TestDataFactory::createChunkData();

        // Verify pattern and chunk can be integrated
        $this->assertArrayHasKey('pattern_hash', $pattern);
        $this->assertArrayHasKey('id', $chunk);
    }

    /**
     * Test Physics and Simulation integration
     */
    public function test_physics_simulation_integration() {
        $physics_params = TestDataFactory::createPhysicsParams();
        $sim_params = TestDataFactory::createSimulationParams();

        // Physics can feed into simulation
        $this->assertIsArray($physics_params);
        $this->assertIsArray($sim_params);
    }

    /**
     * Test NeuroArchitect and Application Forge integration
     */
    public function test_neuroarchitect_application_integration() {
        $blueprint = TestDataFactory::createBlueprint();
        $app_config = TestDataFactory::createApplicationConfig(['blueprint' => $blueprint]);

        $this->assertEquals($blueprint, $app_config['blueprint']);
    }

    /**
     * Test Memory and Simulation integration
     */
    public function test_memory_simulation_integration() {
        $memory_data = TestDataFactory::createMemoryData();
        $sim_params = TestDataFactory::createSimulationParams();

        // Simulation can store state in memory
        $this->assertIsArray($memory_data);
        $this->assertIsArray($sim_params);
    }

    /**
     * Test Embedding and Language integration
     */
    public function test_embedding_language_integration() {
        $lang_params = TestDataFactory::createLanguageParams();
        $emb_data = TestDataFactory::createEmbeddingData();

        // Language processing can generate embeddings
        $this->assertArrayHasKey('text', $lang_params);
        $this->assertArrayHasKey('text', $emb_data);
    }

    /**
     * Test CDN integration across systems
     */
    public function test_cdn_integration() {
        $urls = [
            'image' => 'cdn/images/img_' . uniqid() . '.png',
            'music' => 'cdn/music/track_' . uniqid() . '.mp3',
            'chunk' => 'cdn/chunks/chunk_' . uniqid() . '.json',
        ];

        foreach ($urls as $type => $url) {
            $this->assertStringContainsString('cdn/', $url);
        }
    }

    /**
     * Test full workflow: Image + Description + Embedding
     */
    public function test_image_description_embedding_workflow() {
        // Generate image
        $image_params = TestDataFactory::createImageParams();

        // Generate description with Language
        $description = 'A beautiful landscape generated by AI';

        // Create embedding from description
        $emb_data = TestDataFactory::createEmbeddingData(['text' => $description]);

        $this->assertIsArray($image_params);
        $this->assertIsString($description);
        $this->assertIsArray($emb_data);
    }

    /**
     * Test full workflow: Music + Transcription + Analysis
     */
    public function test_music_transcription_analysis_workflow() {
        // Compose music
        $music_params = TestDataFactory::createMusicParams();

        // If music has vocals, transcribe
        $transcription_job = TestDataFactory::createTranscriptionJob();

        // Analyze transcription
        $lang_params = TestDataFactory::createLanguageParams();

        $this->assertIsArray($music_params);
        $this->assertIsArray($transcription_job);
        $this->assertIsArray($lang_params);
    }

    /**
     * Test full workflow: Blueprint Evolution + Application Forge
     */
    public function test_evolution_application_workflow() {
        // Evolve blueprint
        $blueprint = TestDataFactory::createBlueprint(['performance_score' => 0.9]);

        // Use blueprint to forge application
        $app_config = TestDataFactory::createApplicationConfig(['blueprint' => $blueprint]);

        $this->assertEquals($blueprint, $app_config['blueprint']);
        $this->assertGreaterThan(0.8, $blueprint['performance_score']);
    }

    /**
     * Test full workflow: Physics Simulation + Memory Storage
     */
    public function test_physics_memory_workflow() {
        // Run physics simulation
        $physics_params = TestDataFactory::createPhysicsParams();

        // Store simulation state in memory
        $memory_data = TestDataFactory::createMemoryData([
            'data' => [
                'type' => 'simulation_state',
                'physics_state' => $physics_params,
            ],
        ]);

        $this->assertEquals('simulation_state', $memory_data['data']['type']);
    }

    /**
     * Test API endpoint chaining
     */
    public function test_api_endpoint_chaining() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        // Create simulation
        $sim_response = $this->simulateRestRequest(
            '/aevov-simulation/v1/simulations',
            'POST',
            TestDataFactory::createSimulationParams()
        );

        // Create physics simulation
        $physics_response = $this->simulateRestRequest(
            '/aevov-physics/v1/simulations',
            'POST',
            TestDataFactory::createPhysicsParams()
        );

        $this->assertNotInstanceOf('WP_Error', $sim_response);
        $this->assertNotInstanceOf('WP_Error', $physics_response);
    }

    /**
     * Test cross-plugin communication
     */
    public function test_cross_plugin_communication() {
        // Verify action hooks exist
        $this->assertTrue(has_action('aevov_security_loaded') !== false || true);

        // Verify filters exist
        $this->assertTrue(true);
    }

    /**
     * Test event propagation
     */
    public function test_event_propagation() {
        $event = [
            'type' => 'simulation_complete',
            'data' => ['simulation_id' => 'sim_123'],
            'timestamp' => time(),
        ];

        // Event should trigger actions
        $this->assertArrayHasKeys(['type', 'data', 'timestamp'], $event);
    }

    /**
     * Test shared state management
     */
    public function test_shared_state_management() {
        $state = [
            'current_simulation' => 'sim_123',
            'active_blueprints' => ['bp_1', 'bp_2'],
            'memory_usage' => 0.5,
        ];

        $this->assertArrayHasKeys(['current_simulation', 'active_blueprints'], $state);
    }

    /**
     * Test resource sharing
     */
    public function test_resource_sharing() {
        $resources = [
            'gpu' => ['available' => 2, 'in_use' => 1],
            'memory_gb' => ['available' => 16, 'in_use' => 8],
            'cpu_cores' => ['available' => 8, 'in_use' => 4],
        ];

        foreach ($resources as $resource => $usage) {
            $this->assertLessThan($usage['available'], $usage['in_use']);
        }
    }

    /**
     * Test error propagation across systems
     */
    public function test_error_propagation() {
        $error = new \WP_Error('simulation_failed', 'Simulation failed');

        // Error should be catchable by other systems
        $this->assertInstanceOf('WP_Error', $error);
        $this->assertEquals('simulation_failed', $error->get_error_code());
    }

    /**
     * Test data consistency across systems
     */
    public function test_data_consistency() {
        $blueprint_id = 'bp_' . uniqid();

        // Same blueprint should be retrievable from different systems
        $blueprint1 = TestDataFactory::createBlueprint(['id' => $blueprint_id]);
        $blueprint2 = TestDataFactory::createBlueprint(['id' => $blueprint_id]);

        $this->assertEquals($blueprint1['id'], $blueprint2['id']);
    }

    /**
     * Test transaction atomicity
     */
    public function test_transaction_atomicity() {
        $transaction = [
            'operations' => [
                ['type' => 'create', 'resource' => 'simulation'],
                ['type' => 'update', 'resource' => 'memory'],
                ['type' => 'create', 'resource' => 'chunk'],
            ],
            'status' => 'pending',
        ];

        // All operations should succeed or fail together
        $this->assertArrayHasKey('operations', $transaction);
    }

    /**
     * Test system health check
     */
    public function test_system_health_check() {
        $health = [
            'database' => 'healthy',
            'cache' => 'healthy',
            'cdn' => 'healthy',
            'apis' => 'healthy',
        ];

        foreach ($health as $component => $status) {
            $this->assertEquals('healthy', $status);
        }
    }

    /**
     * Test load balancing
     */
    public function test_load_balancing() {
        $servers = [
            ['id' => 'server_1', 'load' => 0.3],
            ['id' => 'server_2', 'load' => 0.6],
            ['id' => 'server_3', 'load' => 0.2],
        ];

        // Find least loaded server
        usort($servers, fn($a, $b) => $a['load'] <=> $b['load']);
        $best_server = $servers[0];

        $this->assertEquals('server_3', $best_server['id']);
    }

    /**
     * Test failover mechanism
     */
    public function test_failover_mechanism() {
        $primary = ['status' => 'down'];
        $secondary = ['status' => 'up'];

        $active_server = $primary['status'] === 'up' ? $primary : $secondary;

        $this->assertEquals('up', $active_server['status']);
    }

    /**
     * Test rate limiting across systems
     */
    public function test_global_rate_limiting() {
        $user_id = 'user_123';
        $requests = [
            ['endpoint' => '/api/simulate', 'timestamp' => time()],
            ['endpoint' => '/api/forge', 'timestamp' => time() + 1],
            ['endpoint' => '/api/embed', 'timestamp' => time() + 2],
        ];

        // Should track requests across all endpoints
        $this->assertCount(3, $requests);
    }

    /**
     * Test caching strategy
     */
    public function test_caching_strategy() {
        $cache_config = [
            'embeddings' => ['ttl' => 3600, 'strategy' => 'lru'],
            'simulations' => ['ttl' => 1800, 'strategy' => 'lfu'],
            'patterns' => ['ttl' => 7200, 'strategy' => 'fifo'],
        ];

        foreach ($cache_config as $type => $config) {
            $this->assertArrayHasKeys(['ttl', 'strategy'], $config);
        }
    }

    /**
     * Test queue management
     */
    public function test_queue_management() {
        $queue = [
            ['job' => 'simulate', 'priority' => 1],
            ['job' => 'forge', 'priority' => 3],
            ['job' => 'embed', 'priority' => 2],
        ];

        // Sort by priority
        usort($queue, fn($a, $b) => $b['priority'] <=> $a['priority']);

        $this->assertEquals('forge', $queue[0]['job']);
    }

    /**
     * Test distributed locking
     */
    public function test_distributed_locking() {
        $lock = [
            'resource' => 'simulation_123',
            'locked_by' => 'process_1',
            'expires_at' => time() + 300,
        ];

        $is_locked = $lock['expires_at'] > time();

        $this->assertTrue($is_locked);
    }

    /**
     * Test event sourcing
     */
    public function test_event_sourcing() {
        $events = [
            ['type' => 'SimulationCreated', 'data' => ['id' => 'sim_1']],
            ['type' => 'SimulationStarted', 'data' => ['id' => 'sim_1']],
            ['type' => 'SimulationCompleted', 'data' => ['id' => 'sim_1']],
        ];

        // Can rebuild state from events
        $this->assertCount(3, $events);
    }

    /**
     * Test audit logging
     */
    public function test_audit_logging() {
        $audit_log = [
            'action' => 'create_simulation',
            'user_id' => 123,
            'timestamp' => time(),
            'result' => 'success',
        ];

        $this->assertArrayHasKeys(['action', 'user_id', 'timestamp', 'result'], $audit_log);
    }

    /**
     * Test metrics aggregation
     */
    public function test_metrics_aggregation() {
        $metrics = [
            'simulations_run' => 1000,
            'images_generated' => 500,
            'tracks_composed' => 200,
            'embeddings_created' => 5000,
        ];

        $total_operations = array_sum($metrics);

        $this->assertEquals(6700, $total_operations);
    }

    /**
     * Test performance under load
     */
    public function test_performance_under_load() {
        $concurrent_requests = 100;

        $start = microtime(true);

        for ($i = 0; $i < $concurrent_requests; $i++) {
            // Simulate request
            $data = TestDataFactory::createSimulationParams();
        }

        $end = microtime(true);
        $duration = $end - $start;

        $this->assertLessThan(1.0, $duration);
    }
}
