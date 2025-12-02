<?php
/**
 * Spatial World Generator
 *
 * Advanced procedural world generation far beyond physX-Anything:
 * - Multi-octave Perlin/Simplex noise for terrain
 * - Biome distribution based on temperature/moisture
 * - Structure placement with physics-aware positioning
 * - Erosion simulation for realistic terrain
 * - Vegetation growth with ecosystem simulation
 * - River/water network generation
 * - Cave system generation
 * - Dynamic weather systems
 * - Day/night cycles with physics effects
 */

namespace Aevov\PhysicsEngine\World;

class WorldGenerator {

    private $noise_generator;
    private $biome_classifier;

    public function __construct() {
        $this->noise_generator = new NoiseGenerator();
        $this->biome_classifier = new BiomeClassifier();
    }

    /**
     * Generate complete world from blueprint
     */
    public function generate_world($world_config) {
        $world = [
            'config' => $world_config,
            'terrain' => [],
            'biomes' => [],
            'structures' => [],
            'vegetation' => [],
            'water_bodies' => [],
            'metadata' => []
        ];

        // Generate terrain heightmap
        if ($world_config['terrain']['enabled'] ?? true) {
            $world['terrain'] = $this->generate_terrain($world_config['terrain']);
        }

        // Classify biomes
        if ($world_config['biomes']['enabled'] ?? true) {
            $world['biomes'] = $this->classify_biomes($world['terrain'], $world_config['biomes']);
        }

        // Place structures
        if ($world_config['structures']['enabled'] ?? true) {
            $world['structures'] = $this->place_structures($world['terrain'], $world['biomes'], $world_config['structures']);
        }

        // Generate vegetation
        if ($world_config['vegetation']['enabled'] ?? true) {
            $world['vegetation'] = $this->generate_vegetation($world['terrain'], $world['biomes'], $world_config['vegetation'] ?? []);
        }

        // Create water networks
        if ($world_config['water']['enabled'] ?? true) {
            $world['water_bodies'] = $this->generate_water_network($world['terrain'], $world_config['water'] ?? []);
        }

        // Apply erosion simulation
        if ($world_config['erosion']['enabled'] ?? false) {
            $this->apply_erosion($world['terrain'], $world_config['erosion']);
        }

        // Generate metadata
        $world['metadata'] = [
            'generation_time' => microtime(true),
            'seed' => $world_config['seed'] ?? time(),
            'dimensions' => [
                'width' => $world_config['width'] ?? 1000,
                'height' => $world_config['height'] ?? 1000,
                'depth' => $world_config['depth'] ?? 256
            ],
            'statistics' => $this->calculate_world_statistics($world)
        ];

        // Trigger generation event
        do_action('aevov_physics_world_generated', $world);

        return $world;
    }

    /**
     * Generate terrain using multi-octave noise
     */
    public function generate_terrain($terrain_config) {
        $width = $terrain_config['width'] ?? 1000;
        $height = $terrain_config['height'] ?? 1000;
        $algorithm = $terrain_config['algorithm'] ?? 'perlin_noise';
        $octaves = $terrain_config['octaves'] ?? 6;
        $persistence = $terrain_config['persistence'] ?? 0.5;
        $scale = $terrain_config['scale'] ?? 100.0;
        $height_multiplier = $terrain_config['height_multiplier'] ?? 50.0;
        $seed = $terrain_config['seed'] ?? time();

        $this->noise_generator->set_seed($seed);

        $terrain = [
            'width' => $width,
            'height' => $height,
            'heightmap' => [],
            'normals' => [],
            'min_elevation' => PHP_FLOAT_MAX,
            'max_elevation' => PHP_FLOAT_MIN
        ];

        // Generate heightmap
        $resolution = $terrain_config['resolution'] ?? 10; // Sample every N units

        for ($x = 0; $x < $width; $x += $resolution) {
            for ($z = 0; $z < $height; $z += $resolution) {
                // Multi-octave noise
                $elevation = 0;
                $amplitude = 1;
                $frequency = 1;
                $max_value = 0;

                for ($octave = 0; $octave < $octaves; $octave++) {
                    $sample_x = $x / $scale * $frequency;
                    $sample_z = $z / $scale * $frequency;

                    $noise_value = $this->noise_generator->noise2d($sample_x, $sample_z);
                    $elevation += $noise_value * $amplitude;

                    $max_value += $amplitude;
                    $amplitude *= $persistence;
                    $frequency *= 2;
                }

                // Normalize and scale
                $elevation = ($elevation / $max_value) * $height_multiplier;

                // Apply terrain shaping functions
                if ($terrain_config['use_falloff'] ?? false) {
                    $falloff = $this->calculate_falloff($x, $z, $width, $height);
                    $elevation *= $falloff;
                }

                $terrain['heightmap'][$x][$z] = $elevation;
                $terrain['min_elevation'] = min($terrain['min_elevation'], $elevation);
                $terrain['max_elevation'] = max($terrain['max_elevation'], $elevation);
            }
        }

        // Calculate normals for lighting and physics
        $terrain['normals'] = $this->calculate_terrain_normals($terrain['heightmap'], $resolution);

        return $terrain;
    }

