<?php
/**
 * Long-Term Memory System
 *
 * Implements episodic and semantic memory with consolidation
 * Based on Tulving's memory systems and ACT-R declarative memory
 *
 * Features:
 * - Episodic memory (events with temporal and spatial context)
 * - Semantic memory (facts and concepts without context)
 * - Memory consolidation (working memory → long-term memory)
 * - Spreading activation network
 * - Context-dependent retrieval
 * - Semantic networks with association strengths
 */

namespace AevovCognitiveEngine;

class LongTermMemory {

    private $episodic_memory = [];
    private $semantic_memory = [];
    private $semantic_network = []; // Nodes and edges for spreading activation
    private $consolidation_threshold = 3; // Access count required for consolidation
    private $association_decay = 0.95; // Decay rate for association strengths

    /**
     * Store episodic memory (event with context)
     *
     * @param mixed $content Event content
     * @param array $context Temporal and spatial context
     * @return string Memory ID
     */
    public function store_episodic($content, $context = []) {
        $memory_id = $this->generate_memory_id('episodic', $content, $context);

        // Check if similar episode exists
        $similar_episode = $this->find_similar_episode($content, $context);

        if ($similar_episode) {
            // Strengthen existing memory
            $this->episodic_memory[$similar_episode]['strength'] += 0.2;
            $this->episodic_memory[$similar_episode]['access_count']++;
            $this->episodic_memory[$similar_episode]['last_access'] = microtime(true);
            return $similar_episode;
        }

        // Create new episodic memory
        $this->episodic_memory[$memory_id] = [
            'id' => $memory_id,
            'type' => 'episodic',
            'content' => $content,
            'context' => array_merge([
                'time' => microtime(true),
                'location' => null,
                'emotional_valence' => 0,
                'importance' => 0.5
            ], $context),
            'strength' => 1.0,
            'creation_time' => microtime(true),
            'last_access' => microtime(true),
            'access_count' => 1,
            'consolidated' => false,
            'associations' => [] // Links to other memories
        ];

        // Extract semantic concepts for network
        $this->extract_and_link_concepts($memory_id, $content);

        return $memory_id;
    }

    /**
     * Store semantic memory (decontextualized fact)
     *
     * @param string $concept Concept identifier
     * @param mixed $knowledge Knowledge content
     * @param array $properties Concept properties
     * @return string Memory ID
     */
    public function store_semantic($concept, $knowledge, $properties = []) {
        $memory_id = $this->generate_memory_id('semantic', $concept, $knowledge);

        if (isset($this->semantic_memory[$memory_id])) {
            // Update existing semantic memory
            $this->semantic_memory[$memory_id]['knowledge'] = $knowledge;
            $this->semantic_memory[$memory_id]['strength'] += 0.1;
            $this->semantic_memory[$memory_id]['access_count']++;
            $this->semantic_memory[$memory_id]['last_access'] = microtime(true);
        } else {
            // Create new semantic memory
            $this->semantic_memory[$memory_id] = [
                'id' => $memory_id,
                'type' => 'semantic',
                'concept' => $concept,
                'knowledge' => $knowledge,
                'properties' => $properties,
                'strength' => 1.0,
                'creation_time' => microtime(true),
                'last_access' => microtime(true),
                'access_count' => 1,
                'category' => $properties['category'] ?? 'general',
                'associations' => []
            ];

            // Add to semantic network
            $this->add_to_semantic_network($memory_id, $concept, $properties);
        }

        return $memory_id;
    }

