<?php
/**
 * Physics Engine REST API
 *
 * Endpoints for physics simulation control, world generation, and state management
 */

namespace Aevov\PhysicsEngine\API;

use Aevov\Security\SecurityHelper;

use Aevov\PhysicsEngine\Core\PhysicsCore;
use Aevov\PhysicsEngine\World\WorldGenerator;

class PhysicsEndpoint {

    public function register_routes() {
        // World generation endpoints
        register_rest_route('aevov-physics/v1', '/world/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_world'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        register_rest_route('aevov-physics/v1', '/world/(?P<world_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_world'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        // Physics simulation endpoints
        register_rest_route('aevov-physics/v1', '/simulate/start', [
            'methods' => 'POST',
            'callback' => [$this, 'start_simulation'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        register_rest_route('aevov-physics/v1', '/simulate/stop/(?P<simulation_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'stop_simulation'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        register_rest_route('aevov-physics/v1', '/simulate/state/(?P<simulation_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_simulation_state'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        // Entity manipulation
        register_rest_route('aevov-physics/v1', '/entity/add', [
            'methods' => 'POST',
            'callback' => [$this, 'add_entity'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        register_rest_route('aevov-physics/v1', '/entity/remove', [
            'methods' => 'POST',
            'callback' => [$this, 'remove_entity'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        register_rest_route('aevov-physics/v1', '/force/apply', [
            'methods' => 'POST',
            'callback' => [$this, 'apply_force'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        // Field manipulation
        register_rest_route('aevov-physics/v1', '/field/create', [
            'methods' => 'POST',
            'callback' => [$this, 'create_field'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        register_rest_route('aevov-physics/v1', '/field/remove', [
            'methods' => 'POST',
            'callback' => [$this, 'remove_field'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        // Constraint manipulation
        register_rest_route('aevov-physics/v1', '/constraint/create', [
            'methods' => 'POST',
            'callback' => [$this, 'create_constraint'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        // Distributed physics (AevIP)
        register_rest_route('aevov-physics/v1', '/distributed/node/register', [
            'methods' => 'POST',
            'callback' => [$this, 'register_compute_node'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);

        register_rest_route('aevov-physics/v1', '/distributed/workload/distribute', [
            'methods' => 'POST',
            'callback' => [$this, 'distribute_workload'],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov']
        ]);
    }

    /**
     * Generate world
     */
    public function generate_world($request) {
        $world_config = $request->get_json_params();

        if (empty($world_config)) {
            return new \WP_Error('missing_config', 'World configuration is required', ['status' => 400]);
        }

        $world_generator = new WorldGenerator();
        $world = $world_generator->generate_world($world_config);

        // Store world
        $world_id = $world_config['world_id'] ?? 'world_' . uniqid();
        update_option('aevov_physics_world_data_' . $world_id, $world);

        return [
            'success' => true,
            'world_id' => $world_id,
            'world' => $world,
            'statistics' => $world['metadata']['statistics']
        ];
    }

    /**
     * Get world data
     */
    public function get_world($request) {
        $world_id = $request['world_id'];
        $world = get_option('aevov_physics_world_data_' . $world_id);

        if (!$world) {
            return new \WP_Error('world_not_found', 'World not found', ['status' => 404]);
        }

        return [
            'success' => true,
            'world_id' => $world_id,
            'world' => $world
        ];
    }

    /**
     * Start physics simulation
     */
    public function start_simulation($request) {
        $params = $request->get_json_params();

        $simulation_id = $params['simulation_id'] ?? 'sim_' . uniqid();
        $blueprint = $params['blueprint'] ?? [];

        // Initialize physics world
        $physics_core = new PhysicsCore();
        $simulation_data = ['job_id' => $simulation_id];
        $simulation_data = $physics_core->initialize_physics_world($simulation_data, $blueprint);

        return [
            'success' => true,
            'simulation_id' => $simulation_id,
            'simulation_data' => $simulation_data
        ];
    }

    /**
     * Stop simulation
     */
    public function stop_simulation($request) {
        $simulation_id = $request['simulation_id'];

        // Clean up physics world
        delete_option('aevov_physics_world_' . $simulation_id);

        return [
            'success' => true,
            'message' => 'Simulation stopped'
        ];
    }

    /**
     * Get simulation state
     */
    public function get_simulation_state($request) {
        $simulation_id = $request['simulation_id'];

        $physics_core = new PhysicsCore();
        $state = $physics_core->get_physics_state($simulation_id);

        if (!$state) {
            return new \WP_Error('simulation_not_found', 'Simulation not found', ['status' => 404]);
        }

        return [
            'success' => true,
            'simulation_id' => $simulation_id,
            'state' => $state
        ];
    }

    /**
     * Add entity to simulation
     */
    public function add_entity($request) {
        $params = $request->get_json_params();
        $simulation_id = $params['simulation_id'];
        $entity = $params['entity'];

        $physics_world = get_option('aevov_physics_world_' . $simulation_id);
        if (!$physics_world) {
            return new \WP_Error('simulation_not_found', 'Simulation not found', ['status' => 404]);
        }

        // Create physics entity
        $physics_core = new PhysicsCore();
        $physics_entity = $this->create_physics_entity($entity, $physics_world['config']);

        $physics_world['entities'][] = $physics_entity;
        update_option('aevov_physics_world_' . $simulation_id, $physics_world);

        return [
            'success' => true,
            'entity_id' => $physics_entity['id']
        ];
    }

    /**
     * Remove entity from simulation
     */
    public function remove_entity($request) {
        $params = $request->get_json_params();
        $simulation_id = $params['simulation_id'];
        $entity_id = $params['entity_id'];

        $physics_world = get_option('aevov_physics_world_' . $simulation_id);
        if (!$physics_world) {
            return new \WP_Error('simulation_not_found', 'Simulation not found', ['status' => 404]);
        }

        // Remove entity
        $physics_world['entities'] = array_filter($physics_world['entities'], function($entity) use ($entity_id) {
            return $entity['id'] !== $entity_id;
        });

        update_option('aevov_physics_world_' . $simulation_id, $physics_world);

        return [
            'success' => true,
            'message' => 'Entity removed'
        ];
    }

    /**
     * Apply force to entity
     */
    public function apply_force($request) {
        $params = $request->get_json_params();
        $simulation_id = $params['simulation_id'];
        $entity_id = $params['entity_id'] ?? null;
        $force = $params['force'];

        $physics_world = get_option('aevov_physics_world_' . $simulation_id);
        if (!$physics_world) {
            return new \WP_Error('simulation_not_found', 'Simulation not found', ['status' => 404]);
        }

        // Add force to world
        $physics_world['forces'][] = [
            'target_id' => $entity_id,
            'force' => $force,
            'duration' => $params['duration'] ?? 1
        ];

        update_option('aevov_physics_world_' . $simulation_id, $physics_world);

        return [
            'success' => true,
            'message' => 'Force applied'
        ];
    }

    /**
     * Create force field
     */
    public function create_field($request) {
        $params = $request->get_json_params();
        $simulation_id = $params['simulation_id'];
        $field = $params['field'];

        $physics_world = get_option('aevov_physics_world_' . $simulation_id);
        if (!$physics_world) {
            return new \WP_Error('simulation_not_found', 'Simulation not found', ['status' => 404]);
        }

        $field['id'] = $field['id'] ?? 'field_' . uniqid();
        $physics_world['fields'][] = $field;

        update_option('aevov_physics_world_' . $simulation_id, $physics_world);

        return [
            'success' => true,
            'field_id' => $field['id']
        ];
    }

    /**
     * Remove field
     */
    public function remove_field($request) {
        $params = $request->get_json_params();
        $simulation_id = $params['simulation_id'];
        $field_id = $params['field_id'];

        $physics_world = get_option('aevov_physics_world_' . $simulation_id);
        if (!$physics_world) {
            return new \WP_Error('simulation_not_found', 'Simulation not found', ['status' => 404]);
        }

        $physics_world['fields'] = array_filter($physics_world['fields'], function($field) use ($field_id) {
            return $field['id'] !== $field_id;
        });

        update_option('aevov_physics_world_' . $simulation_id, $physics_world);

        return [
            'success' => true,
            'message' => 'Field removed'
        ];
    }

    /**
     * Create constraint
     */
    public function create_constraint($request) {
        $params = $request->get_json_params();
        $simulation_id = $params['simulation_id'];
        $constraint = $params['constraint'];

        $physics_world = get_option('aevov_physics_world_' . $simulation_id);
        if (!$physics_world) {
            return new \WP_Error('simulation_not_found', 'Simulation not found', ['status' => 404]);
        }

        $constraint['id'] = $constraint['id'] ?? 'constraint_' . uniqid();
        $physics_world['constraints'][] = $constraint;

        update_option('aevov_physics_world_' . $simulation_id, $physics_world);

        return [
            'success' => true,
            'constraint_id' => $constraint['id']
        ];
    }

    /**
     * Register compute node for distributed physics
     */
    public function register_compute_node($request) {
        $params = $request->get_json_params();
        $node_id = $params['node_id'];
        $node_capabilities = $params['capabilities'];

        // Store node info
        $nodes = get_option('aevov_physics_compute_nodes', []);
        $nodes[$node_id] = [
            'id' => $node_id,
            'capabilities' => $node_capabilities,
            'status' => 'active',
            'registered_at' => time()
        ];
        update_option('aevov_physics_compute_nodes', $nodes);

        return [
            'success' => true,
            'node_id' => $node_id,
            'message' => 'Compute node registered'
        ];
    }

    /**
     * Distribute physics workload across nodes (AevIP integration)
     */
    public function distribute_workload($request) {
        $params = $request->get_json_params();
        $simulation_id = $params['simulation_id'];

        $physics_world = get_option('aevov_physics_world_' . $simulation_id);
        if (!$physics_world) {
            return new \WP_Error('simulation_not_found', 'Simulation not found', ['status' => 404]);
        }

        $nodes = get_option('aevov_physics_compute_nodes', []);
        if (empty($nodes)) {
            return new \WP_Error('no_nodes', 'No compute nodes available', ['status' => 503]);
        }

        // Partition entities across nodes
        $entity_count = count($physics_world['entities']);
        $node_count = count($nodes);
        $entities_per_node = ceil($entity_count / $node_count);

        $workload_distribution = [];
        $node_ids = array_keys($nodes);

        for ($i = 0; $i < $node_count; $i++) {
            $start = $i * $entities_per_node;
            $end = min(($i + 1) * $entities_per_node, $entity_count);

            $workload_distribution[$node_ids[$i]] = [
                'entity_indices' => range($start, $end - 1),
                'entity_count' => $end - $start
            ];
        }

        return [
            'success' => true,
            'distribution' => $workload_distribution,
            'total_nodes' => $node_count,
            'total_entities' => $entity_count
        ];
    }

    /**
     * Helper: Create physics entity
     */
    private function create_physics_entity($entity, $config) {
        return [
            'id' => $entity['id'] ?? uniqid('entity_'),
            'type' => $entity['type'] ?? 'rigid_body',
            'active' => true,
            'mass' => $entity['mass'] ?? 1.0,
            'inverse_mass' => 1.0 / ($entity['mass'] ?? 1.0),
            'restitution' => $entity['restitution'] ?? 0.5,
            'friction' => $entity['friction'] ?? 0.3,
            'position' => $entity['position'] ?? ['x' => 0, 'y' => 0, 'z' => 0],
            'rotation' => $entity['rotation'] ?? ['x' => 0, 'y' => 0, 'z' => 0, 'w' => 1],
            'scale' => $entity['scale'] ?? ['x' => 1, 'y' => 1, 'z' => 1],
            'velocity' => $entity['velocity'] ?? ['x' => 0, 'y' => 0, 'z' => 0],
            'angular_velocity' => $entity['angular_velocity'] ?? ['x' => 0, 'y' => 0, 'z' => 0],
            'acceleration' => ['x' => 0, 'y' => 0, 'z' => 0],
            'force_accumulator' => ['x' => 0, 'y' => 0, 'z' => 0],
            'torque_accumulator' => ['x' => 0, 'y' => 0, 'z' => 0],
            'collider' => $entity['collider'] ?? ['type' => 'sphere', 'radius' => 1.0],
            'original_data' => $entity
        ];
    }
}
