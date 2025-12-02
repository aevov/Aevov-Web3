<?php
/**
 * Music Theory Engine
 * Real music theory implementation - scales, chords, intervals, progressions
 */

namespace AevovMusicForge;

class MusicTheory {

    // Chromatic scale (semitones from C)
    const NOTES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

    // Scale formulas (intervals in semitones)
    const SCALES = [
        'major'            => [0, 2, 4, 5, 7, 9, 11],
        'minor'            => [0, 2, 3, 5, 7, 8, 10],
        'harmonic_minor'   => [0, 2, 3, 5, 7, 8, 11],
        'melodic_minor'    => [0, 2, 3, 5, 7, 9, 11],
        'dorian'           => [0, 2, 3, 5, 7, 9, 10],
        'phrygian'         => [0, 1, 3, 5, 7, 8, 10],
        'lydian'           => [0, 2, 4, 6, 7, 9, 11],
        'mixolydian'       => [0, 2, 4, 5, 7, 9, 10],
        'locrian'          => [0, 1, 3, 5, 6, 8, 10],
        'pentatonic_major' => [0, 2, 4, 7, 9],
        'pentatonic_minor' => [0, 3, 5, 7, 10],
        'blues'            => [0, 3, 5, 6, 7, 10],
        'chromatic'        => [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    ];

    // Chord formulas (intervals from root)
    const CHORDS = [
        'major'       => [0, 4, 7],
        'minor'       => [0, 3, 7],
        'diminished'  => [0, 3, 6],
        'augmented'   => [0, 4, 8],
        'sus2'        => [0, 2, 7],
        'sus4'        => [0, 5, 7],
        'major7'      => [0, 4, 7, 11],
        'minor7'      => [0, 3, 7, 10],
        'dominant7'   => [0, 4, 7, 10],
        'diminished7' => [0, 3, 6, 9],
        'major9'      => [0, 4, 7, 11, 14],
        'minor9'      => [0, 3, 7, 10, 14],
    ];

    // Common chord progressions in major keys
    const PROGRESSIONS = [
        'I-IV-V-I'     => [0, 3, 4, 0],  // Classic rock/pop
        'I-V-vi-IV'    => [0, 4, 5, 3],  // Popular pop progression
        'I-vi-IV-V'    => [0, 5, 3, 4],  // 50s progression
        'ii-V-I'       => [1, 4, 0],     // Jazz turnaround
        'I-IV-I-V'     => [0, 3, 0, 4],  // Blues
        'vi-IV-I-V'    => [5, 3, 0, 4],  // Pop ballad
        'I-iii-IV-V'   => [0, 2, 3, 4],  // Circle progression
        'I-bVII-IV-I'  => [0, 6, 3, 0],  // Modal progression
    ];

    /**
     * Get notes in a scale
     */
    public function get_scale($root_note, $scale_type = 'major', $octave = 4) {
        if (!isset(self::SCALES[$scale_type])) {
            $scale_type = 'major';
        }

        $root_index = array_search($root_note, self::NOTES);
        if ($root_index === false) {
            $root_index = 0; // Default to C
        }

        $scale_notes = [];
        $intervals = self::SCALES[$scale_type];

        foreach ($intervals as $interval) {
            $note_index = ($root_index + $interval) % 12;
            $note_name = self::NOTES[$note_index];

            // Calculate MIDI note number
            $midi_note = 12 + ($octave * 12) + $root_index + $interval;

            $scale_notes[] = [
                'note' => $note_name,
                'midi' => $midi_note,
                'frequency' => $this->midi_to_frequency($midi_note),
                'interval' => $interval,
            ];
        }

        return $scale_notes;
    }

    /**
     * Build a chord from root note
     */
    public function build_chord($root_note, $chord_type = 'major', $octave = 4, $inversion = 0) {
        if (!isset(self::CHORDS[$chord_type])) {
            $chord_type = 'major';
        }

        $root_index = array_search($root_note, self::NOTES);
        if ($root_index === false) {
            $root_index = 0;
        }

        $chord_notes = [];
        $intervals = self::CHORDS[$chord_type];

        foreach ($intervals as $interval) {
            $note_index = ($root_index + $interval) % 12;
            $note_name = self::NOTES[$note_index];
            $midi_note = 12 + ($octave * 12) + $root_index + $interval;

            $chord_notes[] = [
                'note' => $note_name,
                'midi' => $midi_note,
                'frequency' => $this->midi_to_frequency($midi_note),
                'interval' => $interval,
            ];
        }

        // Apply inversion
        for ($i = 0; $i < $inversion; $i++) {
            $lowest = array_shift($chord_notes);
            $lowest['midi'] += 12; // Move up an octave
            $lowest['frequency'] = $this->midi_to_frequency($lowest['midi']);
            $chord_notes[] = $lowest;
        }

        return $chord_notes;
    }

    /**
     * Generate chord progression
     */
    public function generate_progression($key, $progression_name, $scale_type = 'major') {
        if (!isset(self::PROGRESSIONS[$progression_name])) {
            $progression_name = 'I-IV-V-I';
        }

        $scale = $this->get_scale($key, $scale_type, 4);
        $degree_indices = self::PROGRESSIONS[$progression_name];

        $chords = [];
        foreach ($degree_indices as $degree) {
            if ($degree >= count($scale)) {
                $degree = 0;
            }

            $root = $scale[$degree]['note'];

            // Determine chord quality based on scale degree (major scale)
            $chord_type = 'major';
            if ($scale_type === 'major') {
                if (in_array($degree, [1, 2, 5])) { // ii, iii, vi
                    $chord_type = 'minor';
                } elseif ($degree === 6) { // vii
                    $chord_type = 'diminished';
                }
            } elseif ($scale_type === 'minor') {
                if (in_array($degree, [2, 3, 5])) { // III, VI, VII
                    $chord_type = 'major';
                } elseif ($degree === 1) { // ii
                    $chord_type = 'diminished';
                }
            }

            $chords[] = [
                'root' => $root,
                'type' => $chord_type,
                'degree' => $degree + 1,
                'notes' => $this->build_chord($root, $chord_type, 4),
            ];
        }

        return $chords;
    }

    /**
     * Calculate interval between two notes
     */
    public function get_interval($note1, $note2) {
        $index1 = array_search($note1, self::NOTES);
        $index2 = array_search($note2, self::NOTES);

        if ($index1 === false || $index2 === false) {
            return 0;
        }

        $interval = $index2 - $index1;
        if ($interval < 0) {
            $interval += 12;
        }

        return $interval;
    }

    /**
     * Get interval name
     */
    public function get_interval_name($semitones) {
        $semitones = $semitones % 12;
        $names = [
            0 => 'Unison',
            1 => 'Minor 2nd',
            2 => 'Major 2nd',
            3 => 'Minor 3rd',
            4 => 'Major 3rd',
            5 => 'Perfect 4th',
            6 => 'Tritone',
            7 => 'Perfect 5th',
            8 => 'Minor 6th',
            9 => 'Major 6th',
            10 => 'Minor 7th',
            11 => 'Major 7th',
        ];

        return $names[$semitones] ?? 'Unknown';
    }

    /**
     * Convert MIDI note to frequency (Hz)
     * A4 = MIDI 69 = 440 Hz
     */
    public function midi_to_frequency($midi_note) {
        return 440.0 * pow(2, ($midi_note - 69) / 12.0);
    }

    /**
     * Convert frequency to MIDI note
     */
    public function frequency_to_midi($frequency) {
        return round(69 + 12 * log($frequency / 440.0) / log(2));
    }

    /**
     * Transpose note by semitones
     */
    public function transpose($note, $semitones) {
        $index = array_search($note, self::NOTES);
        if ($index === false) {
            return $note;
        }

        $new_index = ($index + $semitones) % 12;
        if ($new_index < 0) {
            $new_index += 12;
        }

        return self::NOTES[$new_index];
    }

    /**
     * Check if notes form a consonant interval
     */
    public function is_consonant($note1, $note2) {
        $interval = $this->get_interval($note1, $note2);
        // Consonant intervals: unison, 3rds, 4th, 5th, 6ths, octave
        $consonant_intervals = [0, 3, 4, 5, 7, 8, 9, 12];
        return in_array($interval, $consonant_intervals);
    }

    /**
     * Voice leading - find smoothest transition between chords
     */
    public function voice_lead($chord1, $chord2) {
        $voiced_chord2 = [];

        foreach ($chord2 as $note2) {
            $min_distance = PHP_INT_MAX;
            $best_octave = 0;

            // Try different octaves to find closest voicing
            for ($octave_shift = -2; $octave_shift <= 2; $octave_shift++) {
                $transposed_midi = $note2['midi'] + ($octave_shift * 12);

                foreach ($chord1 as $note1) {
                    $distance = abs($transposed_midi - $note1['midi']);
                    if ($distance < $min_distance) {
                        $min_distance = $distance;
                        $best_octave = $octave_shift;
                    }
                }
            }

            $voiced_note = $note2;
            $voiced_note['midi'] += ($best_octave * 12);
            $voiced_note['frequency'] = $this->midi_to_frequency($voiced_note['midi']);
            $voiced_chord2[] = $voiced_note;
        }

        return $voiced_chord2;
    }
}