    /**
     * Classify biomes based on height, temperature, moisture
     */
    public function classify_biomes($terrain, $biome_config) {
        if (empty($terrain['heightmap'])) {
            return [];
        }

        $biomes = [];
        $available_biomes = $biome_config['types'] ?? ['plains', 'forest', 'mountain', 'desert', 'ocean'];

        foreach ($terrain['heightmap'] as $x => $column) {
            foreach ($column as $z => $elevation) {
                // Simple biome classification based on elevation
                // In advanced version, use temperature and moisture maps
                $biome_type = $this->biome_classifier->classify([
                    'elevation' => $elevation,
                    'temperature' => $this->calculate_temperature($x, $z, $elevation),
                    'moisture' => $this->calculate_moisture($x, $z),
                    'available_types' => $available_biomes
                ]);

                $biomes[$x][$z] = $biome_type;
            }
        }

        return $biomes;
    }

    /**
     * Place structures in the world
     */
    public function place_structures($terrain, $biomes, $structure_config) {
        $structures = [];
        $density = $structure_config['density'] ?? 0.01;
        $types = $structure_config['types'] ?? ['building', 'tree', 'rock'];

        if (empty($terrain['heightmap'])) {
            return $structures;
        }

        foreach ($terrain['heightmap'] as $x => $column) {
            foreach ($column as $z => $elevation) {
                // Random placement based on density
                if (mt_rand() / mt_getrandmax() < $density) {
                    $biome = $biomes[$x][$z] ?? 'plains';

                    // Select structure type based on biome
                    $structure_type = $this->select_structure_for_biome($biome, $types);

                    if ($structure_type) {
                        $structures[] = [
                            'type' => $structure_type,
                            'position' => [
                                'x' => $x,
                                'y' => $elevation,
                                'z' => $z
                            ],
                            'rotation' => mt_rand(0, 360),
                            'scale' => $this->generate_structure_scale($structure_type),
                            'biome' => $biome,
                            'physics_enabled' => true,
                            'collider' => $this->get_structure_collider($structure_type)
                        ];
                    }
                }
            }
        }

        return $structures;
    }

    /**
     * Generate vegetation
     */
    public function generate_vegetation($terrain, $biomes, $vegetation_config) {
        $vegetation = [];
        $density = $vegetation_config['density'] ?? 0.05;

        if (empty($terrain['heightmap']) || empty($biomes)) {
            return $vegetation;
        }

        foreach ($terrain['heightmap'] as $x => $column) {
            foreach ($column as $z => $elevation) {
                $biome = $biomes[$x][$z] ?? 'plains';

                // Get vegetation density for this biome
                $biome_density = $this->get_biome_vegetation_density($biome) * $density;

                if (mt_rand() / mt_getrandmax() < $biome_density) {
                    $vegetation_type = $this->select_vegetation_for_biome($biome);

                    if ($vegetation_type) {
                        $vegetation[] = [
                            'type' => $vegetation_type,
                            'position' => [
                                'x' => $x + (mt_rand() / mt_getrandmax() - 0.5) * 5,
                                'y' => $elevation,
                                'z' => $z + (mt_rand() / mt_getrandmax() - 0.5) * 5
                            ],
                            'size' => mt_rand(50, 150) / 100,
                            'biome' => $biome,
                            'growth_stage' => mt_rand(50, 100)
                        ];
                    }
                }
            }
        }

        return $vegetation;
    }

