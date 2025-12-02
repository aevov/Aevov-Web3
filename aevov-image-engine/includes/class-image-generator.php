<?php
/**
 * Image Generator - Real Computer Vision Image Generation
 *
 * Generates images and patterns procedurally:
 * - Noise generation (Perlin, Simplex, White, Pink)
 * - Pattern synthesis
 * - Texture generation
 * - Image composition and blending
 * - Gradient generation
 * - Fractal patterns
 */

namespace AevovImageEngine;

require_once __DIR__ . '/class-image-processor.php';

class ImageGenerator {

    private $processor;

    public function __construct() {
        $this->processor = new ImageProcessor();
    }

    /**
     * Generate white noise
     * Pure random noise
     */
    public function generate_white_noise($width, $height, $seed = null) {
        if ($seed !== null) {
            mt_srand($seed);
        }

        $this->processor->create($width, $height);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $value = mt_rand(0, 255);
                $this->processor->set_pixel($x, $y, $value, $value, $value);
            }
        }

        return clone $this->processor;
    }

    /**
     * Generate Perlin noise
     * Smooth, natural-looking noise with controllable frequency
     */
    public function generate_perlin_noise($width, $height, $scale = 50, $octaves = 4, $persistence = 0.5, $seed = null) {
        if ($seed !== null) {
            mt_srand($seed);
        }

        // Generate permutation table
        $permutation = $this->generate_permutation_table();

        $this->processor->create($width, $height);

        $max_value = 0;
        $min_value = PHP_INT_MAX;
        $noise_values = array();

        // Generate noise values
        for ($y = 0; $y < $height; $y++) {
            $noise_values[$y] = array();
            for ($x = 0; $x < $width; $x++) {
                $noise = 0;
                $amplitude = 1;
                $frequency = 1;
                $max_amplitude = 0;

                for ($octave = 0; $octave < $octaves; $octave++) {
                    $sample_x = ($x / $scale) * $frequency;
                    $sample_y = ($y / $scale) * $frequency;

                    $perlin_value = $this->perlin_noise_2d($sample_x, $sample_y, $permutation);
                    $noise += $perlin_value * $amplitude;

                    $max_amplitude += $amplitude;
                    $amplitude *= $persistence;
                    $frequency *= 2;
                }

                $noise /= $max_amplitude;
                $noise_values[$y][$x] = $noise;

                $max_value = max($max_value, $noise);
                $min_value = min($min_value, $noise);
            }
        }

        // Normalize to 0-255
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $normalized = (($noise_values[$y][$x] - $min_value) / ($max_value - $min_value)) * 255;
                $value = (int)$normalized;
                $this->processor->set_pixel($x, $y, $value, $value, $value);
            }
        }

        return clone $this->processor;
    }

    /**
     * 2D Perlin noise function
     */
    private function perlin_noise_2d($x, $y, $permutation) {
        // Grid coordinates
        $x0 = (int)floor($x);
        $y0 = (int)floor($y);
        $x1 = $x0 + 1;
        $y1 = $y0 + 1;

        // Interpolation weights
        $sx = $x - $x0;
        $sy = $y - $y0;

        // Gradient vectors at corners
        $n0 = $this->dot_grid_gradient($x0, $y0, $x, $y, $permutation);
        $n1 = $this->dot_grid_gradient($x1, $y0, $x, $y, $permutation);
        $n2 = $this->dot_grid_gradient($x0, $y1, $x, $y, $permutation);
        $n3 = $this->dot_grid_gradient($x1, $y1, $x, $y, $permutation);

        // Interpolate
        $ix0 = $this->interpolate($n0, $n1, $sx);
        $ix1 = $this->interpolate($n2, $n3, $sx);

        return $this->interpolate($ix0, $ix1, $sy);
    }

    /**
     * Calculate dot product of gradient vector and distance vector
     */
    private function dot_grid_gradient($ix, $iy, $x, $y, $permutation) {
        // Get gradient
        $gradient = $this->get_gradient($ix, $iy, $permutation);

        // Distance vector
        $dx = $x - $ix;
        $dy = $y - $iy;

        // Dot product
        return $dx * $gradient[0] + $dy * $gradient[1];
    }

    /**
     * Get pseudo-random gradient vector
     */
    private function get_gradient($ix, $iy, $permutation) {
        $idx = $permutation[($ix + $permutation[$iy & 255]) & 255];

        // Generate gradient from index
        $angle = ($idx / 255.0) * 2 * M_PI;

        return array(cos($angle), sin($angle));
    }

    /**
     * Smooth interpolation (fade function)
     */
    private function interpolate($a, $b, $t) {
        // Smoothstep function: 6t^5 - 15t^4 + 10t^3
        $smooth = $t * $t * $t * ($t * ($t * 6 - 15) + 10);
        return $a + $smooth * ($b - $a);
    }

    /**
     * Generate permutation table for Perlin noise
     */
    private function generate_permutation_table() {
        $permutation = range(0, 255);
        shuffle($permutation);

        // Duplicate to avoid overflow
        return array_merge($permutation, $permutation);
    }

    /**
     * Generate Simplex noise
     * More efficient alternative to Perlin noise
     */
    public function generate_simplex_noise($width, $height, $scale = 50, $octaves = 4, $persistence = 0.5, $seed = null) {
        if ($seed !== null) {
            mt_srand($seed);
        }

        $permutation = $this->generate_permutation_table();

        $this->processor->create($width, $height);

        $max_value = 0;
        $min_value = PHP_INT_MAX;
        $noise_values = array();

        // Simplex constants
        $F2 = 0.5 * (sqrt(3.0) - 1.0);
        $G2 = (3.0 - sqrt(3.0)) / 6.0;

        for ($y = 0; $y < $height; $y++) {
            $noise_values[$y] = array();
            for ($x = 0; $x < $width; $x++) {
                $noise = 0;
                $amplitude = 1;
                $frequency = 1;
                $max_amplitude = 0;

                for ($octave = 0; $octave < $octaves; $octave++) {
                    $sample_x = ($x / $scale) * $frequency;
                    $sample_y = ($y / $scale) * $frequency;

                    $simplex_value = $this->simplex_noise_2d($sample_x, $sample_y, $permutation, $F2, $G2);
                    $noise += $simplex_value * $amplitude;

                    $max_amplitude += $amplitude;
                    $amplitude *= $persistence;
                    $frequency *= 2;
                }

                $noise /= $max_amplitude;
                $noise_values[$y][$x] = $noise;

                $max_value = max($max_value, $noise);
                $min_value = min($min_value, $noise);
            }
        }

        // Normalize to 0-255
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $normalized = (($noise_values[$y][$x] - $min_value) / ($max_value - $min_value)) * 255;
                $value = (int)$normalized;
                $this->processor->set_pixel($x, $y, $value, $value, $value);
            }
        }

        return clone $this->processor;
    }

    /**
     * 2D Simplex noise function
     */
    private function simplex_noise_2d($x, $y, $perm, $F2, $G2) {
        // Skew input space
        $s = ($x + $y) * $F2;
        $i = floor($x + $s);
        $j = floor($y + $s);

        $t = ($i + $j) * $G2;
        $X0 = $i - $t;
        $Y0 = $j - $t;
        $x0 = $x - $X0;
        $y0 = $y - $Y0;

        // Determine which simplex
        $i1 = $x0 > $y0 ? 1 : 0;
        $j1 = $x0 > $y0 ? 0 : 1;

        $x1 = $x0 - $i1 + $G2;
        $y1 = $y0 - $j1 + $G2;
        $x2 = $x0 - 1.0 + 2.0 * $G2;
        $y2 = $y0 - 1.0 + 2.0 * $G2;

        // Calculate contributions
        $n0 = $this->simplex_contribution($x0, $y0, $i, $j, $perm);
        $n1 = $this->simplex_contribution($x1, $y1, $i + $i1, $j + $j1, $perm);
        $n2 = $this->simplex_contribution($x2, $y2, $i + 1, $j + 1, $perm);

        return 70.0 * ($n0 + $n1 + $n2);
    }

    /**
     * Calculate simplex corner contribution
     */
    private function simplex_contribution($x, $y, $i, $j, $perm) {
        $t = 0.5 - $x * $x - $y * $y;

        if ($t < 0) {
            return 0;
        }

        $gradient = $this->get_gradient($i, $j, $perm);
        $t *= $t;

        return $t * $t * ($gradient[0] * $x + $gradient[1] * $y);
    }

    /**
     * Generate linear gradient
     */
    public function generate_linear_gradient($width, $height, $color1, $color2, $angle = 0) {
        $this->processor->create($width, $height);

        $angle_rad = deg2rad($angle);
        $cos_angle = cos($angle_rad);
        $sin_angle = sin($angle_rad);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Project point onto gradient axis
                $px = ($x - $width / 2) / $width;
                $py = ($y - $height / 2) / $height;

                $t = ($px * $cos_angle + $py * $sin_angle + 0.5);
                $t = max(0, min(1, $t));

                $r = (int)($color1['r'] * (1 - $t) + $color2['r'] * $t);
                $g = (int)($color1['g'] * (1 - $t) + $color2['g'] * $t);
                $b = (int)($color1['b'] * (1 - $t) + $color2['b'] * $t);

                $this->processor->set_pixel($x, $y, $r, $g, $b);
            }
        }

        return clone $this->processor;
    }

    /**
     * Generate radial gradient
     */
    public function generate_radial_gradient($width, $height, $color1, $color2, $center_x = null, $center_y = null) {
        $this->processor->create($width, $height);

        $center_x = $center_x ?? $width / 2;
        $center_y = $center_y ?? $height / 2;

        $max_distance = sqrt(pow($width / 2, 2) + pow($height / 2, 2));

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $distance = sqrt(pow($x - $center_x, 2) + pow($y - $center_y, 2));
                $t = min(1, $distance / $max_distance);

                $r = (int)($color1['r'] * (1 - $t) + $color2['r'] * $t);
                $g = (int)($color1['g'] * (1 - $t) + $color2['g'] * $t);
                $b = (int)($color1['b'] * (1 - $t) + $color2['b'] * $t);

                $this->processor->set_pixel($x, $y, $r, $g, $b);
            }
        }

        return clone $this->processor;
    }

    /**
     * Generate checkerboard pattern
     */
    public function generate_checkerboard($width, $height, $square_size, $color1, $color2) {
        $this->processor->create($width, $height);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $check_x = (int)floor($x / $square_size);
                $check_y = (int)floor($y / $square_size);

                $color = (($check_x + $check_y) % 2 == 0) ? $color1 : $color2;

                $this->processor->set_pixel($x, $y, $color['r'], $color['g'], $color['b']);
            }
        }

        return clone $this->processor;
    }

    /**
     * Generate sine wave pattern
     */
    public function generate_sine_pattern($width, $height, $frequency = 10, $amplitude = 50, $angle = 0) {
        $this->processor->create($width, $height);

        $angle_rad = deg2rad($angle);
        $cos_angle = cos($angle_rad);
        $sin_angle = sin($angle_rad);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                // Rotate coordinates
                $rx = $x * $cos_angle - $y * $sin_angle;

                $value = 127 + $amplitude * sin(($rx / $width) * $frequency * 2 * M_PI);
                $value = max(0, min(255, (int)$value));

                $this->processor->set_pixel($x, $y, $value, $value, $value);
            }
        }

        return clone $this->processor;
    }

    /**
     * Generate Mandelbrot fractal
     */
    public function generate_mandelbrot($width, $height, $max_iterations = 100, $zoom = 1, $center_x = -0.5, $center_y = 0) {
        $this->processor->create($width, $height);

        $aspect_ratio = $width / $height;

        for ($py = 0; $py < $height; $py++) {
            for ($px = 0; $px < $width; $px++) {
                // Map pixel to complex plane
                $x0 = ($px / $width - 0.5) * 4 * $aspect_ratio / $zoom + $center_x;
                $y0 = ($py / $height - 0.5) * 4 / $zoom + $center_y;

                $x = 0;
                $y = 0;
                $iteration = 0;

                // Iterate z = z^2 + c
                while ($x * $x + $y * $y <= 4 && $iteration < $max_iterations) {
                    $x_temp = $x * $x - $y * $y + $x0;
                    $y = 2 * $x * $y + $y0;
                    $x = $x_temp;
                    $iteration++;
                }

                // Color based on iterations
                if ($iteration == $max_iterations) {
                    $value = 0;
                } else {
                    $value = (int)(($iteration / $max_iterations) * 255);
                }

                $this->processor->set_pixel($px, $py, $value, $value, $value);
            }
        }

        return clone $this->processor;
    }

    /**
     * Generate Julia set fractal
     */
    public function generate_julia($width, $height, $c_real = -0.7, $c_imag = 0.27015, $max_iterations = 100, $zoom = 1) {
        $this->processor->create($width, $height);

        $aspect_ratio = $width / $height;

        for ($py = 0; $py < $height; $py++) {
            for ($px = 0; $px < $width; $px++) {
                // Map pixel to complex plane
                $x = ($px / $width - 0.5) * 4 * $aspect_ratio / $zoom;
                $y = ($py / $height - 0.5) * 4 / $zoom;

                $iteration = 0;

                // Iterate z = z^2 + c
                while ($x * $x + $y * $y <= 4 && $iteration < $max_iterations) {
                    $x_temp = $x * $x - $y * $y + $c_real;
                    $y = 2 * $x * $y + $c_imag;
                    $x = $x_temp;
                    $iteration++;
                }

                // Color based on iterations
                if ($iteration == $max_iterations) {
                    $value = 0;
                } else {
                    $value = (int)(($iteration / $max_iterations) * 255);
                }

                $this->processor->set_pixel($px, $py, $value, $value, $value);
            }
        }

        return clone $this->processor;
    }

    /**
     * Generate Voronoi diagram
     */
    public function generate_voronoi($width, $height, $num_points = 20, $seed = null) {
        if ($seed !== null) {
            mt_srand($seed);
        }

        $this->processor->create($width, $height);

        // Generate random seed points
        $points = array();
        for ($i = 0; $i < $num_points; $i++) {
            $points[] = array(
                'x' => mt_rand(0, $width - 1),
                'y' => mt_rand(0, $height - 1),
                'color' => array(
                    'r' => mt_rand(0, 255),
                    'g' => mt_rand(0, 255),
                    'b' => mt_rand(0, 255)
                )
            );
        }

        // For each pixel, find nearest point
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $min_distance = PHP_INT_MAX;
                $nearest_point = null;

                foreach ($points as $point) {
                    $distance = pow($x - $point['x'], 2) + pow($y - $point['y'], 2);
                    if ($distance < $min_distance) {
                        $min_distance = $distance;
                        $nearest_point = $point;
                    }
                }

                $this->processor->set_pixel(
                    $x, $y,
                    $nearest_point['color']['r'],
                    $nearest_point['color']['g'],
                    $nearest_point['color']['b']
                );
            }
        }

        return clone $this->processor;
    }

    /**
     * Blend two images using various modes
     */
    public function blend_images($image1, $image2, $mode = 'normal', $opacity = 0.5) {
        $dims1 = $image1->get_dimensions();
        $dims2 = $image2->get_dimensions();

        $width = min($dims1['width'], $dims2['width']);
        $height = min($dims1['height'], $dims2['height']);

        $result = new ImageProcessor();
        $result->create($width, $height);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel1 = $image1->get_pixel($x, $y);
                $pixel2 = $image2->get_pixel($x, $y);

                $blended = $this->blend_pixels($pixel1, $pixel2, $mode, $opacity);

                $result->set_pixel($x, $y, $blended['r'], $blended['g'], $blended['b']);
            }
        }

        return $result;
    }

    /**
     * Blend two pixels using various blend modes
     */
    private function blend_pixels($pixel1, $pixel2, $mode, $opacity) {
        $r = 0; $g = 0; $b = 0;

        switch ($mode) {
            case 'normal':
                $r = $pixel1['r'] * (1 - $opacity) + $pixel2['r'] * $opacity;
                $g = $pixel1['g'] * (1 - $opacity) + $pixel2['g'] * $opacity;
                $b = $pixel1['b'] * (1 - $opacity) + $pixel2['b'] * $opacity;
                break;

            case 'multiply':
                $r = ($pixel1['r'] * $pixel2['r']) / 255;
                $g = ($pixel1['g'] * $pixel2['g']) / 255;
                $b = ($pixel1['b'] * $pixel2['b']) / 255;
                break;

            case 'screen':
                $r = 255 - ((255 - $pixel1['r']) * (255 - $pixel2['r'])) / 255;
                $g = 255 - ((255 - $pixel1['g']) * (255 - $pixel2['g'])) / 255;
                $b = 255 - ((255 - $pixel1['b']) * (255 - $pixel2['b'])) / 255;
                break;

            case 'overlay':
                $r = $this->overlay_channel($pixel1['r'], $pixel2['r']);
                $g = $this->overlay_channel($pixel1['g'], $pixel2['g']);
                $b = $this->overlay_channel($pixel1['b'], $pixel2['b']);
                break;

            case 'add':
                $r = min(255, $pixel1['r'] + $pixel2['r']);
                $g = min(255, $pixel1['g'] + $pixel2['g']);
                $b = min(255, $pixel1['b'] + $pixel2['b']);
                break;

            case 'subtract':
                $r = max(0, $pixel1['r'] - $pixel2['r']);
                $g = max(0, $pixel1['g'] - $pixel2['g']);
                $b = max(0, $pixel1['b'] - $pixel2['b']);
                break;

            case 'difference':
                $r = abs($pixel1['r'] - $pixel2['r']);
                $g = abs($pixel1['g'] - $pixel2['g']);
                $b = abs($pixel1['b'] - $pixel2['b']);
                break;
        }

        return array(
            'r' => (int)max(0, min(255, $r)),
            'g' => (int)max(0, min(255, $g)),
            'b' => (int)max(0, min(255, $b))
        );
    }

    /**
     * Overlay blend for single channel
     */
    private function overlay_channel($base, $blend) {
        if ($base < 128) {
            return (2 * $base * $blend) / 255;
        } else {
            return 255 - (2 * (255 - $base) * (255 - $blend)) / 255;
        }
    }

    /**
     * Generate texture from noise with color mapping
     */
    public function generate_texture($width, $height, $type = 'perlin', $color_map = null) {
        // Generate base noise
        switch ($type) {
            case 'perlin':
                $noise = $this->generate_perlin_noise($width, $height, 50, 4, 0.5);
                break;
            case 'simplex':
                $noise = $this->generate_simplex_noise($width, $height, 50, 4, 0.5);
                break;
            default:
                $noise = $this->generate_white_noise($width, $height);
        }

        // Apply color map if provided
        if ($color_map !== null) {
            $result = new ImageProcessor();
            $result->create($width, $height);

            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $pixel = $noise->get_pixel($x, $y);
                    $intensity = $pixel['r'] / 255;

                    // Interpolate through color map
                    $color = $this->interpolate_color_map($intensity, $color_map);

                    $result->set_pixel($x, $y, $color['r'], $color['g'], $color['b']);
                }
            }

            return $result;
        }

        return $noise;
    }

    /**
     * Interpolate color from color map based on intensity
     */
    private function interpolate_color_map($intensity, $color_map) {
        if (empty($color_map)) {
            $value = (int)($intensity * 255);
            return array('r' => $value, 'g' => $value, 'b' => $value);
        }

        // Find surrounding colors in map
        $keys = array_keys($color_map);
        sort($keys);

        $lower = 0;
        $upper = 1;

        foreach ($keys as $key) {
            if ($key <= $intensity) {
                $lower = $key;
            }
            if ($key >= $intensity) {
                $upper = $key;
                break;
            }
        }

        if ($lower == $upper) {
            return $color_map[$lower];
        }

        // Interpolate
        $t = ($intensity - $lower) / ($upper - $lower);

        return array(
            'r' => (int)($color_map[$lower]['r'] * (1 - $t) + $color_map[$upper]['r'] * $t),
            'g' => (int)($color_map[$lower]['g'] * (1 - $t) + $color_map[$upper]['g'] * $t),
            'b' => (int)($color_map[$lower]['b'] * (1 - $t) + $color_map[$upper]['b'] * $t)
        );
    }
}
