<?php
/**
 * Feature Extractor - Real Computer Vision Feature Extraction
 *
 * Extracts meaningful features from images:
 * - Color histograms (RGB, HSV)
 * - Edge histograms
 * - Texture features (Local Binary Patterns)
 * - Shape descriptors (moments, contours)
 * - SIFT-like keypoint detection
 */

namespace AevovImageEngine;

require_once __DIR__ . '/class-image-processor.php';

class FeatureExtractor {

    private $processor;

    public function __construct() {
        $this->processor = new ImageProcessor();
    }

    /**
     * Extract all features from an image
     */
    public function extract_all_features($image_path) {
        return array(
            'color_histogram' => $this->extract_color_histogram($image_path),
            'edge_histogram' => $this->extract_edge_histogram($image_path),
            'texture_features' => $this->extract_lbp_features($image_path),
            'shape_descriptors' => $this->extract_shape_descriptors($image_path),
            'keypoints' => $this->extract_keypoints($image_path)
        );
    }

    /**
     * Extract RGB color histogram
     * Returns normalized histogram for each color channel
     */
    public function extract_color_histogram($image_path, $bins = 16) {
        $this->processor->load_image($image_path);
        $dimensions = $this->processor->get_dimensions();

        $histogram = array(
            'r' => array_fill(0, $bins, 0),
            'g' => array_fill(0, $bins, 0),
            'b' => array_fill(0, $bins, 0)
        );

        $total_pixels = $dimensions['width'] * $dimensions['height'];
        $bin_size = 256 / $bins;

        for ($y = 0; $y < $dimensions['height']; $y++) {
            for ($x = 0; $x < $dimensions['width']; $x++) {
                $pixel = $this->processor->get_pixel($x, $y);

                $r_bin = min((int)($pixel['r'] / $bin_size), $bins - 1);
                $g_bin = min((int)($pixel['g'] / $bin_size), $bins - 1);
                $b_bin = min((int)($pixel['b'] / $bin_size), $bins - 1);

                $histogram['r'][$r_bin]++;
                $histogram['g'][$g_bin]++;
                $histogram['b'][$b_bin]++;
            }
        }

        // Normalize
        foreach ($histogram as $channel => $values) {
            for ($i = 0; $i < $bins; $i++) {
                $histogram[$channel][$i] /= $total_pixels;
            }
        }

        return $histogram;
    }

    /**
     * Extract HSV color histogram
     * More robust to lighting changes than RGB
     */
    public function extract_hsv_histogram($image_path, $h_bins = 18, $s_bins = 8, $v_bins = 8) {
        $this->processor->load_image($image_path);
        $dimensions = $this->processor->get_dimensions();

        $histogram = array(
            'h' => array_fill(0, $h_bins, 0),
            's' => array_fill(0, $s_bins, 0),
            'v' => array_fill(0, $v_bins, 0)
        );

        $total_pixels = $dimensions['width'] * $dimensions['height'];

        for ($y = 0; $y < $dimensions['height']; $y++) {
            for ($x = 0; $x < $dimensions['width']; $x++) {
                $pixel = $this->processor->get_pixel($x, $y);
                $hsv = $this->processor->rgb_to_hsv($pixel['r'], $pixel['g'], $pixel['b']);

                $h_bin = min((int)($hsv[0] / (360 / $h_bins)), $h_bins - 1);
                $s_bin = min((int)($hsv[1] / (100 / $s_bins)), $s_bins - 1);
                $v_bin = min((int)($hsv[2] / (100 / $v_bins)), $v_bins - 1);

                $histogram['h'][$h_bin]++;
                $histogram['s'][$s_bin]++;
                $histogram['v'][$v_bin]++;
            }
        }

        // Normalize
        foreach ($histogram as $channel => $values) {
            $bins_count = count($values);
            for ($i = 0; $i < $bins_count; $i++) {
                $histogram[$channel][$i] /= $total_pixels;
            }
        }

        return $histogram;
    }

    /**
     * Extract edge histogram using edge orientation
     */
    public function extract_edge_histogram($image_path, $bins = 8) {
        $processor = clone $this->processor;
        $processor->load_image($image_path);

        $gradients = $processor->sobel_edge_detection();
        $magnitude = $gradients['magnitude'];
        $direction = $gradients['direction'];

        $histogram = array_fill(0, $bins, 0);
        $edge_count = 0;
        $threshold = 30; // Minimum magnitude to consider as edge

        foreach ($magnitude as $y => $row) {
            foreach ($row as $x => $mag) {
                if ($mag > $threshold) {
                    $angle = $direction[$y][$x];

                    // Convert to 0-360 range
                    $angle_deg = $angle * 180 / M_PI;
                    if ($angle_deg < 0) $angle_deg += 360;

                    $bin = (int)(($angle_deg / 360) * $bins) % $bins;
                    $histogram[$bin]++;
                    $edge_count++;
                }
            }
        }

        // Normalize
        if ($edge_count > 0) {
            for ($i = 0; $i < $bins; $i++) {
                $histogram[$i] /= $edge_count;
            }
        }

        return $histogram;
    }

