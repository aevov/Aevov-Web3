<?php
/**
 * Analogy Engine - Structure Mapping Theory Implementation
 *
 * Implements analogical reasoning using Structure Mapping Theory (Gentner, 1983)
 * for finding and mapping structural similarities between patterns.
 *
 * @package AevovPatternSyncProtocol
 * @subpackage Reasoning
 * @since 1.0.0
 */

namespace APS\Reasoning;

use APS\Core\Logger;
use APS\DB\APS_Pattern_DB;

class AnalogyEngine {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Pattern database
     *
     * @var APS_Pattern_DB
     */
    private $patternDB;

    /**
     * Similarity threshold for analogies (0-1)
     *
     * @var float
     */
    private $similarityThreshold;

    /**
     * Maximum number of mappings to consider
     *
     * @var int
     */
    private $maxMappings;

    /**
     * Constructor
     *
     * @param float $similarity_threshold Minimum similarity score for valid analogies
     * @param int $max_mappings Maximum number of mappings to evaluate
     */
    public function __construct($similarity_threshold = 0.6, $max_mappings = 100) {
        $this->logger = Logger::get_instance();
        $this->patternDB = new APS_Pattern_DB();
        $this->similarityThreshold = $similarity_threshold;
        $this->maxMappings = $max_mappings;
    }

