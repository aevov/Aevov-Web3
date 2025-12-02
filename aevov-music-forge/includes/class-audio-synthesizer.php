<?php
/**
 * Audio Synthesizer
 * Generate audio waveforms from frequencies
 */

namespace AevovMusicForge;

class AudioSynthesizer {

    private $sample_rate = 44100; // CD quality
    private $bit_depth = 16;
    private $channels = 1; // Mono

    /**
     * Generate waveform for a single note
     */
    public function generate_waveform($frequency, $duration, $waveform_type = 'sine', $amplitude = 0.5) {
        $num_samples = floor($this->sample_rate * $duration);
        $samples = [];

        for ($i = 0; $i < $num_samples; $i++) {
            $t = $i / $this->sample_rate;
            $sample = $this->generate_sample($frequency, $t, $waveform_type) * $amplitude;

            // Apply ADSR envelope
            $sample *= $this->adsr_envelope($t, $duration);

            $samples[] = $sample;
        }

        return $samples;
    }

    /**
     * Generate single sample value
     */
    private function generate_sample($frequency, $time, $waveform_type) {
        $phase = 2 * M_PI * $frequency * $time;

        switch ($waveform_type) {
            case 'sine':
                return sin($phase);

            case 'square':
                return sin($phase) >= 0 ? 1 : -1;

            case 'sawtooth':
                return 2 * (($phase / (2 * M_PI)) - floor(($phase / (2 * M_PI)) + 0.5));

            case 'triangle':
                $saw = 2 * (($phase / (2 * M_PI)) - floor(($phase / (2 * M_PI)) + 0.5));
                return 2 * abs($saw) - 1;

            case 'pulse':
                $duty_cycle = 0.25;
                $normalized_phase = fmod($phase, 2 * M_PI) / (2 * M_PI);
                return $normalized_phase < $duty_cycle ? 1 : -1;

            default:
                return sin($phase);
        }
    }

    /**
     * ADSR Envelope (Attack, Decay, Sustain, Release)
     */
    private function adsr_envelope($time, $duration, $attack = 0.01, $decay = 0.1, $sustain_level = 0.7, $release = 0.2) {
        $sustain_time = $duration - $release;

        if ($time < $attack) {
            // Attack phase
            return $time / $attack;
        } elseif ($time < $attack + $decay) {
            // Decay phase
            $decay_progress = ($time - $attack) / $decay;
            return 1.0 - (1.0 - $sustain_level) * $decay_progress;
        } elseif ($time < $sustain_time) {
            // Sustain phase
            return $sustain_level;
        } else {
            // Release phase
            $release_progress = ($time - $sustain_time) / $release;
            return $sustain_level * (1.0 - $release_progress);
        }
    }

    /**
     * Mix multiple waveforms (for chords)
     */
    public function mix_waveforms($waveforms) {
        if (empty($waveforms)) {
            return [];
        }

        // Find longest waveform
        $max_length = 0;
        foreach ($waveforms as $waveform) {
            $max_length = max($max_length, count($waveform));
        }

        // Mix samples
        $mixed = [];
        for ($i = 0; $i < $max_length; $i++) {
            $sample_sum = 0;
            $count = 0;

            foreach ($waveforms as $waveform) {
                if (isset($waveform[$i])) {
                    $sample_sum += $waveform[$i];
                    $count++;
                }
            }

            // Average to prevent clipping
            $mixed[] = $count > 0 ? $sample_sum / $count : 0;
        }

        return $mixed;
    }

    /**
     * Apply low-pass filter (simple one-pole)
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
     * Apply high-pass filter
     */
    public function high_pass_filter($samples, $cutoff_frequency) {
        $rc = 1.0 / (2 * M_PI * $cutoff_frequency);
        $dt = 1.0 / $this->sample_rate;
        $alpha = $rc / ($rc + $dt);

        $filtered = [];
        $filtered[0] = $samples[0];

        for ($i = 1; $i < count($samples); $i++) {
            $filtered[$i] = $alpha * ($filtered[$i - 1] + $samples[$i] - $samples[$i - 1]);
        }

        return $filtered;
    }

    /**
     * Add reverb (simple delay-based)
     */
    public function add_reverb($samples, $delay_time = 0.05, $decay = 0.3, $num_echoes = 5) {
        $delay_samples = floor($delay_time * $this->sample_rate);
        $output = $samples;

        for ($echo = 1; $echo <= $num_echoes; $echo++) {
            $gain = pow($decay, $echo);
            $offset = $delay_samples * $echo;

            for ($i = 0; $i < count($samples); $i++) {
                $output_index = $i + $offset;
                if ($output_index < count($output)) {
                    $output[$output_index] += $samples[$i] * $gain;
                }
            }
        }

        return $output;
    }

    /**
     * Add tremolo (amplitude modulation)
     */
    public function add_tremolo($samples, $rate = 5, $depth = 0.5) {
        $modulated = [];

        for ($i = 0; $i < count($samples); $i++) {
            $t = $i / $this->sample_rate;
            $lfo = sin(2 * M_PI * $rate * $t); // Low Frequency Oscillator
            $gain = 1.0 - ($depth * ($lfo + 1) / 2);
            $modulated[] = $samples[$i] * $gain;
        }

        return $modulated;
    }

