<?php
/**
 * Image Comparator - Real Computer Vision Similarity Metrics
 *
 * Compares images using various algorithms:
 * - Histogram comparison (Chi-Square, Intersection, Bhattacharyya)
 * - Structural Similarity Index (SSIM)
 * - Perceptual hashing (pHash, dHash, aHash)
 * - Feature matching and distance metrics
 * - Mean Squared Error (MSE) and Peak Signal-to-Noise Ratio (PSNR)
 */

namespace AevovImageEngine;

require_once __DIR__ . '/class-image-processor.php';
require_once __DIR__ . '/class-feature-extractor.php';

class ImageComparator {

    private $processor;
    private $feature_extractor;

    public function __construct() {
        $this->processor = new ImageProcessor();
        $this->feature_extractor = new FeatureExtractor();
    }

    /**
     * Compare two images using all available metrics
     */
    public function compare_images($image_path1, $image_path2) {
        return array(
            'histogram_similarity' => $this->compare_histograms($image_path1, $image_path2),
            'ssim' => $this->calculate_ssim($image_path1, $image_path2),
            'perceptual_hash' => $this->compare_perceptual_hashes($image_path1, $image_path2),
            'feature_similarity' => $this->compare_features($image_path1, $image_path2),
            'mse' => $this->calculate_mse($image_path1, $image_path2),
            'psnr' => $this->calculate_psnr($image_path1, $image_path2)
        );
    }

    /**
     * Compare color histograms using multiple metrics
     */
    public function compare_histograms($image_path1, $image_path2, $method = 'all') {
        $hist1 = $this->feature_extractor->extract_color_histogram($image_path1);
        $hist2 = $this->feature_extractor->extract_color_histogram($image_path2);

        $results = array();

        if ($method === 'all' || $method === 'chi_square') {
            $results['chi_square'] = $this->chi_square_distance($hist1, $hist2);
        }

        if ($method === 'all' || $method === 'intersection') {
            $results['intersection'] = $this->histogram_intersection($hist1, $hist2);
        }

        if ($method === 'all' || $method === 'bhattacharyya') {
            $results['bhattacharyya'] = $this->bhattacharyya_distance($hist1, $hist2);
        }

        if ($method === 'all' || $method === 'correlation') {
            $results['correlation'] = $this->histogram_correlation($hist1, $hist2);
        }

        return $results;
    }

    /**
     * Chi-Square distance between histograms
     * Lower values indicate more similarity
     */
    private function chi_square_distance($hist1, $hist2) {
        $distance = 0;

        foreach ($hist1 as $channel => $values) {
            for ($i = 0; $i < count($values); $i++) {
                $sum = $hist1[$channel][$i] + $hist2[$channel][$i];
                if ($sum > 0) {
                    $diff = $hist1[$channel][$i] - $hist2[$channel][$i];
                    $distance += ($diff * $diff) / $sum;
                }
            }
        }

        return $distance;
    }

    /**
     * Histogram intersection
     * Returns similarity score between 0 and 1 (1 = identical)
     */
    private function histogram_intersection($hist1, $hist2) {
        $intersection = 0;
        $bins = 0;

        foreach ($hist1 as $channel => $values) {
            for ($i = 0; $i < count($values); $i++) {
                $intersection += min($hist1[$channel][$i], $hist2[$channel][$i]);
                $bins++;
            }
        }

        return $intersection;
    }

    /**
     * Bhattacharyya distance
     * Measures similarity of probability distributions
     */
    private function bhattacharyya_distance($hist1, $hist2) {
        $bc = 0; // Bhattacharyya coefficient

        foreach ($hist1 as $channel => $values) {
            for ($i = 0; $i < count($values); $i++) {
                $bc += sqrt($hist1[$channel][$i] * $hist2[$channel][$i]);
            }
        }

        // Convert coefficient to distance
        $distance = -log(max($bc, 1e-10));

        return $distance;
    }

    /**
     * Histogram correlation
     * Returns correlation coefficient between -1 and 1
     */
    private function histogram_correlation($hist1, $hist2) {
        $correlation = 0;
        $channels = 0;

        foreach ($hist1 as $channel => $values) {
            $mean1 = array_sum($hist1[$channel]) / count($hist1[$channel]);
            $mean2 = array_sum($hist2[$channel]) / count($hist2[$channel]);

            $numerator = 0;
            $denom1 = 0;
            $denom2 = 0;

            for ($i = 0; $i < count($values); $i++) {
                $diff1 = $hist1[$channel][$i] - $mean1;
                $diff2 = $hist2[$channel][$i] - $mean2;

                $numerator += $diff1 * $diff2;
                $denom1 += $diff1 * $diff1;
                $denom2 += $diff2 * $diff2;
            }

            $denom = sqrt($denom1 * $denom2);
            if ($denom > 0) {
                $correlation += $numerator / $denom;
                $channels++;
            }
        }

        return $channels > 0 ? $correlation / $channels : 0;
    }