    /**
     * Find analogies for a given source pattern
     *
     * @param array $source_pattern Source pattern to find analogies for
     * @param array $target_domain Target domain patterns to search
     * @return array Array of analogies with mappings and scores
     */
    public function findAnalogies($source_pattern, $target_domain = null) {
        $this->logger->log('info', 'Finding analogies for pattern', [
            'source_type' => $source_pattern['type'] ?? 'unknown'
        ]);

        // Get target patterns from database if not provided
        if ($target_domain === null) {
            $target_domain = $this->patternDB->get_all_patterns(['limit' => 1000]);
        }

        $analogies = [];

        foreach ($target_domain as $target_pattern) {
            // Skip identical patterns
            if ($this->areIdentical($source_pattern, $target_pattern)) {
                continue;
            }

            // Compute structural mapping
            $mapping = $this->computeStructureMapping($source_pattern, $target_pattern);

            if ($mapping['score'] >= $this->similarityThreshold) {
                $analogies[] = [
                    'source' => $source_pattern,
                    'target' => $target_pattern,
                    'mapping' => $mapping['correspondences'],
                    'score' => $mapping['score'],
                    'explanation' => $this->generateExplanation($mapping)
                ];
            }
        }

        // Sort by score (best analogies first)
        usort($analogies, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $this->logger->log('info', 'Analogies found', [
            'count' => count($analogies),
            'threshold' => $this->similarityThreshold
        ]);

        return $analogies;
    }

    /**
     * Compute structure mapping between source and target patterns
     *
     * Implements Structure Mapping Theory algorithm
     *
     * @param array $source Source pattern
     * @param array $target Target pattern
     * @return array Mapping result with score and correspondences
     */
    public function computeStructureMapping($source, $target) {
        // Extract structural elements
        $source_structure = $this->extractStructure($source);
        $target_structure = $this->extractStructure($target);

        // Generate potential correspondences
        $correspondences = $this->generateCorrespondences(
            $source_structure,
            $target_structure
        );

        // Evaluate systematicity - prefer deep, connected structures
        $systematicity_score = $this->evaluateSystematicity($correspondences, $source_structure);

        // Compute structural similarity
        $similarity_score = $this->computeSimilarityScore($correspondences);

        // Combine scores (weighted)
        $total_score = (0.6 * $similarity_score) + (0.4 * $systematicity_score);

        return [
            'score' => $total_score,
            'correspondences' => $correspondences,
            'systematicity' => $systematicity_score,
            'similarity' => $similarity_score
        ];
    }

    /**
     * Extract structural elements from a pattern
     *
     * @param array $pattern Pattern to analyze
     * @return array Structural representation
     */
    private function extractStructure($pattern) {
        $structure = [
            'entities' => [],
            'attributes' => [],
            'relations' => [],
            'hierarchy_depth' => 0
        ];

        // Extract entities (objects, nodes)
        if (isset($pattern['entities'])) {
            $structure['entities'] = $pattern['entities'];
        } elseif (isset($pattern['nodes'])) {
            $structure['entities'] = $pattern['nodes'];
        }

        // Extract attributes
        if (isset($pattern['attributes'])) {
            $structure['attributes'] = $pattern['attributes'];
        } elseif (isset($pattern['properties'])) {
            $structure['attributes'] = $pattern['properties'];
        }

        // Extract relations (edges, connections)
        if (isset($pattern['relations'])) {
            $structure['relations'] = $pattern['relations'];
        } elseif (isset($pattern['edges'])) {
            $structure['relations'] = $pattern['edges'];
        } elseif (isset($pattern['connections'])) {
            $structure['relations'] = $pattern['connections'];
        }

        // Calculate hierarchy depth
        $structure['hierarchy_depth'] = $this->calculateHierarchyDepth($pattern);

        return $structure;
    }

    /**
     * Generate correspondences between source and target structures
     *
     * @param array $source_structure Source structure
     * @param array $target_structure Target structure
     * @return array Correspondences
     */
    private function generateCorrespondences($source_structure, $target_structure) {
        $correspondences = [];

        // Map entities
        foreach ($source_structure['entities'] as $s_entity) {
            foreach ($target_structure['entities'] as $t_entity) {
                $similarity = $this->computeEntitySimilarity($s_entity, $t_entity);

                if ($similarity > 0.3) { // Threshold for consideration
                    $correspondences[] = [
                        'type' => 'entity',
                        'source' => $s_entity,
                        'target' => $t_entity,
                        'similarity' => $similarity
                    ];
                }
            }
        }

        // Map relations
        foreach ($source_structure['relations'] as $s_relation) {
            foreach ($target_structure['relations'] as $t_relation) {
                $similarity = $this->computeRelationSimilarity($s_relation, $t_relation);

                if ($similarity > 0.3) {
                    $correspondences[] = [
                        'type' => 'relation',
                        'source' => $s_relation,
                        'target' => $t_relation,
                        'similarity' => $similarity
                    ];
                }
            }
        }

        // Filter and rank correspondences
        $correspondences = $this->filterCorrespondences($correspondences);

        return $correspondences;
    }

    /**
     * Compute similarity between two entities
     *
     * @param mixed $entity1 First entity
     * @param mixed $entity2 Second entity
     * @return float Similarity score (0-1)
     */
    private function computeEntitySimilarity($entity1, $entity2) {
        $score = 0.0;
        $factors = 0;

        // Compare types
        if (isset($entity1['type']) && isset($entity2['type'])) {
            $factors++;
            if ($entity1['type'] === $entity2['type']) {
                $score += 1.0;
            }
        }

        // Compare attributes
        if (isset($entity1['attributes']) && isset($entity2['attributes'])) {
            $factors++;
            $attr_similarity = $this->computeAttributeSimilarity(
                $entity1['attributes'],
                $entity2['attributes']
            );
            $score += $attr_similarity;
        }

        // Compare roles/functions
        if (isset($entity1['role']) && isset($entity2['role'])) {
            $factors++;
            if ($entity1['role'] === $entity2['role']) {
                $score += 1.0;
            } else {
                // Use semantic similarity if available
                $score += 0.5;
            }
        }

        return $factors > 0 ? $score / $factors : 0.0;
    }

    /**
     * Compute similarity between two relations
     *
     * @param mixed $relation1 First relation
     * @param mixed $relation2 Second relation
     * @return float Similarity score (0-1)
     */
    private function computeRelationSimilarity($relation1, $relation2) {
        $score = 0.0;
        $factors = 0;

        // Compare relation types
        if (isset($relation1['type']) && isset($relation2['type'])) {
            $factors++;
            if ($relation1['type'] === $relation2['type']) {
                $score += 1.0;
            } elseif ($this->areRelatedRelationTypes($relation1['type'], $relation2['type'])) {
                $score += 0.7;
            }
        }

        // Compare arity (number of arguments)
        if (isset($relation1['arity']) && isset($relation2['arity'])) {
            $factors++;
            if ($relation1['arity'] === $relation2['arity']) {
                $score += 1.0;
            }
        }

        // Compare directionality
        if (isset($relation1['directed']) && isset($relation2['directed'])) {
            $factors++;
            if ($relation1['directed'] === $relation2['directed']) {
                $score += 1.0;
            }
        }

        return $factors > 0 ? $score / $factors : 0.0;
    }

    /**
     * Compute similarity between attribute sets
     *
     * @param array $attrs1 First attribute set
     * @param array $attrs2 Second attribute set
     * @return float Similarity score (0-1)
     */
    private function computeAttributeSimilarity($attrs1, $attrs2) {
        $common = array_intersect_key($attrs1, $attrs2);
        $total = count($attrs1) + count($attrs2) - count($common);

        if ($total === 0) {
            return 0.0;
        }

        $similarity = 0.0;
        foreach ($common as $key => $value) {
            if ($attrs1[$key] === $attrs2[$key]) {
                $similarity += 1.0;
            } elseif (is_numeric($attrs1[$key]) && is_numeric($attrs2[$key])) {
                // Numeric similarity
                $diff = abs($attrs1[$key] - $attrs2[$key]);
                $avg = (abs($attrs1[$key]) + abs($attrs2[$key])) / 2;
                $similarity += max(0, 1 - ($diff / max($avg, 1)));
            } else {
                $similarity += 0.5; // Partial match
            }
        }

        return $similarity / max($total, 1);
    }

    /**
     * Evaluate systematicity of correspondences
     *
     * Systematicity principle: prefer deep, interconnected structures
     *
     * @param array $correspondences Correspondences to evaluate
     * @param array $source_structure Source structure
     * @return float Systematicity score (0-1)
     */
    private function evaluateSystematicity($correspondences, $source_structure) {
        if (empty($correspondences)) {
            return 0.0;
        }

        $score = 0.0;
        $max_score = 0.0;

        // Calculate depth-based scores
        foreach ($correspondences as $correspondence) {
            $depth = $this->getCorrespondenceDepth($correspondence, $source_structure);
            $weight = pow(1.5, $depth); // Exponential preference for depth
            $score += $correspondence['similarity'] * $weight;
            $max_score += $weight;
        }

        // Calculate connectivity bonus
        $connectivity = $this->calculateConnectivity($correspondences);
        $score += $connectivity * 2; // Bonus for connected correspondences
        $max_score += 2;

        return $max_score > 0 ? min(1.0, $score / $max_score) : 0.0;
    }

    /**
     * Calculate depth of a correspondence in the structure hierarchy
     *
     * @param array $correspondence Correspondence to evaluate
     * @param array $structure Structure to analyze
     * @return int Depth level
     */
    private function getCorrespondenceDepth($correspondence, $structure) {
        // Higher-order relations (relations between relations) have greater depth
        if ($correspondence['type'] === 'relation') {
            return 2;
        }
        return 1;
    }

    /**
     * Calculate connectivity of correspondences
     *
     * @param array $correspondences Correspondences to analyze
     * @return float Connectivity score (0-1)
     */
    private function calculateConnectivity($correspondences) {
        $connected_count = 0;
        $total_pairs = 0;

        for ($i = 0; $i < count($correspondences); $i++) {
            for ($j = $i + 1; $j < count($correspondences); $j++) {
                $total_pairs++;
                if ($this->areConnected($correspondences[$i], $correspondences[$j])) {
                    $connected_count++;
                }
            }
        }

        return $total_pairs > 0 ? $connected_count / $total_pairs : 0.0;
    }

    /**
     * Check if two correspondences are connected
     *
     * @param array $corr1 First correspondence
     * @param array $corr2 Second correspondence
     * @return bool True if connected
     */
    private function areConnected($corr1, $corr2) {
        // Check if they share entities or are related
        if ($corr1['type'] === 'relation' && $corr2['type'] === 'entity') {
            $relation = $corr1['source'];
            $entity = $corr2['source'];

            if (isset($relation['from']) && $relation['from'] === $entity) {
                return true;
            }
            if (isset($relation['to']) && $relation['to'] === $entity) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compute overall similarity score
     *
     * @param array $correspondences Correspondences to score
     * @return float Similarity score (0-1)
     */
    private function computeSimilarityScore($correspondences) {
        if (empty($correspondences)) {
            return 0.0;
        }

        $total_similarity = 0.0;
        foreach ($correspondences as $correspondence) {
            $total_similarity += $correspondence['similarity'];
        }

        return $total_similarity / count($correspondences);
    }

    /**
     * Filter correspondences to remove conflicts
     *
     * @param array $correspondences Correspondences to filter
     * @return array Filtered correspondences
     */
    private function filterCorrespondences($correspondences) {
        // Remove 1-to-many mappings (keep highest scoring)
        $filtered = [];
        $source_map = [];
        $target_map = [];

        usort($correspondences, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        foreach ($correspondences as $corr) {
            $source_key = json_encode($corr['source']);
            $target_key = json_encode($corr['target']);

            // One-to-one constraint
            if (!isset($source_map[$source_key]) && !isset($target_map[$target_key])) {
                $filtered[] = $corr;
                $source_map[$source_key] = true;
                $target_map[$target_key] = true;

                // Limit number of mappings
                if (count($filtered) >= $this->maxMappings) {
                    break;
                }
            }
        }

        return $filtered;
    }

    /**
     * Check if two patterns are identical
     *
     * @param array $pattern1 First pattern
     * @param array $pattern2 Second pattern
     * @return bool True if identical
     */
    private function areIdentical($pattern1, $pattern2) {
        if (isset($pattern1['id']) && isset($pattern2['id'])) {
            return $pattern1['id'] === $pattern2['id'];
        }

        return json_encode($pattern1) === json_encode($pattern2);
    }

    /**
     * Check if two relation types are related
     *
     * @param string $type1 First relation type
     * @param string $type2 Second relation type
     * @return bool True if related
     */
    private function areRelatedRelationTypes($type1, $type2) {
        $related_pairs = [
            ['causes', 'enables'],
            ['precedes', 'follows'],
            ['contains', 'part_of'],
            ['parent', 'child'],
            ['greater_than', 'less_than']
        ];

        foreach ($related_pairs as $pair) {
            if ((in_array($type1, $pair) && in_array($type2, $pair))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate hierarchy depth of a pattern
     *
     * @param array $pattern Pattern to analyze
     * @return int Depth
     */
    private function calculateHierarchyDepth($pattern) {
        $depth = 0;

        if (isset($pattern['children']) && is_array($pattern['children'])) {
            foreach ($pattern['children'] as $child) {
                $child_depth = $this->calculateHierarchyDepth($child);
                $depth = max($depth, $child_depth + 1);
            }
        }

        return $depth;
    }

    /**
     * Generate human-readable explanation of analogy
     *
     * @param array $mapping Mapping result
     * @return string Explanation
     */
    private function generateExplanation($mapping) {
        $corr_count = count($mapping['correspondences']);
        $score = round($mapping['score'] * 100, 1);

        $explanation = "Found {$corr_count} structural correspondences with {$score}% similarity. ";
        $explanation .= "Systematicity score: " . round($mapping['systematicity'] * 100, 1) . "%. ";

        if ($mapping['systematicity'] > 0.7) {
            $explanation .= "High structural coherence suggests strong analogy.";
        } elseif ($mapping['systematicity'] > 0.4) {
            $explanation .= "Moderate structural coherence.";
        } else {
            $explanation .= "Weak structural coherence.";
        }

        return $explanation;
    }

    /**
     * Retrieve analogies from pattern database
     *
     * @param int $pattern_id Pattern ID to find analogies for
     * @param int $limit Maximum number of analogies to return
     * @return array Analogies
     */
    public function retrieveAnalogiesFromDatabase($pattern_id, $limit = 10) {
        $source_pattern = $this->patternDB->get_pattern($pattern_id);

        if (!$source_pattern) {
            $this->logger->log('error', 'Source pattern not found', ['pattern_id' => $pattern_id]);
            return [];
        }

        // Decode pattern data if needed
        if (isset($source_pattern['pattern_data']) && is_string($source_pattern['pattern_data'])) {
            $source_pattern = json_decode($source_pattern['pattern_data'], true);
        }

        $analogies = $this->findAnalogies($source_pattern);

        return array_slice($analogies, 0, $limit);
    }

    /**
     * Set similarity threshold
     *
     * @param float $threshold Threshold value (0-1)
     * @return void
     */
    public function setSimilarityThreshold($threshold) {
        $this->similarityThreshold = max(0.0, min(1.0, $threshold));
    }

    /**
     * Get current similarity threshold
     *
     * @return float Threshold
     */
    public function getSimilarityThreshold() {
        return $this->similarityThreshold;
    }
}