    /**
     * Add vibrato (frequency modulation)
     */
    public function generate_vibrato_waveform($frequency, $duration, $waveform_type = 'sine', $vibrato_rate = 5, $vibrato_depth = 0.02) {
        $num_samples = floor($this->sample_rate * $duration);
        $samples = [];

        for ($i = 0; $i < $num_samples; $i++) {
            $t = $i / $this->sample_rate;

            // Modulate frequency
            $lfo = sin(2 * M_PI * $vibrato_rate * $t);
            $modulated_freq = $frequency * (1 + $vibrato_depth * $lfo);

            $sample = $this->generate_sample($modulated_freq, $t, $waveform_type);
            $sample *= $this->adsr_envelope($t, $duration);

            $samples[] = $sample;
        }

        return $samples;
    }

    /**
     * Normalize samples to prevent clipping
     */
    public function normalize($samples) {
        $max_amplitude = 0;

        foreach ($samples as $sample) {
            $max_amplitude = max($max_amplitude, abs($sample));
        }

        if ($max_amplitude === 0) {
            return $samples;
        }

        $normalized = [];
        foreach ($samples as $sample) {
            $normalized[] = $sample / $max_amplitude * 0.95; // Leave headroom
        }

        return $normalized;
    }

    /**
     * Export to WAV file
     */
    public function export_wav($samples, $filepath) {
        $samples = $this->normalize($samples);

        // Convert float samples to 16-bit integers
        $data = '';
        foreach ($samples as $sample) {
            $int_sample = (int)($sample * 32767);
            $int_sample = max(-32768, min(32767, $int_sample));
            $data .= pack('s', $int_sample);
        }

        $num_samples = count($samples);
        $byte_rate = $this->sample_rate * $this->channels * ($this->bit_depth / 8);
        $block_align = $this->channels * ($this->bit_depth / 8);

        // WAV header
        $header = '';
        $header .= 'RIFF';
        $header .= pack('V', 36 + strlen($data)); // File size - 8
        $header .= 'WAVE';
        $header .= 'fmt ';
        $header .= pack('V', 16); // Subchunk1 size
        $header .= pack('v', 1); // Audio format (1 = PCM)
        $header .= pack('v', $this->channels);
        $header .= pack('V', $this->sample_rate);
        $header .= pack('V', $byte_rate);
        $header .= pack('v', $block_align);
        $header .= pack('v', $this->bit_depth);
        $header .= 'data';
        $header .= pack('V', strlen($data));

        return file_put_contents($filepath, $header . $data);
    }

    /**
     * Generate chord waveform
     */
    public function generate_chord($chord_notes, $duration, $waveform_type = 'sine') {
        $waveforms = [];

        foreach ($chord_notes as $note) {
            $frequency = is_array($note) ? $note['frequency'] : $note;
            $waveforms[] = $this->generate_waveform($frequency, $duration, $waveform_type, 0.3);
        }

        return $this->mix_waveforms($waveforms);
    }

    /**
     * Generate FM synthesis (frequency modulation)
     */
    public function generate_fm_synthesis($carrier_freq, $modulator_freq, $modulation_index, $duration) {
        $num_samples = floor($this->sample_rate * $duration);
        $samples = [];

        for ($i = 0; $i < $num_samples; $i++) {
            $t = $i / $this->sample_rate;

            // FM synthesis formula
            $modulator = sin(2 * M_PI * $modulator_freq * $t);
            $carrier = sin(2 * M_PI * $carrier_freq * $t + $modulation_index * $modulator);

            $sample = $carrier * $this->adsr_envelope($t, $duration);
            $samples[] = $sample;
        }

        return $samples;
    }

    /**
     * Generate noise (white noise)
     */
    public function generate_noise($duration) {
        $num_samples = floor($this->sample_rate * $duration);
        $samples = [];

        for ($i = 0; $i < $num_samples; $i++) {
            $samples[] = (mt_rand() / mt_getrandmax()) * 2 - 1;
        }

        return $samples;
    }

    /**
     * Add harmonic series (additive synthesis)
     */
    public function generate_additive($fundamental_freq, $harmonics, $duration) {
        $num_samples = floor($this->sample_rate * $duration);
        $samples = array_fill(0, $num_samples, 0);

        foreach ($harmonics as $harmonic_num => $amplitude) {
            $freq = $fundamental_freq * ($harmonic_num + 1);

            for ($i = 0; $i < $num_samples; $i++) {
                $t = $i / $this->sample_rate;
                $samples[$i] += sin(2 * M_PI * $freq * $t) * $amplitude;
            }
        }

        // Normalize and apply envelope
        $max = max(array_map('abs', $samples));
        for ($i = 0; $i < $num_samples; $i++) {
            $t = $i / $this->sample_rate;
            $samples[$i] = ($samples[$i] / $max) * $this->adsr_envelope($t, $duration);
        }

        return $samples;
    }
}
