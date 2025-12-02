<?php
/**
 * Melody Generator
 * Advanced melody generation with contour shaping and motif development
 */

namespace AevovMusicForge;

class MelodyGenerator {

    private $theory;
    private $last_note = null;
    private $motif_memory = [];

    public function __construct() {
        $this->theory = new MusicTheory();
    }

    /**
     * Generate melody using contour shaping
     * Contour types: ascending, descending, arch, valley, wave
     */
    public function generate_contour_melody($scale_notes, $num_notes, $contour_type = 'arch') {
        $melody = [];
        $scale_range = count($scale_notes);

        for ($i = 0; $i < $num_notes; $i++) {
            $position = $i / $num_notes; // 0 to 1

            switch ($contour_type) {
                case 'ascending':
                    $scale_index = floor($position * $scale_range);
                    break;

                case 'descending':
                    $scale_index = floor((1 - $position) * $scale_range);
                    break;

                case 'arch':
                    // Parabolic arch
                    $height = 4 * $position * (1 - $position);
                    $scale_index = floor($height * $scale_range);
                    break;

                case 'valley':
                    // Inverted arch
                    $depth = 1 - (4 * $position * (1 - $position));
                    $scale_index = floor($depth * $scale_range);
                    break;

                case 'wave':
                    // Sine wave
                    $wave = (sin($position * M_PI * 4) + 1) / 2;
                    $scale_index = floor($wave * $scale_range);
                    break;

                default:
                    $scale_index = mt_rand(0, $scale_range - 1);
            }

            $scale_index = max(0, min($scale_range - 1, $scale_index));
            $melody[] = $scale_notes[$scale_index];
        }

        return $melody;
    }

    /**
     * Generate melody using random walk (constrained movement)
     */
    public function generate_random_walk($scale_notes, $num_notes, $max_jump = 2) {
        $melody = [];
        $current_index = floor(count($scale_notes) / 2); // Start in middle

        for ($i = 0; $i < $num_notes; $i++) {
            $melody[] = $scale_notes[$current_index];

            // Random step within max_jump
            $step = mt_rand(-$max_jump, $max_jump);
            $current_index += $step;

            // Constrain to scale range
            $current_index = max(0, min(count($scale_notes) - 1, $current_index));
        }

        return $melody;
    }

    /**
     * Generate melody using Markov chain
     */
    public function generate_markov_melody($scale_notes, $num_notes, $training_data = null) {
        if ($training_data === null) {
            // Create simple transition probabilities
            $transitions = $this->create_default_transitions(count($scale_notes));
        } else {
            $transitions = $this->learn_transitions($training_data);
        }

        $melody = [];
        $current_index = mt_rand(0, count($scale_notes) - 1);

        for ($i = 0; $i < $num_notes; $i++) {
            $melody[] = $scale_notes[$current_index];

            // Choose next note based on transition probabilities
            $current_index = $this->sample_next_note($current_index, $transitions);
        }

        return $melody;
    }

    /**
     * Create default transition matrix (prefers stepwise motion)
     */
    private function create_default_transitions($num_notes) {
        $transitions = [];

        for ($i = 0; $i < $num_notes; $i++) {
            $transitions[$i] = [];
            $total_weight = 0;

            for ($j = 0; $j < $num_notes; $j++) {
                $interval = abs($i - $j);

                // Prefer small intervals
                if ($interval === 0) {
                    $weight = 0.1; // Repeat
                } elseif ($interval === 1) {
                    $weight = 0.4; // Step
                } elseif ($interval === 2) {
                    $weight = 0.2; // Skip
                } else {
                    $weight = 0.05; // Jump
                }

                $transitions[$i][$j] = $weight;
                $total_weight += $weight;
            }

            // Normalize to probabilities
            foreach ($transitions[$i] as $j => $weight) {
                $transitions[$i][$j] = $weight / $total_weight;
            }
        }

        return $transitions;
    }

