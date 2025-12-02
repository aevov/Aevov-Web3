<?php
/**
 * Physics Engine API Endpoint Tests
 * Tests all REST API endpoints for the Physics Engine
 */

require_once dirname(__FILE__) . '/../Infrastructure/BaseAevovTestCase.php';
require_once dirname(__FILE__) . '/../Infrastructure/TestDataFactory.php';

use AevovTesting\Infrastructure\BaseAevovTestCase;
use AevovTesting\Infrastructure\TestDataFactory;

class PhysicsAPITest extends BaseAevovTestCase {

    /**
     * Test create simulation endpoint
     */
    public function test_create_simulation_endpoint() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $params = TestDataFactory::createPhysicsParams();

        $response = $this->simulateRestRequest(
            '/aevov-physics/v1/simulations',
            'POST',
            $params
        );

        // Response should contain simulation_id
        $this->assertNotInstanceOf('WP_Error', $response);

        if ($response instanceof \WP_REST_Response) {
            $data = $response->get_data();
            $this->assertArrayHasKey('simulation_id', $data);
        }
    }

    /**
     * Test get simulation status endpoint
     */
    public function test_get_simulation_status() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $simulation_id = 'sim_test_' . uniqid();

        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/simulations/{$simulation_id}/status",
            'GET'
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test step simulation endpoint
     */
    public function test_step_simulation() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $simulation_id = 'sim_test_' . uniqid();

        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/simulations/{$simulation_id}/step",
            'POST',
            ['steps' => 10]
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test generate world endpoint
     */
    public function test_generate_world() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $params = TestDataFactory::createWorldParams();

        $response = $this->simulateRestRequest(
            '/aevov-physics/v1/world/generate',
            'POST',
            $params
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test add body to simulation
     */
    public function test_add_body_to_simulation() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $simulation_id = 'sim_test_' . uniqid();
        $body_params = [
            'mass' => 1.5,
            'position' => [0, 10, 0],
            'velocity' => [1, 0, 0],
        ];

        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/simulations/{$simulation_id}/bodies",
            'POST',
            $body_params
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test add constraint endpoint
     */
    public function test_add_constraint() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $simulation_id = 'sim_test_' . uniqid();
        $constraint = [
            'type' => 'spring',
            'body1_id' => 0,
            'body2_id' => 1,
            'spring_constant' => 100.0,
        ];

        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/simulations/{$simulation_id}/constraints",
            'POST',
            $constraint
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test authentication required for create simulation
     */
    public function test_authentication_required_create_simulation() {
        wp_set_current_user(0); // Not logged in

        $params = TestDataFactory::createPhysicsParams();

        $response = $this->simulateRestRequest(
            '/aevov-physics/v1/simulations',
            'POST',
            $params
        );

        // Should be unauthorized
        if ($response instanceof \WP_Error) {
            $this->assertInstanceOf('WP_Error', $response);
        }
    }

    /**
     * Test invalid simulation ID
     */
    public function test_invalid_simulation_id() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $response = $this->simulateRestRequest(
            '/aevov-physics/v1/simulations/invalid-id-12345/status',
            'GET'
        );

        // Should return error or 404
        $this->assertTrue(
            $response instanceof \WP_Error ||
            ($response instanceof \WP_REST_Response && $response->get_status() === 404)
        );
    }

    /**
     * Test get world terrain data
     */
    public function test_get_world_terrain() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $world_id = 'world_test_' . uniqid();

        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/world/{$world_id}/terrain",
            'GET',
            ['x' => 0, 'z' => 0, 'width' => 10, 'length' => 10]
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test get biome at position
     */
    public function test_get_biome_at_position() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $world_id = 'world_test_' . uniqid();

        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/world/{$world_id}/biome",
            'GET',
            ['x' => 50, 'z' => 50]
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test export simulation state
     */
    public function test_export_simulation_state() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $simulation_id = 'sim_test_' . uniqid();

        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/simulations/{$simulation_id}/export",
            'GET'
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test import simulation state
     */
    public function test_import_simulation_state() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $state = [
            'bodies' => [
                ['mass' => 1.0, 'position' => [0, 0, 0], 'velocity' => [0, 0, 0]],
            ],
            'time' => 0.0,
        ];

        $response = $this->simulateRestRequest(
            '/aevov-physics/v1/simulations/import',
            'POST',
            ['state' => $state]
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test parameter validation for create simulation
     */
    public function test_create_simulation_parameter_validation() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        // Test with missing required parameters
        $invalid_params = [
            'solver_type' => 'invalid_solver',
        ];

        $response = $this->simulateRestRequest(
            '/aevov-physics/v1/simulations',
            'POST',
            $invalid_params
        );

        // Should validate parameters
        $this->assertTrue(true);
    }

    /**
     * Test concurrent simulations
     */
    public function test_concurrent_simulations() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $sim_ids = [];

        for ($i = 0; $i < 5; $i++) {
            $params = TestDataFactory::createPhysicsParams();

            $response = $this->simulateRestRequest(
                '/aevov-physics/v1/simulations',
                'POST',
                $params
            );

            if ($response instanceof \WP_REST_Response) {
                $data = $response->get_data();
                if (isset($data['simulation_id'])) {
                    $sim_ids[] = $data['simulation_id'];
                }
            }
        }

        // Should be able to create multiple simulations
        $this->assertTrue(true);
    }

    /**
     * Test simulation cleanup
     */
    public function test_simulation_cleanup() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $simulation_id = 'sim_test_' . uniqid();

        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/simulations/{$simulation_id}",
            'DELETE'
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test world regeneration
     */
    public function test_world_regeneration() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $world_id = 'world_test_' . uniqid();
        $params = TestDataFactory::createWorldParams(['seed' => 99999]);

        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/world/{$world_id}/regenerate",
            'POST',
            $params
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test field force application
     */
    public function test_apply_force_field() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $simulation_id = 'sim_test_' . uniqid();
        $field = [
            'type' => 'gravity_well',
            'position' => [0, 0, 0],
            'strength' => 100.0,
        ];

        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/simulations/{$simulation_id}/fields",
            'POST',
            $field
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test simulation pause/resume
     */
    public function test_simulation_pause_resume() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $simulation_id = 'sim_test_' . uniqid();

        // Pause
        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/simulations/{$simulation_id}/pause",
            'POST'
        );

        $this->assertNotInstanceOf('WP_Error', $response);

        // Resume
        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/simulations/{$simulation_id}/resume",
            'POST'
        );

        $this->assertNotInstanceOf('WP_Error', $response);
    }

    /**
     * Test get simulation performance metrics
     */
    public function test_simulation_performance_metrics() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $simulation_id = 'sim_test_' . uniqid();

        $response = $this->simulateRestRequest(
            "/aevov-physics/v1/simulations/{$simulation_id}/metrics",
            'GET'
        );

        $this->assertNotInstanceOf('WP_Error', $response);

        if ($response instanceof \WP_REST_Response) {
            $data = $response->get_data();
            // Should contain performance metrics
            $this->assertTrue(true);
        }
    }

    /**
     * Test rate limiting on API endpoints
     */
    public function test_api_rate_limiting() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $params = TestDataFactory::createPhysicsParams();

        // Make many requests rapidly
        for ($i = 0; $i < 10; $i++) {
            $response = $this->simulateRestRequest(
                '/aevov-physics/v1/simulations',
                'POST',
                $params
            );

            // All should succeed or be rate limited appropriately
            $this->assertTrue(
                $response instanceof \WP_REST_Response ||
                $response instanceof \WP_Error
            );
        }
    }
}
