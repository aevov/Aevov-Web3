<?php
/**
 * Consensus Mechanism for AevovPatternSyncProtocol
 *
 * Implements Byzantine Fault Tolerance (BFT) consensus algorithm for
 * distributed pattern validation and synchronization.
 *
 * @package AevovPatternSyncProtocol
 * @subpackage Consensus
 * @since 1.0.0
 */

namespace APS\Consensus;

use APS\DB\APS_Pattern_DB;

class ConsensusMechanism {

    /**
     * Database handler
     *
     * @var APS_Pattern_DB
     */
    private $pattern_db;

    /**
     * WordPress database object
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Minimum number of nodes required for consensus
     *
     * @var int
     */
    private $min_nodes = 3;

    /**
     * Consensus threshold percentage (Byzantine Fault Tolerance requires >66%)
     *
     * @var float
     */
    private $consensus_threshold = 0.67;

    /**
     * Timeout for consensus operations (in seconds)
     *
     * @var int
     */
    private $consensus_timeout = 30;

    /**
     * Whether running in production mode
     *
     * @var bool
     */
    private $is_production = false;

    /**
     * Network nodes table
     *
     * @var string
     */
    private $nodes_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->pattern_db = new APS_Pattern_DB();
        $this->nodes_table = $wpdb->prefix . 'aps_network_nodes';

        // Detect production environment
        $this->is_production = $this->detect_production_environment();