    /**
     * Calculate Structural Similarity Index (SSIM)
     * Returns similarity between 0 and 1 (1 = identical)
     */
    public function calculate_ssim($image_path1, $image_path2, $window_size = 11) {
        $processor1 = new ImageProcessor();
        $processor1->load_image($image_path1);
        $processor1_gray = clone $processor1;
        $processor1_gray->to_grayscale();

        $processor2 = new ImageProcessor();
        $processor2->load_image($image_path2);
        $processor2_gray = clone $processor2;
        $processor2_gray->to_grayscale();

        $dims1 = $processor1_gray->get_dimensions();
        $dims2 = $processor2_gray->get_dimensions();

        // Resize to same dimensions if needed
        if ($dims1['width'] != $dims2['width'] || $dims1['height'] != $dims2['height']) {
            $processor2_gray->resize($dims1['width'], $dims1['height'], false);
        }

        $width = $dims1['width'];
        $height = $dims1['height'];

        $c1 = 6.5025; // (0.01 * 255)^2
        $c2 = 58.5225; // (0.03 * 255)^2

        $ssim_map = array();
        $half_window = (int)floor($window_size / 2);

        for ($y = $half_window; $y < $height - $half_window; $y += $window_size) {
            for ($x = $half_window; $x < $width - $half_window; $x += $window_size) {
                $window1 = array();
                $window2 = array();

                // Extract windows
                for ($wy = -$half_window; $wy <= $half_window; $wy++) {
                    for ($wx = -$half_window; $wx <= $half_window; $wx++) {
                        $px = max(0, min($width - 1, $x + $wx));
                        $py = max(0, min($height - 1, $y + $wy));

                        $pixel1 = $processor1_gray->get_pixel($px, $py);
                        $pixel2 = $processor2_gray->get_pixel($px, $py);

                        $window1[] = $pixel1['r'];
                        $window2[] = $pixel2['r'];
                    }
                }

                // Calculate statistics
                $mean1 = array_sum($window1) / count($window1);
                $mean2 = array_sum($window2) / count($window2);

                $variance1 = 0;
                $variance2 = 0;
                $covariance = 0;

                for ($i = 0; $i < count($window1); $i++) {
                    $diff1 = $window1[$i] - $mean1;
                    $diff2 = $window2[$i] - $mean2;

                    $variance1 += $diff1 * $diff1;
                    $variance2 += $diff2 * $diff2;
                    $covariance += $diff1 * $diff2;
                }

                $variance1 /= count($window1);
                $variance2 /= count($window2);
                $covariance /= count($window1);

                // Calculate SSIM for this window
                $numerator = (2 * $mean1 * $mean2 + $c1) * (2 * $covariance + $c2);
                $denominator = ($mean1 * $mean1 + $mean2 * $mean2 + $c1) * ($variance1 + $variance2 + $c2);

                $ssim_map[] = $denominator > 0 ? $numerator / $denominator : 0;
            }
        }

        // Return mean SSIM
        return count($ssim_map) > 0 ? array_sum($ssim_map) / count($ssim_map) : 0;
    }

