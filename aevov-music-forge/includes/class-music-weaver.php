<?php
/**
 * Music Weaver - Real Music Generation System
 * Generates actual music using music theory, MIDI, and synthesis
 */

namespace AevovMusicForge;

require_once __DIR__ . '/class-music-theory.php';
require_once __DIR__ . '/class-midi-generator.php';
require_once __DIR__ . '/class-melody-generator.php';
require_once __DIR__ . '/class-audio-synthesizer.php';

class MusicWeaver {

    private $theory;
    private $midi;
    private $melody_gen;
    private $synth;
    private $logger;

    public function __construct() {
        $this->theory = new MusicTheory();
        $this->midi = new MIDIGenerator();
        $this->melody_gen = new MelodyGenerator();
        $this->synth = new AudioSynthesizer();

        $this->logger = new class {
            public function info($message, $context = []) {
                error_log('[MusicWeaver] ' . $message . ' ' . json_encode($context));
            }
        };
    }

    /**
     * Generate complete musical composition
     */
    public function generate_composition($params = []) {
        $this->logger->info('Generating musical composition', $params);

        // Parse parameters with defaults
        $key = $params['key'] ?? 'C';
        $scale_type = $params['scale'] ?? 'major';
        $tempo = $params['tempo'] ?? 120;
        $num_bars = $params['bars'] ?? 8;
        $progression_name = $params['progression'] ?? 'I-IV-V-I';
        $melody_style = $params['melody_style'] ?? 'arch';

        // Set up MIDI
        $this->midi->set_tempo($tempo);
        $this->midi->set_time_signature(4, 4);

        // Generate harmonic foundation
        $scale = $this->theory->get_scale($key, $scale_type, 4);
        $progression = $this->theory->generate_progression($key, $progression_name, $scale_type);

        // Generate chord track
        $this->generate_chord_track($progression, $num_bars);

        // Generate bass line
        $this->generate_bass_track($progression, $num_bars);

        // Generate melody
        $this->generate_melody_track($scale, $num_bars, $melody_style);

        // Get MIDI data
        $midi_data = $this->midi->generate_midi();

        return [
            'midi' => base64_encode($midi_data),
            'midi_size' => strlen($midi_data),
            'key' => $key,
            'scale' => $scale_type,
            'tempo' => $tempo,
            'bars' => $num_bars,
            'progression' => $progression_name,
            'scale_notes' => $scale,
            'chords' => $progression,
        ];
    }

    /**
     * Generate chord accompaniment track
     */
    private function generate_chord_track($progression, $num_bars) {
        $time = 0;
        $bars_per_chord = $num_bars / count($progression);

        foreach ($progression as $chord) {
            // Add full chord
            $this->midi->add_chord(
                0, // Track 0
                $chord['notes'],
                $time,
                $bars_per_chord * 4, // Duration in beats
                70, // Velocity
                0  // Channel
            );

            $time += $bars_per_chord * 4;
        }
    }

    /**
     * Generate walking bass line
     */
    private function generate_bass_track($progression, $num_bars) {
        $time = 0;
        $bars_per_chord = $num_bars / count($progression);
        $beats_per_chord = $bars_per_chord * 4;

        foreach ($progression as $chord) {
            // Root note in lower octave
            $root_note = $chord['notes'][0];
            $bass_note = $root_note['midi'] - 12; // One octave down

            // Walking bass pattern
            $fifth_note = $chord['notes'][2]['midi'] - 12; // Fifth

            for ($beat = 0; $beat < $beats_per_chord; $beat++) {
                if ($beat % 2 === 0) {
                    $this->midi->add_note(1, $bass_note, $time + $beat, 0.9, 80, 0);
                } else {
                    $this->midi->add_note(1, $fifth_note, $time + $beat, 0.9, 70, 0);
                }
            }

            $time += $beats_per_chord;
        }
    }

    /**
     * Generate melodic line
     */
    private function generate_melody_track($scale, $num_bars, $style = 'arch') {
        // Generate melody using scale
        $notes_per_bar = 4;
        $total_notes = $num_bars * $notes_per_bar;

        $melody = $this->melody_gen->generate_contour_melody($scale, $total_notes, $style);

        // Add to MIDI
        $time = 0;
        foreach ($melody as $note) {
            $velocity = mt_rand(70, 100); // Dynamic variation
            $duration = 0.9; // Slightly detached

            $this->midi->add_note(2, $note['midi'], $time, $duration, $velocity, 0);
            $time += 1; // One beat per note
        }
    }

    /**
     * Generate audio from composition
     */
    public function generate_audio($composition_data, $waveform_type = 'sine') {
        $this->logger->info('Generating audio waveform');

        $tempo = $composition_data['tempo'];
        $beat_duration = 60.0 / $tempo;

        $all_samples = [];

        // Generate audio for each chord in progression
        foreach ($composition_data['chords'] as $chord) {
            $chord_duration = 4 * $beat_duration; // 4 beats per chord

            $chord_samples = $this->synth->generate_chord(
                $chord['notes'],
                $chord_duration,
                $waveform_type
            );

            $all_samples = array_merge($all_samples, $chord_samples);
        }

        return $all_samples;
    }

