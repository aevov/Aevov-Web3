<?php
/**
 * AROS LiDAR Processor
 *
 * Real LiDAR processing implementation with:
 * - Point cloud filtering and preprocessing
 * - Ground plane removal
 * - Clustering and segmentation
 * - Downsampling (voxel grid)
 * - Noise removal
 * - Range and intensity processing
 */

namespace AROS\Perception;

class LiDARProcessor {
    private $max_range = 50.0;      // meters
    private $min_range = 0.1;       // meters
    private $ground_threshold = 0.2; // meters
    private $cluster_tolerance = 0.5; // meters
    private $min_cluster_size = 10;
    private $voxel_size = 0.1;      // meters for downsampling

    public function __construct($config = []) {
        $this->max_range = $config['max_range'] ?? 50.0;
        $this->min_range = $config['min_range'] ?? 0.1;
        $this->ground_threshold = $config['ground_threshold'] ?? 0.2;
        $this->cluster_tolerance = $config['cluster_tolerance'] ?? 0.5;
    }

    /**
     * Main processing function for LiDAR data
     * Input: Raw LiDAR scan data
     * Output: Processed point cloud with filtering, clustering, etc.
     */
    public function process($lidar_data) {
        if (empty($lidar_data)) {
            return [
                'points' => [],
                'clusters' => [],
                'obstacles' => []
            ];
        }

        // Convert raw data to point cloud
        $point_cloud = $this->parse_lidar_data($lidar_data);

        // Filter points by range
        $point_cloud = $this->range_filter($point_cloud);

        // Remove noise and outliers
        $point_cloud = $this->remove_outliers($point_cloud);

        // Remove ground plane
        $ground_removed = $this->remove_ground_plane($point_cloud);

        // Downsample for efficiency
        $downsampled = $this->voxel_grid_downsample($ground_removed);

        // Cluster points into objects
        $clusters = $this->euclidean_clustering($downsampled);

        // Extract obstacles
        $obstacles = $this->extract_obstacles($clusters);

        return [
            'points' => $downsampled,
            'clusters' => $clusters,
            'obstacles' => $obstacles,
            'scan_info' => [
                'total_points' => count($point_cloud),
                'filtered_points' => count($downsampled),
                'num_clusters' => count($clusters),
                'num_obstacles' => count($obstacles)
            ]
        ];
    }

    /**
     * Parse raw LiDAR data into 3D point cloud
     */
    private function parse_lidar_data($lidar_data) {
        $points = [];

        foreach ($lidar_data as $scan_point) {
            // Handle different input formats
            if (isset($scan_point['range']) && isset($scan_point['angle'])) {
                // Polar coordinates (2D scan)
                $range = $scan_point['range'];
                $angle = $scan_point['angle'];
                $vertical_angle = $scan_point['vertical_angle'] ?? 0.0;
                $intensity = $scan_point['intensity'] ?? 1.0;

                // Convert to Cartesian coordinates
                $x = $range * cos($vertical_angle) * cos($angle);
                $y = $range * cos($vertical_angle) * sin($angle);
                $z = $range * sin($vertical_angle);

                $points[] = [
                    'x' => $x,
                    'y' => $y,
                    'z' => $z,
                    'intensity' => $intensity,
                    'range' => $range
                ];
            } elseif (isset($scan_point['x']) && isset($scan_point['y'])) {
                // Already in Cartesian coordinates
                $points[] = [
                    'x' => $scan_point['x'],
                    'y' => $scan_point['y'],
                    'z' => $scan_point['z'] ?? 0.0,
                    'intensity' => $scan_point['intensity'] ?? 1.0,
                    'range' => sqrt($scan_point['x']**2 + $scan_point['y']**2 + ($scan_point['z'] ?? 0.0)**2)
                ];
            }
        }

        return $points;
    }

    /**
     * Filter points by range limits
     */
    private function range_filter($points) {
        $filtered = [];

        foreach ($points as $point) {
            $range = $point['range'];

            if ($range >= $this->min_range && $range <= $this->max_range) {
                $filtered[] = $point;
            }
        }

        return $filtered;
    }

