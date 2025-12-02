<?php
/**
 * Memory Consolidation Engine
 * Implements sleep-like replay and synaptic scaling for memory consolidation
 */

namespace AevovMemoryCore;

class ConsolidationEngine {

    private $memory_manager;
    private $replay_rate = 10; // How many times to replay each memory
    private $synaptic_scaling_factor = 0.1;
    private $consolidation_threshold = 0.5; // Minimum strength to consolidate

    public function __construct($memory_manager = null) {
        $this->memory_manager = $memory_manager;
    }

    /**
     * Consolidate memories through replay (inspired by hippocampal replay)
     */
    public function consolidate_memories($memory_ids) {
        $consolidated = [];

        foreach ($memory_ids as $memory_id) {
            // Read memory
            $memory = $this->memory_manager->read_from_memory($memory_id);

            if (!$memory) {
                continue;
            }

            // Check if memory is strong enough to consolidate
            $strength = $memory['strength'] ?? 0;

            if ($strength < $this->consolidation_threshold) {
                continue; // Skip weak memories
            }

            // Replay memory multiple times
            $replayed_memory = $this->replay_memory($memory, $this->replay_rate);

            // Apply synaptic scaling
            $scaled_memory = $this->apply_synaptic_scaling($replayed_memory);

            // Store consolidated memory
            $this->memory_manager->write_to_memory(
                $memory_id,
                $scaled_memory,
                null,
                true // Offload to long-term storage
            );

            $consolidated[] = [
                'memory_id' => $memory_id,
                'original_strength' => $strength,
                'consolidated_strength' => $scaled_memory['strength'],
                'replay_count' => $this->replay_rate,
            ];
        }

        return $consolidated;
    }

    /**
     * Replay memory (similar to hippocampal replay during sleep)
     */
    private function replay_memory($memory, $replay_count) {
        $replayed = $memory;
        $initial_strength = $memory['strength'] ?? 1.0;

        for ($i = 0; $i < $replay_count; $i++) {
            // Each replay strengthens the memory trace
            $replayed['strength'] = $this->strengthen_trace(
                $replayed['strength'] ?? $initial_strength,
                $i,
                $replay_count
            );

            // Replay also adds noise for generalization
            if (isset($replayed['features'])) {
                $replayed['features'] = $this->add_replay_noise($replayed['features'], 0.05);
            }

            // Track replay history
            $replayed['replay_history'][] = [
                'iteration' => $i,
                'strength' => $replayed['strength'],
                'timestamp' => time(),
            ];
        }

        return $replayed;
    }

    /**
     * Strengthen memory trace with diminishing returns
     */
    private function strengthen_trace($current_strength, $iteration, $total_iterations) {
        // Logarithmic strengthening (diminishing returns)
        $progress = ($iteration + 1) / $total_iterations;
        $boost = 0.2 * log($progress + 1);

        return min(1.0, $current_strength + $boost);
    }

    /**
     * Add small noise for generalization
     */
    private function add_replay_noise($features, $noise_level) {
        if (!is_array($features)) {
            return $features;
        }

        $noisy_features = [];

        foreach ($features as $key => $value) {
            if (is_numeric($value)) {
                $noise = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $noise_level;
                $noisy_features[$key] = $value + ($value * $noise);
            } elseif (is_array($value)) {
                $noisy_features[$key] = $this->add_replay_noise($value, $noise_level);
            } else {
                $noisy_features[$key] = $value;
            }
        }

        return $noisy_features;
    }

    /**
     * Synaptic scaling (homeostatic plasticity)
     * Normalizes synaptic weights to maintain network stability
     */
    private function apply_synaptic_scaling($memory) {
        $scaled = $memory;

        // Compute current activity level
        $activity = $this->compute_activity_level($memory);

        // Target activity level
        $target_activity = 0.5;

        // Scaling factor
        $scale = $target_activity / max($activity, 0.01);

        // Apply scaling
        if (isset($scaled['weights'])) {
            $scaled['weights'] = $this->scale_weights($scaled['weights'], $scale);
        }

        // Apply to strength
        if (isset($scaled['strength'])) {
            $scaled['strength'] *= (1 + $this->synaptic_scaling_factor * ($scale - 1));
            $scaled['strength'] = max(0, min(1, $scaled['strength']));
        }

        $scaled['scaled'] = true;
        $scaled['scaling_factor'] = $scale;

        return $scaled;
    }

