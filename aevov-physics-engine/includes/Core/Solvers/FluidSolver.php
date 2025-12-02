<?php
/**
 * Fluid Dynamics Solver
 *
 * SPH (Smoothed Particle Hydrodynamics) for liquids and gases
 * Beyond physX-Anything with support for viscosity, surface tension, turbulence
 */

namespace Aevov\PhysicsEngine\Core\Solvers;

class FluidSolver {

    private $smoothing_radius = 1.0;
    private $rest_density = 1000.0; // kg/m³ (water)
    private $gas_constant = 2000.0;
    private $viscosity = 0.001;

    /**
     * Solve fluid dynamics using SPH
     */
    public function solve($particles, $dt) {
        // Build spatial grid for neighbor finding
        $grid = $this->build_spatial_grid($particles);

        // Calculate densities and pressures
        foreach ($particles as &$particle) {
            $particle['density'] = $this->calculate_density($particle, $particles, $grid);
            $particle['pressure'] = $this->calculate_pressure($particle['density']);
        }

        // Calculate forces
        foreach ($particles as &$particle) {
            $particle['force'] = ['x' => 0, 'y' => 0, 'z' => 0];

            // Gravity
            $particle['force']['y'] -= $particle['mass'] * 9.81;

            // Pressure force
            $pressure_force = $this->calculate_pressure_force($particle, $particles, $grid);
            $particle['force']['x'] += $pressure_force['x'];
            $particle['force']['y'] += $pressure_force['y'];
            $particle['force']['z'] += $pressure_force['z'];

            // Viscosity force
            $viscosity_force = $this->calculate_viscosity_force($particle, $particles, $grid);
            $particle['force']['x'] += $viscosity_force['x'];
            $particle['force']['y'] += $viscosity_force['y'];
            $particle['force']['z'] += $viscosity_force['z'];

            // Surface tension
            if ($this->is_surface_particle($particle, $particles, $grid)) {
                $surface_force = $this->calculate_surface_tension($particle, $particles, $grid);
                $particle['force']['x'] += $surface_force['x'];
                $particle['force']['y'] += $surface_force['y'];
                $particle['force']['z'] += $surface_force['z'];
            }
        }

        // Integrate
        foreach ($particles as &$particle) {
            // Update velocity
            $particle['velocity']['x'] += ($particle['force']['x'] / $particle['mass']) * $dt;
            $particle['velocity']['y'] += ($particle['force']['y'] / $particle['mass']) * $dt;
            $particle['velocity']['z'] += ($particle['force']['z'] / $particle['mass']) * $dt;

            // Update position
            $particle['position']['x'] += $particle['velocity']['x'] * $dt;
            $particle['position']['y'] += $particle['velocity']['y'] * $dt;
            $particle['position']['z'] += $particle['velocity']['z'] * $dt;

            // Boundary conditions
            $this->apply_boundary_conditions($particle);
        }

        return $particles;
    }

    /**
     * Build spatial grid for efficient neighbor finding
     */
    private function build_spatial_grid($particles) {
        $grid = [];
        $cell_size = $this->smoothing_radius;

        foreach ($particles as $index => $particle) {
            $cell_x = (int)floor($particle['position']['x'] / $cell_size);
            $cell_y = (int)floor($particle['position']['y'] / $cell_size);
            $cell_z = (int)floor($particle['position']['z'] / $cell_size);

            $key = "{$cell_x}_{$cell_y}_{$cell_z}";
            if (!isset($grid[$key])) {
                $grid[$key] = [];
            }
            $grid[$key][] = $index;
        }

        return $grid;
    }