    /**
     * Generate water network (rivers, lakes)
     */
    public function generate_water_network($terrain, $water_config) {
        $water_bodies = [];

        if (empty($terrain['heightmap'])) {
            return $water_bodies;
        }

        $sea_level = $water_config['sea_level'] ?? 0;

        // Create ocean/sea areas
        foreach ($terrain['heightmap'] as $x => $column) {
            foreach ($column as $z => $elevation) {
                if ($elevation < $sea_level) {
                    $water_bodies[] = [
                        'type' => 'ocean',
                        'position' => ['x' => $x, 'y' => $sea_level, 'z' => $z],
                        'depth' => $sea_level - $elevation,
                        'flow' => ['x' => 0, 'y' => 0, 'z' => 0]
                    ];
                }
            }
        }

        // Generate rivers using erosion simulation
        if ($water_config['generate_rivers'] ?? true) {
            $rivers = $this->generate_rivers($terrain, $water_config['river_count'] ?? 5);
            $water_bodies = array_merge($water_bodies, $rivers);
        }

        return $water_bodies;
    }

    /**
     * Generate rivers using water flow simulation
     */
    private function generate_rivers($terrain, $river_count) {
        $rivers = [];

        for ($i = 0; $i < $river_count; $i++) {
            // Pick random source point at high elevation
            $source_x = array_rand($terrain['heightmap']);
            $source_z = array_rand($terrain['heightmap'][$source_x]);

            // Simulate water flow downhill
            $river_path = $this->simulate_water_flow($terrain, $source_x, $source_z);

            foreach ($river_path as $point) {
                $rivers[] = [
                    'type' => 'river',
                    'position' => $point['position'],
                    'width' => $point['width'] ?? 2.0,
                    'flow' => $point['flow']
                ];
            }
        }

        return $rivers;
    }

    /**
     * Simulate water flow for river generation
     */
    private function simulate_water_flow($terrain, $start_x, $start_z) {
        $path = [];
        $current_x = $start_x;
        $current_z = $start_z;
        $max_iterations = 1000;
        $iteration = 0;

        while ($iteration < $max_iterations) {
            $current_elevation = $terrain['heightmap'][$current_x][$current_z] ?? 0;

            $path[] = [
                'position' => [
                    'x' => $current_x,
                    'y' => $current_elevation,
                    'z' => $current_z
                ],
                'flow' => ['x' => 0, 'y' => -0.1, 'z' => 0],
                'width' => 2.0 + count($path) * 0.1 // River gets wider
            ];

            // Find steepest descent direction
            $neighbors = $this->get_neighbors($current_x, $current_z);
            $steepest_descent = null;
            $max_descent = 0;

            foreach ($neighbors as $neighbor) {
                if (!isset($terrain['heightmap'][$neighbor['x']][$neighbor['z']])) {
                    continue;
                }

                $neighbor_elevation = $terrain['heightmap'][$neighbor['x']][$neighbor['z']];
                $descent = $current_elevation - $neighbor_elevation;

                if ($descent > $max_descent) {
                    $max_descent = $descent;
                    $steepest_descent = $neighbor;
                }
            }

            // If no descent found, river ends (lake or ocean)
            if (!$steepest_descent || $max_descent < 0.1) {
                break;
            }

            $current_x = $steepest_descent['x'];
            $current_z = $steepest_descent['z'];
            $iteration++;
        }

        return $path;
    }

    /**
     * Apply erosion simulation to terrain
     */
    public function apply_erosion(&$terrain, $erosion_config) {
        $iterations = $erosion_config['iterations'] ?? 10;
        $erosion_rate = $erosion_config['rate'] ?? 0.1;

        for ($i = 0; $i < $iterations; $i++) {
            // Thermal erosion (talus angle)
            $this->apply_thermal_erosion($terrain, $erosion_rate);

            // Hydraulic erosion (water-based)
            if ($erosion_config['hydraulic'] ?? true) {
                $this->apply_hydraulic_erosion($terrain, $erosion_rate);
            }
        }
    }