    /**
     * Compute activity level of memory
     */
    private function compute_activity_level($memory) {
        $activity = 0;
        $count = 0;

        if (isset($memory['weights']) && is_array($memory['weights'])) {
            foreach ($memory['weights'] as $weight) {
                $activity += abs($weight);
                $count++;
            }
        }

        if (isset($memory['strength'])) {
            $activity += $memory['strength'];
            $count++;
        }

        return $count > 0 ? $activity / $count : 0;
    }

    /**
     * Scale weights
     */
    private function scale_weights($weights, $scale) {
        if (!is_array($weights)) {
            return $weights * $scale;
        }

        $scaled = [];
        foreach ($weights as $key => $weight) {
            if (is_numeric($weight)) {
                $scaled[$key] = $weight * $scale;
            } elseif (is_array($weight)) {
                $scaled[$key] = $this->scale_weights($weight, $scale);
            } else {
                $scaled[$key] = $weight;
            }
        }

        return $scaled;
    }

    /**
     * Systems consolidation - transfer from episodic to semantic memory
     */
    public function systems_consolidation($episodic_memories) {
        $semantic_memories = [];

        // Group similar memories
        $clusters = $this->cluster_memories($episodic_memories);

        // Extract common patterns from each cluster
        foreach ($clusters as $cluster) {
            $semantic_memory = $this->extract_semantic_memory($cluster);
            $semantic_memories[] = $semantic_memory;
        }

        return $semantic_memories;
    }

    /**
     * Cluster similar memories
     */
    private function cluster_memories($memories) {
        $clusters = [];
        $similarity_threshold = 0.7;

        foreach ($memories as $memory) {
            $assigned = false;

            // Try to assign to existing cluster
            foreach ($clusters as &$cluster) {
                $similarity = $this->compute_memory_similarity($memory, $cluster[0]);

                if ($similarity > $similarity_threshold) {
                    $cluster[] = $memory;
                    $assigned = true;
                    break;
                }
            }

            // Create new cluster if not assigned
            if (!$assigned) {
                $clusters[] = [$memory];
            }
        }

        return $clusters;
    }

    /**
     * Compute similarity between memories
     */
    private function compute_memory_similarity($mem1, $mem2) {
        // Simple overlap-based similarity
        $common_keys = array_intersect_key(
            $mem1['features'] ?? [],
            $mem2['features'] ?? []
        );

        $total_keys = array_unique(array_merge(
            array_keys($mem1['features'] ?? []),
            array_keys($mem2['features'] ?? [])
        ));

        return count($common_keys) / max(count($total_keys), 1);
    }

    /**
     * Extract semantic memory from episodic cluster
     */
    private function extract_semantic_memory($cluster) {
        // Average features across cluster
        $semantic = [
            'type' => 'semantic',
            'source_count' => count($cluster),
            'features' => $this->average_features($cluster),
            'strength' => $this->average_strength($cluster),
            'created' => time(),
        ];

        return $semantic;
    }

    /**
     * Average features across memories
     */
    private function average_features($memories) {
        $all_features = [];

        foreach ($memories as $memory) {
            if (isset($memory['features'])) {
                foreach ($memory['features'] as $key => $value) {
                    if (!isset($all_features[$key])) {
                        $all_features[$key] = [];
                    }
                    $all_features[$key][] = $value;
                }
            }
        }

        $averaged = [];
        foreach ($all_features as $key => $values) {
            if (is_numeric($values[0])) {
                $averaged[$key] = array_sum($values) / count($values);
            } else {
                // For non-numeric, use most common
                $averaged[$key] = $this->most_common($values);
            }
        }

        return $averaged;
    }

    /**
     * Average strength across memories
     */
    private function average_strength($memories) {
        $strengths = array_map(function($mem) {
            return $mem['strength'] ?? 0.5;
        }, $memories);

        return array_sum($strengths) / count($strengths);
    }

    /**
     * Get most common value
     */
    private function most_common($values) {
        $counts = array_count_values($values);
        arsort($counts);
        return array_key_first($counts);
    }

    /**
     * Set replay rate
     */
    public function set_replay_rate($rate) {
        $this->replay_rate = $rate;
    }

    /**
     * Set consolidation threshold
     */
    public function set_consolidation_threshold($threshold) {
        $this->consolidation_threshold = $threshold;
    }
}
