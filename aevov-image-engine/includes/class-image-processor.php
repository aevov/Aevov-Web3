<?php
/**
 * Image Processor - Real Computer Vision Operations
 *
 * Provides core image processing functionality using GD library:
 * - Image loading/saving (JPEG, PNG, GIF)
 * - Resizing and cropping
 * - Color space conversions
 * - Histogram equalization
 * - Gaussian blur
 * - Edge detection (Sobel, Canny)
 * - Convolution operations
 */

namespace AevovImageEngine;

class ImageProcessor {

    private $image;
    private $width;
    private $height;
    private $type;

    /**
     * Load image from file
     */
    public function load_image($file_path) {
        if (!file_exists($file_path)) {
            throw new \Exception("Image file not found: $file_path");
        }

        $image_info = getimagesize($file_path);
        $this->type = $image_info[2];

        switch ($this->type) {
            case IMAGETYPE_JPEG:
                $this->image = imagecreatefromjpeg($file_path);
                break;
            case IMAGETYPE_PNG:
                $this->image = imagecreatefrompng($file_path);
                imagealphablending($this->image, false);
                imagesavealpha($this->image, true);
                break;
            case IMAGETYPE_GIF:
                $this->image = imagecreatefromgif($file_path);
                break;
            default:
                throw new \Exception("Unsupported image type");
        }

        $this->width = imagesx($this->image);
        $this->height = imagesy($this->image);

        return $this;
    }

    /**
     * Load from GD resource
     */
    public function load_from_resource($gd_resource) {
        $this->image = $gd_resource;
        $this->width = imagesx($gd_resource);
        $this->height = imagesy($gd_resource);
        return $this;
    }

    /**
     * Create blank image
     */
    public function create($width, $height) {
        $this->width = $width;
        $this->height = $height;
        $this->image = imagecreatetruecolor($width, $height);
        return $this;
    }

    /**
     * Save image to file
     */
    public function save($file_path, $quality = 90) {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($this->image, $file_path, $quality);
                break;
            case 'png':
                $png_quality = (int) (9 - ($quality / 10));
                imagepng($this->image, $file_path, $png_quality);
                break;
            case 'gif':
                imagegif($this->image, $file_path);
                break;
            default:
                throw new \Exception("Unsupported output format: $extension");
        }

