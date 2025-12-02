<?php
/**
 * Plugin Name: AROS - Aevov Robot Operating System
 * Description: Self-improving robot operating system built on Aevov's physics and spatial engine with comprehensive AI-driven capabilities
 * Version: 1.0.0
 * Author: Aevov Team
 * License: MIT
 *
 * AROS (Aevov Robot Operating System) is a revolutionary, self-improving robot operating
 * system that leverages the entire Aevov ecosystem for unprecedented robotic capabilities.
 *
 * Key Features:
 * - Self-improving neural architecture using NeuroArchitect
 * - Real-time physics simulation using Aevov Physics Engine
 * - Advanced spatial reasoning and SLAM
 * - Multi-modal perception (vision, audio, LiDAR)
 * - Cognitive task planning and decision making
 * - Multi-robot coordination
 * - Comprehensive safety systems
 * - Integration with entire Aevov ecosystem
 */

namespace AROS;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define AROS constants
define('AROS_VERSION', '1.0.0');
define('AROS_PATH', plugin_dir_path(__FILE__));
define('AROS_URL', plugin_dir_url(__FILE__));

// Load core systems
require_once AROS_PATH . 'aros-kernel/class-aros-kernel.php';
require_once AROS_PATH . 'aros-kernel/class-aros-runtime.php';
require_once AROS_PATH . 'aros-kernel/class-aros-boot.php';

// Load self-improvement systems
require_once AROS_PATH . 'aros-learning/class-reinforcement-learner.php';
require_once AROS_PATH . 'aros-learning/class-experience-replay.php';
require_once AROS_PATH . 'aros-learning/class-model-optimizer.php';
require_once AROS_PATH . 'aros-learning/class-self-improvement-engine.php';

// Load robot control systems
require_once AROS_PATH . 'aros-control/class-motor-controller.php';
require_once AROS_PATH . 'aros-control/class-joint-controller.php';
require_once AROS_PATH . 'aros-control/class-trajectory-planner.php';
require_once AROS_PATH . 'aros-control/class-inverse-kinematics.php';

// Load spatial systems
require_once AROS_PATH . 'aros-spatial/class-slam-engine.php';
require_once AROS_PATH . 'aros-spatial/class-path-planner.php';
require_once AROS_PATH . 'aros-spatial/class-obstacle-avoidance.php';
require_once AROS_PATH . 'aros-spatial/class-mapper.php';

// Load perception systems
require_once AROS_PATH . 'aros-perception/class-sensor-fusion.php';
require_once AROS_PATH . 'aros-perception/class-vision-processor.php';
require_once AROS_PATH . 'aros-perception/class-audio-processor.php';
require_once AROS_PATH . 'aros-perception/class-lidar-processor.php';

// Load cognition systems
require_once AROS_PATH . 'aros-cognition/class-task-planner.php';
require_once AROS_PATH . 'aros-cognition/class-decision-maker.php';
require_once AROS_PATH . 'aros-cognition/class-goal-manager.php';
require_once AROS_PATH . 'aros-cognition/class-behavior-tree.php';

// Load communication systems
require_once AROS_PATH . 'aros-comm/class-ros-bridge.php';
require_once AROS_PATH . 'aros-comm/class-multi-robot-protocol.php';
require_once AROS_PATH . 'aros-comm/class-human-robot-interface.php';

// Load safety systems
require_once AROS_PATH . 'aros-safety/class-collision-detector.php';
require_once AROS_PATH . 'aros-safety/class-emergency-stop.php';
require_once AROS_PATH . 'aros-safety/class-health-monitor.php';
require_once AROS_PATH . 'aros-safety/class-fault-tolerance.php';

// Load integration
require_once AROS_PATH . 'aros-integration/class-aevov-integrator.php';

// Load API
require_once AROS_PATH . 'aros-api/class-aros-endpoint.php';

/**
 * Main AROS Plugin Class
 */
class AROS {

    private static $instance = null;
    private $kernel;
    private $runtime;
    private $systems = [];

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    /**
     * Initialize AROS
     */
    private function init() {
        // Initialize kernel
        $this->kernel = new Kernel\AROSKernel();
        $this->runtime = new Kernel\AROSRuntime();

        // Boot AROS
        $boot = new Kernel\AROSBoot();
        $boot->boot();

        // Initialize all subsystems
        $this->init_learning_systems();
        $this->init_control_systems();
        $this->init_spatial_systems();
        $this->init_perception_systems();
        $this->init_cognition_systems();
        $this->init_communication_systems();
        $this->init_safety_systems();
        $this->init_integration();

        // Register REST API
        add_action('rest_api_init', [$this, 'register_api']);

        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Start self-improvement loop
        $this->start_self_improvement();

        do_action('aros_initialized');
    }

    /**
     * Initialize learning systems
     */
    private function init_learning_systems() {
        $this->systems['learner'] = new Learning\ReinforcementLearner();
        $this->systems['replay'] = new Learning\ExperienceReplay();
        $this->systems['optimizer'] = new Learning\ModelOptimizer();
        $this->systems['improvement'] = new Learning\SelfImprovementEngine();
    }

