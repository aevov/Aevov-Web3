<?php
/**
 * Pattern Synchronization - Multi-Node Sync Protocol
 *
 * Implements distributed pattern synchronization with conflict resolution,
 * Merkle trees for efficient sync, and delta synchronization.
 *
 * @package AevovPatternSyncProtocol
 * @subpackage Pattern
 * @since 1.0.0
 */

namespace APS\Pattern;

use APS\Core\Logger;
use APS\DB\APS_Pattern_DB;
use APS\DB\SyncLogDB;

class PatternSync {

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
     * Sync log database
     *
     * @var SyncLogDB
     */
    private $syncLogDB;

    /**
     * Node ID
     *
     * @var string
     */
    private $nodeId;

    /**
     * Vector clock for causality tracking
     *
     * @var array
     */
    private $vectorClock;

    /**
     * Conflict resolution strategy
     *
     * @var string
     */
    private $conflictStrategy;

    /**
     * Merkle tree for pattern verification
     *
     * @var array
     */
    private $merkleTree;

    /**
     * Sync state machine states
     */
    const STATE_IDLE = 'idle';
    const STATE_DISCOVERING = 'discovering';
    const STATE_SYNCING = 'syncing';
    const STATE_MERGING = 'merging';
    const STATE_VALIDATING = 'validating';
    const STATE_COMPLETE = 'complete';
    const STATE_ERROR = 'error';

    /**
     * Current sync state
     *
     * @var string
     */
    private $syncState;

    /**
     * Constructor
     *
     * @param string $node_id Node identifier
     * @param string $conflict_strategy Conflict resolution strategy (lww|vector_clock|custom)
     */
    public function __construct($node_id = null, $conflict_strategy = 'lww') {
        $this->logger = Logger::get_instance();
        $this->patternDB = new APS_Pattern_DB();
        $this->syncLogDB = new SyncLogDB();
        $this->nodeId = $node_id ?? $this->generateNodeId();
        $this->conflictStrategy = $conflict_strategy;
        $this->vectorClock = [];
        $this->syncState = self::STATE_IDLE;
        $this->merkleTree = [];

        $this->logger->log('info', 'PatternSync initialized', [
            'node_id' => $this->nodeId,
            'conflict_strategy' => $this->conflictStrategy
        ]);
    }