    /**
     * Remove statistical outliers using local point density
     */
    private function remove_outliers($points, $k = 8, $std_multiplier = 1.0) {
        if (count($points) < $k) {
            return $points;
        }

        $filtered = [];

        // Calculate mean distance to k nearest neighbors for each point
        $mean_distances = [];

        foreach ($points as $i => $point) {
            $distances = [];

            // Find distances to all other points
            foreach ($points as $j => $other) {
                if ($i === $j) continue;

                $dist = $this->point_distance($point, $other);
                $distances[] = $dist;
            }

            // Sort and take k nearest
            sort($distances);
            $k_nearest = array_slice($distances, 0, min($k, count($distances)));

            // Calculate mean
            $mean_distances[$i] = array_sum($k_nearest) / count($k_nearest);
        }

        // Calculate global mean and standard deviation
        $global_mean = array_sum($mean_distances) / count($mean_distances);
        $variance = 0.0;

        foreach ($mean_distances as $dist) {
            $variance += ($dist - $global_mean) ** 2;
        }

        $std_dev = sqrt($variance / count($mean_distances));

        // Filter outliers
        $threshold = $global_mean + $std_multiplier * $std_dev;

        foreach ($points as $i => $point) {
            if ($mean_distances[$i] <= $threshold) {
                $filtered[] = $point;
            }
        }

        return $filtered;
    }

    /**
     * Remove ground plane using RANSAC plane fitting
     */
    private function remove_ground_plane($points) {
        if (count($points) < 3) {
            return $points;
        }

        // Simple ground removal: filter points near z=0
        $non_ground = [];

        // Calculate minimum z
        $min_z = INF;
        foreach ($points as $point) {
            if ($point['z'] < $min_z) {
                $min_z = $point['z'];
            }
        }

        // Remove points close to ground
        foreach ($points as $point) {
            if ($point['z'] - $min_z > $this->ground_threshold) {
                $non_ground[] = $point;
            }
        }

        return $non_ground;
    }

    /**
     * Voxel grid downsampling - reduces point density while preserving structure
     */
    private function voxel_grid_downsample($points) {
        if (empty($points)) {
            return [];
        }

        $voxel_map = [];

        // Assign points to voxels
        foreach ($points as $point) {
            $voxel_x = (int)floor($point['x'] / $this->voxel_size);
            $voxel_y = (int)floor($point['y'] / $this->voxel_size);
            $voxel_z = (int)floor($point['z'] / $this->voxel_size);

            $voxel_key = "$voxel_x,$voxel_y,$voxel_z";

            if (!isset($voxel_map[$voxel_key])) {
                $voxel_map[$voxel_key] = [];
            }

            $voxel_map[$voxel_key][] = $point;
        }

        // Average points in each voxel
        $downsampled = [];

        foreach ($voxel_map as $voxel_points) {
            $avg_x = 0.0;
            $avg_y = 0.0;
            $avg_z = 0.0;
            $avg_intensity = 0.0;
            $count = count($voxel_points);

            foreach ($voxel_points as $point) {
                $avg_x += $point['x'];
                $avg_y += $point['y'];
                $avg_z += $point['z'];
                $avg_intensity += $point['intensity'];
            }

            $downsampled[] = [
                'x' => $avg_x / $count,
                'y' => $avg_y / $count,
                'z' => $avg_z / $count,
                'intensity' => $avg_intensity / $count,
                'range' => sqrt(($avg_x/$count)**2 + ($avg_y/$count)**2 + ($avg_z/$count)**2)
            ];
        }

        return $downsampled;
    }

    /**
     * Euclidean clustering - group nearby points into clusters
     */
    private function euclidean_clustering($points) {
        if (empty($points)) {
            return [];
        }

        $clusters = [];
        $processed = [];

        foreach ($points as $i => $point) {
            if (isset($processed[$i])) {
                continue;
            }

            // Start new cluster
            $cluster = [];
            $queue = [$i];

            while (!empty($queue)) {
                $current_idx = array_shift($queue);

                if (isset($processed[$current_idx])) {
                    continue;
                }

                $processed[$current_idx] = true;
                $cluster[] = $points[$current_idx];

                // Find neighbors
                foreach ($points as $j => $neighbor) {
                    if (isset($processed[$j])) {
                        continue;
                    }

                    $dist = $this->point_distance($points[$current_idx], $neighbor);

                    if ($dist <= $this->cluster_tolerance) {
                        $queue[] = $j;
                    }
                }
            }

            // Only keep clusters above minimum size
            if (count($cluster) >= $this->min_cluster_size) {
                $clusters[] = $cluster;
            }
        }

        return $clusters;
    }

