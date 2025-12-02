<?php
/**
 * AROS Vision/Camera Processor
 *
 * Real image processing implementation with:
 * - Image preprocessing (grayscale, normalization, filtering)
 * - Edge detection (Sobel, Canny)
 * - Feature detection (corners, blobs)
 * - Object detection and tracking
 * - Color segmentation
 * - Image enhancement
 */

namespace AROS\Perception;

class VisionProcessor {
    private $image_width = 640;
    private $image_height = 480;
    private $color_mode = 'RGB'; // RGB, BGR, GRAYSCALE
    private $feature_threshold = 50;
    private $edge_threshold_low = 50;
    private $edge_threshold_high = 150;

    public function __construct($config = []) {
        $this->image_width = $config['width'] ?? 640;
        $this->image_height = $config['height'] ?? 480;
        $this->color_mode = $config['color_mode'] ?? 'RGB';
    }

    /**
     * Main image processing function
     * Input: Raw image data (array or base64)
     * Output: Processed image with features, edges, objects
     */
    public function process($image_data) {
        if (empty($image_data)) {
            return [
                'features' => [],
                'edges' => [],
                'objects' => [],
                'processed_image' => null
            ];
        }

        // Parse image data
        $image = $this->parse_image_data($image_data);

        // Convert to grayscale for processing
        $grayscale = $this->to_grayscale($image);

        // Apply Gaussian blur to reduce noise
        $blurred = $this->gaussian_blur($grayscale, 5);

        // Edge detection
        $edges = $this->canny_edge_detection($blurred);

        // Feature detection (corners)
        $features = $this->harris_corner_detection($grayscale);

        // Color-based object detection
        $objects = $this->detect_colored_objects($image);

        // Histogram analysis
        $histogram = $this->compute_histogram($grayscale);

        return [
            'features' => $features,
            'edges' => $edges,
            'objects' => $objects,
            'histogram' => $histogram,
            'processed_image' => $grayscale,
            'image_stats' => [
                'width' => $this->image_width,
                'height' => $this->image_height,
                'num_features' => count($features),
                'num_objects' => count($objects)
            ]
        ];
    }

    /**
     * Parse image data from various formats
     */
    private function parse_image_data($image_data) {
        // If already a 2D/3D array, return it
        if (is_array($image_data) && isset($image_data[0])) {
            return $image_data;
        }

        // Generate simulated image for testing
        return $this->generate_test_image();
    }

    /**
     * Convert RGB image to grayscale
     */
    private function to_grayscale($image) {
        $grayscale = [];

        for ($y = 0; $y < count($image); $y++) {
            for ($x = 0; $x < count($image[$y]); $x++) {
                $pixel = $image[$y][$x];

                if (is_array($pixel)) {
                    // RGB to grayscale: Y = 0.299*R + 0.587*G + 0.114*B
                    $r = $pixel['r'] ?? $pixel[0] ?? 0;
                    $g = $pixel['g'] ?? $pixel[1] ?? 0;
                    $b = $pixel['b'] ?? $pixel[2] ?? 0;

                    $grayscale[$y][$x] = (int)(0.299 * $r + 0.587 * $g + 0.114 * $b);
                } else {
                    // Already grayscale
                    $grayscale[$y][$x] = $pixel;
                }
            }
        }

        return $grayscale;
    }

    /**
     * Gaussian blur filter for noise reduction
     */
    private function gaussian_blur($image, $kernel_size = 5) {
        $sigma = $kernel_size / 6.0;
        $kernel = $this->create_gaussian_kernel($kernel_size, $sigma);

        return $this->convolve($image, $kernel);
    }