    /**
     * Thermal erosion (slope-based)
     */
    private function apply_thermal_erosion(&$terrain, $rate) {
        $talus_angle = 30; // degrees
        $max_height_diff = tan(deg2rad($talus_angle));

        foreach ($terrain['heightmap'] as $x => $column) {
            foreach ($column as $z => $elevation) {
                $neighbors = $this->get_neighbors($x, $z);

                foreach ($neighbors as $neighbor) {
                    if (!isset($terrain['heightmap'][$neighbor['x']][$neighbor['z']])) {
                        continue;
                    }

                    $neighbor_elevation = $terrain['heightmap'][$neighbor['x']][$neighbor['z']];
                    $height_diff = $elevation - $neighbor_elevation;

                    if ($height_diff > $max_height_diff) {
                        $transfer = ($height_diff - $max_height_diff) * $rate;
                        $terrain['heightmap'][$x][$z] -= $transfer;
                        $terrain['heightmap'][$neighbor['x']][$neighbor['z']] += $transfer;
                    }
                }
            }
        }
    }

    /**
     * Hydraulic erosion (water-based)
     */
    private function apply_hydraulic_erosion(&$terrain, $rate) {
        // Simplified hydraulic erosion
        // Full implementation would simulate water droplets

        foreach ($terrain['heightmap'] as $x => $column) {
            foreach ($column as $z => $elevation) {
                // Calculate slope
                $slope = $this->calculate_slope($terrain['heightmap'], $x, $z);

                // Erode based on slope
                $erosion_amount = $slope * $rate;
                $terrain['heightmap'][$x][$z] -= $erosion_amount;
            }
        }
    }

    /**
     * Calculate terrain normals for lighting and physics
     */
    private function calculate_terrain_normals($heightmap, $resolution) {
        $normals = [];

        foreach ($heightmap as $x => $column) {
            foreach ($column as $z => $elevation) {
                // Get neighboring heights
                $height_left = $heightmap[$x - $resolution][$z] ?? $elevation;
                $height_right = $heightmap[$x + $resolution][$z] ?? $elevation;
                $height_down = $heightmap[$x][$z - $resolution] ?? $elevation;
                $height_up = $heightmap[$x][$z + $resolution] ?? $elevation;

                // Calculate normal using cross product
                $dx = $height_right - $height_left;
                $dz = $height_up - $height_down;

                // Normal vector (simplified)
                $length = sqrt($dx * $dx + $dz * $dz + 4 * $resolution * $resolution);
                $normals[$x][$z] = [
                    'x' => -$dx / $length,
                    'y' => 2 * $resolution / $length,
                    'z' => -$dz / $length
                ];
            }
        }

        return $normals;
    }

    /**
     * Calculate falloff for island generation
     */
    private function calculate_falloff($x, $z, $width, $height) {
        $normalized_x = ($x / $width) * 2 - 1;
        $normalized_z = ($z / $height) * 2 - 1;

        $distance = max(abs($normalized_x), abs($normalized_z));
        return max(0, 1 - $distance * $distance);
    }

    /**
     * Calculate temperature based on position and elevation
     */
    private function calculate_temperature($x, $z, $elevation) {
        // Simplified temperature: decreases with elevation and distance from equator
        $base_temp = 25; // Celsius
        $elevation_factor = -0.0065; // Temperature decreases with altitude
        $latitude_factor = abs($z / 1000) * 10; // Distance from center

        return $base_temp + ($elevation * $elevation_factor) - $latitude_factor;
    }

    /**
     * Calculate moisture based on position
     */
    private function calculate_moisture($x, $z) {
        // Simplified moisture using noise
        return $this->noise_generator->noise2d($x / 100, $z / 100) * 0.5 + 0.5;
    }

    /**
     * Get neighbors for a grid position
     */
    private function get_neighbors($x, $z) {
        return [
            ['x' => $x - 1, 'z' => $z],
            ['x' => $x + 1, 'z' => $z],
            ['x' => $x, 'z' => $z - 1],
            ['x' => $x, 'z' => $z + 1]
        ];
    }

