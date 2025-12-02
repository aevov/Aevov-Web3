<?php
/**
 * MIDI File Generator
 * Creates real MIDI files with proper formatting
 */

namespace AevovMusicForge;

class MIDIGenerator {

    private $tracks = [];
    private $tempo = 120; // BPM
    private $time_signature = [4, 4]; // 4/4 time
    private $ticks_per_quarter = 480; // Standard MIDI resolution

    /**
     * Set tempo in BPM
     */
    public function set_tempo($bpm) {
        $this->tempo = max(20, min(300, $bpm));
    }

    /**
     * Set time signature
     */
    public function set_time_signature($numerator, $denominator) {
        $this->time_signature = [$numerator, $denominator];
    }

    /**
     * Add a note to a track
     */
    public function add_note($track, $midi_note, $start_time, $duration, $velocity = 64, $channel = 0) {
        if (!isset($this->tracks[$track])) {
            $this->tracks[$track] = [];
        }

        // Convert time from beats to ticks
        $start_ticks = round($start_time * $this->ticks_per_quarter);
        $duration_ticks = round($duration * $this->ticks_per_quarter);

        $this->tracks[$track][] = [
            'type' => 'note',
            'midi' => $midi_note,
            'start' => $start_ticks,
            'duration' => $duration_ticks,
            'velocity' => $velocity,
            'channel' => $channel,
        ];
    }

    /**
     * Add chord (multiple notes at once)
     */
    public function add_chord($track, $chord_notes, $start_time, $duration, $velocity = 64, $channel = 0) {
        foreach ($chord_notes as $note) {
            $midi = is_array($note) ? $note['midi'] : $note;
            $this->add_note($track, $midi, $start_time, $duration, $velocity, $channel);
        }
    }

    /**
     * Add arpeggio (notes played in sequence)
     */
    public function add_arpeggio($track, $chord_notes, $start_time, $note_duration, $spacing, $velocity = 64) {
        $time = $start_time;
        foreach ($chord_notes as $note) {
            $midi = is_array($note) ? $note['midi'] : $note;
            $this->add_note($track, $midi, $time, $note_duration, $velocity);
            $time += $spacing;
        }
    }

    /**
     * Generate MIDI file binary data
     */
    public function generate_midi() {
        $midi = '';

        // MIDI Header Chunk
        $midi .= 'MThd'; // Header chunk type
        $midi .= pack('N', 6); // Header length (always 6)
        $midi .= pack('n', 1); // Format 1 (multiple tracks, synchronous)
        $midi .= pack('n', count($this->tracks) + 1); // Number of tracks + 1 for tempo track
        $midi .= pack('n', $this->ticks_per_quarter); // Ticks per quarter note

        // Tempo Track
        $midi .= $this->create_tempo_track();

        // Note Tracks
        foreach ($this->tracks as $track_events) {
            $midi .= $this->create_track($track_events);
        }

        return $midi;
    }

    /**
     * Create tempo track with meta events
     */
    private function create_tempo_track() {
        $track_data = '';

        // Time signature
        $track_data .= $this->create_delta_time(0);
        $track_data .= "\xFF\x58\x04"; // Meta event: Time Signature
        $track_data .= chr($this->time_signature[0]); // Numerator
        $track_data .= chr(log($this->time_signature[1], 2)); // Denominator (power of 2)
        $track_data .= "\x18\x08"; // Clocks per click, 32nd notes per quarter

        // Set tempo
        $microseconds_per_quarter = round(60000000 / $this->tempo);
        $track_data .= $this->create_delta_time(0);
        $track_data .= "\xFF\x51\x03"; // Meta event: Set Tempo
        $track_data .= $this->pack_int24($microseconds_per_quarter);

        // End of track
        $track_data .= $this->create_delta_time(0);
        $track_data .= "\xFF\x2F\x00"; // End of track

        // Create track chunk
        $track = 'MTrk';
        $track .= pack('N', strlen($track_data));
        $track .= $track_data;

        return $track;
    }

