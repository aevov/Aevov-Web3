<?php
/**
 * Pattern Miner - Extract patterns from data
 *
 * Implements Apriori algorithm for frequent pattern mining,
 * sequential pattern discovery, association rule generation,
 * and pattern quality metrics.
 *
 * @package AevovPatternSyncProtocol
 * @subpackage Analysis
 * @since 1.0.0
 */

namespace APS\Analysis;

use APS\Core\Logger;
use APS\DB\APS_Pattern_DB;

class PatternMiner {

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
     * Minimum support threshold
     *
     * @var float
     */
    private $minSupport;

    /**
     * Minimum confidence threshold
     *
     * @var float
     */
    private $minConfidence;

    /**
     * Maximum itemset size
     *
     * @var int
     */
    private $maxItemsetSize;

    /**
     * Transaction database
     *
     * @var array
     */
    private $transactions;

    /**
     * Frequent itemsets cache
     *
     * @var array
     */
    private $frequentItemsets;

    /**
     * Constructor
     *
     * @param float $min_support Minimum support (0-1)
     * @param float $min_confidence Minimum confidence (0-1)
     * @param int $max_itemset_size Maximum itemset size
     */
    public function __construct($min_support = 0.1, $min_confidence = 0.5, $max_itemset_size = 5) {
        $this->logger = Logger::get_instance();
        $this->patternDB = new APS_Pattern_DB();

        $this->minSupport = $min_support;
        $this->minConfidence = $min_confidence;
        $this->maxItemsetSize = $max_itemset_size;

        $this->transactions = [];
        $this->frequentItemsets = [];
    }

    /**
     * Mine frequent patterns from dataset using Apriori algorithm
     *
     * @param array $dataset Input dataset (array of transactions)
     * @return array Frequent patterns
     */
    public function mineFrequentPatterns($dataset) {
        $this->logger->log('info', 'Starting frequent pattern mining', [
            'transactions' => count($dataset),
            'min_support' => $this->minSupport
        ]);

        $this->transactions = $dataset;
        $total_transactions = count($dataset);

        if ($total_transactions === 0) {
            $this->logger->log('warning', 'Empty dataset provided');
            return [];
        }

        // Step 1: Find frequent 1-itemsets
        $frequent_1_itemsets = $this->findFrequent1Itemsets($dataset, $total_transactions);

        $this->logger->log('debug', 'Frequent 1-itemsets found', [
            'count' => count($frequent_1_itemsets)
        ]);

        // Step 2: Generate larger itemsets using Apriori principle
        $all_frequent_itemsets = [$frequent_1_itemsets];
        $k = 2;

        while ($k <= $this->maxItemsetSize) {
            // Generate candidate k-itemsets
            $candidates = $this->generateCandidates($all_frequent_itemsets[$k - 2], $k);

            if (empty($candidates)) {
                break;
            }

            // Count support for candidates
            $candidate_counts = $this->countCandidateSupport($candidates, $dataset);

            // Filter by minimum support
            $frequent_k_itemsets = $this->filterBySupport(
                $candidate_counts,
                $total_transactions,
                $this->minSupport
            );

            if (empty($frequent_k_itemsets)) {
                break;
            }

            $this->logger->log('debug', "Frequent {$k}-itemsets found", [
                'count' => count($frequent_k_itemsets)
            ]);

            $all_frequent_itemsets[] = $frequent_k_itemsets;
            $k++;
        }

        // Flatten and store
        $this->frequentItemsets = $this->flattenItemsets($all_frequent_itemsets);

        $this->logger->log('info', 'Frequent pattern mining completed', [
            'total_patterns' => count($this->frequentItemsets)
        ]);

        return $this->frequentItemsets;
    }