    /**
     * Get neighbors within smoothing radius
     */
    private function get_neighbors($particle, $particles, $grid) {
        $neighbors = [];
        $cell_size = $this->smoothing_radius;

        $cell_x = (int)floor($particle['position']['x'] / $cell_size);
        $cell_y = (int)floor($particle['position']['y'] / $cell_size);
        $cell_z = (int)floor($particle['position']['z'] / $cell_size);

        // Check neighboring cells
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                for ($dz = -1; $dz <= 1; $dz++) {
                    $key = ($cell_x + $dx) . '_' . ($cell_y + $dy) . '_' . ($cell_z + $dz);
                    if (isset($grid[$key])) {
                        foreach ($grid[$key] as $index) {
                            $other = $particles[$index];
                            $distance = $this->distance($particle['position'], $other['position']);
                            if ($distance < $this->smoothing_radius) {
                                $neighbors[] = ['particle' => $other, 'distance' => $distance];
                            }
                        }
                    }
                }
            }
        }

        return $neighbors;
    }

    /**
     * Calculate density using SPH kernel
     */
    private function calculate_density($particle, $particles, $grid) {
        $density = 0;
        $neighbors = $this->get_neighbors($particle, $particles, $grid);

        foreach ($neighbors as $neighbor) {
            $density += $neighbor['particle']['mass'] * $this->kernel($neighbor['distance']);
        }

        return max($density, $this->rest_density * 0.01); // Prevent division by zero
    }

    /**
     * Calculate pressure from density
     */
    private function calculate_pressure($density) {
        // Equation of state: P = k(ρ - ρ₀)
        return $this->gas_constant * ($density - $this->rest_density);
    }

    /**
     * Calculate pressure force
     */
    private function calculate_pressure_force($particle, $particles, $grid) {
        $force = ['x' => 0, 'y' => 0, 'z' => 0];
        $neighbors = $this->get_neighbors($particle, $particles, $grid);

        foreach ($neighbors as $neighbor) {
            if ($neighbor['distance'] < 0.0001) continue;

            $other = $neighbor['particle'];
            $pressure_term = ($particle['pressure'] + $other['pressure']) / (2 * $other['density']);
            $gradient = $this->kernel_gradient($particle['position'], $other['position'], $neighbor['distance']);

            $force['x'] -= $other['mass'] * $pressure_term * $gradient['x'];
            $force['y'] -= $other['mass'] * $pressure_term * $gradient['y'];
            $force['z'] -= $other['mass'] * $pressure_term * $gradient['z'];
        }

        return $force;
    }

    /**
     * Calculate viscosity force
     */
    private function calculate_viscosity_force($particle, $particles, $grid) {
        $force = ['x' => 0, 'y' => 0, 'z' => 0];
        $neighbors = $this->get_neighbors($particle, $particles, $grid);

        foreach ($neighbors as $neighbor) {
            if ($neighbor['distance'] < 0.0001) continue;

            $other = $neighbor['particle'];
            $vel_diff = [
                'x' => $other['velocity']['x'] - $particle['velocity']['x'],
                'y' => $other['velocity']['y'] - $particle['velocity']['y'],
                'z' => $other['velocity']['z'] - $particle['velocity']['z']
            ];

            $laplacian = $this->kernel_laplacian($neighbor['distance']);
            $viscosity_term = $this->viscosity * ($other['mass'] / $other['density']) * $laplacian;

            $force['x'] += $vel_diff['x'] * $viscosity_term;
            $force['y'] += $vel_diff['y'] * $viscosity_term;
            $force['z'] += $vel_diff['z'] * $viscosity_term;
        }

        return $force;
    }

    /**
     * Check if particle is on surface
     */
    private function is_surface_particle($particle, $particles, $grid) {
        $neighbors = $this->get_neighbors($particle, $particles, $grid);
        return count($neighbors) < 20; // Threshold for surface detection
    }

    /**
     * Calculate surface tension
     */
    private function calculate_surface_tension($particle, $particles, $grid) {
        $surface_tension_coefficient = 0.0728; // Water at 20°C
        $force = ['x' => 0, 'y' => 0, 'z' => 0];
        $neighbors = $this->get_neighbors($particle, $particles, $grid);

        $normal = ['x' => 0, 'y' => 0, 'z' => 0];
        foreach ($neighbors as $neighbor) {
            $gradient = $this->kernel_gradient($particle['position'], $neighbor['particle']['position'], $neighbor['distance']);
            $normal['x'] += $gradient['x'];
            $normal['y'] += $gradient['y'];
            $normal['z'] += $gradient['z'];
        }

        $normal_length = sqrt($normal['x']**2 + $normal['y']**2 + $normal['z']**2);
        if ($normal_length > 0.01) {
            $force['x'] = -$surface_tension_coefficient * $normal['x'];
            $force['y'] = -$surface_tension_coefficient * $normal['y'];
            $force['z'] = -$surface_tension_coefficient * $normal['z'];
        }

        return $force;
    }

    /**
     * SPH kernel function (Poly6)
     */
    private function kernel($distance) {
        if ($distance >= $this->smoothing_radius) return 0;

        $h = $this->smoothing_radius;
        $factor = 315.0 / (64.0 * pi() * pow($h, 9));
        return $factor * pow($h * $h - $distance * $distance, 3);
    }

    /**
     * SPH kernel gradient (Spiky)
     */
    private function kernel_gradient($pos_a, $pos_b, $distance) {
        if ($distance >= $this->smoothing_radius || $distance < 0.0001) {
            return ['x' => 0, 'y' => 0, 'z' => 0];
        }

        $h = $this->smoothing_radius;
        $factor = -45.0 / (pi() * pow($h, 6));
        $scalar = $factor * pow($h - $distance, 2) / $distance;

        $dx = $pos_b['x'] - $pos_a['x'];
        $dy = $pos_b['y'] - $pos_a['y'];
        $dz = $pos_b['z'] - $pos_a['z'];

        return [
            'x' => $scalar * $dx,
            'y' => $scalar * $dy,
            'z' => $scalar * $dz
        ];
    }

    /**
     * SPH kernel laplacian (Viscosity)
     */
    private function kernel_laplacian($distance) {
        if ($distance >= $this->smoothing_radius) return 0;

        $h = $this->smoothing_radius;
        $factor = 45.0 / (pi() * pow($h, 6));
        return $factor * ($h - $distance);
    }

    /**
     * Calculate distance between two positions
     */
    private function distance($pos_a, $pos_b) {
        $dx = $pos_b['x'] - $pos_a['x'];
        $dy = $pos_b['y'] - $pos_a['y'];
        $dz = $pos_b['z'] - $pos_a['z'];
        return sqrt($dx*$dx + $dy*$dy + $dz*$dz);
    }

    /**
     * Apply boundary conditions
     */
    private function apply_boundary_conditions(&$particle) {
        $damping = 0.5;

        // Ground plane
        if ($particle['position']['y'] < 0) {
            $particle['position']['y'] = 0;
            $particle['velocity']['y'] *= -$damping;
        }

        // Box boundaries (example)
        $bounds = 100;
        if (abs($particle['position']['x']) > $bounds) {
            $particle['position']['x'] = $bounds * ($particle['position']['x'] > 0 ? 1 : -1);
            $particle['velocity']['x'] *= -$damping;
        }
        if (abs($particle['position']['z']) > $bounds) {
            $particle['position']['z'] = $bounds * ($particle['position']['z'] > 0 ? 1 : -1);
            $particle['velocity']['z'] *= -$damping;
        }
    }
}