    /**
     * Retrieve episodic memories matching criteria
     *
     * @param array $cues Retrieval cues (content, context, time window)
     * @return array Matching episodes sorted by relevance
     */
    public function retrieve_episodic($cues = []) {
        $matches = [];

        foreach ($this->episodic_memory as $memory) {
            $score = $this->calculate_episodic_match_score($memory, $cues);

            if ($score > 0.3) { // Threshold for retrieval
                // Update access statistics
                $this->episodic_memory[$memory['id']]['last_access'] = microtime(true);
                $this->episodic_memory[$memory['id']]['access_count']++;

                $matches[] = [
                    'memory' => $memory,
                    'relevance_score' => $score
                ];
            }
        }

        // Sort by relevance
        usort($matches, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        return $matches;
    }

    /**
     * Retrieve semantic knowledge using spreading activation
     *
     * @param string $concept Starting concept
     * @param int $max_hops Maximum network distance
     * @return array Retrieved knowledge with activation levels
     */
    public function retrieve_semantic($concept, $max_hops = 2) {
        $activated_nodes = [];
        $activation_queue = [['concept' => $concept, 'activation' => 1.0, 'hops' => 0]];
        $visited = [];

        // Spreading activation algorithm
        while (!empty($activation_queue)) {
            $current = array_shift($activation_queue);

            if (isset($visited[$current['concept']]) || $current['hops'] > $max_hops) {
                continue;
            }

            $visited[$current['concept']] = true;
            $activated_nodes[$current['concept']] = $current['activation'];

            // Find memories with this concept
            foreach ($this->semantic_memory as $memory) {
                if ($memory['concept'] === $current['concept']) {
                    // Update access statistics
                    $this->semantic_memory[$memory['id']]['last_access'] = microtime(true);
                    $this->semantic_memory[$memory['id']]['access_count']++;
                }
            }

            // Spread activation to associated concepts
            if (isset($this->semantic_network[$current['concept']])) {
                foreach ($this->semantic_network[$current['concept']]['edges'] as $edge) {
                    $new_activation = $current['activation'] * $edge['strength'] * 0.8;

                    if ($new_activation > 0.1) { // Activation threshold
                        $activation_queue[] = [
                            'concept' => $edge['target'],
                            'activation' => $new_activation,
                            'hops' => $current['hops'] + 1
                        ];
                    }
                }
            }
        }

        // Retrieve memories for activated concepts
        $results = [];
        foreach ($activated_nodes as $concept => $activation) {
            foreach ($this->semantic_memory as $memory) {
                if ($memory['concept'] === $concept) {
                    $results[] = [
                        'concept' => $concept,
                        'knowledge' => $memory['knowledge'],
                        'activation' => $activation,
                        'strength' => $memory['strength']
                    ];
                }
            }
        }

        // Sort by activation
        usort($results, function($a, $b) {
            return $b['activation'] <=> $a['activation'];
        });

        return $results;
    }

    /**
     * Consolidate memories from working memory
     * Repeated access promotes working memory items to long-term storage
     *
     * @param array $working_memory_item Item from working memory
     * @param bool $force Force consolidation regardless of access count
     */
    public function consolidate($working_memory_item, $force = false) {
        $access_count = $working_memory_item['access_count'] ?? 1;

        // Check consolidation criteria
        if (!$force && $access_count < $this->consolidation_threshold) {
            return false;
        }

        $content = $working_memory_item['content'];
        $context = $working_memory_item['context'] ?? [];

        // Determine if episodic or semantic
        if (isset($context['time']) || isset($context['location']) ||
            isset($context['emotional_valence'])) {
            // Context-rich → episodic
            $this->store_episodic($content, $context);
        } else {
            // Context-free → semantic
            if (is_array($content) && isset($content['concept'])) {
                $this->store_semantic(
                    $content['concept'],
                    $content['knowledge'] ?? $content,
                    $content['properties'] ?? []
                );
            } else {
                // Extract concept from content
                $concept = $this->extract_concept($content);
                $this->store_semantic($concept, $content);
            }
        }

        return true;
    }

    /**
     * Create association between two memories
     *
     * @param string $memory_id_1 First memory
     * @param string $memory_id_2 Second memory
     * @param float $strength Association strength [0, 1]
     */
    public function create_association($memory_id_1, $memory_id_2, $strength = 0.5) {
        // Associate in episodic memory
        if (isset($this->episodic_memory[$memory_id_1])) {
            $this->episodic_memory[$memory_id_1]['associations'][$memory_id_2] = $strength;
        }

        if (isset($this->episodic_memory[$memory_id_2])) {
            $this->episodic_memory[$memory_id_2]['associations'][$memory_id_1] = $strength;
        }

        // Associate in semantic memory
        if (isset($this->semantic_memory[$memory_id_1])) {
            $this->semantic_memory[$memory_id_1]['associations'][$memory_id_2] = $strength;
        }

        if (isset($this->semantic_memory[$memory_id_2])) {
            $this->semantic_memory[$memory_id_2]['associations'][$memory_id_1] = $strength;
        }

        // Update semantic network edges
        $this->update_network_edge($memory_id_1, $memory_id_2, $strength);
    }

    /**
     * Apply memory decay over time
     * Models natural forgetting
     */
    public function apply_decay() {
        $current_time = microtime(true);

        // Decay episodic memories
        foreach ($this->episodic_memory as $id => &$memory) {
            $time_since_access = $current_time - $memory['last_access'];

            // Power law of forgetting
            $decay_rate = 0.3; // Slower decay than working memory
            $decay_factor = pow($time_since_access / 86400 + 1, -$decay_rate); // Days

            $memory['strength'] *= $decay_factor;

            // Remove very weak, old memories
            if ($memory['strength'] < 0.05 && $time_since_access > 604800) { // 1 week
                unset($this->episodic_memory[$id]);
            }
        }

        // Decay semantic memories (much slower)
        foreach ($this->semantic_memory as $id => &$memory) {
            $time_since_access = $current_time - $memory['last_access'];

            // Semantic memories decay very slowly
            $decay_rate = 0.1;
            $decay_factor = pow($time_since_access / 86400 + 1, -$decay_rate);

            $memory['strength'] *= $decay_factor;

            // Semantic memories are rarely fully forgotten
            if ($memory['strength'] < 0.01 && $time_since_access > 2592000) { // 30 days
                $memory['strength'] = 0.01; // Floor, not complete removal
            }
        }

        // Decay association strengths
        foreach ($this->semantic_network as $concept => &$node) {
            foreach ($node['edges'] as $idx => &$edge) {
                $edge['strength'] *= $this->association_decay;

                // Remove very weak associations
                if ($edge['strength'] < 0.05) {
                    unset($node['edges'][$idx]);
                }
            }
        }
    }

    /**
     * Calculate match score for episodic retrieval
     */
    private function calculate_episodic_match_score($memory, $cues) {
        $score = 0.0;
        $weight_sum = 0.0;

        // Content similarity
        if (isset($cues['content'])) {
            $content_sim = $this->calculate_content_similarity(
                $memory['content'],
                $cues['content']
            );
            $score += $content_sim * 0.5;
            $weight_sum += 0.5;
        }

        // Temporal proximity
        if (isset($cues['time_window'])) {
            $time_diff = abs($memory['context']['time'] - $cues['time_window']['center']);
            $time_score = exp(-$time_diff / ($cues['time_window']['radius'] ?? 3600));
            $score += $time_score * 0.3;
            $weight_sum += 0.3;
        }

        // Spatial proximity
        if (isset($cues['location']) && $memory['context']['location']) {
            $location_sim = $this->calculate_location_similarity(
                $memory['context']['location'],
                $cues['location']
            );
            $score += $location_sim * 0.2;
            $weight_sum += 0.2;
        }

        // Normalize score
        if ($weight_sum > 0) {
            $score /= $weight_sum;
        }

        // Boost by memory strength
        $score *= $memory['strength'];

        return $score;
    }

    /**
     * Find similar episode in memory
     */
    private function find_similar_episode($content, $context) {
        $threshold = 0.85;

        foreach ($this->episodic_memory as $id => $memory) {
            $content_sim = $this->calculate_content_similarity(
                $memory['content'],
                $content
            );

            // Check temporal proximity (within 1 hour = same episode)
            $time_diff = abs($memory['context']['time'] - ($context['time'] ?? microtime(true)));

            if ($content_sim > $threshold && $time_diff < 3600) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Extract concepts and link to semantic network
     */
    private function extract_and_link_concepts($memory_id, $content) {
        $concepts = $this->extract_concepts_from_content($content);

        foreach ($concepts as $concept) {
            // Store as semantic memory
            $semantic_id = $this->store_semantic($concept, [
                'extracted_from' => $memory_id,
                'type' => 'auto_extracted'
            ]);

            // Create association
            $this->create_association($memory_id, $semantic_id, 0.6);
        }
    }

    /**
     * Add concept to semantic network
     */
    private function add_to_semantic_network($memory_id, $concept, $properties) {
        if (!isset($this->semantic_network[$concept])) {
            $this->semantic_network[$concept] = [
                'concept' => $concept,
                'memory_ids' => [],
                'edges' => []
            ];
        }

        $this->semantic_network[$concept]['memory_ids'][] = $memory_id;

        // Create edges based on properties
        if (isset($properties['related_to'])) {
            foreach ($properties['related_to'] as $related_concept) {
                $this->update_network_edge($concept, $related_concept, 0.7);
            }
        }

        if (isset($properties['category'])) {
            $this->update_network_edge($concept, $properties['category'], 0.5);
        }
    }

    /**
     * Update edge in semantic network
     */
    private function update_network_edge($concept_a, $concept_b, $strength) {
        // Ensure nodes exist
        if (!isset($this->semantic_network[$concept_a])) {
            $this->semantic_network[$concept_a] = [
                'concept' => $concept_a,
                'memory_ids' => [],
                'edges' => []
            ];
        }

        if (!isset($this->semantic_network[$concept_b])) {
            $this->semantic_network[$concept_b] = [
                'concept' => $concept_b,
                'memory_ids' => [],
                'edges' => []
            ];
        }

        // Add bidirectional edges
        $this->semantic_network[$concept_a]['edges'][] = [
            'target' => $concept_b,
            'strength' => $strength
        ];

        $this->semantic_network[$concept_b]['edges'][] = [
            'target' => $concept_a,
            'strength' => $strength
        ];
    }

    /**
     * Extract concept identifier from content
     */
    private function extract_concept($content) {
        if (is_string($content)) {
            // Extract first significant word
            $words = preg_split('/\s+/', $content);
            return strtolower($words[0] ?? 'unknown');
        } elseif (is_array($content)) {
            return 'array_' . count($content);
        } else {
            return gettype($content);
        }
    }

    /**
     * Extract multiple concepts from content
     */
    private function extract_concepts_from_content($content) {
        if (is_string($content)) {
            // Extract keywords (simple approach)
            $words = preg_split('/\s+/', strtolower($content));
            return array_unique(array_filter($words, function($word) {
                return strlen($word) > 3; // Ignore short words
            }));
        }

        return [$this->extract_concept($content)];
    }

    /**
     * Calculate content similarity
     */
    private function calculate_content_similarity($a, $b) {
        if (is_string($a) && is_string($b)) {
            $max_len = max(strlen($a), strlen($b));
            if ($max_len === 0) return 1.0;

            $distance = levenshtein(
                substr($a, 0, 255),
                substr($b, 0, 255)
            );
            return 1.0 - ($distance / $max_len);
        }

        return $a === $b ? 1.0 : 0.0;
    }

    /**
     * Calculate location similarity
     */
    private function calculate_location_similarity($loc_a, $loc_b) {
        if (is_array($loc_a) && is_array($loc_b)) {
            $dx = ($loc_a['x'] ?? 0) - ($loc_b['x'] ?? 0);
            $dy = ($loc_a['y'] ?? 0) - ($loc_b['y'] ?? 0);
            $distance = sqrt($dx * $dx + $dy * $dy);

            return 1.0 / (1.0 + $distance);
        }

        return $loc_a === $loc_b ? 1.0 : 0.0;
    }

    /**
     * Generate unique memory ID
     */
    private function generate_memory_id($type, $content, $context = []) {
        return md5($type . serialize($content) . microtime());
    }

    /**
     * Get memory statistics
     */
    public function get_statistics() {
        return [
            'episodic_count' => count($this->episodic_memory),
            'semantic_count' => count($this->semantic_memory),
            'network_nodes' => count($this->semantic_network),
            'total_associations' => array_sum(array_map(function($node) {
                return count($node['edges']);
            }, $this->semantic_network))
        ];
    }
}