    /**
     * Find frequent 1-itemsets (single items that meet support threshold)
     *
     * @param array $dataset Dataset
     * @param int $total_transactions Total number of transactions
     * @return array Frequent 1-itemsets
     */
    private function findFrequent1Itemsets($dataset, $total_transactions) {
        $item_counts = [];

        // Count occurrences of each item
        foreach ($dataset as $transaction) {
            $items = is_array($transaction) ? $transaction : [$transaction];

            foreach ($items as $item) {
                if (!isset($item_counts[$item])) {
                    $item_counts[$item] = 0;
                }
                $item_counts[$item]++;
            }
        }

        // Filter by minimum support
        $frequent_1_itemsets = [];
        $min_count = $this->minSupport * $total_transactions;

        foreach ($item_counts as $item => $count) {
            if ($count >= $min_count) {
                $support = $count / $total_transactions;
                $frequent_1_itemsets[] = [
                    'itemset' => [$item],
                    'support' => $support,
                    'count' => $count
                ];
            }
        }

        return $frequent_1_itemsets;
    }

    /**
     * Generate candidate k-itemsets from frequent (k-1)-itemsets
     *
     * @param array $frequent_itemsets Frequent (k-1)-itemsets
     * @param int $k Size of itemsets to generate
     * @return array Candidate k-itemsets
     */
    private function generateCandidates($frequent_itemsets, $k) {
        $candidates = [];

        $count = count($frequent_itemsets);

        // Join step: merge pairs of (k-1)-itemsets
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $itemset1 = $frequent_itemsets[$i]['itemset'];
                $itemset2 = $frequent_itemsets[$j]['itemset'];

                // Check if first k-2 items are identical
                $common = array_slice($itemset1, 0, $k - 2);
                $prefix1 = array_slice($itemset1, 0, $k - 2);
                $prefix2 = array_slice($itemset2, 0, $k - 2);

                if ($prefix1 === $prefix2) {
                    // Merge to create candidate
                    $candidate = array_unique(array_merge($itemset1, $itemset2));
                    sort($candidate);

                    if (count($candidate) === $k) {
                        // Prune step: check if all (k-1)-subsets are frequent
                        if ($this->hasFrequentSubsets($candidate, $frequent_itemsets)) {
                            $candidates[] = $candidate;
                        }
                    }
                }
            }
        }

        // Remove duplicates
        $unique_candidates = [];
        foreach ($candidates as $candidate) {
            $key = implode(',', $candidate);
            $unique_candidates[$key] = $candidate;
        }

        return array_values($unique_candidates);
    }

    /**
     * Check if all (k-1)-subsets of candidate are frequent
     *
     * @param array $candidate Candidate itemset
     * @param array $frequent_itemsets Frequent (k-1)-itemsets
     * @return bool True if all subsets are frequent
     */
    private function hasFrequentSubsets($candidate, $frequent_itemsets) {
        // Extract all (k-1)-subsets
        $subsets = $this->generateSubsets($candidate, count($candidate) - 1);

        // Create lookup for frequent itemsets
        $frequent_lookup = [];
        foreach ($frequent_itemsets as $itemset_data) {
            $key = implode(',', $itemset_data['itemset']);
            $frequent_lookup[$key] = true;
        }

        // Check if all subsets are frequent
        foreach ($subsets as $subset) {
            $key = implode(',', $subset);
            if (!isset($frequent_lookup[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate all subsets of given size
     *
     * @param array $set Input set
     * @param int $size Subset size
     * @return array All subsets of given size
     */
    private function generateSubsets($set, $size) {
        $subsets = [];
        $n = count($set);

        if ($size > $n || $size < 0) {
            return [];
        }

        if ($size === 0) {
            return [[]];
        }

        if ($size === $n) {
            return [$set];
        }

        // Generate combinations
        $this->generateCombinations($set, $size, 0, [], $subsets);

        return $subsets;
    }

    /**
     * Generate combinations recursively
     *
     * @param array $set Input set
     * @param int $size Size of combinations
     * @param int $start Start index
     * @param array $current Current combination
     * @param array &$result Result array (by reference)
     * @return void
     */
    private function generateCombinations($set, $size, $start, $current, &$result) {
        if (count($current) === $size) {
            $result[] = $current;
            return;
        }

        for ($i = $start; $i < count($set); $i++) {
            $this->generateCombinations(
                $set,
                $size,
                $i + 1,
                array_merge($current, [$set[$i]]),
                $result
            );
        }
    }

    /**
     * Count support for candidate itemsets
     *
     * @param array $candidates Candidate itemsets
     * @param array $dataset Dataset
     * @return array Candidate counts
     */
    private function countCandidateSupport($candidates, $dataset) {
        $counts = [];

        foreach ($candidates as $candidate) {
            $counts[implode(',', $candidate)] = 0;
        }

        // Count occurrences in transactions
        foreach ($dataset as $transaction) {
            $transaction_items = is_array($transaction) ? $transaction : [$transaction];

            foreach ($candidates as $candidate) {
                if ($this->isSubset($candidate, $transaction_items)) {
                    $key = implode(',', $candidate);
                    $counts[$key]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Check if subset is contained in superset
     *
     * @param array $subset Subset
     * @param array $superset Superset
     * @return bool True if subset is contained in superset
     */
    private function isSubset($subset, $superset) {
        foreach ($subset as $item) {
            if (!in_array($item, $superset)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Filter candidates by minimum support
     *
     * @param array $candidate_counts Candidate counts
     * @param int $total_transactions Total transactions
     * @param float $min_support Minimum support threshold
     * @return array Frequent itemsets
     */
    private function filterBySupport($candidate_counts, $total_transactions, $min_support) {
        $frequent = [];
        $min_count = $min_support * $total_transactions;

        foreach ($candidate_counts as $itemset_key => $count) {
            if ($count >= $min_count) {
                $itemset = explode(',', $itemset_key);
                $support = $count / $total_transactions;

                $frequent[] = [
                    'itemset' => $itemset,
                    'support' => $support,
                    'count' => $count
                ];
            }
        }

        return $frequent;
    }

    /**
     * Flatten itemsets array
     *
     * @param array $itemsets_by_size Itemsets grouped by size
     * @return array Flattened itemsets
     */
    private function flattenItemsets($itemsets_by_size) {
        $flattened = [];

        foreach ($itemsets_by_size as $itemsets) {
            foreach ($itemsets as $itemset_data) {
                $flattened[] = $itemset_data;
            }
        }

        return $flattened;
    }

    /**
     * Generate association rules from frequent itemsets
     *
     * @param array $frequent_itemsets Frequent itemsets (if null, use stored)
     * @return array Association rules
     */
    public function generateAssociationRules($frequent_itemsets = null) {
        if ($frequent_itemsets === null) {
            $frequent_itemsets = $this->frequentItemsets;
        }

        $this->logger->log('info', 'Generating association rules', [
            'itemsets' => count($frequent_itemsets)
        ]);

        $rules = [];

        // Only consider itemsets with 2+ items
        foreach ($frequent_itemsets as $itemset_data) {
            $itemset = $itemset_data['itemset'];
            $itemset_support = $itemset_data['support'];

            if (count($itemset) < 2) {
                continue;
            }

            // Generate all non-empty subsets as antecedents
            for ($i = 1; $i < count($itemset); $i++) {
                $antecedent_subsets = $this->generateSubsets($itemset, $i);

                foreach ($antecedent_subsets as $antecedent) {
                    $consequent = array_diff($itemset, $antecedent);

                    if (empty($consequent)) {
                        continue;
                    }

                    // Find support of antecedent
                    $antecedent_support = $this->findItemsetSupport($antecedent, $frequent_itemsets);

                    if ($antecedent_support > 0) {
                        // Calculate confidence
                        $confidence = $itemset_support / $antecedent_support;

                        if ($confidence >= $this->minConfidence) {
                            // Calculate lift
                            $consequent_support = $this->findItemsetSupport($consequent, $frequent_itemsets);
                            $lift = $consequent_support > 0 ? $confidence / $consequent_support : 0;

                            $rules[] = [
                                'antecedent' => array_values($antecedent),
                                'consequent' => array_values($consequent),
                                'support' => $itemset_support,
                                'confidence' => $confidence,
                                'lift' => $lift
                            ];
                        }
                    }
                }
            }
        }

        $this->logger->log('info', 'Association rules generated', [
            'rules' => count($rules)
        ]);

        return $rules;
    }

    /**
     * Find support of an itemset
     *
     * @param array $itemset Itemset
     * @param array $frequent_itemsets Frequent itemsets
     * @return float Support value
     */
    private function findItemsetSupport($itemset, $frequent_itemsets) {
        sort($itemset);
        $itemset_key = implode(',', $itemset);

        foreach ($frequent_itemsets as $itemset_data) {
            $candidate = $itemset_data['itemset'];
            sort($candidate);
            $candidate_key = implode(',', $candidate);

            if ($candidate_key === $itemset_key) {
                return $itemset_data['support'];
            }
        }

        return 0.0;
    }

    /**
     * Mine sequential patterns from ordered transactions
     *
     * @param array $sequences Array of sequences (ordered transactions)
     * @return array Sequential patterns
     */
    public function mineSequentialPatterns($sequences) {
        $this->logger->log('info', 'Mining sequential patterns', [
            'sequences' => count($sequences)
        ]);

        $sequential_patterns = [];

        // Find frequent sequences of length 1
        $frequent_1_sequences = $this->findFrequentSequences($sequences, 1);

        // Extend to longer sequences
        $k = 2;
        $all_frequent = [$frequent_1_sequences];

        while ($k <= $this->maxItemsetSize) {
            $candidates = $this->generateSequenceCandidates($all_frequent[$k - 2]);

            if (empty($candidates)) {
                break;
            }

            $frequent_k = $this->findFrequentSequencesFromCandidates($sequences, $candidates);

            if (empty($frequent_k)) {
                break;
            }

            $all_frequent[] = $frequent_k;
            $k++;
        }

        $sequential_patterns = $this->flattenItemsets($all_frequent);

        $this->logger->log('info', 'Sequential pattern mining completed', [
            'patterns' => count($sequential_patterns)
        ]);

        return $sequential_patterns;
    }

    /**
     * Find frequent sequences of given length
     *
     * @param array $sequences Input sequences
     * @param int $length Sequence length
     * @return array Frequent sequences
     */
    private function findFrequentSequences($sequences, $length) {
        $sequence_counts = [];

        foreach ($sequences as $sequence) {
            // Extract all subsequences of given length
            $subsequences = $this->extractSubsequences($sequence, $length);

            foreach ($subsequences as $subseq) {
                $key = implode('->', $subseq);
                if (!isset($sequence_counts[$key])) {
                    $sequence_counts[$key] = 0;
                }
                $sequence_counts[$key]++;
            }
        }

        // Filter by support
        $frequent = [];
        $min_count = $this->minSupport * count($sequences);

        foreach ($sequence_counts as $seq_key => $count) {
            if ($count >= $min_count) {
                $support = $count / count($sequences);
                $frequent[] = [
                    'itemset' => explode('->', $seq_key),
                    'support' => $support,
                    'count' => $count
                ];
            }
        }

        return $frequent;
    }

    /**
     * Extract all subsequences of given length
     *
     * @param array $sequence Input sequence
     * @param int $length Subsequence length
     * @return array Subsequences
     */
    private function extractSubsequences($sequence, $length) {
        $subsequences = [];
        $seq_length = count($sequence);

        for ($i = 0; $i <= $seq_length - $length; $i++) {
            $subsequences[] = array_slice($sequence, $i, $length);
        }

        return $subsequences;
    }

    /**
     * Generate candidate sequences
     *
     * @param array $frequent_sequences Frequent sequences
     * @return array Candidate sequences
     */
    private function generateSequenceCandidates($frequent_sequences) {
        $candidates = [];

        foreach ($frequent_sequences as $seq_data) {
            $sequence = $seq_data['itemset'];

            // Try appending each frequent 1-item
            foreach ($frequent_sequences as $item_data) {
                if (count($item_data['itemset']) === 1) {
                    $candidate = array_merge($sequence, $item_data['itemset']);
                    $candidates[] = $candidate;
                }
            }
        }

        return $candidates;
    }

    /**
     * Find frequent sequences from candidates
     *
     * @param array $sequences Input sequences
     * @param array $candidates Candidate sequences
     * @return array Frequent sequences
     */
    private function findFrequentSequencesFromCandidates($sequences, $candidates) {
        $counts = [];

        foreach ($candidates as $candidate) {
            $counts[implode('->', $candidate)] = 0;
        }

        foreach ($sequences as $sequence) {
            foreach ($candidates as $candidate) {
                if ($this->isSubsequence($candidate, $sequence)) {
                    $key = implode('->', $candidate);
                    $counts[$key]++;
                }
            }
        }

        // Filter by support
        $frequent = [];
        $min_count = $this->minSupport * count($sequences);

        foreach ($counts as $seq_key => $count) {
            if ($count >= $min_count) {
                $support = $count / count($sequences);
                $frequent[] = [
                    'itemset' => explode('->', $seq_key),
                    'support' => $support,
                    'count' => $count
                ];
            }
        }

        return $frequent;
    }

    /**
     * Check if needle is a subsequence of haystack
     *
     * @param array $needle Subsequence to find
     * @param array $haystack Sequence to search in
     * @return bool True if needle is subsequence of haystack
     */
    private function isSubsequence($needle, $haystack) {
        $needle_len = count($needle);
        $haystack_len = count($haystack);

        if ($needle_len > $haystack_len) {
            return false;
        }

        $j = 0; // Needle index

        for ($i = 0; $i < $haystack_len && $j < $needle_len; $i++) {
            if ($haystack[$i] === $needle[$j]) {
                $j++;
            }
        }

        return $j === $needle_len;
    }

    /**
     * Calculate pattern quality metrics
     *
     * @param array $pattern Pattern to evaluate
     * @return array Quality metrics
     */
    public function calculatePatternQuality($pattern) {
        $metrics = [
            'support' => $pattern['support'] ?? 0,
            'confidence' => $pattern['confidence'] ?? 0,
            'lift' => $pattern['lift'] ?? 0,
            'coverage' => 0,
            'novelty' => 0,
            'significance' => 0
        ];

        // Coverage: how many transactions contain this pattern
        if (isset($pattern['count'])) {
            $metrics['coverage'] = $pattern['count'] / max(count($this->transactions), 1);
        }

        // Novelty: inverse of support (rare patterns are more novel)
        $metrics['novelty'] = 1 - $metrics['support'];

        // Significance: chi-square test (simplified)
        $metrics['significance'] = $this->calculateSignificance($pattern);

        return $metrics;
    }

    /**
     * Calculate statistical significance of pattern
     *
     * @param array $pattern Pattern
     * @return float Significance score
     */
    private function calculateSignificance($pattern) {
        // Simplified chi-square calculation
        // In production, use proper statistical test

        $support = $pattern['support'] ?? 0;
        $expected = 0.1; // Expected random support

        if ($expected === 0) {
            return 0;
        }

        $chi_square = pow($support - $expected, 2) / $expected;

        return min(1.0, $chi_square / 10); // Normalize
    }

    /**
     * Set minimum support threshold
     *
     * @param float $min_support Minimum support (0-1)
     * @return void
     */
    public function setMinSupport($min_support) {
        $this->minSupport = max(0.0, min(1.0, $min_support));
    }

    /**
     * Set minimum confidence threshold
     *
     * @param float $min_confidence Minimum confidence (0-1)
     * @return void
     */
    public function setMinConfidence($min_confidence) {
        $this->minConfidence = max(0.0, min(1.0, $min_confidence));
    }

    /**
     * Get mining statistics
     *
     * @return array Statistics
     */
    public function getStatistics() {
        return [
            'min_support' => $this->minSupport,
            'min_confidence' => $this->minConfidence,
            'max_itemset_size' => $this->maxItemsetSize,
            'transactions_count' => count($this->transactions),
            'frequent_itemsets_count' => count($this->frequentItemsets)
        ];
    }
}