        // Create consensus table if needed
        $this->create_tables();
    }

    /**
     * Detect if running in production server environment
     *
     * @return bool
     */
    private function detect_production_environment() {
        if (getenv('AEVOV_ENV') === 'production' || getenv('WP_ENV') === 'production') {
            return true;
        }
        if (file_exists('/.dockerenv') || getenv('KUBERNETES_SERVICE_HOST')) {
            return true;
        }
        if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'production') {
            return true;
        }
        return false;
    }

    /**
     * Create database tables for consensus tracking
     *
     * @return void
     */
    public function create_tables() {
        $table_name = $this->wpdb->prefix . 'aps_consensus';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            pattern_id BIGINT UNSIGNED NOT NULL,
            consensus_hash VARCHAR(64) NOT NULL,
            participants_count INT NOT NULL DEFAULT 0,
            agreement_percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            status ENUM('pending', 'reached', 'failed') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            timeout_at DATETIME NULL,
            metadata LONGTEXT NULL,
            INDEX pattern_id (pattern_id),
            INDEX status (status),
            INDEX consensus_hash (consensus_hash),
            INDEX timeout_at (timeout_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Create consensus votes table
        $votes_table = $this->wpdb->prefix . 'aps_consensus_votes';
        $sql_votes = "CREATE TABLE IF NOT EXISTS {$votes_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            consensus_id BIGINT UNSIGNED NOT NULL,
            node_id VARCHAR(255) NOT NULL,
            vote ENUM('approve', 'reject', 'abstain') NOT NULL,
            vote_hash VARCHAR(64) NOT NULL,
            signature VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX consensus_id (consensus_id),
            INDEX node_id (node_id),
            UNIQUE KEY unique_vote (consensus_id, node_id)
        ) {$charset_collate};";

        dbDelta($sql_votes);
    }

    /**
     * Validate pattern against consensus rules
     *
     * @param array $pattern_data Pattern data to validate
     * @return array Validation result with consensus status
     */
    public function validate_pattern($pattern_data) {
        // Validate input
        if (empty($pattern_data['id']) || empty($pattern_data['data'])) {
            return [
                'valid' => false,
                'error' => 'Invalid pattern data structure'
            ];
        }

        $pattern_id = $pattern_data['id'];

        // Generate consensus hash
        $consensus_hash = $this->generate_consensus_hash($pattern_data);

        // Create consensus record
        $consensus_id = $this->create_consensus_record($pattern_id, $consensus_hash);

        // Initiate consensus process
        $result = $this->reach_consensus([
            'pattern_id' => $pattern_id,
            'consensus_id' => $consensus_id,
            'pattern_data' => $pattern_data
        ]);

        return $result;
    }

    /**
     * Reach consensus among network participants
     *
     * @param array $contributors Array of contributor nodes
     * @return array Consensus result
     */
    public function reach_consensus($contributors) {
        $pattern_id = $contributors['pattern_id'];
        $consensus_id = $contributors['consensus_id'];

        // Collect votes from participants
        $votes = $this->collect_votes($consensus_id, $contributors);

        // Calculate consensus
        $total_votes = count($votes);
        $approve_votes = count(array_filter($votes, function($vote) {
            return $vote['vote'] === 'approve';
        }));

        $agreement_percentage = $total_votes > 0 ? ($approve_votes / $total_votes) * 100 : 0;

        // Check if consensus is reached
        $consensus_reached = $agreement_percentage >= ($this->consensus_threshold * 100);
        $status = $consensus_reached ? 'reached' : 'failed';

        // Update consensus record
        $this->update_consensus_status(
            $consensus_id,
            $status,
            $total_votes,
            $agreement_percentage
        );

        // Trigger WordPress action
        do_action('aps_consensus_' . $status, $pattern_id, $consensus_id, $agreement_percentage);

        return [
            'consensus_reached' => $consensus_reached,
            'consensus_id' => $consensus_id,
            'participants' => $total_votes,
            'agreement_percentage' => $agreement_percentage,
            'threshold' => $this->consensus_threshold * 100,
            'status' => $status
        ];
    }

    /**
     * Verify proof of contribution
     *
     * @param array $proof_data Proof data to verify
     * @return bool True if proof is valid
     */
    public function verify_proof($proof_data) {
        if (empty($proof_data['proof_hash']) || empty($proof_data['pattern_id'])) {
            return false;
        }

        // Verify proof hash matches pattern
        $pattern_id = $proof_data['pattern_id'];
        $proof_hash = $proof_data['proof_hash'];

        // Check if proof exists in consensus votes
        $votes_table = $this->wpdb->prefix . 'aps_consensus_votes';
        $vote = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$votes_table} WHERE vote_hash = %s LIMIT 1",
            $proof_hash
        ));

        if (!$vote) {
            return false;
        }

        // Verify signature if provided
        if (!empty($proof_data['signature']) && !empty($vote->signature)) {
            if ($proof_data['signature'] !== $vote->signature) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate consensus threshold based on network size
     *
     * @param int $network_size Number of nodes in network
     * @return float Consensus threshold percentage
     */
    public function calculate_threshold($network_size = null) {
        // Byzantine Fault Tolerance: (n > 3f) where f is number of faulty nodes
        // Requires >66% agreement for safety

        if ($network_size === null) {
            return $this->consensus_threshold;
        }

        // For small networks, require higher threshold
        if ($network_size < $this->min_nodes) {
            return 1.0; // 100% for networks smaller than minimum
        }

        // For larger networks, can use standard BFT threshold
        if ($network_size >= 10) {
            return 0.67; // Standard BFT threshold
        }

        // For medium networks (3-9 nodes), use graduated threshold
        $threshold = 0.67 + (0.33 * (10 - $network_size) / 7);
        return min($threshold, 1.0);
    }

    /**
     * Handle consensus dispute resolution
     *
     * @param array $dispute_data Dispute information
     * @return array Resolution result
     */
    public function handle_dispute($dispute_data) {
        $consensus_id = $dispute_data['consensus_id'] ?? null;

        if (!$consensus_id) {
            return [
                'resolved' => false,
                'error' => 'Invalid consensus ID'
            ];
        }

        // Get consensus record
        $consensus = $this->get_consensus_record($consensus_id);

        if (!$consensus) {
            return [
                'resolved' => false,
                'error' => 'Consensus record not found'
            ];
        }

        // If consensus was reached, no dispute
        if ($consensus->status === 'reached') {
            return [
                'resolved' => true,
                'action' => 'none',
                'message' => 'Consensus already reached'
            ];
        }

        // Re-initiate consensus with stricter validation
        $pattern = $this->pattern_db->get_pattern($consensus->pattern_id);

        if (!$pattern) {
            return [
                'resolved' => false,
                'error' => 'Pattern not found'
            ];
        }

        // Create new consensus round
        $new_consensus_hash = $this->generate_consensus_hash([
            'id' => $consensus->pattern_id,
            'data' => $pattern,
            'dispute_round' => true
        ]);

        $new_consensus_id = $this->create_consensus_record(
            $consensus->pattern_id,
            $new_consensus_hash
        );

        return [
            'resolved' => true,
            'action' => 'new_round',
            'consensus_id' => $new_consensus_id,
            'message' => 'New consensus round initiated'
        ];
    }

    /**
     * Generate consensus hash for pattern
     *
     * @param array $pattern_data Pattern data
     * @return string Consensus hash
     */
    private function generate_consensus_hash($pattern_data) {
        $hash_data = json_encode($pattern_data, JSON_UNESCAPED_UNICODE);
        return hash('sha256', $hash_data);
    }

    /**
     * Create consensus record in database
     *
     * @param int $pattern_id Pattern ID
     * @param string $consensus_hash Consensus hash
     * @return int Consensus ID
     */
    private function create_consensus_record($pattern_id, $consensus_hash) {
        $table_name = $this->wpdb->prefix . 'aps_consensus';

        $timeout_at = gmdate('Y-m-d H:i:s', time() + $this->consensus_timeout);

        $this->wpdb->insert(
            $table_name,
            [
                'pattern_id' => $pattern_id,
                'consensus_hash' => $consensus_hash,
                'status' => 'pending',
                'timeout_at' => $timeout_at
            ],
            ['%d', '%s', '%s', '%s']
        );

        return $this->wpdb->insert_id;
    }

    /**
     * Collect votes from participants
     *
     * @param int $consensus_id Consensus ID
     * @param array $contributors Contributor information
     * @return array Array of votes
     */
    private function collect_votes($consensus_id, $contributors) {
        $votes_table = $this->wpdb->prefix . 'aps_consensus_votes';

        if ($this->is_production) {
            // Production mode: Request votes from network nodes
            $nodes = $this->get_active_network_nodes();

            if (!empty($nodes)) {
                $this->request_votes_from_nodes($consensus_id, $contributors, $nodes);

                // Wait for votes with timeout
                $timeout = time() + $this->consensus_timeout;
                $required_votes = max($this->min_nodes, ceil(count($nodes) * $this->consensus_threshold));

                while (time() < $timeout) {
                    $vote_count = $this->wpdb->get_var($this->wpdb->prepare(
                        "SELECT COUNT(*) FROM {$votes_table} WHERE consensus_id = %d",
                        $consensus_id
                    ));

                    if ($vote_count >= $required_votes) {
                        break;
                    }

                    usleep(100000); // 100ms
                }
            }
        }

        // Return all collected votes
        $existing_votes = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$votes_table} WHERE consensus_id = %d",
            $consensus_id
        ), ARRAY_A);

        return $existing_votes;
    }

    /**
     * Get active network nodes
     *
     * @return array Array of active nodes
     */
    private function get_active_network_nodes() {
        $table_exists = $this->wpdb->get_var(
            "SHOW TABLES LIKE '{$this->nodes_table}'"
        );

        if (!$table_exists) {
            $this->create_nodes_table();
        }

        $nodes = $this->wpdb->get_results(
            "SELECT * FROM {$this->nodes_table}
             WHERE status = 'active'
             AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             ORDER BY reputation DESC",
            ARRAY_A
        );

        return $nodes ?: [];
    }

    /**
     * Create network nodes table
     */
    private function create_nodes_table() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->nodes_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            node_id VARCHAR(255) NOT NULL UNIQUE,
            endpoint_url VARCHAR(500) NOT NULL,
            public_key TEXT NULL,
            reputation DECIMAL(5,4) DEFAULT 0.5000,
            status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
            capabilities TEXT NULL,
            last_heartbeat DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX status (status),
            INDEX reputation (reputation),
            INDEX last_heartbeat (last_heartbeat)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Request votes from network nodes
     *
     * @param int $consensus_id Consensus ID
     * @param array $contributors Contributor data
     * @param array $nodes Network nodes
     * @return void
     */
    private function request_votes_from_nodes($consensus_id, $contributors, $nodes) {
        $consensus_record = $this->get_consensus_record($consensus_id);

        $vote_request = [
            'action' => 'request_vote',
            'consensus_id' => $consensus_id,
            'consensus_hash' => $consensus_record->consensus_hash ?? '',
            'pattern_id' => $contributors['pattern_id'],
            'pattern_data' => $contributors['pattern_data'] ?? null,
            'timeout' => time() + $this->consensus_timeout,
            'requester' => $this->get_local_node_id()
        ];

        foreach ($nodes as $node) {
            $this->send_vote_request($node, $vote_request);
        }
    }

    /**
     * Send vote request to a node
     *
     * @param array $node Node information
     * @param array $request Vote request data
     * @return bool Success
     */
    private function send_vote_request($node, $request) {
        $endpoint = rtrim($node['endpoint_url'], '/') . '/aps/v1/consensus/vote';

        $response = wp_remote_post($endpoint, [
            'timeout' => 5,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-APS-Node-ID' => $this->get_local_node_id(),
                'X-APS-Signature' => $this->sign_request($request)
            ],
            'body' => json_encode($request)
        ]);

        if (is_wp_error($response)) {
            error_log('[APS Consensus] Vote request failed to ' . $node['node_id'] . ': ' . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }

    /**
     * Get local node identifier
     *
     * @return string
     */
    private function get_local_node_id() {
        $node_id = get_option('aps_local_node_id');

        if (!$node_id) {
            $node_id = 'node_' . wp_generate_uuid4();
            update_option('aps_local_node_id', $node_id);
        }

        return $node_id;
    }

    /**
     * Sign a request for authentication
     *
     * @param array $data Request data
     * @return string Signature
     */
    private function sign_request($data) {
        $secret = defined('AUTH_SALT') ? AUTH_SALT : wp_salt('auth');
        return hash_hmac('sha256', json_encode($data), $secret);
    }

    /**
     * Register a network node
     *
     * @param string $node_id Node identifier
     * @param string $endpoint_url Node endpoint URL
     * @param string $public_key Optional public key
     * @return bool Success
     */
    public function register_node($node_id, $endpoint_url, $public_key = null) {
        $table_exists = $this->wpdb->get_var(
            "SHOW TABLES LIKE '{$this->nodes_table}'"
        );

        if (!$table_exists) {
            $this->create_nodes_table();
        }

        $result = $this->wpdb->replace(
            $this->nodes_table,
            [
                'node_id' => $node_id,
                'endpoint_url' => $endpoint_url,
                'public_key' => $public_key,
                'status' => 'active',
                'last_heartbeat' => current_time('mysql', 1)
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Update node heartbeat
     *
     * @param string $node_id Node identifier
     * @return bool Success
     */
    public function update_node_heartbeat($node_id) {
        return $this->wpdb->update(
            $this->nodes_table,
            ['last_heartbeat' => current_time('mysql', 1)],
            ['node_id' => $node_id],
            ['%s'],
            ['%s']
        ) !== false;
    }

    /**
     * Check if running in production mode
     *
     * @return bool
     */
    public function is_production_mode() {
        return $this->is_production;
    }

    /**
     * Update consensus status
     *
     * @param int $consensus_id Consensus ID
     * @param string $status New status
     * @param int $participants Number of participants
     * @param float $agreement Agreement percentage
     * @return bool Success status
     */
    private function update_consensus_status($consensus_id, $status, $participants, $agreement) {
        $table_name = $this->wpdb->prefix . 'aps_consensus';

        return $this->wpdb->update(
            $table_name,
            [
                'status' => $status,
                'participants_count' => $participants,
                'agreement_percentage' => $agreement,
                'resolved_at' => current_time('mysql', 1)
            ],
            ['id' => $consensus_id],
            ['%s', '%d', '%f', '%s'],
            ['%d']
        );
    }

    /**
     * Get consensus record
     *
     * @param int $consensus_id Consensus ID
     * @return object|null Consensus record
     */
    private function get_consensus_record($consensus_id) {
        $table_name = $this->wpdb->prefix . 'aps_consensus';

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $consensus_id
        ));
    }

    /**
     * Submit vote for consensus
     *
     * @param int $consensus_id Consensus ID
     * @param string $node_id Node identifier
     * @param string $vote Vote (approve/reject/abstain)
     * @param string $signature Optional cryptographic signature
     * @return bool Success status
     */
    public function submit_vote($consensus_id, $node_id, $vote, $signature = null) {
        $votes_table = $this->wpdb->prefix . 'aps_consensus_votes';

        // Generate vote hash
        $vote_hash = hash('sha256', $consensus_id . $node_id . $vote . time());

        $result = $this->wpdb->replace(
            $votes_table,
            [
                'consensus_id' => $consensus_id,
                'node_id' => $node_id,
                'vote' => $vote,
                'vote_hash' => $vote_hash,
                'signature' => $signature
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Check for timed out consensus operations
     *
     * @return array Array of timed out consensus IDs
     */
    public function check_timeouts() {
        $table_name = $this->wpdb->prefix . 'aps_consensus';

        $timed_out = $this->wpdb->get_results(
            "SELECT id, pattern_id FROM {$table_name}
            WHERE status = 'pending'
            AND timeout_at < NOW()",
            ARRAY_A
        );

        // Mark as failed
        foreach ($timed_out as $consensus) {
            $this->update_consensus_status($consensus['id'], 'failed', 0, 0.00);
            do_action('aps_consensus_timeout', $consensus['pattern_id'], $consensus['id']);
        }

        return $timed_out;
    }
}