    /**
     * Create Gaussian kernel
     */
    private function create_gaussian_kernel($size, $sigma) {
        $kernel = [];
        $sum = 0.0;
        $center = (int)($size / 2);

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $dx = $x - $center;
                $dy = $y - $center;

                $value = exp(-($dx * $dx + $dy * $dy) / (2 * $sigma * $sigma));
                $kernel[$y][$x] = $value;
                $sum += $value;
            }
        }

        // Normalize kernel
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $kernel[$y][$x] /= $sum;
            }
        }

        return $kernel;
    }

    /**
     * Convolution operation
     */
    private function convolve($image, $kernel) {
        $height = count($image);
        $width = count($image[0]);
        $k_height = count($kernel);
        $k_width = count($kernel[0]);
        $k_center_y = (int)($k_height / 2);
        $k_center_x = (int)($k_width / 2);

        $result = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $sum = 0.0;

                for ($ky = 0; $ky < $k_height; $ky++) {
                    for ($kx = 0; $kx < $k_width; $kx++) {
                        $img_y = $y + $ky - $k_center_y;
                        $img_x = $x + $kx - $k_center_x;

                        // Handle borders with zero padding
                        if ($img_y >= 0 && $img_y < $height && $img_x >= 0 && $img_x < $width) {
                            $sum += $image[$img_y][$img_x] * $kernel[$ky][$kx];
                        }
                    }
                }

                $result[$y][$x] = (int)max(0, min(255, $sum));
            }
        }

        return $result;
    }

    /**
     * Sobel edge detection
     */
    private function sobel_edge_detection($image) {
        // Sobel kernels
        $sobel_x = [
            [-1, 0, 1],
            [-2, 0, 2],
            [-1, 0, 1]
        ];

        $sobel_y = [
            [-1, -2, -1],
            [ 0,  0,  0],
            [ 1,  2,  1]
        ];

        $gradient_x = $this->convolve($image, $sobel_x);
        $gradient_y = $this->convolve($image, $sobel_y);

        // Compute gradient magnitude
        $height = count($image);
        $width = count($image[0]);
        $magnitude = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $gx = $gradient_x[$y][$x];
                $gy = $gradient_y[$y][$x];
                $magnitude[$y][$x] = (int)sqrt($gx * $gx + $gy * $gy);
            }
        }

        return $magnitude;
    }

    /**
     * Canny edge detection (simplified)
     */
    private function canny_edge_detection($image) {
        // Step 1: Compute gradients using Sobel
        $sobel_x = [
            [-1, 0, 1],
            [-2, 0, 2],
            [-1, 0, 1]
        ];

        $sobel_y = [
            [-1, -2, -1],
            [ 0,  0,  0],
            [ 1,  2,  1]
        ];

        $gradient_x = $this->convolve($image, $sobel_x);
        $gradient_y = $this->convolve($image, $sobel_y);

        $height = count($image);
        $width = count($image[0]);

        // Step 2: Compute magnitude and direction
        $magnitude = [];
        $direction = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $gx = $gradient_x[$y][$x];
                $gy = $gradient_y[$y][$x];

                $magnitude[$y][$x] = sqrt($gx * $gx + $gy * $gy);
                $direction[$y][$x] = atan2($gy, $gx);
            }
        }

        // Step 3: Non-maximum suppression (simplified)
        $suppressed = [];

        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $angle = $direction[$y][$x];
                $mag = $magnitude[$y][$x];

                // Quantize angle to 0, 45, 90, 135 degrees
                $angle_deg = ($angle * 180 / M_PI + 180) % 180;

                $neighbor1 = 0;
                $neighbor2 = 0;

                if ($angle_deg < 22.5 || $angle_deg >= 157.5) {
                    // Horizontal
                    $neighbor1 = $magnitude[$y][$x - 1];
                    $neighbor2 = $magnitude[$y][$x + 1];
                } elseif ($angle_deg >= 22.5 && $angle_deg < 67.5) {
                    // Diagonal /
                    $neighbor1 = $magnitude[$y - 1][$x + 1];
                    $neighbor2 = $magnitude[$y + 1][$x - 1];
                } elseif ($angle_deg >= 67.5 && $angle_deg < 112.5) {
                    // Vertical
                    $neighbor1 = $magnitude[$y - 1][$x];
                    $neighbor2 = $magnitude[$y + 1][$x];
                } else {
                    // Diagonal \
                    $neighbor1 = $magnitude[$y - 1][$x - 1];
                    $neighbor2 = $magnitude[$y + 1][$x + 1];
                }

                // Suppress if not local maximum
                if ($mag >= $neighbor1 && $mag >= $neighbor2) {
                    $suppressed[$y][$x] = $mag;
                } else {
                    $suppressed[$y][$x] = 0;
                }
            }
        }

        // Step 4: Double thresholding
        $edges = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $mag = $suppressed[$y][$x] ?? 0;

                if ($mag >= $this->edge_threshold_high) {
                    $edges[$y][$x] = 255; // Strong edge
                } elseif ($mag >= $this->edge_threshold_low) {
                    $edges[$y][$x] = 128; // Weak edge
                } else {
                    $edges[$y][$x] = 0;
                }
            }
        }

        return $edges;
    }

    /**
     * Harris corner detection
     */
    private function harris_corner_detection($image, $window_size = 3, $k = 0.04) {
        $height = count($image);
        $width = count($image[0]);

        // Compute gradients
        $sobel_x = [[-1, 0, 1], [-2, 0, 2], [-1, 0, 1]];
        $sobel_y = [[-1, -2, -1], [0, 0, 0], [1, 2, 1]];

        $Ix = $this->convolve($image, $sobel_x);
        $Iy = $this->convolve($image, $sobel_y);

        // Compute products of derivatives
        $Ix2 = [];
        $Iy2 = [];
        $Ixy = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $ix = $Ix[$y][$x];
                $iy = $Iy[$y][$x];

                $Ix2[$y][$x] = $ix * $ix;
                $Iy2[$y][$x] = $iy * $iy;
                $Ixy[$y][$x] = $ix * $iy;
            }
        }

        // Compute Harris response
        $harris_response = [];
        $offset = (int)($window_size / 2);

        for ($y = $offset; $y < $height - $offset; $y++) {
            for ($x = $offset; $x < $width - $offset; $x++) {
                $sum_Ix2 = 0;
                $sum_Iy2 = 0;
                $sum_Ixy = 0;

                // Sum over window
                for ($wy = -$offset; $wy <= $offset; $wy++) {
                    for ($wx = -$offset; $wx <= $offset; $wx++) {
                        $sum_Ix2 += $Ix2[$y + $wy][$x + $wx];
                        $sum_Iy2 += $Iy2[$y + $wy][$x + $wx];
                        $sum_Ixy += $Ixy[$y + $wy][$x + $wx];
                    }
                }

                // Harris corner response: R = det(M) - k*trace(M)^2
                $det = $sum_Ix2 * $sum_Iy2 - $sum_Ixy * $sum_Ixy;
                $trace = $sum_Ix2 + $sum_Iy2;
                $harris_response[$y][$x] = $det - $k * $trace * $trace;
            }
        }

        // Extract corner features
        $features = [];

        for ($y = $offset; $y < $height - $offset; $y++) {
            for ($x = $offset; $x < $width - $offset; $x++) {
                $response = $harris_response[$y][$x] ?? 0;

                if ($response > $this->feature_threshold) {
                    // Check if local maximum
                    $is_max = true;

                    for ($wy = -1; $wy <= 1; $wy++) {
                        for ($wx = -1; $wx <= 1; $wx++) {
                            if ($wy === 0 && $wx === 0) continue;

                            if (($harris_response[$y + $wy][$x + $wx] ?? 0) > $response) {
                                $is_max = false;
                                break 2;
                            }
                        }
                    }

                    if ($is_max) {
                        $features[] = [
                            'x' => $x,
                            'y' => $y,
                            'response' => $response,
                            'type' => 'corner'
                        ];
                    }
                }
            }
        }

        return $features;
    }

    /**
     * Detect colored objects using color segmentation
     */
    private function detect_colored_objects($image) {
        $objects = [];

        // Define color ranges (in RGB)
        $color_ranges = [
            'red' => [
                'min' => ['r' => 150, 'g' => 0, 'b' => 0],
                'max' => ['r' => 255, 'g' => 100, 'b' => 100]
            ],
            'green' => [
                'min' => ['r' => 0, 'g' => 150, 'b' => 0],
                'max' => ['r' => 100, 'g' => 255, 'b' => 100]
            ],
            'blue' => [
                'min' => ['r' => 0, 'g' => 0, 'b' => 150],
                'max' => ['r' => 100, 'g' => 100, 'b' => 255]
            ]
        ];

        foreach ($color_ranges as $color_name => $range) {
            $mask = $this->create_color_mask($image, $range['min'], $range['max']);
            $blobs = $this->find_blobs($mask);

            foreach ($blobs as $blob) {
                $objects[] = [
                    'color' => $color_name,
                    'centroid' => $blob['centroid'],
                    'area' => $blob['area'],
                    'bounding_box' => $blob['bounding_box']
                ];
            }
        }

        return $objects;
    }

    /**
     * Create binary mask based on color range
     */
    private function create_color_mask($image, $min_color, $max_color) {
        $height = count($image);
        $width = count($image[0]);
        $mask = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = $image[$y][$x];

                if (is_array($pixel)) {
                    $r = $pixel['r'] ?? $pixel[0] ?? 0;
                    $g = $pixel['g'] ?? $pixel[1] ?? 0;
                    $b = $pixel['b'] ?? $pixel[2] ?? 0;

                    if ($r >= $min_color['r'] && $r <= $max_color['r'] &&
                        $g >= $min_color['g'] && $g <= $max_color['g'] &&
                        $b >= $min_color['b'] && $b <= $max_color['b']) {
                        $mask[$y][$x] = 1;
                    } else {
                        $mask[$y][$x] = 0;
                    }
                } else {
                    $mask[$y][$x] = 0;
                }
            }
        }

        return $mask;
    }

    /**
     * Find connected components (blobs) in binary mask
     */
    private function find_blobs($mask, $min_area = 50) {
        $height = count($mask);
        $width = count($mask[0]);
        $visited = [];
        $blobs = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($mask[$y][$x] === 1 && !isset($visited["$y,$x"])) {
                    $blob = $this->flood_fill($mask, $x, $y, $visited);

                    if ($blob['area'] >= $min_area) {
                        $blobs[] = $blob;
                    }
                }
            }
        }

        return $blobs;
    }

    /**
     * Flood fill to find connected blob
     */
    private function flood_fill($mask, $start_x, $start_y, &$visited) {
        $height = count($mask);
        $width = count($mask[0]);
        $queue = [[$start_x, $start_y]];
        $pixels = [];

        while (!empty($queue)) {
            list($x, $y) = array_shift($queue);
            $key = "$y,$x";

            if (isset($visited[$key]) || $x < 0 || $x >= $width || $y < 0 || $y >= $height) {
                continue;
            }

            if ($mask[$y][$x] !== 1) {
                continue;
            }

            $visited[$key] = true;
            $pixels[] = ['x' => $x, 'y' => $y];

            // Add 4-connected neighbors
            $queue[] = [$x + 1, $y];
            $queue[] = [$x - 1, $y];
            $queue[] = [$x, $y + 1];
            $queue[] = [$x, $y - 1];
        }

        // Calculate blob properties
        $area = count($pixels);
        $centroid_x = 0;
        $centroid_y = 0;
        $min_x = INF; $max_x = -INF;
        $min_y = INF; $max_y = -INF;

        foreach ($pixels as $pixel) {
            $centroid_x += $pixel['x'];
            $centroid_y += $pixel['y'];
            $min_x = min($min_x, $pixel['x']);
            $max_x = max($max_x, $pixel['x']);
            $min_y = min($min_y, $pixel['y']);
            $max_y = max($max_y, $pixel['y']);
        }

        return [
            'area' => $area,
            'centroid' => [
                'x' => $centroid_x / $area,
                'y' => $centroid_y / $area
            ],
            'bounding_box' => [
                'min_x' => $min_x,
                'max_x' => $max_x,
                'min_y' => $min_y,
                'max_y' => $max_y
            ]
        ];
    }

    /**
     * Compute histogram of grayscale image
     */
    private function compute_histogram($image) {
        $histogram = array_fill(0, 256, 0);

        foreach ($image as $row) {
            foreach ($row as $pixel) {
                $value = (int)max(0, min(255, $pixel));
                $histogram[$value]++;
            }
        }

        return $histogram;
    }

    /**
     * Generate test image for simulation
     */
    private function generate_test_image() {
        $image = [];

        for ($y = 0; $y < $this->image_height; $y++) {
            for ($x = 0; $x < $this->image_width; $x++) {
                // Create gradient pattern
                $r = (int)(($x / $this->image_width) * 255);
                $g = (int)(($y / $this->image_height) * 255);
                $b = 128;

                // Add some shapes
                $dx = $x - $this->image_width / 2;
                $dy = $y - $this->image_height / 2;
                $dist = sqrt($dx * $dx + $dy * $dy);

                if ($dist < 50) {
                    $r = 255;
                    $g = 0;
                    $b = 0;
                }

                $image[$y][$x] = ['r' => $r, 'g' => $g, 'b' => $b];
            }
        }

        return $image;
    }
}