    /**
     * Extract Local Binary Pattern (LBP) features for texture analysis
     * LBP is rotation invariant and excellent for texture classification
     */
    public function extract_lbp_features($image_path, $radius = 1, $neighbors = 8) {
        $this->processor->load_image($image_path);
        $processor_gray = clone $this->processor;
        $processor_gray->to_grayscale();

        $dimensions = $processor_gray->get_dimensions();
        $lbp_histogram = array_fill(0, 256, 0);

        for ($y = $radius; $y < $dimensions['height'] - $radius; $y++) {
            for ($x = $radius; $x < $dimensions['width'] - $radius; $x++) {
                $center_pixel = $processor_gray->get_pixel($x, $y);
                $center_value = $center_pixel['r']; // Grayscale, so r=g=b

                $lbp_code = 0;

                for ($n = 0; $n < $neighbors; $n++) {
                    $angle = (2 * M_PI * $n) / $neighbors;
                    $nx = $x + (int)round($radius * cos($angle));
                    $ny = $y - (int)round($radius * sin($angle));

                    // Clamp coordinates
                    $nx = max(0, min($dimensions['width'] - 1, $nx));
                    $ny = max(0, min($dimensions['height'] - 1, $ny));

                    $neighbor_pixel = $processor_gray->get_pixel($nx, $ny);
                    $neighbor_value = $neighbor_pixel['r'];

                    if ($neighbor_value >= $center_value) {
                        $lbp_code |= (1 << $n);
                    }
                }

                $lbp_histogram[$lbp_code]++;
            }
        }

        // Normalize
        $total = array_sum($lbp_histogram);
        if ($total > 0) {
            for ($i = 0; $i < 256; $i++) {
                $lbp_histogram[$i] /= $total;
            }
        }

        return array(
            'histogram' => $lbp_histogram,
            'entropy' => $this->calculate_entropy($lbp_histogram),
            'uniformity' => $this->calculate_uniformity($lbp_histogram)
        );
    }

    /**
     * Extract shape descriptors using image moments
     * Hu moments are invariant to translation, scale, and rotation
     */
    public function extract_shape_descriptors($image_path) {
        $this->processor->load_image($image_path);
        $processor_gray = clone $this->processor;
        $processor_gray->to_grayscale();

        $dimensions = $processor_gray->get_dimensions();

        // Calculate raw moments
        $moments = array();
        for ($p = 0; $p <= 3; $p++) {
            for ($q = 0; $q <= 3; $q++) {
                $moments["m$p$q"] = 0;
            }
        }

        for ($y = 0; $y < $dimensions['height']; $y++) {
            for ($x = 0; $x < $dimensions['width']; $x++) {
                $pixel = $processor_gray->get_pixel($x, $y);
                $intensity = $pixel['r'] / 255; // Normalize to 0-1

                for ($p = 0; $p <= 3; $p++) {
                    for ($q = 0; $q <= 3; $q++) {
                        $moments["m$p$q"] += pow($x, $p) * pow($y, $q) * $intensity;
                    }
                }
            }
        }

        // Calculate central moments
        $x_bar = $moments['m10'] / max($moments['m00'], 0.00001);
        $y_bar = $moments['m01'] / max($moments['m00'], 0.00001);

        $central_moments = array();
        for ($p = 0; $p <= 3; $p++) {
            for ($q = 0; $q <= 3; $q++) {
                $central_moments["mu$p$q"] = 0;
            }
        }

        for ($y = 0; $y < $dimensions['height']; $y++) {
            for ($x = 0; $x < $dimensions['width']; $x++) {
                $pixel = $processor_gray->get_pixel($x, $y);
                $intensity = $pixel['r'] / 255;

                for ($p = 0; $p <= 3; $p++) {
                    for ($q = 0; $q <= 3; $q++) {
                        $central_moments["mu$p$q"] += pow($x - $x_bar, $p) * pow($y - $y_bar, $q) * $intensity;
                    }
                }
            }
        }

        // Calculate normalized central moments (scale invariant)
        $normalized_moments = array();
        for ($p = 0; $p <= 3; $p++) {
            for ($q = 0; $q <= 3; $q++) {
                if ($p + $q >= 2) {
                    $gamma = ($p + $q) / 2 + 1;
                    $normalized_moments["eta$p$q"] = $central_moments["mu$p$q"] / pow(max($central_moments['mu00'], 0.00001), $gamma);
                }
            }
        }

        // Calculate Hu moments (rotation, scale, translation invariant)
        $eta = $normalized_moments;
        $hu_moments = array();

        $hu_moments[1] = $eta['eta20'] + $eta['eta02'];
        $hu_moments[2] = pow($eta['eta20'] - $eta['eta02'], 2) + 4 * pow($eta['eta11'], 2);
        $hu_moments[3] = pow($eta['eta30'] - 3 * $eta['eta12'], 2) + pow(3 * $eta['eta21'] - $eta['eta03'], 2);
        $hu_moments[4] = pow($eta['eta30'] + $eta['eta12'], 2) + pow($eta['eta21'] + $eta['eta03'], 2);

        return array(
            'raw_moments' => $moments,
            'central_moments' => $central_moments,
            'normalized_moments' => $normalized_moments,
            'hu_moments' => $hu_moments,
            'centroid' => array('x' => $x_bar, 'y' => $y_bar)
        );
    }

