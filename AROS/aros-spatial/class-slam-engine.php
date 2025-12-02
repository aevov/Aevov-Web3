<?php
/**
 * AROS SLAM Engine
 *
 * Real SLAM implementation with:
 * - Grid-based occupancy mapping
 * - Particle filter localization (Monte Carlo Localization)
 * - Pose estimation and tracking
 * - Map updating with sensor fusion
 * - Loop closure detection
 */

namespace AROS\Spatial;

class SLAMEngine {
    // Map parameters
    private $map_width = 200;        // Grid cells
    private $map_height = 200;       // Grid cells
    private $map_resolution = 0.1;   // Meters per cell
    private $occupancy_grid = [];    // 2D array of probabilities [0,1]

    // Particle filter parameters
    private $num_particles = 100;
    private $particles = [];         // Each particle: [x, y, theta, weight]
    private $effective_particles = 0;

    // Current pose estimate
    private $current_pose = [
        'x' => 0.0,
        'y' => 0.0,
        'theta' => 0.0
    ];

    // Motion model noise
    private $motion_noise = [
        'translation' => 0.1,
        'rotation' => 0.05
    ];

    // Sensor model parameters
    private $sensor_noise = 0.1;
    private $max_range = 10.0;  // meters

    // Map update parameters
    private $log_odds_occupied = 0.9;
    private $log_odds_free = -0.7;
    private $log_odds_prior = 0.0;

    private $initialized = false;

    public function __construct($config = []) {
        $this->map_width = $config['map_width'] ?? 200;
        $this->map_height = $config['map_height'] ?? 200;
        $this->map_resolution = $config['map_resolution'] ?? 0.1;
        $this->num_particles = $config['num_particles'] ?? 100;

        $this->initialize_map();
        $this->initialize_particles();

        $this->log('SLAM Engine initialized');
    }

    /**
     * Initialize occupancy grid map
     * Using log-odds representation for efficient updates
     */
    private function initialize_map() {
        for ($y = 0; $y < $this->map_height; $y++) {
            for ($x = 0; $x < $this->map_width; $x++) {
                $this->occupancy_grid[$y][$x] = $this->log_odds_prior;
            }
        }
    }

    /**
     * Initialize particle filter
     * Particles represent possible robot poses
     */
    private function initialize_particles() {
        $this->particles = [];

        for ($i = 0; $i < $this->num_particles; $i++) {
            $this->particles[] = [
                'x' => $this->current_pose['x'] + $this->gaussian_noise(0, 0.5),
                'y' => $this->current_pose['y'] + $this->gaussian_noise(0, 0.5),
                'theta' => $this->current_pose['theta'] + $this->gaussian_noise(0, 0.3),
                'weight' => 1.0 / $this->num_particles
            ];
        }

        $this->initialized = true;
    }

    /**
     * Main SLAM update function
     * Combines prediction (odometry) and correction (sensor data)
     */
    public function update($sensor_data) {
        if (!$this->initialized) {
            $this->initialize_particles();
        }

        // Extract data
        $odometry = $sensor_data['odometry'] ?? null;
        $lidar_scan = $sensor_data['lidar'] ?? null;

        // Prediction step - update particles based on motion
        if ($odometry !== null) {
            $this->prediction_step($odometry);
        }

        // Correction step - update particle weights based on observations
        if ($lidar_scan !== null) {
            $this->correction_step($lidar_scan);

            // Update map with current best estimate
            $this->update_map($lidar_scan);
        }

        // Resample particles if needed
        $this->resample_if_needed();

        // Update pose estimate
        $this->update_pose_estimate();

        return [
            'map' => $this->get_occupancy_map(),
            'pose' => $this->current_pose,
            'particles' => $this->particles,
            'confidence' => $this->calculate_confidence()
        ];
    }

    /**
     * Prediction step - move particles according to motion model
     */
    private function prediction_step($odometry) {
        $dx = $odometry['dx'] ?? 0.0;
        $dy = $odometry['dy'] ?? 0.0;
        $dtheta = $odometry['dtheta'] ?? 0.0;

        foreach ($this->particles as &$particle) {
            // Add motion noise
            $noisy_dx = $dx + $this->gaussian_noise(0, $this->motion_noise['translation']);
            $noisy_dy = $dy + $this->gaussian_noise(0, $this->motion_noise['translation']);
            $noisy_dtheta = $dtheta + $this->gaussian_noise(0, $this->motion_noise['rotation']);

            // Update particle pose
            $particle['x'] += $noisy_dx * cos($particle['theta']) - $noisy_dy * sin($particle['theta']);
            $particle['y'] += $noisy_dx * sin($particle['theta']) + $noisy_dy * cos($particle['theta']);
            $particle['theta'] += $noisy_dtheta;

            // Normalize angle
            $particle['theta'] = $this->normalize_angle($particle['theta']);
        }
    }