    /**
     * Export composition to MIDI file
     */
    public function export_midi($composition_data, $filepath) {
        $midi_binary = base64_decode($composition_data['midi']);
        return file_put_contents($filepath, $midi_binary);
    }

    /**
     * Export composition to WAV file
     */
    public function export_wav($composition_data, $filepath, $waveform_type = 'sine') {
        $samples = $this->generate_audio($composition_data, $waveform_type);
        return $this->synth->export_wav($samples, $filepath);
    }

    /**
     * Generate motif-based composition
     */
    public function generate_motif_composition($params = []) {
        $key = $params['key'] ?? 'C';
        $scale_type = $params['scale'] ?? 'major';
        $tempo = $params['tempo'] ?? 120;

        $this->midi->set_tempo($tempo);

        $scale = $this->theory->get_scale($key, $scale_type, 4);

        // Generate primary motif
        $motif = $this->melody_gen->generate_motif($scale, 4);

        // Develop motif through various techniques
        $time = 0;
        $techniques = ['original', 'transpose', 'invert', 'retrograde', 'augment'];

        foreach ($techniques as $technique) {
            if ($technique === 'original') {
                $developed = $motif;
            } else {
                $developed = $this->melody_gen->develop_motif($motif, $technique);
            }

            // Add to MIDI
            foreach ($developed as $note_event) {
                $this->midi->add_note(
                    0,
                    $note_event['note']['midi'],
                    $time,
                    $note_event['duration'],
                    80,
                    0
                );
                $time += $note_event['duration'];
            }
        }

        return [
            'midi' => $this->midi->get_base64(),
            'key' => $key,
            'scale' => $scale_type,
            'tempo' => $tempo,
            'motif_techniques' => $techniques,
        ];
    }

    /**
     * Generate arpeggio pattern
     */
    public function generate_arpeggio_pattern($params = []) {
        $key = $params['key'] ?? 'C';
        $chord_type = $params['chord_type'] ?? 'major';
        $tempo = $params['tempo'] ?? 120;
        $pattern_type = $params['pattern'] ?? 'up'; // up, down, up-down

        $this->midi->set_tempo($tempo);

        $chord = $this->theory->build_chord($key, $chord_type, 4);

        $time = 0;
        $arp_notes = $chord;

        // Apply pattern
        if ($pattern_type === 'down') {
            $arp_notes = array_reverse($arp_notes);
        } elseif ($pattern_type === 'up-down') {
            $arp_notes = array_merge($arp_notes, array_reverse($arp_notes));
        }

        // Repeat pattern
        for ($i = 0; $i < 4; $i++) {
            $this->midi->add_arpeggio(0, $arp_notes, $time, 0.9, 0.5, 70);
            $time += count($arp_notes) * 0.5;
        }

        return [
            'midi' => $this->midi->get_base64(),
            'chord' => $chord,
            'pattern' => $pattern_type,
        ];
    }

    /**
     * Analyze musical parameters and suggest composition
     */
    public function analyze_and_compose($mood, $energy_level = 0.5) {
        // Map mood to musical parameters
        $compositions = [
            'happy' => [
                'key' => 'C',
                'scale' => 'major',
                'tempo' => 120 + ($energy_level * 60),
                'progression' => 'I-V-vi-IV',
                'melody_style' => 'arch',
            ],
            'sad' => [
                'key' => 'A',
                'scale' => 'minor',
                'tempo' => 70 + ($energy_level * 30),
                'progression' => 'i-iv-v-i',
                'melody_style' => 'descending',
            ],
            'energetic' => [
                'key' => 'D',
                'scale' => 'major',
                'tempo' => 140 + ($energy_level * 40),
                'progression' => 'I-IV-I-V',
                'melody_style' => 'wave',
            ],
            'calm' => [
                'key' => 'F',
                'scale' => 'major',
                'tempo' => 60 + ($energy_level * 20),
                'progression' => 'I-iii-IV-V',
                'melody_style' => 'arch',
            ],
            'mysterious' => [
                'key' => 'E',
                'scale' => 'phrygian',
                'tempo' => 80 + ($energy_level * 30),
                'progression' => 'i-bII-bVII-i',
                'melody_style' => 'valley',
            ],
        ];

        $params = $compositions[$mood] ?? $compositions['happy'];
        return $this->generate_composition($params);
    }

    /**
     * Get pattern IDs (backward compatibility)
     */
    public function get_pattern_ids($params) {
        // Generate actual composition and return identifier
        $composition = $this->generate_composition($params);

        $pattern_id = 'music-' . md5($composition['midi']);

        return [$pattern_id];
    }
}