    /**
     * Initialize control systems
     */
    private function init_control_systems() {
        $this->systems['motor'] = new Control\MotorController();
        $this->systems['joint'] = new Control\JointController();
        $this->systems['trajectory'] = new Control\TrajectoryPlanner();
        $this->systems['kinematics'] = new Control\InverseKinematics();
    }

    /**
     * Initialize spatial systems
     */
    private function init_spatial_systems() {
        $this->systems['slam'] = new Spatial\SLAMEngine();
        $this->systems['planner'] = new Spatial\PathPlanner();
        $this->systems['avoidance'] = new Spatial\ObstacleAvoidance();
        $this->systems['mapper'] = new Spatial\Mapper();
    }

    /**
     * Initialize perception systems
     */
    private function init_perception_systems() {
        $this->systems['fusion'] = new Perception\SensorFusion();
        $this->systems['vision'] = new Perception\VisionProcessor();
        $this->systems['audio'] = new Perception\AudioProcessor();
        $this->systems['lidar'] = new Perception\LiDARProcessor();
    }

    /**
     * Initialize cognition systems
     */
    private function init_cognition_systems() {
        $this->systems['task_planner'] = new Cognition\TaskPlanner();
        $this->systems['decision'] = new Cognition\DecisionMaker();
        $this->systems['goals'] = new Cognition\GoalManager();
        $this->systems['behavior'] = new Cognition\BehaviorTree();
    }

    /**
     * Initialize communication systems
     */
    private function init_communication_systems() {
        $this->systems['ros'] = new Communication\ROSBridge();
        $this->systems['multi_robot'] = new Communication\MultiRobotProtocol();
        $this->systems['hri'] = new Communication\HumanRobotInterface();
    }

    /**
     * Initialize safety systems
     */
    private function init_safety_systems() {
        $this->systems['collision'] = new Safety\CollisionDetector();
        $this->systems['estop'] = new Safety\EmergencyStop();
        $this->systems['health'] = new Safety\HealthMonitor();
        $this->systems['fault'] = new Safety\FaultTolerance();
    }

    /**
     * Initialize Aevov integration
     */
    private function init_integration() {
        $this->systems['integrator'] = new Integration\AevovIntegrator();
    }

    /**
     * Start self-improvement loop
     */
    private function start_self_improvement() {
        // Schedule continuous self-improvement
        if (!wp_next_scheduled('aros_self_improve')) {
            wp_schedule_event(time(), 'hourly', 'aros_self_improve');
        }

        add_action('aros_self_improve', [$this->systems['improvement'], 'improve']);
    }

    /**
     * Register REST API endpoints
     */
    public function register_api() {
        $api = new API\AROSEndpoint();
        $api->register_routes();
    }

    /**
     * Get system by name
     */
    public function get_system($name) {
        return $this->systems[$name] ?? null;
    }

    /**
     * Get all systems
     */
    public function get_all_systems() {
        return $this->systems;
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create necessary database tables
        $this->create_tables();

        // Set default options
        $this->set_default_options();

        // Initialize robot configuration
        $this->init_robot_config();

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('aros_self_improve');

        // Stop all motors for safety
        $this->systems['motor']->emergency_stop();

        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            // Robot states table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aros_states (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                robot_id varchar(100) NOT NULL,
                state_data longtext NOT NULL,
                timestamp datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY robot_id (robot_id)
            ) $charset_collate;",

            // Experiences table for learning
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aros_experiences (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                state longtext NOT NULL,
                action longtext NOT NULL,
                reward float NOT NULL,
                next_state longtext NOT NULL,
                done tinyint(1) NOT NULL,
                timestamp datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",

            // Maps table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aros_maps (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                map_name varchar(255) NOT NULL,
                map_data longtext NOT NULL,
                resolution float NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",

            // Tasks table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aros_tasks (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                task_type varchar(100) NOT NULL,
                task_data longtext NOT NULL,
                status varchar(50) NOT NULL,
                priority int NOT NULL DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                completed_at datetime,
                PRIMARY KEY (id)
            ) $charset_collate;"
        ];

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        add_option('aros_version', AROS_VERSION);
        add_option('aros_learning_rate', 0.001);
        add_option('aros_discount_factor', 0.99);
        add_option('aros_exploration_rate', 0.1);
        add_option('aros_safety_threshold', 0.95);
        add_option('aros_update_frequency', 10); // Hz
    }

    /**
     * Initialize robot configuration
     */
    private function init_robot_config() {
        $default_config = [
            'robot_type' => 'generic',
            'dof' => 6, // Degrees of freedom
            'max_velocity' => 1.0, // m/s
            'max_acceleration' => 2.0, // m/s²
            'sensor_suite' => ['camera', 'lidar', 'imu', 'encoders'],
            'control_frequency' => 100, // Hz
        ];

        add_option('aros_robot_config', $default_config);
    }
}

// Initialize AROS
function aros_init() {
    return AROS::get_instance();
}

// Start AROS on plugins_loaded
add_action('plugins_loaded', 'AROS\\aros_init', 100);
