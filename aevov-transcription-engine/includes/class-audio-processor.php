<?php
/**
 * Audio Processor
 * Real audio signal processing - windowing, FFT, spectrograms
 */

namespace AevovTranscriptionEngine;

class AudioProcessor {

    private $sample_rate = 16000; // 16kHz for speech
    private $frame_length = 0.025; // 25ms frames
    private $frame_step = 0.010;   // 10ms overlap

    /**
     * Load WAV file and extract samples
     */
    public function load_wav($filepath) {
        if (!file_exists($filepath)) {
            throw new \Exception("Audio file not found: $filepath");
        }

        $data = file_get_contents($filepath);

        // Parse WAV header
        $header = unpack('Vsize/a4format', substr($data, 8, 8));

        if ($header['format'] !== 'WAVE') {
            throw new \Exception("Not a valid WAV file");
        }

        // Find data chunk
        $pos = 12;
        while ($pos < strlen($data)) {
            $chunk_header = unpack('a4id/Vsize', substr($data, $pos, 8));
            $pos += 8;

            if ($chunk_header['id'] === 'data') {
                $audio_data = substr($data, $pos, $chunk_header['size']);
                break;
            }

            $pos += $chunk_header['size'];
        }

        // Convert binary data to samples (16-bit PCM)
        $samples = [];
        $num_samples = strlen($audio_data) / 2;

        for ($i = 0; $i < $num_samples; $i++) {
            $sample = unpack('s', substr($audio_data, $i * 2, 2))[1];
            $samples[] = $sample / 32768.0; // Normalize to -1.0 to 1.0
        }

        return $samples;
    }

    /**
     * Pre-emphasis filter (boost high frequencies)
     */
    public function pre_emphasis($samples, $coefficient = 0.97) {
        $emphasized = [$samples[0]];

        for ($i = 1; $i < count($samples); $i++) {
            $emphasized[] = $samples[$i] - $coefficient * $samples[$i - 1];
        }

        return $emphasized;
    }

    /**
     * Frame the signal into overlapping windows
     */
    public function frame_signal($samples) {
        $frame_length = (int)($this->frame_length * $this->sample_rate);
        $frame_step = (int)($this->frame_step * $this->sample_rate);

        $num_frames = 1 + (int)(( count($samples) - $frame_length) / $frame_step);
        $frames = [];

        for ($i = 0; $i < $num_frames; $i++) {
            $start = $i * $frame_step;
            $frame = array_slice($samples, $start, $frame_length);

            // Pad if necessary
            while (count($frame) < $frame_length) {
                $frame[] = 0;
            }

            $frames[] = $frame;
        }

        return $frames;
    }

    /**
     * Apply Hamming window
     */
    public function hamming_window($frame) {
        $n = count($frame);
        $windowed = [];

        for ($i = 0; $i < $n; $i++) {
            $window_value = 0.54 - 0.46 * cos(2 * M_PI * $i / ($n - 1));
            $windowed[] = $frame[$i] * $window_value;
        }

        return $windowed;
    }

    /**
     * Compute power spectrum using FFT
     */
    public function power_spectrum($frame) {
        // Apply Hamming window
        $windowed = $this->hamming_window($frame);

        // Compute FFT (simplified - real implementation would use Fast Fourier Transform)
        $n = count($windowed);
        $fft_size = $this->next_power_of_2($n);

        // Pad to power of 2
        while (count($windowed) < $fft_size) {
            $windowed[] = 0;
        }

        $spectrum = $this->simple_fft($windowed);

        // Compute power spectrum
        $power = [];
        $half = $fft_size / 2 + 1;

        for ($i = 0; $i < $half; $i++) {
            $real = $spectrum[$i]['real'];
            $imag = $spectrum[$i]['imag'];
            $power[] = ($real * $real + $imag * $imag) / $fft_size;
        }

        return $power;
    }

    /**
     * Simplified FFT (Discrete Fourier Transform)
     * Real FFT would use Cooley-Tukey algorithm
     */
    private function simple_fft($samples) {
        $n = count($samples);
        $fft = [];

        for ($k = 0; $k < $n; $k++) {
            $real = 0;
            $imag = 0;

            for ($t = 0; $t < $n; $t++) {
                $angle = -2 * M_PI * $k * $t / $n;
                $real += $samples[$t] * cos($angle);
                $imag += $samples[$t] * sin($angle);
            }

            $fft[] = ['real' => $real, 'imag' => $imag];
        }

        return $fft;
    }