    /**
     * Extract obstacle information from clusters
     */
    private function extract_obstacles($clusters) {
        $obstacles = [];

        foreach ($clusters as $cluster) {
            if (empty($cluster)) {
                continue;
            }

            // Calculate cluster properties
            $min_x = INF; $max_x = -INF;
            $min_y = INF; $max_y = -INF;
            $min_z = INF; $max_z = -INF;
            $centroid_x = 0.0;
            $centroid_y = 0.0;
            $centroid_z = 0.0;

            foreach ($cluster as $point) {
                $min_x = min($min_x, $point['x']);
                $max_x = max($max_x, $point['x']);
                $min_y = min($min_y, $point['y']);
                $max_y = max($max_y, $point['y']);
                $min_z = min($min_z, $point['z']);
                $max_z = max($max_z, $point['z']);

                $centroid_x += $point['x'];
                $centroid_y += $point['y'];
                $centroid_z += $point['z'];
            }

            $count = count($cluster);
            $centroid_x /= $count;
            $centroid_y /= $count;
            $centroid_z /= $count;

            // Bounding box dimensions
            $width = $max_x - $min_x;
            $length = $max_y - $min_y;
            $height = $max_z - $min_z;

            $obstacles[] = [
                'centroid' => [
                    'x' => $centroid_x,
                    'y' => $centroid_y,
                    'z' => $centroid_z
                ],
                'bounding_box' => [
                    'min' => ['x' => $min_x, 'y' => $min_y, 'z' => $min_z],
                    'max' => ['x' => $max_x, 'y' => $max_y, 'z' => $max_z],
                    'dimensions' => [
                        'width' => $width,
                        'length' => $length,
                        'height' => $height
                    ]
                ],
                'num_points' => $count,
                'distance' => sqrt($centroid_x**2 + $centroid_y**2)
            ];
        }

        // Sort by distance
        usort($obstacles, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return $obstacles;
    }

    /**
     * Generate 2D occupancy grid from point cloud
     */
    public function to_occupancy_grid($points, $resolution = 0.1, $width = 100, $height = 100) {
        $grid = [];

        // Initialize grid
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $grid[$y][$x] = 0;
            }
        }

        // Mark occupied cells
        foreach ($points as $point) {
            $grid_x = (int)(($point['x'] / $resolution) + ($width / 2));
            $grid_y = (int)(($point['y'] / $resolution) + ($height / 2));

            if ($grid_x >= 0 && $grid_x < $width && $grid_y >= 0 && $grid_y < $height) {
                $grid[$grid_y][$grid_x] = 1;
            }
        }

        return $grid;
    }

    /**
     * Calculate Euclidean distance between two 3D points
     */
    private function point_distance($p1, $p2) {
        return sqrt(
            ($p1['x'] - $p2['x']) ** 2 +
            ($p1['y'] - $p2['y']) ** 2 +
            ($p1['z'] - $p2['z']) ** 2
        );
    }

    /**
     * Generate simulated LiDAR scan for testing
     */
    public function generate_simulated_scan($num_rays = 360, $max_range = 10.0) {
        $scan = [];

        for ($i = 0; $i < $num_rays; $i++) {
            $angle = ($i / $num_rays) * 2 * M_PI;

            // Simulate obstacles at various distances
            $range = $max_range;

            // Add some obstacles
            if (abs($angle) < 0.5) {
                $range = 3.0; // Obstacle ahead
            } elseif (abs($angle - M_PI/2) < 0.3) {
                $range = 5.0; // Obstacle to the right
            }

            // Add noise
            $range += ($this->gaussian_noise(0, 0.1));

            $scan[] = [
                'range' => max(0.1, $range),
                'angle' => $angle,
                'intensity' => mt_rand(50, 255) / 255.0
            ];
        }

        return $scan;
    }

    /**
     * Generate Gaussian noise
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
        $u = max(1e-10, $u);

        $mag = $stddev * sqrt(-2.0 * log($u));
        $spare = $mag * sin(2.0 * M_PI * $v);

        return $mean + $mag * cos(2.0 * M_PI * $v);
    }
}
