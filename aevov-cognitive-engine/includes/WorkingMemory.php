<?php
/**
 * Working Memory System
 *
 * Implements short-term memory with capacity limits and temporal decay
 * Based on Baddeley's Working Memory model and ACT-R activation spreading
 *
 * Features:
 * - Limited capacity (7±2 items, Miller's Law)
 * - Temporal decay (activation decreases over time)
 * - Rehearsal mechanism (reactivation)
 * - Chunking (combining items to increase effective capacity)
 * - Interference effects (similarity-based)
 */

namespace AevovCognitiveEngine;

class WorkingMemory {

    private $items = [];
    private $capacity = 7; // Miller's magic number
    private $decay_rate = 0.5; // Activation decay per second
    private $base_activation = 1.0;
    private $retrieval_threshold = 0.2; // Minimum activation for retrieval
    private $current_time = 0.0;

    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->capacity = $config['capacity'] ?? 7;
        $this->decay_rate = $config['decay_rate'] ?? 0.5;
        $this->retrieval_threshold = $config['threshold'] ?? 0.2;
        $this->current_time = microtime(true);
    }

    /**
     * Store item in working memory
     *
     * @param mixed $content Item content
     * @param array $context Contextual information
     * @return bool Success status
     */
    public function store($content, $context = []) {
        // Check if item already exists (update activation instead)
        $item_id = $this->generate_item_id($content);

        if (isset($this->items[$item_id])) {
            // Rehearsal: boost existing item activation
            $this->items[$item_id]['activation'] = $this->base_activation;
            $this->items[$item_id]['last_access'] = microtime(true);
            $this->items[$item_id]['access_count']++;
            return true;
        }

        // Apply capacity limit - remove least activated item if full
        if (count($this->items) >= $this->capacity) {
            $this->evict_weakest();
        }

        // Create new memory item
        $this->items[$item_id] = [
            'id' => $item_id,
            'content' => $content,
            'context' => $context,
            'activation' => $this->base_activation,
            'creation_time' => microtime(true),
            'last_access' => microtime(true),
            'access_count' => 1,
            'chunk_members' => [] // For chunking related items
        ];

        return true;
    }

    /**
     * Retrieve item from working memory
     *
     * @param mixed $cue Retrieval cue
     * @param string $mode 'exact' or 'fuzzy'
     * @return mixed|null Retrieved item or null
     */
    public function retrieve($cue, $mode = 'fuzzy') {
        $this->update_activations();

        if ($mode === 'exact') {
            return $this->exact_retrieval($cue);
        } else {
            return $this->fuzzy_retrieval($cue);
        }
    }

    /**
     * Exact retrieval - find item by content match
     */
    private function exact_retrieval($cue) {
        $cue_id = $this->generate_item_id($cue);

        if (isset($this->items[$cue_id])) {
            $item = $this->items[$cue_id];

            // Check if activation is above threshold
            if ($item['activation'] >= $this->retrieval_threshold) {
                // Update activation (rehearsal effect)
                $this->items[$cue_id]['activation'] = min(
                    $item['activation'] + 0.2,
                    $this->base_activation
                );
                $this->items[$cue_id]['last_access'] = microtime(true);
                $this->items[$cue_id]['access_count']++;

                return $item['content'];
            }
        }

        return null;
    }

    /**
     * Fuzzy retrieval - find most similar item using spreading activation
     */
    private function fuzzy_retrieval($cue) {
        $best_match = null;
        $best_activation = $this->retrieval_threshold;

        foreach ($this->items as $id => $item) {
            // Calculate similarity-based activation
            $similarity = $this->calculate_similarity($cue, $item['content']);
            $total_activation = $item['activation'] * $similarity;

            // Recency bonus
            $time_since_access = microtime(true) - $item['last_access'];
            $recency_boost = exp(-$this->decay_rate * $time_since_access * 0.1);
            $total_activation *= (1 + $recency_boost * 0.3);

            // Frequency bonus (access count)
            $frequency_boost = log($item['access_count'] + 1) * 0.1;
            $total_activation += $frequency_boost;

            if ($total_activation > $best_activation) {
                $best_activation = $total_activation;
                $best_match = $item;
            }
        }

        if ($best_match) {
            // Update activation
            $this->items[$best_match['id']]['last_access'] = microtime(true);
            $this->items[$best_match['id']]['access_count']++;
            return $best_match['content'];
        }

        return null;
    }

    /**
     * Update activation levels with temporal decay
     */
    private function update_activations() {
        $current_time = microtime(true);
        $time_delta = $current_time - $this->current_time;
        $this->current_time = $current_time;

        // Apply decay to all items
        foreach ($this->items as $id => &$item) {
            $time_since_access = $current_time - $item['last_access'];

            // Power law of forgetting (ACT-R style)
            // A(t) = A0 * t^(-d) where d is decay rate
            $decay_factor = pow($time_since_access + 1, -$this->decay_rate);
            $item['activation'] = $this->base_activation * $decay_factor;

            // Remove items below retrieval threshold after long periods
            if ($item['activation'] < $this->retrieval_threshold * 0.5 &&
                $time_since_access > 30) {
                unset($this->items[$id]);
            }
        }
    }

    /**
     * Evict weakest item to maintain capacity
     */
    private function evict_weakest() {
        $weakest_id = null;
        $weakest_activation = PHP_FLOAT_MAX;

        foreach ($this->items as $id => $item) {
            if ($item['activation'] < $weakest_activation) {
                $weakest_activation = $item['activation'];
                $weakest_id = $id;
            }
        }

        if ($weakest_id !== null) {
            unset($this->items[$weakest_id]);
        }
    }

    /**
     * Create chunk from multiple items
     * Chunking increases effective working memory capacity
     *
     * @param array $item_ids Items to chunk together
     * @param mixed $chunk_label Label for the chunk
     * @return bool Success
     */
    public function create_chunk($item_ids, $chunk_label) {
        // Verify all items exist
        foreach ($item_ids as $id) {
            if (!isset($this->items[$id])) {
                return false;
            }
        }

        // Create chunk item
        $chunk_content = [
            'type' => 'chunk',
            'label' => $chunk_label,
            'members' => array_map(function($id) {
                return $this->items[$id]['content'];
            }, $item_ids)
        ];

        // Store chunk
        $chunk_id = $this->generate_item_id($chunk_content);
        $this->items[$chunk_id] = [
            'id' => $chunk_id,
            'content' => $chunk_content,
            'context' => ['chunk' => true],
            'activation' => $this->base_activation,
            'creation_time' => microtime(true),
            'last_access' => microtime(true),
            'access_count' => 1,
            'chunk_members' => $item_ids
        ];

        // Remove individual items (they're now in the chunk)
        foreach ($item_ids as $id) {
            unset($this->items[$id]);
        }

        return true;
    }

    /**
     * Calculate similarity between two items
     *
     * @param mixed $a First item
     * @param mixed $b Second item
     * @return float Similarity score [0, 1]
     */
    private function calculate_similarity($a, $b) {
        // Type-based similarity
        if (gettype($a) !== gettype($b)) {
            return 0.1;
        }

        if (is_string($a) && is_string($b)) {
            // Levenshtein distance for strings
            $max_len = max(strlen($a), strlen($b));
            if ($max_len === 0) return 1.0;

            $distance = levenshtein(
                substr($a, 0, 255),
                substr($b, 0, 255)
            );
            return 1.0 - ($distance / $max_len);
        }

        if (is_numeric($a) && is_numeric($b)) {
            // Numeric similarity (inverse of normalized difference)
            $diff = abs($a - $b);
            $max_val = max(abs($a), abs($b), 1);
            return 1.0 / (1.0 + ($diff / $max_val));
        }

        if (is_array($a) && is_array($b)) {
            // Array overlap similarity
            $intersection = count(array_intersect_assoc($a, $b));
            $union = count(array_unique(array_merge($a, $b)));
            return $union > 0 ? $intersection / $union : 0.0;
        }

        // Default: exact match only
        return $a === $b ? 1.0 : 0.0;
    }

    /**
     * Generate unique ID for item
     */
    private function generate_item_id($content) {
        return md5(serialize($content));
    }

    /**
     * Get all items currently in working memory
     *
     * @return array Items with activation levels
     */
    public function get_contents() {
        $this->update_activations();

        $contents = [];
        foreach ($this->items as $item) {
            if ($item['activation'] >= $this->retrieval_threshold) {
                $contents[] = [
                    'content' => $item['content'],
                    'activation' => $item['activation'],
                    'age' => microtime(true) - $item['creation_time'],
                    'access_count' => $item['access_count']
                ];
            }
        }

        // Sort by activation (descending)
        usort($contents, function($a, $b) {
            return $b['activation'] <=> $a['activation'];
        });

        return $contents;
    }

    /**
     * Clear all items from working memory
     */
    public function clear() {
        $this->items = [];
        $this->current_time = microtime(true);
    }

    /**
     * Get current load (percentage of capacity used)
     *
     * @return float Load percentage [0, 1]
     */
    public function get_load() {
        $this->update_activations();
        $active_count = 0;

        foreach ($this->items as $item) {
            if ($item['activation'] >= $this->retrieval_threshold) {
                $active_count++;
            }
        }

        return $active_count / $this->capacity;
    }

    /**
     * Apply interference to similar items
     * Models cognitive interference in working memory
     *
     * @param mixed $interfering_item New item causing interference
     */
    public function apply_interference($interfering_item) {
        foreach ($this->items as $id => &$item) {
            $similarity = $this->calculate_similarity(
                $interfering_item,
                $item['content']
            );

            // Similar items interfere with each other
            if ($similarity > 0.7) {
                $interference_amount = $similarity * 0.3;
                $item['activation'] *= (1 - $interference_amount);
            }
        }
    }
}
