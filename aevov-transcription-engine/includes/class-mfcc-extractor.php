<?php
/**
 * MFCC Extractor
 * Mel-Frequency Cepstral Coefficients - standard features for speech recognition
 */

namespace AevovTranscriptionEngine;

require_once __DIR__ . '/class-audio-processor.php';

class MFCCExtractor {

    private $audio_processor;
    private $num_mfcc = 13; // Standard 13 MFCCs
    private $num_filters = 40;

    public function __construct() {
        $this->audio_processor = new AudioProcessor();
    }

    /**
     * Extract MFCC features from audio file
     */
    public function extract_from_file($filepath) {
        $samples = $this->audio_processor->load_wav($filepath);
        return $this->extract_from_samples($samples);
    }

    /**
     * Extract MFCC features from samples
     */
    public function extract_from_samples($samples) {
        // Pre-emphasis
        $emphasized = $this->audio_processor->pre_emphasis($samples);

        // Frame the signal
        $frames = $this->audio_processor->frame_signal($emphasized);

        // Extract MFCCs for each frame
        $mfccs = [];

        foreach ($frames as $frame) {
            $mfcc_frame = $this->extract_mfcc_frame($frame);
            $mfccs[] = $mfcc_frame;
        }

        return $mfccs;
    }

    /**
     * Extract MFCC for a single frame
     */
    private function extract_mfcc_frame($frame) {
        // 1. Compute power spectrum
        $power_spectrum = $this->audio_processor->power_spectrum($frame);

        // 2. Apply mel filterbank
        $mel_energies = $this->audio_processor->mel_filterbank($power_spectrum, $this->num_filters);

        // 3. Take logarithm
        $log_mel = array_map(function($e) {
            return log(max($e, 1e-10)); // Avoid log(0)
        }, $mel_energies);

        // 4. Apply DCT (Discrete Cosine Transform)
        $mfcc = $this->dct($log_mel);

        // 5. Keep first num_mfcc coefficients
        return array_slice($mfcc, 0, $this->num_mfcc);
    }

    /**
     * Discrete Cosine Transform (Type-II)
     */
    private function dct($signal) {
        $n = count($signal);
        $dct = [];

        for ($k = 0; $k < $n; $k++) {
            $sum = 0;

            for ($t = 0; $t < $n; $t++) {
                $sum += $signal[$t] * cos(M_PI * $k * (2 * $t + 1) / (2 * $n));
            }

            // Normalization
            $factor = $k === 0 ? sqrt(1 / $n) : sqrt(2 / $n);
            $dct[] = $factor * $sum;
        }

        return $dct;
    }

    /**
     * Compute delta features (first derivative)
     */
    public function compute_delta($features, $window = 2) {
        $delta = [];

        for ($t = 0; $t < count($features); $t++) {
            $delta_frame = [];

            for ($c = 0; $c < count($features[$t]); $c++) {
                $numerator = 0;
                $denominator = 0;

                for ($n = 1; $n <= $window; $n++) {
                    $prev_t = max(0, $t - $n);
                    $next_t = min(count($features) - 1, $t + $n);

                    $numerator += $n * ($features[$next_t][$c] - $features[$prev_t][$c]);
                    $denominator += 2 * $n * $n;
                }

                $delta_frame[] = $denominator > 0 ? $numerator / $denominator : 0;
            }

            $delta[] = $delta_frame;
        }

        return $delta;
    }

    /**
     * Compute delta-delta features (second derivative/acceleration)
     */
    public function compute_delta_delta($features, $window = 2) {
        $delta = $this->compute_delta($features, $window);
        return $this->compute_delta($delta, $window);
    }

    /**
     * Extract full feature set (MFCC + delta + delta-delta)
     */
    public function extract_full_features($samples) {
        $mfcc = $this->extract_from_samples($samples);
        $delta = $this->compute_delta($mfcc);
        $delta_delta = $this->compute_delta_delta($mfcc);

        // Concatenate features
        $full_features = [];

        for ($t = 0; $t < count($mfcc); $t++) {
            $full_features[] = array_merge(
                $mfcc[$t],
                $delta[$t],
                $delta_delta[$t]
            );
        }

        return $full_features;
    }

    /**
     * Mean normalization (cepstral mean normalization)
     */
    public function normalize_mean($features) {
        $num_frames = count($features);
        $num_coeffs = count($features[0]);

        // Calculate mean for each coefficient
        $means = array_fill(0, $num_coeffs, 0);

        foreach ($features as $frame) {
            for ($c = 0; $c < $num_coeffs; $c++) {
                $means[$c] += $frame[$c];
            }
        }

        for ($c = 0; $c < $num_coeffs; $c++) {
            $means[$c] /= $num_frames;
        }

        // Subtract mean
        $normalized = [];

        foreach ($features as $frame) {
            $norm_frame = [];
            for ($c = 0; $c < $num_coeffs; $c++) {
                $norm_frame[] = $frame[$c] - $means[$c];
            }
            $normalized[] = $norm_frame;
        }

        return $normalized;
    }

    /**
     * Variance normalization
     */
    public function normalize_variance($features) {
        $num_frames = count($features);
        $num_coeffs = count($features[0]);

        // Calculate mean
        $means = array_fill(0, $num_coeffs, 0);
        foreach ($features as $frame) {
            for ($c = 0; $c < $num_coeffs; $c++) {
                $means[$c] += $frame[$c];
            }
        }
        for ($c = 0; $c < $num_coeffs; $c++) {
            $means[$c] /= $num_frames;
        }

        // Calculate variance
        $variances = array_fill(0, $num_coeffs, 0);
        foreach ($features as $frame) {
            for ($c = 0; $c < $num_coeffs; $c++) {
                $diff = $frame[$c] - $means[$c];
                $variances[$c] += $diff * $diff;
            }
        }
        for ($c = 0; $c < $num_coeffs; $c++) {
            $variances[$c] = sqrt($variances[$c] / $num_frames);
        }

        // Normalize
        $normalized = [];
        foreach ($features as $frame) {
            $norm_frame = [];
            for ($c = 0; $c < $num_coeffs; $c++) {
                $std = max($variances[$c], 1e-10);
                $norm_frame[] = ($frame[$c] - $means[$c]) / $std;
            }
            $normalized[] = $norm_frame;
        }

        return $normalized;
    }

    /**
     * Compute feature statistics
     */
    public function compute_statistics($features) {
        $num_frames = count($features);
        $num_coeffs = count($features[0]);

        $means = array_fill(0, $num_coeffs, 0);
        $mins = array_fill(0, $num_coeffs, PHP_FLOAT_MAX);
        $maxs = array_fill(0, $num_coeffs, -PHP_FLOAT_MAX);

        foreach ($features as $frame) {
            for ($c = 0; $c < $num_coeffs; $c++) {
                $means[$c] += $frame[$c];
                $mins[$c] = min($mins[$c], $frame[$c]);
                $maxs[$c] = max($maxs[$c], $frame[$c]);
            }
        }

        for ($c = 0; $c < $num_coeffs; $c++) {
            $means[$c] /= $num_frames;
        }

        return [
            'mean' => $means,
            'min' => $mins,
            'max' => $maxs,
            'num_frames' => $num_frames,
            'num_coefficients' => $num_coeffs,
        ];
    }

    /**
     * Set number of MFCC coefficients
     */
    public function set_num_mfcc($num) {
        $this->num_mfcc = $num;
    }

    /**
     * Set number of mel filters
     */
    public function set_num_filters($num) {
        $this->num_filters = $num;
    }
}