    /**
     * Learn transition probabilities from example melodies
     */
    private function learn_transitions($training_melodies) {
        $counts = [];
        $transitions = [];

        // Count transitions
        foreach ($training_melodies as $melody) {
            for ($i = 0; $i < count($melody) - 1; $i++) {
                $current = $melody[$i];
                $next = $melody[$i + 1];

                if (!isset($counts[$current])) {
                    $counts[$current] = [];
                }
                if (!isset($counts[$current][$next])) {
                    $counts[$current][$next] = 0;
                }

                $counts[$current][$next]++;
            }
        }

        // Convert counts to probabilities
        foreach ($counts as $current => $nexts) {
            $total = array_sum($nexts);
            $transitions[$current] = [];

            foreach ($nexts as $next => $count) {
                $transitions[$current][$next] = $count / $total;
            }
        }

        return $transitions;
    }

    /**
     * Sample next note from transition probabilities
     */
    private function sample_next_note($current_index, $transitions) {
        if (!isset($transitions[$current_index])) {
            return mt_rand(0, count($transitions) - 1);
        }

        $rand = mt_rand() / mt_getrandmax();
        $cumulative = 0;

        foreach ($transitions[$current_index] as $next_index => $probability) {
            $cumulative += $probability;
            if ($rand < $cumulative) {
                return $next_index;
            }
        }

        return $current_index;
    }

    /**
     * Generate motif (short musical phrase)
     */
    public function generate_motif($scale_notes, $length = 4) {
        $motif = [];

        // Create rhythmic interest
        $contour = ['arch', 'valley', 'ascending', 'descending'][mt_rand(0, 3)];
        $motif_notes = $this->generate_contour_melody($scale_notes, $length, $contour);

        // Add rhythm
        $rhythms = [1, 0.5, 0.5, 1]; // Example rhythm
        if ($length === 4) {
            $rhythms = [1, 0.5, 0.5, 1];
        } elseif ($length === 3) {
            $rhythms = [1, 1, 1];
        } else {
            $rhythms = array_fill(0, $length, 1);
        }

        for ($i = 0; $i < $length; $i++) {
            $motif[] = [
                'note' => $motif_notes[$i],
                'duration' => $rhythms[$i % count($rhythms)],
            ];
        }

        $this->motif_memory[] = $motif;
        return $motif;
    }

    /**
     * Develop motif through transformation
     */
    public function develop_motif($motif, $technique = 'transpose') {
        $developed = [];

        switch ($technique) {
            case 'transpose':
                // Transpose up or down
                $semitones = [2, 3, 5, 7][mt_rand(0, 3)];
                foreach ($motif as $note_event) {
                    $new_note = $note_event['note'];
                    $new_note['midi'] += $semitones;
                    $new_note['frequency'] = $this->theory->midi_to_frequency($new_note['midi']);

                    $developed[] = [
                        'note' => $new_note,
                        'duration' => $note_event['duration'],
                    ];
                }
                break;

            case 'invert':
                // Melodic inversion
                $first_midi = $motif[0]['note']['midi'];
                foreach ($motif as $note_event) {
                    $interval = $note_event['note']['midi'] - $first_midi;
                    $new_midi = $first_midi - $interval;

                    $new_note = $note_event['note'];
                    $new_note['midi'] = $new_midi;
                    $new_note['frequency'] = $this->theory->midi_to_frequency($new_midi);

                    $developed[] = [
                        'note' => $new_note,
                        'duration' => $note_event['duration'],
                    ];
                }
                break;

            case 'retrograde':
                // Play backwards
                $developed = array_reverse($motif);
                break;

            case 'augment':
                // Lengthen durations
                foreach ($motif as $note_event) {
                    $developed[] = [
                        'note' => $note_event['note'],
                        'duration' => $note_event['duration'] * 2,
                    ];
                }
                break;

            case 'diminish':
                // Shorten durations
                foreach ($motif as $note_event) {
                    $developed[] = [
                        'note' => $note_event['note'],
                        'duration' => $note_event['duration'] * 0.5,
                    ];
                }
                break;

            case 'sequence':
                // Repeat at different pitch levels
                $original = $motif;
                $developed = $motif;

                // Add transposed version
                $transposed = $this->develop_motif($motif, 'transpose');
                $developed = array_merge($developed, $transposed);
                break;

            default:
                $developed = $motif;
        }

        return $developed;
    }