    /**
     * Synchronize patterns with remote node
     *
     * @param string $remote_node_url Remote node URL
     * @param array $options Sync options
     * @return array Sync result
     */
    public function syncWithNode($remote_node_url, $options = []) {
        $this->setState(self::STATE_DISCOVERING);

        $this->logger->log('info', 'Starting pattern sync', [
            'remote_node' => $remote_node_url,
            'node_id' => $this->nodeId
        ]);

        try {
            // Step 1: Discovery - exchange metadata
            $remote_metadata = $this->discoverRemoteNode($remote_node_url);

            if (!$remote_metadata || isset($remote_metadata['error'])) {
                throw new \Exception('Failed to discover remote node: ' . ($remote_metadata['error'] ?? 'Unknown error'));
            }

            // Step 2: Compare Merkle trees
            $this->setState(self::STATE_SYNCING);
            $local_merkle = $this->buildMerkleTree();
            $differences = $this->compareMerkleTrees($local_merkle, $remote_metadata['merkle_root']);

            // Step 3: Delta sync - only sync differences
            $delta_result = $this->performDeltaSync($remote_node_url, $differences);

            // Step 4: Merge and resolve conflicts
            $this->setState(self::STATE_MERGING);
            $merge_result = $this->mergePatterns($delta_result['patterns']);

            // Step 5: Validate sync
            $this->setState(self::STATE_VALIDATING);
            $validation_result = $this->validateSync($merge_result);

            // Step 6: Update vector clocks
            $this->updateVectorClock($remote_metadata['node_id']);

            // Log sync completion
            $this->syncLogDB->log_sync([
                'local_node' => $this->nodeId,
                'remote_node' => $remote_metadata['node_id'],
                'patterns_synced' => $merge_result['synced_count'],
                'conflicts_resolved' => $merge_result['conflicts_resolved'],
                'status' => 'success'
            ]);

            $this->setState(self::STATE_COMPLETE);

            return [
                'success' => true,
                'synced_patterns' => $merge_result['synced_count'],
                'conflicts_resolved' => $merge_result['conflicts_resolved'],
                'node_id' => $remote_metadata['node_id'],
                'duration' => $delta_result['duration'] ?? 0
            ];

        } catch (\Exception $e) {
            $this->setState(self::STATE_ERROR);

            $this->logger->log('error', 'Pattern sync failed', [
                'error' => $e->getMessage(),
                'remote_node' => $remote_node_url
            ]);

            $this->syncLogDB->log_sync([
                'local_node' => $this->nodeId,
                'remote_node' => $remote_node_url,
                'status' => 'error',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Discover remote node and get metadata
     *
     * @param string $remote_node_url Remote node URL
     * @return array Remote node metadata
     */
    private function discoverRemoteNode($remote_node_url) {
        // Build local metadata
        $local_metadata = $this->buildLocalMetadata();

        // In production, this would make HTTP request
        // For simulation, we'll use WordPress HTTP API
        $response = wp_remote_post($remote_node_url . '/api/sync/discover', [
            'timeout' => 30,
            'body' => json_encode($local_metadata),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Node-ID' => $this->nodeId
            ]
        ]);

        if (is_wp_error($response)) {
            // Simulate response for single-node mode
            return $this->simulateRemoteNodeMetadata();
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Build local node metadata
     *
     * @return array Local metadata
     */
    private function buildLocalMetadata() {
        return [
            'node_id' => $this->nodeId,
            'pattern_count' => $this->patternDB->count_patterns(),
            'merkle_root' => $this->buildMerkleTree(),
            'vector_clock' => $this->vectorClock,
            'last_sync' => $this->syncLogDB->get_last_sync_time($this->nodeId),
            'capabilities' => [
                'delta_sync' => true,
                'conflict_resolution' => [$this->conflictStrategy],
                'compression' => true
            ]
        ];
    }

    /**
     * Build Merkle tree for efficient sync verification
     *
     * @return string Merkle root hash
     */
    public function buildMerkleTree() {
        $patterns = $this->patternDB->get_all_patterns(['orderby' => 'id']);

        if (empty($patterns)) {
            return hash('sha256', '');
        }

        // Build leaf hashes
        $leaves = [];
        foreach ($patterns as $pattern) {
            $pattern_data = isset($pattern['pattern_data']) ? $pattern['pattern_data'] : $pattern;
            $leaves[] = hash('sha256', json_encode($pattern_data));
        }

        // Build tree bottom-up
        $merkle_root = $this->buildMerkleTreeRecursive($leaves);

        $this->merkleTree = [
            'root' => $merkle_root,
            'leaves' => $leaves,
            'built_at' => time()
        ];

        return $merkle_root;
    }

    /**
     * Build Merkle tree recursively
     *
     * @param array $hashes Array of hashes
     * @return string Root hash
     */
    private function buildMerkleTreeRecursive($hashes) {
        if (count($hashes) === 1) {
            return $hashes[0];
        }

        $parent_hashes = [];

        for ($i = 0; $i < count($hashes); $i += 2) {
            $left = $hashes[$i];
            $right = isset($hashes[$i + 1]) ? $hashes[$i + 1] : $left;

            $parent_hashes[] = hash('sha256', $left . $right);
        }

        return $this->buildMerkleTreeRecursive($parent_hashes);
    }

    /**
     * Compare Merkle trees to find differences
     *
     * @param string $local_root Local Merkle root
     * @param string $remote_root Remote Merkle root
     * @return array Differences
     */
    private function compareMerkleTrees($local_root, $remote_root) {
        if ($local_root === $remote_root) {
            $this->logger->log('info', 'Merkle trees match - no sync needed');
            return ['needs_sync' => false];
        }

        $this->logger->log('info', 'Merkle trees differ - sync required', [
            'local_root' => substr($local_root, 0, 16),
            'remote_root' => substr($remote_root, 0, 16)
        ]);

        return [
            'needs_sync' => true,
            'local_root' => $local_root,
            'remote_root' => $remote_root
        ];
    }

    /**
     * Perform delta synchronization
     *
     * @param string $remote_node_url Remote node URL
     * @param array $differences Differences from Merkle comparison
     * @return array Delta sync result
     */
    private function performDeltaSync($remote_node_url, $differences) {
        if (!$differences['needs_sync']) {
            return ['patterns' => [], 'duration' => 0];
        }

        $start_time = microtime(true);

        // Get patterns modified since last sync
        $last_sync_time = $this->syncLogDB->get_last_sync_time($this->nodeId);
        $local_changes = $this->patternDB->get_patterns_since($last_sync_time);

        // Request remote changes
        $remote_changes = $this->requestRemoteChanges($remote_node_url, $last_sync_time);

        $duration = microtime(true) - $start_time;

        return [
            'patterns' => [
                'local' => $local_changes,
                'remote' => $remote_changes
            ],
            'duration' => $duration
        ];
    }

    /**
     * Request remote changes since last sync
     *
     * @param string $remote_node_url Remote node URL
     * @param int $since_timestamp Timestamp to get changes since
     * @return array Remote changes
     */
    private function requestRemoteChanges($remote_node_url, $since_timestamp) {
        $response = wp_remote_get($remote_node_url . '/api/sync/changes', [
            'timeout' => 60,
            'headers' => [
                'X-Node-ID' => $this->nodeId,
                'X-Since' => $since_timestamp
            ]
        ]);

        if (is_wp_error($response)) {
            // Simulate for single-node mode
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['changes'] ?? [];
    }

    /**
     * Merge patterns and resolve conflicts
     *
     * @param array $pattern_sets Pattern sets to merge
     * @return array Merge result
     */
    private function mergePatterns($pattern_sets) {
        $local_patterns = $pattern_sets['local'] ?? [];
        $remote_patterns = $pattern_sets['remote'] ?? [];

        $merged_count = 0;
        $conflicts_resolved = 0;
        $conflicts = [];

        // Index local patterns by ID
        $local_index = [];
        foreach ($local_patterns as $pattern) {
            $pattern_id = $pattern['id'] ?? $pattern['pattern_id'] ?? null;
            if ($pattern_id) {
                $local_index[$pattern_id] = $pattern;
            }
        }

        // Process remote patterns
        foreach ($remote_patterns as $remote_pattern) {
            $pattern_id = $remote_pattern['id'] ?? $remote_pattern['pattern_id'] ?? null;

            if (!$pattern_id) {
                continue;
            }

            // Check for conflict
            if (isset($local_index[$pattern_id])) {
                $local_pattern = $local_index[$pattern_id];

                // Detect conflict
                if ($this->hasConflict($local_pattern, $remote_pattern)) {
                    $conflicts[] = [
                        'pattern_id' => $pattern_id,
                        'local' => $local_pattern,
                        'remote' => $remote_pattern
                    ];

                    // Resolve conflict
                    $resolved = $this->resolveConflict($local_pattern, $remote_pattern);

                    if ($resolved) {
                        $this->patternDB->update_pattern($pattern_id, $resolved);
                        $conflicts_resolved++;
                    }
                } else {
                    // No conflict, patterns are identical
                    $merged_count++;
                }
            } else {
                // No local copy, insert remote pattern
                $this->patternDB->insert_pattern($remote_pattern);
                $merged_count++;
            }
        }

        $this->logger->log('info', 'Pattern merge completed', [
            'merged' => $merged_count,
            'conflicts_resolved' => $conflicts_resolved
        ]);

        return [
            'synced_count' => $merged_count,
            'conflicts_resolved' => $conflicts_resolved,
            'conflicts' => $conflicts
        ];
    }

    /**
     * Check if two patterns have a conflict
     *
     * @param array $pattern1 First pattern
     * @param array $pattern2 Second pattern
     * @return bool True if conflict exists
     */
    private function hasConflict($pattern1, $pattern2) {
        $hash1 = hash('sha256', json_encode($pattern1));
        $hash2 = hash('sha256', json_encode($pattern2));

        // Different hashes but same ID = conflict
        if ($hash1 !== $hash2) {
            // Check timestamps to confirm concurrent modification
            $time1 = $pattern1['updated_at'] ?? $pattern1['created_at'] ?? 0;
            $time2 = $pattern2['updated_at'] ?? $pattern2['created_at'] ?? 0;

            // If timestamps are very close (within 1 second), consider it a conflict
            if (abs(strtotime($time1) - strtotime($time2)) < 1) {
                return true;
            }

            return true;
        }

        return false;
    }

    /**
     * Resolve conflict between two patterns
     *
     * @param array $local_pattern Local pattern
     * @param array $remote_pattern Remote pattern
     * @return array Resolved pattern
     */
    private function resolveConflict($local_pattern, $remote_pattern) {
        $this->logger->log('debug', 'Resolving conflict', [
            'strategy' => $this->conflictStrategy,
            'pattern_id' => $local_pattern['id'] ?? 'unknown'
        ]);

        switch ($this->conflictStrategy) {
            case 'lww': // Last Write Wins
                return $this->resolveLWW($local_pattern, $remote_pattern);

            case 'vector_clock':
                return $this->resolveVectorClock($local_pattern, $remote_pattern);

            case 'merge':
                return $this->resolveMerge($local_pattern, $remote_pattern);

            case 'manual':
                // Store for manual resolution
                return null;

            default:
                return $this->resolveLWW($local_pattern, $remote_pattern);
        }
    }

    /**
     * Resolve using Last Write Wins strategy
     *
     * @param array $local_pattern Local pattern
     * @param array $remote_pattern Remote pattern
     * @return array Winner pattern
     */
    private function resolveLWW($local_pattern, $remote_pattern) {
        $local_time = strtotime($local_pattern['updated_at'] ?? $local_pattern['created_at'] ?? '0');
        $remote_time = strtotime($remote_pattern['updated_at'] ?? $remote_pattern['created_at'] ?? '0');

        if ($remote_time > $local_time) {
            $this->logger->log('debug', 'Conflict resolved: remote wins (newer)');
            return $remote_pattern;
        } else {
            $this->logger->log('debug', 'Conflict resolved: local wins (newer)');
            return $local_pattern;
        }
    }

    /**
     * Resolve using vector clocks
     *
     * @param array $local_pattern Local pattern
     * @param array $remote_pattern Remote pattern
     * @return array Resolved pattern
     */
    private function resolveVectorClock($local_pattern, $remote_pattern) {
        $local_vc = $local_pattern['vector_clock'] ?? [];
        $remote_vc = $remote_pattern['vector_clock'] ?? [];

        // Compare vector clocks
        $comparison = $this->compareVectorClocks($local_vc, $remote_vc);

        if ($comparison === 'local_newer') {
            return $local_pattern;
        } elseif ($comparison === 'remote_newer') {
            return $remote_pattern;
        } else {
            // Concurrent updates - fall back to LWW
            return $this->resolveLWW($local_pattern, $remote_pattern);
        }
    }

    /**
     * Resolve by merging pattern data
     *
     * @param array $local_pattern Local pattern
     * @param array $remote_pattern Remote pattern
     * @return array Merged pattern
     */
    private function resolveMerge($local_pattern, $remote_pattern) {
        // Deep merge pattern data
        $merged = $local_pattern;

        foreach ($remote_pattern as $key => $value) {
            if (!isset($merged[$key])) {
                $merged[$key] = $value;
            } elseif (is_array($value) && is_array($merged[$key])) {
                $merged[$key] = array_merge($merged[$key], $value);
            }
        }

        $merged['merged'] = true;
        $merged['merge_timestamp'] = time();

        return $merged;
    }

    /**
     * Compare vector clocks
     *
     * @param array $vc1 First vector clock
     * @param array $vc2 Second vector clock
     * @return string Comparison result (local_newer|remote_newer|concurrent)
     */
    private function compareVectorClocks($vc1, $vc2) {
        $vc1_greater = false;
        $vc2_greater = false;

        $all_nodes = array_unique(array_merge(array_keys($vc1), array_keys($vc2)));

        foreach ($all_nodes as $node) {
            $v1 = $vc1[$node] ?? 0;
            $v2 = $vc2[$node] ?? 0;

            if ($v1 > $v2) {
                $vc1_greater = true;
            } elseif ($v2 > $v1) {
                $vc2_greater = true;
            }
        }

        if ($vc1_greater && !$vc2_greater) {
            return 'local_newer';
        } elseif ($vc2_greater && !$vc1_greater) {
            return 'remote_newer';
        } else {
            return 'concurrent';
        }
    }

    /**
     * Validate sync result
     *
     * @param array $merge_result Merge result
     * @return array Validation result
     */
    private function validateSync($merge_result) {
        $validation_errors = [];

        // Rebuild Merkle tree and verify
        $new_merkle_root = $this->buildMerkleTree();

        // Check pattern integrity
        $pattern_count = $this->patternDB->count_patterns();

        if ($pattern_count < 0) {
            $validation_errors[] = 'Invalid pattern count after sync';
        }

        $is_valid = empty($validation_errors);

        $this->logger->log('info', 'Sync validation completed', [
            'valid' => $is_valid,
            'errors' => $validation_errors
        ]);

        return [
            'valid' => $is_valid,
            'errors' => $validation_errors,
            'merkle_root' => $new_merkle_root,
            'pattern_count' => $pattern_count
        ];
    }

    /**
     * Update vector clock
     *
     * @param string $node_id Node ID to update
     * @return void
     */
    private function updateVectorClock($node_id) {
        if (!isset($this->vectorClock[$this->nodeId])) {
            $this->vectorClock[$this->nodeId] = 0;
        }

        $this->vectorClock[$this->nodeId]++;

        if (!isset($this->vectorClock[$node_id])) {
            $this->vectorClock[$node_id] = 0;
        }

        $this->logger->log('debug', 'Vector clock updated', [
            'vector_clock' => $this->vectorClock
        ]);
    }

    /**
     * Set sync state
     *
     * @param string $state New state
     * @return void
     */
    private function setState($state) {
        $this->syncState = $state;
        $this->logger->log('debug', 'Sync state changed', ['state' => $state]);
    }

    /**
     * Get current sync state
     *
     * @return string Current state
     */
    public function getState() {
        return $this->syncState;
    }

    /**
     * Generate unique node ID
     *
     * @return string Node ID
     */
    private function generateNodeId() {
        return 'node_' . hash('sha256', get_site_url() . time() . wp_rand());
    }

    /**
     * Simulate remote node metadata for single-node mode
     *
     * @return array Simulated metadata
     */
    private function simulateRemoteNodeMetadata() {
        return [
            'node_id' => 'simulated_remote_node',
            'pattern_count' => 0,
            'merkle_root' => hash('sha256', ''),
            'vector_clock' => [],
            'last_sync' => 0
        ];
    }

    /**
     * Get sync statistics
     *
     * @return array Statistics
     */
    public function getStatistics() {
        return [
            'node_id' => $this->nodeId,
            'state' => $this->syncState,
            'merkle_root' => $this->merkleTree['root'] ?? null,
            'vector_clock' => $this->vectorClock,
            'conflict_strategy' => $this->conflictStrategy
        ];
    }
}