    /**
     * Extract SIFT-like keypoints (simplified version)
     * Detects scale and rotation invariant interest points
     */
    public function extract_keypoints($image_path, $threshold = 10) {
        $this->processor->load_image($image_path);
        $processor_gray = clone $this->processor;
        $processor_gray->to_grayscale();

        $dimensions = $processor_gray->get_dimensions();

        // Build scale space using Gaussian pyramids
        $octaves = 3;
        $scales_per_octave = 3;
        $sigma = 1.6;

        $scale_space = array();
        $dog_space = array(); // Difference of Gaussians

        for ($o = 0; $o < $octaves; $o++) {
            $scale_space[$o] = array();
            $dog_space[$o] = array();

            for ($s = 0; $s < $scales_per_octave + 3; $s++) {
                $current_sigma = pow(2, $o) * $sigma * pow(2, $s / $scales_per_octave);

                $scale_processor = clone $processor_gray;
                $scale_processor->gaussian_blur($current_sigma);
                $scale_space[$o][$s] = $scale_processor;

                // Calculate DoG
                if ($s > 0) {
                    $dog_space[$o][$s - 1] = $this->compute_difference_of_gaussians(
                        $scale_space[$o][$s],
                        $scale_space[$o][$s - 1]
                    );
                }
            }
        }

        // Find keypoints (local extrema in DoG space)
        $keypoints = array();

        for ($o = 0; $o < $octaves; $o++) {
            for ($s = 1; $s < $scales_per_octave + 1; $s++) {
                if (!isset($dog_space[$o][$s - 1]) || !isset($dog_space[$o][$s]) || !isset($dog_space[$o][$s + 1])) {
                    continue;
                }

                $dims = $dog_space[$o][$s]->get_dimensions();

                for ($y = 1; $y < $dims['height'] - 1; $y++) {
                    for ($x = 1; $x < $dims['width'] - 1; $x++) {
                        $center_value = $this->get_dog_value($dog_space[$o][$s], $x, $y);

                        if (abs($center_value) < $threshold) {
                            continue;
                        }

                        // Check if it's a local extremum
                        if ($this->is_extremum($dog_space[$o], $s, $x, $y, $center_value)) {
                            // Calculate keypoint orientation
                            $orientation = $this->calculate_keypoint_orientation(
                                $scale_space[$o][$s], $x, $y
                            );

                            $keypoints[] = array(
                                'x' => $x * pow(2, $o),
                                'y' => $y * pow(2, $o),
                                'scale' => pow(2, $o) * $sigma * pow(2, $s / $scales_per_octave),
                                'octave' => $o,
                                'response' => abs($center_value),
                                'orientation' => $orientation
                            );
                        }
                    }
                }
            }
        }

        // Sort by response strength
        usort($keypoints, function($a, $b) {
            return $b['response'] <=> $a['response'];
        });

        // Limit to top keypoints
        $keypoints = array_slice($keypoints, 0, min(100, count($keypoints)));

        return $keypoints;
    }

    /**
     * Compute Difference of Gaussians
     */
    private function compute_difference_of_gaussians($img1, $img2) {
        $dims = $img1->get_dimensions();
        $dog = new ImageProcessor();
        $dog->create($dims['width'], $dims['height']);

        for ($y = 0; $y < $dims['height']; $y++) {
            for ($x = 0; $x < $dims['width']; $x++) {
                $pixel1 = $img1->get_pixel($x, $y);
                $pixel2 = $img2->get_pixel($x, $y);

                $diff = abs($pixel1['r'] - $pixel2['r']);
                $dog->set_pixel($x, $y, $diff, $diff, $diff);
            }
        }

        return $dog;
    }