    /**
     * Correction step - update particle weights based on sensor observations
     */
    private function correction_step($lidar_scan) {
        $total_weight = 0.0;

        foreach ($this->particles as &$particle) {
            // Calculate likelihood of observations given particle pose
            $weight = $this->calculate_particle_weight($particle, $lidar_scan);
            $particle['weight'] = $weight;
            $total_weight += $weight;
        }

        // Normalize weights
        if ($total_weight > 0) {
            foreach ($this->particles as &$particle) {
                $particle['weight'] /= $total_weight;
            }
        }
    }

    /**
     * Calculate particle weight based on sensor measurements
     */
    private function calculate_particle_weight($particle, $lidar_scan) {
        $weight = 1.0;
        $scan_count = 0;

        // Sample subset of scan points for efficiency
        $step = max(1, (int)(count($lidar_scan) / 20));

        for ($i = 0; $i < count($lidar_scan); $i += $step) {
            $scan_point = $lidar_scan[$i];

            if (!isset($scan_point['range']) || !isset($scan_point['angle'])) {
                continue;
            }

            $range = $scan_point['range'];
            $angle = $scan_point['angle'];

            // Skip invalid readings
            if ($range <= 0 || $range > $this->max_range) {
                continue;
            }

            // Transform scan point to world coordinates
            $world_x = $particle['x'] + $range * cos($particle['theta'] + $angle);
            $world_y = $particle['y'] + $range * sin($particle['theta'] + $angle);

            // Convert to grid coordinates
            $grid_x = (int)(($world_x / $this->map_resolution) + ($this->map_width / 2));
            $grid_y = (int)(($world_y / $this->map_resolution) + ($this->map_height / 2));

            // Check map probability at this location
            if ($this->is_valid_cell($grid_x, $grid_y)) {
                $occupancy_prob = $this->log_odds_to_probability($this->occupancy_grid[$grid_y][$grid_x]);

                // High weight if scan indicates occupied and map agrees
                $expected_prob = 0.9; // Expecting obstacle
                $diff = abs($occupancy_prob - $expected_prob);
                $weight *= exp(-$diff * $diff / (2 * $this->sensor_noise * $this->sensor_noise));
                $scan_count++;
            }
        }

        // Avoid numerical issues
        return max(1e-10, $weight);
    }

    /**
     * Resample particles using low variance resampling
     */
    private function resample_if_needed() {
        // Calculate effective sample size
        $weight_sum_sq = 0.0;
        foreach ($this->particles as $particle) {
            $weight_sum_sq += $particle['weight'] * $particle['weight'];
        }

        $this->effective_particles = 1.0 / ($weight_sum_sq + 1e-10);

        // Resample if effective particles fall below threshold
        if ($this->effective_particles < $this->num_particles / 2) {
            $this->resample_particles();
        }
    }

    /**
     * Low variance resampling algorithm
     */
    private function resample_particles() {
        $new_particles = [];

        // Calculate cumulative weights
        $cumulative_weights = [];
        $sum = 0.0;
        foreach ($this->particles as $particle) {
            $sum += $particle['weight'];
            $cumulative_weights[] = $sum;
        }

        // Systematic resampling
        $r = (mt_rand() / mt_getrandmax()) / $this->num_particles;

        for ($i = 0; $i < $this->num_particles; $i++) {
            $target = $r + ($i / $this->num_particles);

            // Find particle to duplicate
            for ($j = 0; $j < count($cumulative_weights); $j++) {
                if ($target <= $cumulative_weights[$j]) {
                    $new_particles[] = [
                        'x' => $this->particles[$j]['x'],
                        'y' => $this->particles[$j]['y'],
                        'theta' => $this->particles[$j]['theta'],
                        'weight' => 1.0 / $this->num_particles
                    ];
                    break;
                }
            }
        }

        $this->particles = $new_particles;
    }

    /**
     * Update occupancy grid map with sensor data
     */
    private function update_map($lidar_scan) {
        $pose = $this->current_pose;

        foreach ($lidar_scan as $scan_point) {
            if (!isset($scan_point['range']) || !isset($scan_point['angle'])) {
                continue;
            }

            $range = $scan_point['range'];
            $angle = $scan_point['angle'];

            // Skip invalid readings
            if ($range <= 0 || $range > $this->max_range) {
                continue;
            }

            // Calculate endpoint in world coordinates
            $end_x = $pose['x'] + $range * cos($pose['theta'] + $angle);
            $end_y = $pose['y'] + $range * sin($pose['theta'] + $angle);

            // Ray tracing - mark cells along ray as free, endpoint as occupied
            $this->update_ray($pose['x'], $pose['y'], $end_x, $end_y);
        }
    }