    /**
     * Create a track with note events
     */
    private function create_track($events) {
        // Sort events by start time
        usort($events, function($a, $b) {
            return $a['start'] <=> $b['start'];
        });

        $track_data = '';
        $current_time = 0;

        // Create note on/off event pairs
        $midi_events = [];
        foreach ($events as $event) {
            if ($event['type'] === 'note') {
                $midi_events[] = [
                    'time' => $event['start'],
                    'type' => 'note_on',
                    'channel' => $event['channel'],
                    'note' => $event['midi'],
                    'velocity' => $event['velocity'],
                ];
                $midi_events[] = [
                    'time' => $event['start'] + $event['duration'],
                    'type' => 'note_off',
                    'channel' => $event['channel'],
                    'note' => $event['midi'],
                    'velocity' => 0,
                ];
            }
        }

        // Sort by time
        usort($midi_events, function($a, $b) {
            return $a['time'] <=> $b['time'];
        });

        // Write events with delta times
        foreach ($midi_events as $event) {
            $delta_time = $event['time'] - $current_time;
            $track_data .= $this->create_delta_time($delta_time);

            if ($event['type'] === 'note_on') {
                $track_data .= chr(0x90 | $event['channel']); // Note On
                $track_data .= chr($event['note']);
                $track_data .= chr($event['velocity']);
            } else {
                $track_data .= chr(0x80 | $event['channel']); // Note Off
                $track_data .= chr($event['note']);
                $track_data .= chr($event['velocity']);
            }

            $current_time = $event['time'];
        }

        // End of track
        $track_data .= $this->create_delta_time(0);
        $track_data .= "\xFF\x2F\x00";

        // Create track chunk
        $track = 'MTrk';
        $track .= pack('N', strlen($track_data));
        $track .= $track_data;

        return $track;
    }

    /**
     * Create variable-length delta time
     */
    private function create_delta_time($ticks) {
        $ticks = (int)$ticks;
        $buffer = $ticks & 0x7F;

        while ($ticks >>= 7) {
            $buffer <<= 8;
            $buffer |= 0x80;
            $buffer += ($ticks & 0x7F);
        }

        $result = '';
        while (true) {
            $result .= chr($buffer & 0xFF);
            if ($buffer & 0x80) {
                $buffer >>= 8;
            } else {
                break;
            }
        }

        return $result;
    }

    /**
     * Pack 24-bit integer (big-endian)
     */
    private function pack_int24($value) {
        return chr(($value >> 16) & 0xFF) .
               chr(($value >> 8) & 0xFF) .
               chr($value & 0xFF);
    }

    /**
     * Save MIDI file
     */
    public function save_to_file($filepath) {
        $midi_data = $this->generate_midi();
        return file_put_contents($filepath, $midi_data);
    }

    /**
     * Get MIDI data as base64
     */
    public function get_base64() {
        return base64_encode($this->generate_midi());
    }

    /**
     * Clear all tracks
     */
    public function clear() {
        $this->tracks = [];
    }

    /**
     * Generate melody from scale
     */
    public function generate_melody($track, $scale_notes, $num_bars = 4, $rhythmic_pattern = null) {
        if ($rhythmic_pattern === null) {
            // Default rhythm: quarter notes
            $rhythmic_pattern = [
                ['duration' => 1, 'rest' => false],
                ['duration' => 1, 'rest' => false],
                ['duration' => 1, 'rest' => false],
                ['duration' => 1, 'rest' => false],
            ];
        }

        $time = 0;
        $beats_per_bar = $this->time_signature[0];

        for ($bar = 0; $bar < $num_bars; $bar++) {
            foreach ($rhythmic_pattern as $rhythm) {
                if (!$rhythm['rest']) {
                    // Random walk through scale
                    $note_index = mt_rand(0, count($scale_notes) - 1);
                    $note = $scale_notes[$note_index];

                    $velocity = mt_rand(60, 100); // Dynamic variation
                    $this->add_note($track, $note['midi'], $time, $rhythm['duration'], $velocity);
                }

                $time += $rhythm['duration'];
            }
        }
    }

    /**
     * Generate rhythmic pattern
     */
    public function create_rhythm_pattern($pattern_type = 'straight') {
        switch ($pattern_type) {
            case 'straight':
                return [
                    ['duration' => 1, 'rest' => false],
                    ['duration' => 1, 'rest' => false],
                    ['duration' => 1, 'rest' => false],
                    ['duration' => 1, 'rest' => false],
                ];

            case 'syncopated':
                return [
                    ['duration' => 0.75, 'rest' => false],
                    ['duration' => 0.25, 'rest' => false],
                    ['duration' => 0.5, 'rest' => false],
                    ['duration' => 0.5, 'rest' => false],
                    ['duration' => 1, 'rest' => false],
                ];

            case 'dotted':
                return [
                    ['duration' => 1.5, 'rest' => false],
                    ['duration' => 0.5, 'rest' => false],
                    ['duration' => 1.5, 'rest' => false],
                    ['duration' => 0.5, 'rest' => false],
                ];

            case 'triplet':
                return [
                    ['duration' => 0.333, 'rest' => false],
                    ['duration' => 0.333, 'rest' => false],
                    ['duration' => 0.333, 'rest' => false],
                    ['duration' => 1, 'rest' => false],
                ];

            default:
                return $this->create_rhythm_pattern('straight');
        }
    }
}