    /**
     * Apply ornamentation (trills, mordents, turns)
     */
    public function add_ornamentation($note, $scale_notes, $ornament_type = 'trill') {
        $current_scale_index = $this->find_note_in_scale($note, $scale_notes);
        if ($current_scale_index === false) {
            return [$note];
        }

        $ornament = [];

        switch ($ornament_type) {
            case 'trill':
                // Alternate with upper neighbor
                if ($current_scale_index < count($scale_notes) - 1) {
                    $upper = $scale_notes[$current_scale_index + 1];
                    $ornament = [
                        ['note' => $note, 'duration' => 0.125],
                        ['note' => $upper, 'duration' => 0.125],
                        ['note' => $note, 'duration' => 0.125],
                        ['note' => $upper, 'duration' => 0.125],
                    ];
                }
                break;

            case 'mordent':
                // Quick dip to lower neighbor
                if ($current_scale_index > 0) {
                    $lower = $scale_notes[$current_scale_index - 1];
                    $ornament = [
                        ['note' => $note, 'duration' => 0.25],
                        ['note' => $lower, 'duration' => 0.125],
                        ['note' => $note, 'duration' => 0.625],
                    ];
                }
                break;

            case 'turn':
                // Upper-main-lower-main
                if ($current_scale_index > 0 && $current_scale_index < count($scale_notes) - 1) {
                    $upper = $scale_notes[$current_scale_index + 1];
                    $lower = $scale_notes[$current_scale_index - 1];
                    $ornament = [
                        ['note' => $upper, 'duration' => 0.125],
                        ['note' => $note, 'duration' => 0.25],
                        ['note' => $lower, 'duration' => 0.125],
                        ['note' => $note, 'duration' => 0.5],
                    ];
                }
                break;

            case 'appoggiatura':
                // Leaning note
                if ($current_scale_index > 0) {
                    $neighbor = $scale_notes[$current_scale_index - 1];
                    $ornament = [
                        ['note' => $neighbor, 'duration' => 0.5],
                        ['note' => $note, 'duration' => 0.5],
                    ];
                }
                break;
        }

        return empty($ornament) ? [['note' => $note, 'duration' => 1]] : $ornament;
    }

    /**
     * Find note in scale
     */
    private function find_note_in_scale($note, $scale_notes) {
        foreach ($scale_notes as $index => $scale_note) {
            if ($scale_note['midi'] === $note['midi']) {
                return $index;
            }
        }
        return false;
    }

    /**
     * Generate complete melody with structure (A-B-A form, etc.)
     */
    public function generate_structured_melody($scale_notes, $form = 'ABA') {
        $sections = [];

        if ($form === 'ABA') {
            $section_a = $this->generate_motif($scale_notes, 8);
            $section_b = $this->generate_motif($scale_notes, 8);

            $sections = [
                'A' => $section_a,
                'B' => $section_b,
                'A2' => $section_a, // Repeat A
            ];
        } elseif ($form === 'AABA') {
            $section_a = $this->generate_motif($scale_notes, 8);
            $section_b = $this->generate_motif($scale_notes, 8);

            $sections = [
                'A1' => $section_a,
                'A2' => $section_a,
                'B' => $section_b,
                'A3' => $section_a,
            ];
        } elseif ($form === 'verse-chorus') {
            $verse = $this->generate_motif($scale_notes, 16);
            $chorus = $this->generate_motif($scale_notes, 8);

            $sections = [
                'verse1' => $verse,
                'chorus1' => $chorus,
                'verse2' => $verse,
                'chorus2' => $chorus,
            ];
        }

        return $sections;
    }
}