    /**
     * Mel filter bank
     */
    public function mel_filterbank($power_spectrum, $num_filters = 40) {
        $nfft = (count($power_spectrum) - 1) * 2;
        $low_freq_mel = 0;
        $high_freq_mel = $this->hz_to_mel($this->sample_rate / 2);

        // Equally spaced mel points
        $mel_points = [];
        for ($i = 0; $i < $num_filters + 2; $i++) {
            $mel_points[] = $low_freq_mel + ($high_freq_mel - $low_freq_mel) * $i / ($num_filters + 1);
        }

        // Convert back to Hz
        $hz_points = array_map([$this, 'mel_to_hz'], $mel_points);

        // Convert Hz to FFT bin numbers
        $bin_points = array_map(function($hz) use ($nfft) {
            return floor(($nfft + 1) * $hz / $this->sample_rate);
        }, $hz_points);

        // Create filter bank
        $fbank = [];
        for ($m = 1; $m < $num_filters + 1; $m++) {
            $filter = array_fill(0, count($power_spectrum), 0);

            $left = (int)$bin_points[$m - 1];
            $center = (int)$bin_points[$m];
            $right = (int)$bin_points[$m + 1];

            // Rising slope
            for ($k = $left; $k < $center; $k++) {
                if ($k < count($filter)) {
                    $filter[$k] = ($k - $left) / ($center - $left);
                }
            }

            // Falling slope
            for ($k = $center; $k < $right; $k++) {
                if ($k < count($filter)) {
                    $filter[$k] = ($right - $k) / ($right - $center);
                }
            }

            // Apply filter to power spectrum
            $energy = 0;
            for ($k = 0; $k < count($power_spectrum); $k++) {
                $energy += $power_spectrum[$k] * $filter[$k];
            }

            $fbank[] = $energy;
        }

        return $fbank;
    }

    /**
     * Convert Hz to Mel scale
     */
    private function hz_to_mel($hz) {
        return 2595 * log10(1 + $hz / 700.0);
    }

    /**
     * Convert Mel to Hz scale
     */
    private function mel_to_hz($mel) {
        return 700 * (pow(10, $mel / 2595.0) - 1);
    }

    /**
     * Compute energy of frame
     */
    public function compute_energy($frame) {
        $energy = 0;

        foreach ($frame as $sample) {
            $energy += $sample * $sample;
        }

        return $energy / count($frame);
    }

    /**
     * Compute zero crossing rate
     */
    public function zero_crossing_rate($frame) {
        $zcr = 0;

        for ($i = 1; $i < count($frame); $i++) {
            if (($frame[$i] >= 0 && $frame[$i - 1] < 0) ||
                ($frame[$i] < 0 && $frame[$i - 1] >= 0)) {
                $zcr++;
            }
        }

        return $zcr / (count($frame) - 1);
    }

    /**
     * Find next power of 2
     */
    private function next_power_of_2($n) {
        $power = 1;
        while ($power < $n) {
            $power *= 2;
        }
        return $power;
    }

    /**
     * Normalize samples
     */
    public function normalize($samples) {
        $max = max(array_map('abs', $samples));

        if ($max === 0) {
            return $samples;
        }

        return array_map(function($s) use ($max) {
            return $s / $max;
        }, $samples);
    }

    /**
     * Apply low-pass filter
     */
    public function low_pass_filter($samples, $cutoff_frequency) {
        $rc = 1.0 / (2 * M_PI * $cutoff_frequency);
        $dt = 1.0 / $this->sample_rate;
        $alpha = $dt / ($rc + $dt);

        $filtered = [];
        $filtered[0] = $samples[0];

        for ($i = 1; $i < count($samples); $i++) {
            $filtered[$i] = $filtered[$i - 1] + $alpha * ($samples[$i] - $filtered[$i - 1]);
        }

        return $filtered;
    }

    /**
     * Set sample rate
     */
    public function set_sample_rate($rate) {
        $this->sample_rate = $rate;
    }

    /**
     * Get sample rate
     */
    public function get_sample_rate() {
        return $this->sample_rate;
    }
}