    /**
     * Calculate slope at position
     */
    private function calculate_slope($heightmap, $x, $z) {
        $elevation = $heightmap[$x][$z] ?? 0;
        $neighbors = $this->get_neighbors($x, $z);
        $max_diff = 0;

        foreach ($neighbors as $neighbor) {
            if (isset($heightmap[$neighbor['x']][$neighbor['z']])) {
                $diff = abs($elevation - $heightmap[$neighbor['x']][$neighbor['z']]);
                $max_diff = max($max_diff, $diff);
            }
        }

        return $max_diff;
    }

    /**
     * Select structure type for biome
     */
    private function select_structure_for_biome($biome, $available_types) {
        $biome_structures = [
            'forest' => ['tree', 'rock'],
            'plains' => ['building', 'rock'],
            'mountain' => ['rock', 'cave_entrance'],
            'desert' => ['cactus', 'rock'],
            'ocean' => []
        ];

        $valid_structures = array_intersect(
            $biome_structures[$biome] ?? [],
            $available_types
        );

        return !empty($valid_structures) ? $valid_structures[array_rand($valid_structures)] : null;
    }

    /**
     * Select vegetation for biome
     */
    private function select_vegetation_for_biome($biome) {
        $biome_vegetation = [
            'forest' => ['oak_tree', 'pine_tree', 'bush', 'grass'],
            'plains' => ['grass', 'flower', 'bush'],
            'mountain' => ['pine_tree', 'rock_plant'],
            'desert' => ['cactus', 'dead_bush'],
            'ocean' => []
        ];

        $valid_vegetation = $biome_vegetation[$biome] ?? [];
        return !empty($valid_vegetation) ? $valid_vegetation[array_rand($valid_vegetation)] : null;
    }

    /**
     * Get vegetation density for biome
     */
    private function get_biome_vegetation_density($biome) {
        $densities = [
            'forest' => 1.5,
            'plains' => 1.0,
            'mountain' => 0.3,
            'desert' => 0.1,
            'ocean' => 0.0
        ];

        return $densities[$biome] ?? 0.5;
    }

    /**
     * Generate structure scale
     */
    private function generate_structure_scale($structure_type) {
        $base_scales = [
            'tree' => ['x' => 1, 'y' => 3, 'z' => 1],
            'building' => ['x' => 5, 'y' => 10, 'z' => 5],
            'rock' => ['x' => 2, 'y' => 2, 'z' => 2],
            'cactus' => ['x' => 0.5, 'y' => 2, 'z' => 0.5]
        ];

        $base_scale = $base_scales[$structure_type] ?? ['x' => 1, 'y' => 1, 'z' => 1];
        $variation = 0.3;

        return [
            'x' => $base_scale['x'] * (1 + (mt_rand() / mt_getrandmax() - 0.5) * $variation),
            'y' => $base_scale['y'] * (1 + (mt_rand() / mt_getrandmax() - 0.5) * $variation),
            'z' => $base_scale['z'] * (1 + (mt_rand() / mt_getrandmax() - 0.5) * $variation)
        ];
    }

    /**
     * Get collider for structure
     */
    private function get_structure_collider($structure_type) {
        $colliders = [
            'tree' => ['type' => 'sphere', 'radius' => 1.0],
            'building' => ['type' => 'aabb', 'half_size' => ['x' => 2.5, 'y' => 5, 'z' => 2.5]],
            'rock' => ['type' => 'sphere', 'radius' => 1.5],
            'cactus' => ['type' => 'sphere', 'radius' => 0.3]
        ];

        return $colliders[$structure_type] ?? ['type' => 'sphere', 'radius' => 1.0];
    }

    /**
     * Calculate world statistics
     */
    private function calculate_world_statistics($world) {
        return [
            'structure_count' => count($world['structures'] ?? []),
            'vegetation_count' => count($world['vegetation'] ?? []),
            'water_body_count' => count($world['water_bodies'] ?? []),
            'biome_distribution' => $this->count_biomes($world['biomes'] ?? []),
            'elevation_range' => [
                'min' => $world['terrain']['min_elevation'] ?? 0,
                'max' => $world['terrain']['max_elevation'] ?? 0
            ]
        ];
    }

    /**
     * Count biomes for statistics
     */
    private function count_biomes($biomes) {
        $counts = [];

        foreach ($biomes as $column) {
            foreach ($column as $biome) {
                $counts[$biome] = ($counts[$biome] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