    /**
     * Calculate Mean Squared Error
     */
    public function calculate_mse($image_path1, $image_path2) {
        $processor1 = new ImageProcessor();
        $processor1->load_image($image_path1);

        $processor2 = new ImageProcessor();
        $processor2->load_image($image_path2);

        $dims1 = $processor1->get_dimensions();
        $dims2 = $processor2->get_dimensions();

        // Resize to same dimensions if needed
        if ($dims1['width'] != $dims2['width'] || $dims1['height'] != $dims2['height']) {
            $processor2->resize($dims1['width'], $dims1['height'], false);
        }

        $width = $dims1['width'];
        $height = $dims1['height'];

        $mse = 0;
        $total_pixels = $width * $height;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel1 = $processor1->get_pixel($x, $y);
                $pixel2 = $processor2->get_pixel($x, $y);

                $diff_r = $pixel1['r'] - $pixel2['r'];
                $diff_g = $pixel1['g'] - $pixel2['g'];
                $diff_b = $pixel1['b'] - $pixel2['b'];

                $mse += ($diff_r * $diff_r + $diff_g * $diff_g + $diff_b * $diff_b) / 3;
            }
        }

        return $mse / $total_pixels;
    }

    /**
     * Calculate Peak Signal-to-Noise Ratio
     * Higher values indicate more similarity
     */
    public function calculate_psnr($image_path1, $image_path2) {
        $mse = $this->calculate_mse($image_path1, $image_path2);

        if ($mse == 0) {
            return INF; // Images are identical
        }

        $max_pixel_value = 255;
        $psnr = 10 * log10(($max_pixel_value * $max_pixel_value) / $mse);

        return $psnr;
    }

    /**
     * Compare perceptual hashes
     */
    public function compare_perceptual_hashes($image_path1, $image_path2) {
        return array(
            'average_hash' => $this->compare_average_hash($image_path1, $image_path2),
            'difference_hash' => $this->compare_difference_hash($image_path1, $image_path2),
            'perceptual_hash' => $this->compare_perceptual_hash($image_path1, $image_path2)
        );
    }

    /**
     * Average Hash (aHash)
     * Fast and simple perceptual hash
     */
    public function compute_average_hash($image_path, $hash_size = 8) {
        $processor = new ImageProcessor();
        $processor->load_image($image_path);
        $processor->to_grayscale();
        $processor->resize($hash_size, $hash_size, false);

        $pixels = array();
        for ($y = 0; $y < $hash_size; $y++) {
            for ($x = 0; $x < $hash_size; $x++) {
                $pixel = $processor->get_pixel($x, $y);
                $pixels[] = $pixel['r'];
            }
        }

        $average = array_sum($pixels) / count($pixels);

        $hash = '';
        foreach ($pixels as $pixel) {
            $hash .= ($pixel >= $average) ? '1' : '0';
        }

        return $hash;
    }

    /**
     * Difference Hash (dHash)
     * More robust to gamma correction and color changes
     */
    public function compute_difference_hash($image_path, $hash_size = 8) {
        $processor = new ImageProcessor();
        $processor->load_image($image_path);
        $processor->to_grayscale();
        $processor->resize($hash_size + 1, $hash_size, false);

        $hash = '';
        for ($y = 0; $y < $hash_size; $y++) {
            for ($x = 0; $x < $hash_size; $x++) {
                $pixel_left = $processor->get_pixel($x, $y);
                $pixel_right = $processor->get_pixel($x + 1, $y);
                $hash .= ($pixel_left['r'] < $pixel_right['r']) ? '1' : '0';
            }
        }

        return $hash;
    }

    /**
     * Perceptual Hash (pHash)
     * Uses DCT for robust similarity detection
     */
    public function compute_perceptual_hash($image_path, $hash_size = 8) {
        $processor = new ImageProcessor();
        $processor->load_image($image_path);
        $processor->to_grayscale();

        $dct_size = 32;
        $processor->resize($dct_size, $dct_size, false);

        // Get pixel matrix
        $pixels = array();
        for ($y = 0; $y < $dct_size; $y++) {
            $pixels[$y] = array();
            for ($x = 0; $x < $dct_size; $x++) {
                $pixel = $processor->get_pixel($x, $y);
                $pixels[$y][$x] = $pixel['r'];
            }
        }

        // Compute DCT
        $dct = $this->compute_dct($pixels);

        // Extract top-left corner (low frequencies)
        $low_freq = array();
        for ($y = 0; $y < $hash_size; $y++) {
            for ($x = 0; $x < $hash_size; $x++) {
                $low_freq[] = $dct[$y][$x];
            }
        }

        // Calculate median
        sort($low_freq);
        $median = $low_freq[count($low_freq) / 2];

        // Generate hash
        $hash = '';
        for ($y = 0; $y < $hash_size; $y++) {
            for ($x = 0; $x < $hash_size; $x++) {
                $hash .= ($dct[$y][$x] >= $median) ? '1' : '0';
            }
        }

        return $hash;
    }

    /**
     * Compute 2D Discrete Cosine Transform
     */
    private function compute_dct($matrix) {
        $N = count($matrix);
        $M = count($matrix[0]);
        $dct = array();

        for ($u = 0; $u < $N; $u++) {
            $dct[$u] = array();
            for ($v = 0; $v < $M; $v++) {
                $sum = 0;

                for ($i = 0; $i < $N; $i++) {
                    for ($j = 0; $j < $M; $j++) {
                        $sum += $matrix[$i][$j] *
                                cos((2 * $i + 1) * $u * M_PI / (2 * $N)) *
                                cos((2 * $j + 1) * $v * M_PI / (2 * $M));
                    }
                }

                $alpha_u = ($u == 0) ? sqrt(1 / $N) : sqrt(2 / $N);
                $alpha_v = ($v == 0) ? sqrt(1 / $M) : sqrt(2 / $M);

                $dct[$u][$v] = $alpha_u * $alpha_v * $sum;
            }
        }

        return $dct;
    }

    /**
     * Compare two hashes using Hamming distance
     */
    private function hamming_distance($hash1, $hash2) {
        if (strlen($hash1) != strlen($hash2)) {
            throw new \Exception("Hashes must be same length");
        }

        $distance = 0;
        for ($i = 0; $i < strlen($hash1); $i++) {
            if ($hash1[$i] != $hash2[$i]) {
                $distance++;
            }
        }

        return $distance;
    }

    /**
     * Compare average hashes
     */
    private function compare_average_hash($image_path1, $image_path2) {
        $hash1 = $this->compute_average_hash($image_path1);
        $hash2 = $this->compute_average_hash($image_path2);

        $distance = $this->hamming_distance($hash1, $hash2);
        $max_distance = strlen($hash1);

        return array(
            'hash1' => $hash1,
            'hash2' => $hash2,
            'hamming_distance' => $distance,
            'similarity' => 1 - ($distance / $max_distance)
        );
    }

    /**
     * Compare difference hashes
     */
    private function compare_difference_hash($image_path1, $image_path2) {
        $hash1 = $this->compute_difference_hash($image_path1);
        $hash2 = $this->compute_difference_hash($image_path2);

        $distance = $this->hamming_distance($hash1, $hash2);
        $max_distance = strlen($hash1);

        return array(
            'hash1' => $hash1,
            'hash2' => $hash2,
            'hamming_distance' => $distance,
            'similarity' => 1 - ($distance / $max_distance)
        );
    }

    /**
     * Compare perceptual hashes
     */
    private function compare_perceptual_hash($image_path1, $image_path2) {
        $hash1 = $this->compute_perceptual_hash($image_path1);
        $hash2 = $this->compute_perceptual_hash($image_path2);

        $distance = $this->hamming_distance($hash1, $hash2);
        $max_distance = strlen($hash1);

        return array(
            'hash1' => $hash1,
            'hash2' => $hash2,
            'hamming_distance' => $distance,
            'similarity' => 1 - ($distance / $max_distance)
        );
    }

    /**
     * Compare extracted features using various distance metrics
     */
    public function compare_features($image_path1, $image_path2) {
        $features1 = $this->feature_extractor->extract_all_features($image_path1);
        $features2 = $this->feature_extractor->extract_all_features($image_path2);

        $vector1 = $this->feature_extractor->create_feature_vector($features1);
        $vector2 = $this->feature_extractor->create_feature_vector($features2);

        return array(
            'euclidean_distance' => $this->euclidean_distance($vector1, $vector2),
            'cosine_similarity' => $this->cosine_similarity($vector1, $vector2),
            'manhattan_distance' => $this->manhattan_distance($vector1, $vector2)
        );
    }

    /**
     * Euclidean distance between feature vectors
     */
    private function euclidean_distance($vector1, $vector2) {
        $min_length = min(count($vector1), count($vector2));
        $sum = 0;

        for ($i = 0; $i < $min_length; $i++) {
            $diff = $vector1[$i] - $vector2[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Cosine similarity between feature vectors
     * Returns value between -1 and 1 (1 = identical direction)
     */
    private function cosine_similarity($vector1, $vector2) {
        $min_length = min(count($vector1), count($vector2));

        $dot_product = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < $min_length; $i++) {
            $dot_product += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }

        return $dot_product / ($magnitude1 * $magnitude2);
    }

    /**
     * Manhattan distance (L1 norm)
     */
    private function manhattan_distance($vector1, $vector2) {
        $min_length = min(count($vector1), count($vector2));
        $sum = 0;

        for ($i = 0; $i < $min_length; $i++) {
            $sum += abs($vector1[$i] - $vector2[$i]);
        }

        return $sum;
    }
}