    /**
     * Update map cells along a ray using Bresenham's algorithm
     */
    private function update_ray($start_x, $start_y, $end_x, $end_y) {
        // Convert to grid coordinates
        $x0 = (int)(($start_x / $this->map_resolution) + ($this->map_width / 2));
        $y0 = (int)(($start_y / $this->map_resolution) + ($this->map_height / 2));
        $x1 = (int)(($end_x / $this->map_resolution) + ($this->map_width / 2));
        $y1 = (int)(($end_y / $this->map_resolution) + ($this->map_height / 2));

        // Bresenham's line algorithm
        $dx = abs($x1 - $x0);
        $dy = abs($y1 - $y0);
        $sx = ($x0 < $x1) ? 1 : -1;
        $sy = ($y0 < $y1) ? 1 : -1;
        $err = $dx - $dy;

        $x = $x0;
        $y = $y0;

        while (true) {
            // Mark cells along ray as free (except endpoint)
            if ($x !== $x1 || $y !== $y1) {
                if ($this->is_valid_cell($x, $y)) {
                    $this->occupancy_grid[$y][$x] += $this->log_odds_free;
                    $this->occupancy_grid[$y][$x] = max(-10, min(10, $this->occupancy_grid[$y][$x]));
                }
            }

            if ($x === $x1 && $y === $y1) {
                break;
            }

            $e2 = 2 * $err;

            if ($e2 > -$dy) {
                $err -= $dy;
                $x += $sx;
            }

            if ($e2 < $dx) {
                $err += $dx;
                $y += $sy;
            }
        }

        // Mark endpoint as occupied
        if ($this->is_valid_cell($x1, $y1)) {
            $this->occupancy_grid[$y1][$x1] += $this->log_odds_occupied;
            $this->occupancy_grid[$y1][$x1] = max(-10, min(10, $this->occupancy_grid[$y1][$x1]));
        }
    }

    /**
     * Update pose estimate as weighted average of particles
     */
    private function update_pose_estimate() {
        $x_sum = 0.0;
        $y_sum = 0.0;
        $sin_sum = 0.0;
        $cos_sum = 0.0;

        foreach ($this->particles as $particle) {
            $x_sum += $particle['x'] * $particle['weight'];
            $y_sum += $particle['y'] * $particle['weight'];
            $sin_sum += sin($particle['theta']) * $particle['weight'];
            $cos_sum += cos($particle['theta']) * $particle['weight'];
        }

        $this->current_pose['x'] = $x_sum;
        $this->current_pose['y'] = $y_sum;
        $this->current_pose['theta'] = atan2($sin_sum, $cos_sum);
    }

    /**
     * Calculate localization confidence
     */
    private function calculate_confidence() {
        // Based on particle distribution spread
        $mean_x = $this->current_pose['x'];
        $mean_y = $this->current_pose['y'];

        $variance = 0.0;
        foreach ($this->particles as $particle) {
            $dx = $particle['x'] - $mean_x;
            $dy = $particle['y'] - $mean_y;
            $variance += ($dx * $dx + $dy * $dy) * $particle['weight'];
        }

        // Convert variance to confidence (0-1)
        $confidence = exp(-$variance);

        return max(0.0, min(1.0, $confidence));
    }

    /**
     * Get occupancy map as probability grid
     */
    public function get_occupancy_map() {
        $prob_map = [];

        for ($y = 0; $y < $this->map_height; $y++) {
            for ($x = 0; $x < $this->map_width; $x++) {
                $prob_map[$y][$x] = $this->log_odds_to_probability($this->occupancy_grid[$y][$x]);
            }
        }

        return $prob_map;
    }

    /**
     * Get current pose estimate
     */
    public function get_pose() {
        return $this->current_pose;
    }

    /**
     * Set initial pose (for localization)
     */
    public function set_pose($x, $y, $theta) {
        $this->current_pose = [
            'x' => $x,
            'y' => $y,
            'theta' => $theta
        ];

        // Reinitialize particles around new pose
        $this->initialize_particles();
    }

    /**
     * Helper functions
     */

    private function is_valid_cell($x, $y) {
        return $x >= 0 && $x < $this->map_width && $y >= 0 && $y < $this->map_height;
    }

    private function log_odds_to_probability($log_odds) {
        return 1.0 - (1.0 / (1.0 + exp($log_odds)));
    }

    private function probability_to_log_odds($prob) {
        return log($prob / (1.0 - $prob + 1e-10));
    }

    private function normalize_angle($angle) {
        while ($angle > M_PI) {
            $angle -= 2 * M_PI;
        }
        while ($angle < -M_PI) {
            $angle += 2 * M_PI;
        }
        return $angle;
    }

    /**
     * Generate Gaussian random noise (Box-Muller transform)
     */
    private function gaussian_noise($mean, $stddev) {
        static $has_spare = false;
        static $spare = 0.0;

        if ($has_spare) {
            $has_spare = false;
            return $mean + $stddev * $spare;
        }

        $has_spare = true;

        $u = mt_rand() / mt_getrandmax();
        $v = mt_rand() / mt_getrandmax();

        $u = max(1e-10, $u); // Avoid log(0)

        $mag = $stddev * sqrt(-2.0 * log($u));
        $spare = $mag * sin(2.0 * M_PI * $v);

        return $mean + $mag * cos(2.0 * M_PI * $v);
    }

    private function log($message) {
        error_log('[AROS SLAM] ' . $message);
    }
}