    /**
     * Get DoG value at position
     */
    private function get_dog_value($dog_image, $x, $y) {
        $pixel = $dog_image->get_pixel($x, $y);
        return $pixel['r'];
    }

    /**
     * Check if point is local extremum in scale space
     */
    private function is_extremum($dog_octave, $scale, $x, $y, $center_value) {
        // Check 26 neighbors (3x3x3 cube in scale space)
        for ($ds = -1; $ds <= 1; $ds++) {
            if (!isset($dog_octave[$scale + $ds])) {
                continue;
            }

            for ($dy = -1; $dy <= 1; $dy++) {
                for ($dx = -1; $dx <= 1; $dx++) {
                    if ($dx == 0 && $dy == 0 && $ds == 0) {
                        continue;
                    }

                    $neighbor_value = $this->get_dog_value(
                        $dog_octave[$scale + $ds],
                        $x + $dx,
                        $y + $dy
                    );

                    // Check for maximum
                    if ($center_value > 0 && $neighbor_value >= $center_value) {
                        return false;
                    }

                    // Check for minimum
                    if ($center_value < 0 && $neighbor_value <= $center_value) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Calculate dominant orientation for keypoint
     */
    private function calculate_keypoint_orientation($image, $x, $y) {
        $dims = $image->get_dimensions();
        $orientation_histogram = array_fill(0, 36, 0); // 36 bins (10 degrees each)

        $radius = 8;
        for ($dy = -$radius; $dy <= $radius; $dy++) {
            for ($dx = -$radius; $dx <= $radius; $dx++) {
                $nx = $x + $dx;
                $ny = $y + $dy;

                if ($nx < 1 || $nx >= $dims['width'] - 1 || $ny < 1 || $ny >= $dims['height'] - 1) {
                    continue;
                }

                // Calculate gradient
                $pixel_left = $image->get_pixel($nx - 1, $ny);
                $pixel_right = $image->get_pixel($nx + 1, $ny);
                $pixel_up = $image->get_pixel($nx, $ny - 1);
                $pixel_down = $image->get_pixel($nx, $ny + 1);

                $gx = $pixel_right['r'] - $pixel_left['r'];
                $gy = $pixel_down['r'] - $pixel_up['r'];

                $magnitude = sqrt($gx * $gx + $gy * $gy);
                $angle = atan2($gy, $gx) * 180 / M_PI;

                if ($angle < 0) $angle += 360;

                $bin = (int)(($angle / 360) * 36) % 36;
                $orientation_histogram[$bin] += $magnitude;
            }
        }

        // Find dominant orientation
        $max_bin = 0;
        $max_value = 0;
        for ($i = 0; $i < 36; $i++) {
            if ($orientation_histogram[$i] > $max_value) {
                $max_value = $orientation_histogram[$i];
                $max_bin = $i;
            }
        }

        return ($max_bin * 10) * M_PI / 180; // Convert to radians
    }

    /**
     * Calculate entropy of histogram
     */
    private function calculate_entropy($histogram) {
        $entropy = 0;
        foreach ($histogram as $value) {
            if ($value > 0) {
                $entropy -= $value * log($value);
            }
        }
        return $entropy;
    }

    /**
     * Calculate uniformity of histogram
     */
    private function calculate_uniformity($histogram) {
        $uniformity = 0;
        foreach ($histogram as $value) {
            $uniformity += $value * $value;
        }
        return $uniformity;
    }

    /**
     * Create feature vector from extracted features
     * Useful for machine learning and similarity comparison
     */
    public function create_feature_vector($features) {
        $vector = array();

        // Color histogram features
        if (isset($features['color_histogram'])) {
            foreach ($features['color_histogram'] as $channel => $values) {
                $vector = array_merge($vector, $values);
            }
        }

        // Edge histogram features
        if (isset($features['edge_histogram'])) {
            $vector = array_merge($vector, $features['edge_histogram']);
        }

        // Texture features
        if (isset($features['texture_features']['histogram'])) {
            // Use only top LBP patterns to reduce dimensionality
            $lbp_hist = $features['texture_features']['histogram'];
            arsort($lbp_hist);
            $top_patterns = array_slice($lbp_hist, 0, 32, true);
            $vector = array_merge($vector, array_values($top_patterns));
        }

        // Shape descriptors
        if (isset($features['shape_descriptors']['hu_moments'])) {
            $vector = array_merge($vector, array_values($features['shape_descriptors']['hu_moments']));
        }

        return $vector;
    }
}