        return $this;
    }

    /**
     * Resize image (maintains aspect ratio)
     */
    public function resize($new_width, $new_height, $maintain_aspect = true) {
        if ($maintain_aspect) {
            $ratio = min($new_width / $this->width, $new_height / $this->height);
            $new_width = (int) ($this->width * $ratio);
            $new_height = (int) ($this->height * $ratio);
        }

        $new_image = imagecreatetruecolor($new_width, $new_height);

        // Preserve transparency for PNG/GIF
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);

        imagecopyresampled(
            $new_image, $this->image,
            0, 0, 0, 0,
            $new_width, $new_height,
            $this->width, $this->height
        );

        imagedestroy($this->image);
        $this->image = $new_image;
        $this->width = $new_width;
        $this->height = $new_height;

        return $this;
    }

    /**
     * Crop image
     */
    public function crop($x, $y, $width, $height) {
        $new_image = imagecreatetruecolor($width, $height);

        imagecopy($new_image, $this->image, 0, 0, $x, $y, $width, $height);

        imagedestroy($this->image);
        $this->image = $new_image;
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * Convert to grayscale
     */
    public function to_grayscale() {
        imagefilter($this->image, IMG_FILTER_GRAYSCALE);
        return $this;
    }

    /**
     * Convert RGB to HSV
     * Returns array of HSV values
     */
    public function rgb_to_hsv($r, $g, $b) {
        $r = $r / 255;
        $g = $g / 255;
        $b = $b / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;

        // Hue calculation
        if ($delta == 0) {
            $h = 0;
        } elseif ($max == $r) {
            $h = 60 * fmod((($g - $b) / $delta), 6);
        } elseif ($max == $g) {
            $h = 60 * ((($b - $r) / $delta) + 2);
        } else {
            $h = 60 * ((($r - $g) / $delta) + 4);
        }

        if ($h < 0) {
            $h += 360;
        }

        // Saturation calculation
        $s = ($max == 0) ? 0 : ($delta / $max);

        // Value calculation
        $v = $max;

        return array($h, $s * 100, $v * 100);
    }

    /**
     * Get pixel color at coordinates
     */
    public function get_pixel($x, $y) {
        $rgb = imagecolorat($this->image, $x, $y);
        return array(
            'r' => ($rgb >> 16) & 0xFF,
            'g' => ($rgb >> 8) & 0xFF,
            'b' => $rgb & 0xFF,
            'a' => ($rgb & 0x7F000000) >> 24
        );
    }

    /**
     * Set pixel color at coordinates
     */
    public function set_pixel($x, $y, $r, $g, $b, $a = 0) {
        $color = imagecolorallocatealpha($this->image, $r, $g, $b, $a);
        imagesetpixel($this->image, $x, $y, $color);
        return $this;
    }

    /**
     * Compute histogram equalization
     * Improves contrast by spreading out intensity values
     */
    public function histogram_equalization() {
        $histogram = array_fill(0, 256, 0);
        $total_pixels = $this->width * $this->height;

        // Calculate histogram
        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                $pixel = $this->get_pixel($x, $y);
                $gray = (int) (0.299 * $pixel['r'] + 0.587 * $pixel['g'] + 0.114 * $pixel['b']);
                $histogram[$gray]++;
            }
        }

        // Calculate cumulative distribution
        $cdf = array();
        $cdf[0] = $histogram[0];
        for ($i = 1; $i < 256; $i++) {
            $cdf[$i] = $cdf[$i - 1] + $histogram[$i];
        }

        // Find minimum CDF value (non-zero)
        $cdf_min = 0;
        for ($i = 0; $i < 256; $i++) {
            if ($cdf[$i] > 0) {
                $cdf_min = $cdf[$i];
                break;
            }
        }

        // Create lookup table
        $lut = array();
        for ($i = 0; $i < 256; $i++) {
            $lut[$i] = (int) round(($cdf[$i] - $cdf_min) / ($total_pixels - $cdf_min) * 255);
        }

        // Apply equalization
        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                $pixel = $this->get_pixel($x, $y);
                $gray = (int) (0.299 * $pixel['r'] + 0.587 * $pixel['g'] + 0.114 * $pixel['b']);
                $new_value = $lut[$gray];
                $this->set_pixel($x, $y, $new_value, $new_value, $new_value);
            }
        }

        return $this;
    }

    /**
     * Apply Gaussian blur using convolution
     */
    public function gaussian_blur($radius = 1.0) {
        // Generate Gaussian kernel
        $kernel_size = (int) ceil($radius * 3) * 2 + 1;
        $kernel = $this->generate_gaussian_kernel($kernel_size, $radius);

        return $this->apply_convolution($kernel);
    }

    /**
     * Generate Gaussian kernel for blurring
     */
    private function generate_gaussian_kernel($size, $sigma) {
        $kernel = array();
        $sum = 0;
        $center = (int) floor($size / 2);

        for ($y = 0; $y < $size; $y++) {
            $kernel[$y] = array();
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
     * Apply convolution matrix to image
     */
    public function apply_convolution($kernel) {
        $kernel_height = count($kernel);
        $kernel_width = count($kernel[0]);
        $offset_y = (int) floor($kernel_height / 2);
        $offset_x = (int) floor($kernel_width / 2);

        $new_image = imagecreatetruecolor($this->width, $this->height);

        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                $r = 0; $g = 0; $b = 0;

                for ($ky = 0; $ky < $kernel_height; $ky++) {
                    for ($kx = 0; $kx < $kernel_width; $kx++) {
                        $px = $x + $kx - $offset_x;
                        $py = $y + $ky - $offset_y;

                        // Handle edges with border replication
                        $px = max(0, min($this->width - 1, $px));
                        $py = max(0, min($this->height - 1, $py));

                        $pixel = $this->get_pixel($px, $py);
                        $k_value = $kernel[$ky][$kx];

                        $r += $pixel['r'] * $k_value;
                        $g += $pixel['g'] * $k_value;
                        $b += $pixel['b'] * $k_value;
                    }
                }

                // Clamp values
                $r = max(0, min(255, (int) $r));
                $g = max(0, min(255, (int) $g));
                $b = max(0, min(255, (int) $b));

                $color = imagecolorallocate($new_image, $r, $g, $b);
                imagesetpixel($new_image, $x, $y, $color);
            }
        }

        imagedestroy($this->image);
        $this->image = $new_image;

        return $this;
    }

    /**
     * Sobel edge detection
     * Returns gradient magnitude and direction
     */
    public function sobel_edge_detection() {
        // Sobel kernels
        $sobel_x = array(
            array(-1, 0, 1),
            array(-2, 0, 2),
            array(-1, 0, 1)
        );

        $sobel_y = array(
            array(-1, -2, -1),
            array(0, 0, 0),
            array(1, 2, 1)
        );

        $gradient_magnitude = array();
        $gradient_direction = array();

        for ($y = 1; $y < $this->height - 1; $y++) {
            $gradient_magnitude[$y] = array();
            $gradient_direction[$y] = array();

            for ($x = 1; $x < $this->width - 1; $x++) {
                $gx = 0;
                $gy = 0;

                // Apply Sobel kernels
                for ($ky = 0; $ky < 3; $ky++) {
                    for ($kx = 0; $kx < 3; $kx++) {
                        $px = $x + $kx - 1;
                        $py = $y + $ky - 1;

                        $pixel = $this->get_pixel($px, $py);
                        $gray = (int) (0.299 * $pixel['r'] + 0.587 * $pixel['g'] + 0.114 * $pixel['b']);

                        $gx += $gray * $sobel_x[$ky][$kx];
                        $gy += $gray * $sobel_y[$ky][$kx];
                    }
                }

                $magnitude = sqrt($gx * $gx + $gy * $gy);
                $direction = atan2($gy, $gx);

                $gradient_magnitude[$y][$x] = $magnitude;
                $gradient_direction[$y][$x] = $direction;
            }
        }

        return array(
            'magnitude' => $gradient_magnitude,
            'direction' => $gradient_direction
        );
    }

    /**
     * Canny edge detection
     * Multi-stage edge detection algorithm
     */
    public function canny_edge_detection($low_threshold = 50, $high_threshold = 150) {
        // Step 1: Apply Gaussian blur to reduce noise
        $blurred = clone $this;
        $blurred->gaussian_blur(1.4);

        // Step 2: Calculate gradients using Sobel
        $gradients = $blurred->sobel_edge_detection();
        $magnitude = $gradients['magnitude'];
        $direction = $gradients['direction'];

        // Step 3: Non-maximum suppression
        $suppressed = array();
        for ($y = 1; $y < $this->height - 1; $y++) {
            $suppressed[$y] = array();
            for ($x = 1; $x < $this->width - 1; $x++) {
                if (!isset($magnitude[$y][$x])) {
                    $suppressed[$y][$x] = 0;
                    continue;
                }

                $angle = $direction[$y][$x] * 180 / M_PI;
                if ($angle < 0) $angle += 180;

                $mag = $magnitude[$y][$x];
                $q = 255;
                $r = 255;

                // Determine neighbors based on gradient direction
                if ((0 <= $angle && $angle < 22.5) || (157.5 <= $angle && $angle <= 180)) {
                    $q = isset($magnitude[$y][$x + 1]) ? $magnitude[$y][$x + 1] : 0;
                    $r = isset($magnitude[$y][$x - 1]) ? $magnitude[$y][$x - 1] : 0;
                } elseif (22.5 <= $angle && $angle < 67.5) {
                    $q = isset($magnitude[$y + 1][$x - 1]) ? $magnitude[$y + 1][$x - 1] : 0;
                    $r = isset($magnitude[$y - 1][$x + 1]) ? $magnitude[$y - 1][$x + 1] : 0;
                } elseif (67.5 <= $angle && $angle < 112.5) {
                    $q = isset($magnitude[$y + 1][$x]) ? $magnitude[$y + 1][$x] : 0;
                    $r = isset($magnitude[$y - 1][$x]) ? $magnitude[$y - 1][$x] : 0;
                } elseif (112.5 <= $angle && $angle < 157.5) {
                    $q = isset($magnitude[$y - 1][$x - 1]) ? $magnitude[$y - 1][$x - 1] : 0;
                    $r = isset($magnitude[$y + 1][$x + 1]) ? $magnitude[$y + 1][$x + 1] : 0;
                }

                if ($mag >= $q && $mag >= $r) {
                    $suppressed[$y][$x] = $mag;
                } else {
                    $suppressed[$y][$x] = 0;
                }
            }
        }

        // Step 4: Double threshold and edge tracking by hysteresis
        $edges = imagecreatetruecolor($this->width, $this->height);
        $white = imagecolorallocate($edges, 255, 255, 255);
        $black = imagecolorallocate($edges, 0, 0, 0);

        // Fill with black
        imagefill($edges, 0, 0, $black);

        for ($y = 1; $y < $this->height - 1; $y++) {
            for ($x = 1; $x < $this->width - 1; $x++) {
                if (!isset($suppressed[$y][$x])) continue;

                $mag = $suppressed[$y][$x];

                if ($mag >= $high_threshold) {
                    // Strong edge
                    imagesetpixel($edges, $x, $y, $white);
                } elseif ($mag >= $low_threshold) {
                    // Weak edge - check if connected to strong edge
                    $has_strong_neighbor = false;
                    for ($dy = -1; $dy <= 1; $dy++) {
                        for ($dx = -1; $dx <= 1; $dx++) {
                            if ($dx == 0 && $dy == 0) continue;
                            $ny = $y + $dy;
                            $nx = $x + $dx;
                            if (isset($suppressed[$ny][$nx]) && $suppressed[$ny][$nx] >= $high_threshold) {
                                $has_strong_neighbor = true;
                                break 2;
                            }
                        }
                    }
                    if ($has_strong_neighbor) {
                        imagesetpixel($edges, $x, $y, $white);
                    }
                }
            }
        }

        imagedestroy($this->image);
        $this->image = $edges;

        return $this;
    }

    /**
     * Get image resource
     */
    public function get_resource() {
        return $this->image;
    }

    /**
     * Get image dimensions
     */
    public function get_dimensions() {
        return array(
            'width' => $this->width,
            'height' => $this->height
        );
    }

    /**
     * Clone image
     */
    public function __clone() {
        $new_image = imagecreatetruecolor($this->width, $this->height);
        imagecopy($new_image, $this->image, 0, 0, 0, 0, $this->width, $this->height);
        $this->image = $new_image;
    }

    /**
     * Cleanup
     */
    public function __destruct() {
        if (is_resource($this->image)) {
            imagedestroy($this->image);
        }
    }
}
