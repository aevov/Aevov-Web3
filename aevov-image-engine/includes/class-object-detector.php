<?php
/**
 * Object Detector - Real Computer Vision Object Detection
 *
 * Detects objects and regions in images:
 * - Template matching
 * - Color-based segmentation
 * - Blob detection
 * - Contour analysis
 * - Connected component labeling
 * - Region properties extraction
 */

namespace AevovImageEngine;

require_once __DIR__ . '/class-image-processor.php';

class ObjectDetector {

    private $processor;

    public function __construct() {
        $this->processor = new ImageProcessor();
    }

    /**
     * Template matching - finds template image in source image
     * Returns locations and confidence scores
     */
    public function template_match($source_path, $template_path, $threshold = 0.8) {
        $source = new ImageProcessor();
        $source->load_image($source_path);

        $template = new ImageProcessor();
        $template->load_image($template_path);

        $source_dims = $source->get_dimensions();
        $template_dims = $template->get_dimensions();

        if ($template_dims['width'] > $source_dims['width'] ||
            $template_dims['height'] > $source_dims['height']) {
            throw new \Exception("Template is larger than source image");
        }

        $matches = array();

        // Slide template over source image
        for ($y = 0; $y <= $source_dims['height'] - $template_dims['height']; $y++) {
            for ($x = 0; $x <= $source_dims['width'] - $template_dims['width']; $x++) {
                $score = $this->calculate_template_match_score(
                    $source, $template, $x, $y, $template_dims['width'], $template_dims['height']
                );

                if ($score >= $threshold) {
                    $matches[] = array(
                        'x' => $x,
                        'y' => $y,
                        'width' => $template_dims['width'],
                        'height' => $template_dims['height'],
                        'confidence' => $score
                    );
                }
            }
        }

        // Non-maximum suppression to remove overlapping detections
        $matches = $this->non_maximum_suppression($matches, 0.5);

        return $matches;
    }

    /**
     * Calculate normalized cross-correlation for template matching
     */
    private function calculate_template_match_score($source, $template, $x, $y, $width, $height) {
        $source_sum = 0;
        $template_sum = 0;
        $cross_correlation = 0;
        $source_sq_sum = 0;
        $template_sq_sum = 0;
        $count = 0;

        for ($ty = 0; $ty < $height; $ty++) {
            for ($tx = 0; $tx < $width; $tx++) {
                $source_pixel = $source->get_pixel($x + $tx, $y + $ty);
                $template_pixel = $template->get_pixel($tx, $ty);

                // Convert to grayscale
                $source_gray = 0.299 * $source_pixel['r'] + 0.587 * $source_pixel['g'] + 0.114 * $source_pixel['b'];
                $template_gray = 0.299 * $template_pixel['r'] + 0.587 * $template_pixel['g'] + 0.114 * $template_pixel['b'];

                $source_sum += $source_gray;
                $template_sum += $template_gray;
                $cross_correlation += $source_gray * $template_gray;
                $source_sq_sum += $source_gray * $source_gray;
                $template_sq_sum += $template_gray * $template_gray;
                $count++;
            }
        }

        $source_mean = $source_sum / $count;
        $template_mean = $template_sum / $count;

        $numerator = $cross_correlation - $count * $source_mean * $template_mean;
        $denominator = sqrt(
            ($source_sq_sum - $count * $source_mean * $source_mean) *
            ($template_sq_sum - $count * $template_mean * $template_mean)
        );

        if ($denominator == 0) {
            return 0;
        }

        return max(0, min(1, $numerator / $denominator));
    }

    /**
     * Non-maximum suppression to remove overlapping detections
     */
    private function non_maximum_suppression($detections, $overlap_threshold = 0.5) {
        if (empty($detections)) {
            return array();
        }

        // Sort by confidence (highest first)
        usort($detections, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });

        $keep = array();

        while (!empty($detections)) {
            $best = array_shift($detections);
            $keep[] = $best;

            // Remove overlapping detections
            $detections = array_filter($detections, function($detection) use ($best, $overlap_threshold) {
                $overlap = $this->calculate_iou($best, $detection);
                return $overlap < $overlap_threshold;
            });

            $detections = array_values($detections); // Re-index
        }

