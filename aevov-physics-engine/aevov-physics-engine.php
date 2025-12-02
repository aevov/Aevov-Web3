<?php
/**
 * Plugin Name: Aevov Physics Engine
 * Plugin URI: https://aevov.com/physics-engine
 * Description: Advanced physics engine for spatial world generation - far beyond physX-Anything with multi-scale simulation, evolutionary physics, and neural-physics hybrid capabilities
 * Version: 1.0.0
 * Author: Aevov Team
 * License: MIT
 *
 * Capabilities:
 * - Spatial world generation (procedural terrain, buildings, ecosystems)
 * - Multi-scale physics (quantum → newtonian → relativistic → cosmic)
 * - Evolutionary physics (physics rules that evolve)
 * - Neural-physics hybrid (AI-learned behaviors)
 * - Distributed physics processing
 * - Stable deformable/soft body simulation
 * - Fluid dynamics (liquids, gases, plasma)
 * - Field simulation (electromagnetic, gravitational, custom)
 * - Constraint evolution and adaptation
 * - Blueprint-driven physics configuration
 */

namespace Aevov\PhysicsEngine;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('AEVOV_PHYSICS_VERSION', '1.0.0');
define('AEVOV_PHYSICS_DIR', plugin_dir_path(__FILE__));
define('AEVOV_PHYSICS_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Aevov\\PhysicsEngine\\';
    $base_dir = AEVOV_PHYSICS_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
add_action('plugins_loaded', function() {
    // Initialize core components
    $physics_core = new Core\PhysicsCore();
    $world_generator = new World\WorldGenerator();
    $physics_api = new API\PhysicsEndpoint();

    // Register hooks
    add_action('rest_api_init', [$physics_api, 'register_routes']);
    add_action('init', [$physics_core, 'initialize']);

    // Integration with simulation engine
    add_filter('aevov_simulation_physics_enabled', '__return_true');
    add_action('aevov_simulation_tick', [$physics_core, 'simulate_physics'], 10, 2);
});

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create physics tables
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevov_physics_entities (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        entity_id varchar(64) NOT NULL,
        simulation_id varchar(64) NOT NULL,
        entity_type varchar(32) NOT NULL,
        mass float NOT NULL DEFAULT 1.0,
        position_x float NOT NULL DEFAULT 0.0,
        position_y float NOT NULL DEFAULT 0.0,
        position_z float NOT NULL DEFAULT 0.0,
        velocity_x float NOT NULL DEFAULT 0.0,
        velocity_y float NOT NULL DEFAULT 0.0,
        velocity_z float NOT NULL DEFAULT 0.0,
        rotation_x float NOT NULL DEFAULT 0.0,
        rotation_y float NOT NULL DEFAULT 0.0,
        rotation_z float NOT NULL DEFAULT 0.0,
        angular_velocity_x float NOT NULL DEFAULT 0.0,
        angular_velocity_y float NOT NULL DEFAULT 0.0,
        angular_velocity_z float NOT NULL DEFAULT 0.0,
        properties longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY entity_id (entity_id),
        KEY simulation_id (simulation_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Initialize default physics blueprints
    update_option('aevov_physics_default_blueprints', [
        'newtonian_3d' => [
            'name' => 'Newtonian 3D Physics',
            'type' => 'physics',
            'engine' => 'newtonian',
            'dimensions' => 3,
            'gravity' => 9.81,
            'timestep' => 0.016,
            'max_velocity' => 1000.0,
            'drag_coefficient' => 0.1
        ],
        'spatial_world' => [
            'name' => 'Spatial World Generation',
            'type' => 'world_generation',
            'terrain' => [
                'algorithm' => 'perlin_noise',
                'octaves' => 6,
                'persistence' => 0.5,
                'scale' => 100.0,
                'height_multiplier' => 50.0
            ],
            'biomes' => [
                'enabled' => true,
                'types' => ['forest', 'desert', 'mountain', 'ocean', 'plains']
            ],
            'structures' => [
                'enabled' => true,
                'density' => 0.01,
                'types' => ['building', 'tree', 'rock', 'water_source']
            ]
        ]
    ]);

    // Set default options
    update_option('aevov_physics_engine_version', AEVOV_PHYSICS_VERSION);
    update_option('aevov_physics_distributed_enabled', false);
    update_option('aevov_physics_neural_hybrid_enabled', true);
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up scheduled events
    wp_clear_scheduled_hook('aevov_physics_cleanup');
});
