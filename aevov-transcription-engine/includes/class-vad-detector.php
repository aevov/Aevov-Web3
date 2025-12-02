<?php
/**
 * VAD (Voice Activity Detection)
 * Detects speech vs. silence using energy and zero-crossing rate
 */

namespace AevovTranscriptionEngine;

require_once __DIR__ . '/class-audio-processor.php';

class VADDetector {

    private $audio_processor;
    private $energy_threshold = 0.01;
    private $zcr_threshold = 0.1;
    private $min_speech_duration = 0.3; // seconds
    private $min_silence_duration = 0.2; // seconds

    public function __construct() {
        $this->audio_processor = new AudioProcessor();
    }

    /**
     * Detect speech segments in audio file
     */
    public function detect_speech($filepath) {
        $samples = $this->audio_processor->load_wav($filepath);
        return $this->detect_speech_from_samples($samples);
    }

    /**
     * Detect speech from audio samples
     */
    public function detect_speech_from_samples($samples) {
        // Frame the signal
        $frames = $this->audio_processor->frame_signal($samples);

        // Compute features for each frame
        $frame_features = [];

        foreach ($frames as $frame) {
            $energy = $this->audio_processor->compute_energy($frame);
            $zcr = $this->audio_processor->zero_crossing_rate($frame);

            $frame_features[] = [
                'energy' => $energy,
                'zcr' => $zcr,
                'is_speech' => $this->is_speech_frame($energy, $zcr),
            ];
        }

        // Smooth decisions using majority voting
        $smoothed = $this->smooth_decisions($frame_features);

        // Extract speech segments
        $segments = $this->extract_segments($smoothed);

        return [
            'segments' => $segments,
            'frame_features' => $frame_features,
        ];
    }

    /**
     * Determine if frame contains speech
     */
    private function is_speech_frame($energy, $zcr) {
        // Speech typically has high energy and moderate ZCR
        // Silence has low energy
        // Noise might have low energy but high ZCR

        if ($energy > $this->energy_threshold) {
            // High energy - likely speech or noise
            // Use ZCR to distinguish
            if ($zcr < $this->zcr_threshold * 2) {
                return true; // Speech
            }
        }

        return false; // Silence or noise
    }

    /**
     * Smooth VAD decisions using majority voting
     */
    private function smooth_decisions($frame_features, $window = 5) {
        $smoothed = [];

        for ($i = 0; $i < count($frame_features); $i++) {
            $start = max(0, $i - $window);
            $end = min(count($frame_features), $i + $window + 1);

            $speech_count = 0;

            for ($j = $start; $j < $end; $j++) {
                if ($frame_features[$j]['is_speech']) {
                    $speech_count++;
                }
            }

            $smoothed[] = $speech_count > ($end - $start) / 2;
        }

        return $smoothed;
    }

    /**
     * Extract continuous speech segments
     */
    private function extract_segments($smoothed_decisions) {
        $sample_rate = $this->audio_processor->get_sample_rate();
        $frame_step = 0.010; // 10ms

        $segments = [];
        $in_speech = false;
        $speech_start = 0;

        for ($i = 0; $i < count($smoothed_decisions); $i++) {
            if ($smoothed_decisions[$i] && !$in_speech) {
                // Speech begins
                $speech_start = $i;
                $in_speech = true;
            } elseif (!$smoothed_decisions[$i] && $in_speech) {
                // Speech ends
                $speech_end = $i;
                $duration = ($speech_end - $speech_start) * $frame_step;

                // Only keep segments longer than minimum duration
                if ($duration >= $this->min_speech_duration) {
                    $segments[] = [
                        'start' => $speech_start * $frame_step,
                        'end' => $speech_end * $frame_step,
                        'duration' => $duration,
                        'start_frame' => $speech_start,
                        'end_frame' => $speech_end,
                    ];
                }

                $in_speech = false;
            }
        }

        // Handle case where speech continues to end
        if ($in_speech) {
            $speech_end = count($smoothed_decisions) - 1;
            $duration = ($speech_end - $speech_start) * $frame_step;

            if ($duration >= $this->min_speech_duration) {
                $segments[] = [
                    'start' => $speech_start * $frame_step,
                    'end' => $speech_end * $frame_step,
                    'duration' => $duration,
                    'start_frame' => $speech_start,
                    'end_frame' => $speech_end,
                ];
            }
        }

        return $segments;
    }

    /**
     * Adaptive thresholding based on signal statistics
     */
    public function set_adaptive_threshold($samples) {
        $frames = $this->audio_processor->frame_signal($samples);

        $energies = [];
        $zcrs = [];

        foreach ($frames as $frame) {
            $energies[] = $this->audio_processor->compute_energy($frame);
            $zcrs[] = $this->audio_processor->zero_crossing_rate($frame);
        }

        // Use percentiles to set thresholds
        sort($energies);
        sort($zcrs);

        $percentile_25 = (int)(count($energies) * 0.25);
        $percentile_75 = (int)(count($energies) * 0.75);

        // Energy threshold between 25th and 50th percentile
        $this->energy_threshold = $energies[(int)(count($energies) * 0.4)];

        // ZCR threshold at 75th percentile
        $this->zcr_threshold = $zcrs[$percentile_75];
    }

    /**
     * Get speech ratio (amount of speech vs. total duration)
     */
    public function compute_speech_ratio($segments, $total_duration) {
        $speech_duration = 0;

        foreach ($segments as $segment) {
            $speech_duration += $segment['duration'];
        }

        return $total_duration > 0 ? $speech_duration / $total_duration : 0;
    }

    /**
     * Set energy threshold
     */
    public function set_energy_threshold($threshold) {
        $this->energy_threshold = $threshold;
    }

    /**
     * Set ZCR threshold
     */
    public function set_zcr_threshold($threshold) {
        $this->zcr_threshold = $threshold;
    }

    /**
     * Set minimum speech duration
     */
    public function set_min_speech_duration($duration) {
        $this->min_speech_duration = $duration;
    }

    /**
     * Extract speech segments as separate audio
     */
    public function extract_speech_audio($samples, $segments) {
        $sample_rate = $this->audio_processor->get_sample_rate();
        $speech_segments = [];

        foreach ($segments as $segment) {
            $start_sample = (int)($segment['start'] * $sample_rate);
            $end_sample = (int)($segment['end'] * $sample_rate);

            $segment_audio = array_slice(
                $samples,
                $start_sample,
                $end_sample - $start_sample
            );

            $speech_segments[] = [
                'audio' => $segment_audio,
                'start' => $segment['start'],
                'end' => $segment['end'],
                'duration' => $segment['duration'],
            ];
        }

        return $speech_segments;
    }
}