        return $keep;
    }

    /**
     * Calculate Intersection over Union (IoU) between two boxes
     */
    private function calculate_iou($box1, $box2) {
        $x1 = max($box1['x'], $box2['x']);
        $y1 = max($box1['y'], $box2['y']);
        $x2 = min($box1['x'] + $box1['width'], $box2['x'] + $box2['width']);
        $y2 = min($box1['y'] + $box1['height'], $box2['y'] + $box2['height']);

        $intersection = max(0, $x2 - $x1) * max(0, $y2 - $y1);

        $area1 = $box1['width'] * $box1['height'];
        $area2 = $box2['width'] * $box2['height'];

        $union = $area1 + $area2 - $intersection;

        if ($union == 0) {
            return 0;
        }

        return $intersection / $union;
    }

    /**
     * Color-based segmentation
     * Segments image based on color ranges
     */
    public function segment_by_color($image_path, $target_color, $tolerance = 30) {
        $this->processor->load_image($image_path);
        $dims = $this->processor->get_dimensions();

        $mask = new ImageProcessor();
        $mask->create($dims['width'], $dims['height']);

        for ($y = 0; $y < $dims['height']; $y++) {
            for ($x = 0; $x < $dims['width']; $x++) {
                $pixel = $this->processor->get_pixel($x, $y);

                $distance = sqrt(
                    pow($pixel['r'] - $target_color['r'], 2) +
                    pow($pixel['g'] - $target_color['g'], 2) +
                    pow($pixel['b'] - $target_color['b'], 2)
                );

                if ($distance <= $tolerance) {
                    $mask->set_pixel($x, $y, 255, 255, 255);
                } else {
                    $mask->set_pixel($x, $y, 0, 0, 0);
                }
            }
        }

        return $mask;
    }

    /**
     * Segment using HSV color space
     * More robust for color-based object detection
     */
    public function segment_by_hsv($image_path, $h_range, $s_range, $v_range) {
        $this->processor->load_image($image_path);
        $dims = $this->processor->get_dimensions();

        $mask = new ImageProcessor();
        $mask->create($dims['width'], $dims['height']);

        for ($y = 0; $y < $dims['height']; $y++) {
            for ($x = 0; $x < $dims['width']; $x++) {
                $pixel = $this->processor->get_pixel($x, $y);
                $hsv = $this->processor->rgb_to_hsv($pixel['r'], $pixel['g'], $pixel['b']);

                $in_range = (
                    $hsv[0] >= $h_range[0] && $hsv[0] <= $h_range[1] &&
                    $hsv[1] >= $s_range[0] && $hsv[1] <= $s_range[1] &&
                    $hsv[2] >= $v_range[0] && $hsv[2] <= $v_range[1]
                );

                if ($in_range) {
                    $mask->set_pixel($x, $y, 255, 255, 255);
                } else {
                    $mask->set_pixel($x, $y, 0, 0, 0);
                }
            }
        }

        return $mask;
    }

    /**
     * Blob detection using connected component labeling
     * Finds connected regions in binary image
     */
    public function detect_blobs($mask_processor, $min_area = 100) {
        $dims = $mask_processor->get_dimensions();

        // Connected component labeling
        $labels = array();
        $current_label = 1;
        $equivalences = array();

        for ($y = 0; $y < $dims['height']; $y++) {
            $labels[$y] = array_fill(0, $dims['width'], 0);
        }

        // First pass - assign labels
        for ($y = 0; $y < $dims['height']; $y++) {
            for ($x = 0; $x < $dims['width']; $x++) {
                $pixel = $mask_processor->get_pixel($x, $y);

                if ($pixel['r'] > 127) { // Foreground pixel
                    $neighbors = array();

                    // Check top and left neighbors
                    if ($y > 0 && $labels[$y - 1][$x] > 0) {
                        $neighbors[] = $labels[$y - 1][$x];
                    }
                    if ($x > 0 && $labels[$y][$x - 1] > 0) {
                        $neighbors[] = $labels[$y][$x - 1];
                    }

                    if (empty($neighbors)) {
                        $labels[$y][$x] = $current_label;
                        $equivalences[$current_label] = $current_label;
                        $current_label++;
                    } else {
                        $min_label = min($neighbors);
                        $labels[$y][$x] = $min_label;

                        // Record equivalences
                        foreach ($neighbors as $neighbor_label) {
                            if ($neighbor_label != $min_label) {
                                $equivalences[$neighbor_label] = $min_label;
                            }
                        }
                    }
                }
            }
        }

        // Resolve equivalences
        foreach ($equivalences as $label => $equiv) {
            while ($equivalences[$equiv] != $equiv) {
                $equiv = $equivalences[$equiv];
            }
            $equivalences[$label] = $equiv;
        }

        // Second pass - resolve labels
        for ($y = 0; $y < $dims['height']; $y++) {
            for ($x = 0; $x < $dims['width']; $x++) {
                if ($labels[$y][$x] > 0) {
                    $labels[$y][$x] = $equivalences[$labels[$y][$x]];
                }
            }
        }

        // Extract blob properties
        $blobs = array();
        $blob_pixels = array();

        for ($y = 0; $y < $dims['height']; $y++) {
            for ($x = 0; $x < $dims['width']; $x++) {
                $label = $labels[$y][$x];
                if ($label > 0) {
                    if (!isset($blob_pixels[$label])) {
                        $blob_pixels[$label] = array();
                    }
                    $blob_pixels[$label][] = array('x' => $x, 'y' => $y);
                }
            }
        }

        // Calculate blob properties
        foreach ($blob_pixels as $label => $pixels) {
            if (count($pixels) < $min_area) {
                continue;
            }

            $min_x = PHP_INT_MAX;
            $max_x = 0;
            $min_y = PHP_INT_MAX;
            $max_y = 0;
            $sum_x = 0;
            $sum_y = 0;

            foreach ($pixels as $pixel) {
                $min_x = min($min_x, $pixel['x']);
                $max_x = max($max_x, $pixel['x']);
                $min_y = min($min_y, $pixel['y']);
                $max_y = max($max_y, $pixel['y']);
                $sum_x += $pixel['x'];
                $sum_y += $pixel['y'];
            }

            $blobs[] = array(
                'label' => $label,
                'area' => count($pixels),
                'centroid' => array(
                    'x' => $sum_x / count($pixels),
                    'y' => $sum_y / count($pixels)
                ),
                'bounding_box' => array(
                    'x' => $min_x,
                    'y' => $min_y,
                    'width' => $max_x - $min_x + 1,
                    'height' => $max_y - $min_y + 1
                ),
                'perimeter' => $this->calculate_perimeter($pixels),
                'circularity' => 0 // Will be calculated
            );

            // Calculate circularity
            $last_idx = count($blobs) - 1;
            $area = $blobs[$last_idx]['area'];
            $perimeter = $blobs[$last_idx]['perimeter'];

            if ($perimeter > 0) {
                $blobs[$last_idx]['circularity'] = (4 * M_PI * $area) / ($perimeter * $perimeter);
            }
        }

        return $blobs;
    }

    /**
     * Calculate perimeter of blob
     */
    private function calculate_perimeter($pixels) {
        // Create a set for fast lookup
        $pixel_set = array();
        foreach ($pixels as $pixel) {
            $key = $pixel['x'] . ',' . $pixel['y'];
            $pixel_set[$key] = true;
        }

        $perimeter = 0;

        // Count edge pixels
        foreach ($pixels as $pixel) {
            $neighbors = 0;

            // Check 4-connectivity
            $offsets = array(
                array(-1, 0), array(1, 0), array(0, -1), array(0, 1)
            );

            foreach ($offsets as $offset) {
                $nx = $pixel['x'] + $offset[0];
                $ny = $pixel['y'] + $offset[1];
                $key = $nx . ',' . $ny;

                if (isset($pixel_set[$key])) {
                    $neighbors++;
                }
            }

            // If not all neighbors are in the blob, it's on the perimeter
            if ($neighbors < 4) {
                $perimeter += (4 - $neighbors);
            }
        }

        return $perimeter;
    }

    /**
     * Find contours in binary image
     * Returns chain code representation
     */
    public function find_contours($mask_processor) {
        $dims = $mask_processor->get_dimensions();

        // Find blobs first
        $blobs = $this->detect_blobs($mask_processor, 10);

        $contours = array();

        foreach ($blobs as $blob) {
            $contour = $this->trace_contour(
                $mask_processor,
                $blob['bounding_box']['x'],
                $blob['bounding_box']['y'],
                $blob['bounding_box']['width'],
                $blob['bounding_box']['height']
            );

            if (!empty($contour)) {
                $contours[] = array(
                    'points' => $contour,
                    'area' => $blob['area'],
                    'length' => count($contour),
                    'centroid' => $blob['centroid']
                );
            }
        }

        return $contours;
    }

    /**
     * Trace contour using Moore-Neighbor tracing
     */
    private function trace_contour($mask, $start_x, $start_y, $width, $height) {
        // Find starting point (first foreground pixel)
        $start = null;
        for ($y = $start_y; $y < $start_y + $height && $start === null; $y++) {
            for ($x = $start_x; $x < $start_x + $width && $start === null; $x++) {
                $pixel = $mask->get_pixel($x, $y);
                if ($pixel['r'] > 127) {
                    $start = array('x' => $x, 'y' => $y);
                }
            }
        }

        if ($start === null) {
            return array();
        }

        $contour = array($start);

        // Moore neighborhood (8-connected)
        $directions = array(
            array(1, 0),   // E
            array(1, 1),   // SE
            array(0, 1),   // S
            array(-1, 1),  // SW
            array(-1, 0),  // W
            array(-1, -1), // NW
            array(0, -1),  // N
            array(1, -1)   // NE
        );

        $current = $start;
        $direction = 7; // Start looking north
        $max_iterations = 10000; // Prevent infinite loops
        $iterations = 0;

        do {
            $found = false;

            // Look for next contour point
            for ($i = 0; $i < 8; $i++) {
                $check_dir = ($direction + $i) % 8;
                $next_x = $current['x'] + $directions[$check_dir][0];
                $next_y = $current['y'] + $directions[$check_dir][1];

                // Check bounds
                $dims = $mask->get_dimensions();
                if ($next_x < 0 || $next_x >= $dims['width'] ||
                    $next_y < 0 || $next_y >= $dims['height']) {
                    continue;
                }

                $pixel = $mask->get_pixel($next_x, $next_y);

                if ($pixel['r'] > 127) {
                    $current = array('x' => $next_x, 'y' => $next_y);
                    $contour[] = $current;
                    $direction = ($check_dir + 5) % 8; // Turn left
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                break;
            }

            $iterations++;

        } while (
            ($current['x'] != $start['x'] || $current['y'] != $start['y']) &&
            $iterations < $max_iterations
        );

        return $contour;
    }

    /**
     * Morphological operations for noise reduction
     */
    public function morphological_erosion($mask_processor, $kernel_size = 3) {
        $dims = $mask_processor->get_dimensions();
        $result = new ImageProcessor();
        $result->create($dims['width'], $dims['height']);

        $offset = (int)floor($kernel_size / 2);

        for ($y = 0; $y < $dims['height']; $y++) {
            for ($x = 0; $x < $dims['width']; $x++) {
                $min_value = 255;

                for ($ky = -$offset; $ky <= $offset; $ky++) {
                    for ($kx = -$offset; $kx <= $offset; $kx++) {
                        $px = max(0, min($dims['width'] - 1, $x + $kx));
                        $py = max(0, min($dims['height'] - 1, $y + $ky));

                        $pixel = $mask_processor->get_pixel($px, $py);
                        $min_value = min($min_value, $pixel['r']);
                    }
                }

                $result->set_pixel($x, $y, $min_value, $min_value, $min_value);
            }
        }

        return $result;
    }

    /**
     * Morphological dilation
     */
    public function morphological_dilation($mask_processor, $kernel_size = 3) {
        $dims = $mask_processor->get_dimensions();
        $result = new ImageProcessor();
        $result->create($dims['width'], $dims['height']);

        $offset = (int)floor($kernel_size / 2);

        for ($y = 0; $y < $dims['height']; $y++) {
            for ($x = 0; $x < $dims['width']; $x++) {
                $max_value = 0;

                for ($ky = -$offset; $ky <= $offset; $ky++) {
                    for ($kx = -$offset; $kx <= $offset; $kx++) {
                        $px = max(0, min($dims['width'] - 1, $x + $kx));
                        $py = max(0, min($dims['height'] - 1, $y + $ky));

                        $pixel = $mask_processor->get_pixel($px, $py);
                        $max_value = max($max_value, $pixel['r']);
                    }
                }

                $result->set_pixel($x, $y, $max_value, $max_value, $max_value);
            }
        }

        return $result;
    }

    /**
     * Morphological opening (erosion followed by dilation)
     * Removes small objects and noise
     */
    public function morphological_opening($mask_processor, $kernel_size = 3) {
        $eroded = $this->morphological_erosion($mask_processor, $kernel_size);
        return $this->morphological_dilation($eroded, $kernel_size);
    }

    /**
     * Morphological closing (dilation followed by erosion)
     * Fills small holes
     */
    public function morphological_closing($mask_processor, $kernel_size = 3) {
        $dilated = $this->morphological_dilation($mask_processor, $kernel_size);
        return $this->morphological_erosion($dilated, $kernel_size);
    }
}
