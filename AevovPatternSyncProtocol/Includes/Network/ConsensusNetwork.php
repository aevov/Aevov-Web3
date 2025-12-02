<?php
/**
 * Consensus Network Layer
 *
 * Implements distributed consensus with Byzantine Fault Tolerance,
 * quorum-based voting, leader election (Raft-like), and network partition handling.
 *
 * @package AevovPatternSyncProtocol
 * @subpackage Network
 * @since 1.0.0
 */

namespace APS\Network;

use APS\Core\Logger;
use APS\Consensus\ConsensusMechanism;

class ConsensusNetwork {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Consensus mechanism
     *
     * @var ConsensusMechanism
     */
    private $consensusMechanism;

    /**
     * Local node information
     *
     * @var array
     */
    private $localNode;

    /**
     * Network participants
     *
     * @var array
     */
    private $participants;

    /**
     * Current term (Raft terminology)
     *
     * @var int
     */
    private $currentTerm;

    /**
     * Voted for in current term
     *
     * @var string|null
     */
    private $votedFor;

    /**
     * Node state (follower|candidate|leader)
     *
     * @var string
     */
    private $nodeState;

    /**
     * Leader ID
     *
     * @var string|null
     */
    private $leaderId;

    /**
     * Election timeout (milliseconds)
     *
     * @var int
     */
    private $electionTimeout;

    /**
     * Heartbeat interval (milliseconds)
     *
     * @var int
     */
    private $heartbeatInterval;

    /**
     * Last heartbeat time
     *
     * @var int
     */
    private $lastHeartbeat;

    /**
     * Commit log for consensus
     *
     * @var array
     */
    private $commitLog;

    /**
     * Pending proposals
     *
     * @var array
     */
    private $pendingProposals;

    /**
     * Quorum size
     *
     * @var int
     */
    private $quorumSize;

    /**
     * Byzantine fault tolerance threshold
     *
     * @var float
     */
    private $bftThreshold;

    /**
     * Network partition detection
     *
     * @var array
     */
    private $partitionState;

    /**
     * Node states
     */
    const STATE_FOLLOWER = 'follower';
    const STATE_CANDIDATE = 'candidate';
    const STATE_LEADER = 'leader';

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct($config = []) {
        $this->logger = Logger::get_instance();
        $this->consensusMechanism = new ConsensusMechanism();

        $this->localNode = [
            'id' => $config['node_id'] ?? $this->generateNodeId(),
            'address' => $config['address'] ?? get_site_url(),
            'type' => $config['type'] ?? 'validator'
        ];

        $this->participants = [];
        $this->currentTerm = 0;
        $this->votedFor = null;
        $this->nodeState = self::STATE_FOLLOWER;
        $this->leaderId = null;

        $this->electionTimeout = $config['election_timeout'] ?? 5000; // 5 seconds
        $this->heartbeatInterval = $config['heartbeat_interval'] ?? 1000; // 1 second
        $this->lastHeartbeat = time() * 1000;

        $this->commitLog = [];
        $this->pendingProposals = [];

        $this->bftThreshold = $config['bft_threshold'] ?? 0.67; // 2/3 + 1
        $this->partitionState = [
            'detected' => false,
            'partition_id' => null,
            'nodes_reachable' => []
        ];

        $this->logger->log('info', 'ConsensusNetwork initialized', [
            'node_id' => $this->localNode['id'],
            'state' => $this->nodeState
        ]);
    }

    /**
     * Join consensus network
     *
     * @param array $initial_participants Initial participant nodes
     * @return bool Success status
     */
    public function joinConsensus($initial_participants = []) {
        $this->logger->log('info', 'Joining consensus network', [
            'participants' => count($initial_participants)
        ]);

        foreach ($initial_participants as $participant) {
            $this->addParticipant($participant);
        }

        $this->calculateQuorumSize();

        // Start as follower
        $this->becomeFollower();

        // Start election timer
        $this->resetElectionTimer();

        return true;
    }

    /**
     * Propose a value for consensus
     *
     * @param mixed $value Value to propose
     * @param array $metadata Proposal metadata
     * @return array Proposal result
     */
    public function proposeValue($value, $metadata = []) {
        $proposal_id = $this->generateProposalId();

        $proposal = [
            'id' => $proposal_id,
            'value' => $value,
            'proposer' => $this->localNode['id'],
            'term' => $this->currentTerm,
            'timestamp' => time(),
            'metadata' => $metadata,
            'votes' => [],
            'status' => 'pending'
        ];

        $this->pendingProposals[$proposal_id] = $proposal;

        $this->logger->log('info', 'Value proposed for consensus', [
            'proposal_id' => $proposal_id,
            'term' => $this->currentTerm
        ]);

        // If leader, broadcast proposal
        if ($this->nodeState === self::STATE_LEADER) {
            return $this->broadcastProposal($proposal);
        }

        // If not leader, forward to leader
        if ($this->leaderId) {
            return $this->forwardToLeader($proposal);
        }

        // No leader - trigger election
        $this->startElection();

        return [
            'success' => false,
            'proposal_id' => $proposal_id,
            'status' => 'pending_election'
        ];
    }

    /**
     * Start leader election
     *
     * @return array Election result
     */
    public function startElection() {
        // Transition to candidate
        $this->becomeCandidate();

        // Increment term
        $this->currentTerm++;

        // Vote for self
        $this->votedFor = $this->localNode['id'];

        $votes = [$this->localNode['id']];

        $this->logger->log('info', 'Starting election', [
            'term' => $this->currentTerm,
            'candidate' => $this->localNode['id']
        ]);

        // Request votes from all participants
        foreach ($this->participants as $participant) {
            if ($participant['id'] === $this->localNode['id']) {
                continue;
            }

            $vote_response = $this->requestVote($participant);

            if ($vote_response && $vote_response['granted']) {
                $votes[] = $participant['id'];
            }
        }

        // Check if won election
        if (count($votes) >= $this->quorumSize) {
            $this->becomeLeader();

            return [
                'success' => true,
                'leader' => $this->localNode['id'],
                'term' => $this->currentTerm,
                'votes' => count($votes)
            ];
        }

        // Did not win - revert to follower
        $this->becomeFollower();

        return [
            'success' => false,
            'votes' => count($votes),
            'required' => $this->quorumSize
        ];
    }

    /**
     * Request vote from participant
     *
     * @param array $participant Participant node
     * @return array|null Vote response
     */
    private function requestVote($participant) {
        // In production, this would be network call
        // Simulate vote decision

        $last_log_index = count($this->commitLog);
        $last_log_term = $last_log_index > 0 ? $this->commitLog[$last_log_index - 1]['term'] : 0;

        $vote_request = [
            'type' => 'REQUEST_VOTE',
            'term' => $this->currentTerm,
            'candidate_id' => $this->localNode['id'],
            'last_log_index' => $last_log_index,
            'last_log_term' => $last_log_term
        ];

        // Simulate vote (in real implementation, this is network call)
        $granted = $this->simulateVoteDecision($participant, $vote_request);

        return [
            'granted' => $granted,
            'term' => $this->currentTerm,
            'voter' => $participant['id']
        ];
    }

    /**
     * Simulate vote decision
     *
     * @param array $participant Participant
     * @param array $vote_request Vote request
     * @return bool Vote granted
     */
    private function simulateVoteDecision($participant, $vote_request) {
        // Simulate various voting scenarios

        // Random vote with high probability for testing
        return (mt_rand(0, 100) > 30); // 70% chance of voting yes
    }

    /**
     * Broadcast proposal to all participants
     *
     * @param array $proposal Proposal
     * @return array Broadcast result
     */
    private function broadcastProposal($proposal) {
        $votes = [$this->localNode['id']]; // Leader votes for own proposal
        $responses = [];

        foreach ($this->participants as $participant) {
            if ($participant['id'] === $this->localNode['id']) {
                continue;
            }

            $response = $this->sendProposalToNode($participant, $proposal);

            if ($response && $response['vote'] === 'approve') {
                $votes[] = $participant['id'];
            }

            $responses[] = $response;
        }

        // Check if reached consensus
        $consensus_reached = count($votes) >= $this->quorumSize;

        if ($consensus_reached) {
            $this->commitProposal($proposal, $votes);
        }

        return [
            'success' => $consensus_reached,
            'proposal_id' => $proposal['id'],
            'votes' => count($votes),
            'required' => $this->quorumSize,
            'status' => $consensus_reached ? 'committed' : 'pending'
        ];
    }

    /**
     * Send proposal to node
     *
     * @param array $node Node
     * @param array $proposal Proposal
     * @return array Response
     */
    private function sendProposalToNode($node, $proposal) {
        // In production, this is network call
        // Simulate Byzantine behavior detection

        $is_byzantine = $this->detectByzantineBehavior($node);

        if ($is_byzantine) {
            $this->logger->log('warning', 'Byzantine behavior detected', [
                'node' => $node['id']
            ]);

            // Byzantine node might vote randomly or maliciously
            $vote = mt_rand(0, 1) ? 'approve' : 'reject';
        } else {
            // Honest node validates and votes
            $vote = $this->validateProposal($proposal) ? 'approve' : 'reject';
        }

        return [
            'node' => $node['id'],
            'vote' => $vote,
            'term' => $this->currentTerm
        ];
    }

    /**
     * Detect Byzantine (faulty/malicious) behavior
     *
     * @param array $node Node to check
     * @return bool True if Byzantine behavior detected
     */
    private function detectByzantineBehavior($node) {
        // Simulate Byzantine detection
        // In reality, this would involve:
        // - Message signature verification
        // - Consistency checks
        // - Reputation scoring
        // - Pattern analysis

        // 5% chance of Byzantine behavior for simulation
        return (mt_rand(0, 100) < 5);
    }

    /**
     * Validate proposal
     *
     * @param array $proposal Proposal
     * @return bool Valid
     */
    private function validateProposal($proposal) {
        // Check proposal structure
        if (!isset($proposal['id']) || !isset($proposal['value'])) {
            return false;
        }

        // Check term
        if ($proposal['term'] < $this->currentTerm) {
            return false;
        }

        // Additional validation logic
        return true;
    }

    /**
     * Commit proposal to log
     *
     * @param array $proposal Proposal
     * @param array $votes Votes
     * @return void
     */
    private function commitProposal($proposal, $votes) {
        $commit_entry = [
            'proposal' => $proposal,
            'votes' => $votes,
            'term' => $this->currentTerm,
            'committed_at' => time(),
            'index' => count($this->commitLog)
        ];

        $this->commitLog[] = $commit_entry;

        // Update proposal status
        if (isset($this->pendingProposals[$proposal['id']])) {
            $this->pendingProposals[$proposal['id']]['status'] = 'committed';
        }

        // Submit to consensus mechanism for recording
        $this->consensusMechanism->submit_vote(
            $proposal['id'],
            $this->localNode['id'],
            'approve'
        );

        $this->logger->log('info', 'Proposal committed', [
            'proposal_id' => $proposal['id'],
            'votes' => count($votes),
            'log_index' => $commit_entry['index']
        ]);
    }

    /**
     * Forward proposal to leader
     *
     * @param array $proposal Proposal
     * @return array Result
     */
    private function forwardToLeader($proposal) {
        $this->logger->log('debug', 'Forwarding proposal to leader', [
            'leader' => $this->leaderId
        ]);

        // In production, send to leader node
        return [
            'success' => true,
            'proposal_id' => $proposal['id'],
            'forwarded_to' => $this->leaderId
        ];
    }

    /**
     * Send heartbeat as leader
     *
     * @return void
     */
    public function sendHeartbeat() {
        if ($this->nodeState !== self::STATE_LEADER) {
            return;
        }

        $heartbeat = [
            'type' => 'HEARTBEAT',
            'term' => $this->currentTerm,
            'leader_id' => $this->localNode['id'],
            'commit_index' => count($this->commitLog),
            'timestamp' => time()
        ];

        foreach ($this->participants as $participant) {
            if ($participant['id'] !== $this->localNode['id']) {
                // Send heartbeat
                $this->sendHeartbeatToNode($participant, $heartbeat);
            }
        }

        $this->lastHeartbeat = time() * 1000;

        $this->logger->log('debug', 'Heartbeat sent', [
            'term' => $this->currentTerm,
            'participants' => count($this->participants)
        ]);
    }

    /**
     * Send heartbeat to specific node
     *
     * @param array $node Node
     * @param array $heartbeat Heartbeat message
     * @return void
     */
    private function sendHeartbeatToNode($node, $heartbeat) {
        // In production, network call
        // For simulation, just log
    }

    /**
     * Handle network partition
     *
     * @return array Partition handling result
     */
    public function handleNetworkPartition() {
        $this->logger->log('warning', 'Network partition detected');

        // Detect which nodes are reachable
        $reachable_nodes = $this->detectReachableNodes();

        $this->partitionState = [
            'detected' => true,
            'partition_id' => uniqid('partition_'),
            'nodes_reachable' => $reachable_nodes,
            'detected_at' => time()
        ];

        // Check if we have quorum in our partition
        $have_quorum = count($reachable_nodes) >= $this->quorumSize;

        if (!$have_quorum) {
            // Lose quorum - step down if leader
            if ($this->nodeState === self::STATE_LEADER) {
                $this->becomeFollower();
                $this->logger->log('warning', 'Lost quorum due to partition - stepping down');
            }

            return [
                'have_quorum' => false,
                'action' => 'step_down',
                'reachable_nodes' => count($reachable_nodes)
            ];
        }

        return [
            'have_quorum' => true,
            'action' => 'continue',
            'reachable_nodes' => count($reachable_nodes)
        ];
    }

    /**
     * Detect reachable nodes
     *
     * @return array Reachable node IDs
     */
    private function detectReachableNodes() {
        $reachable = [$this->localNode['id']];

        foreach ($this->participants as $participant) {
            if ($participant['id'] === $this->localNode['id']) {
                continue;
            }

            // Simulate reachability check
            $is_reachable = $this->pingNode($participant);

            if ($is_reachable) {
                $reachable[] = $participant['id'];
            }
        }

        return $reachable;
    }

    /**
     * Ping node to check reachability
     *
     * @param array $node Node
     * @return bool Reachable
     */
    private function pingNode($node) {
        // Simulate network ping
        // In production, actual network ping/health check

        // 90% success rate for simulation
        return (mt_rand(0, 100) < 90);
    }

    /**
     * Become follower
     *
     * @return void
     */
    private function becomeFollower() {
        $this->nodeState = self::STATE_FOLLOWER;
        $this->votedFor = null;

        $this->logger->log('info', 'Node became follower', [
            'term' => $this->currentTerm
        ]);
    }

    /**
     * Become candidate
     *
     * @return void
     */
    private function becomeCandidate() {
        $this->nodeState = self::STATE_CANDIDATE;

        $this->logger->log('info', 'Node became candidate', [
            'term' => $this->currentTerm
        ]);
    }

    /**
     * Become leader
     *
     * @return void
     */
    private function becomeLeader() {
        $this->nodeState = self::STATE_LEADER;
        $this->leaderId = $this->localNode['id'];

        // Send initial heartbeat
        $this->sendHeartbeat();

        $this->logger->log('info', 'Node became leader', [
            'term' => $this->currentTerm,
            'node_id' => $this->localNode['id']
        ]);
    }

    /**
     * Add participant to consensus network
     *
     * @param array $participant Participant info
     * @return void
     */
    public function addParticipant($participant) {
        if (!isset($this->participants[$participant['id']])) {
            $this->participants[$participant['id']] = $participant;
            $this->calculateQuorumSize();

            $this->logger->log('info', 'Participant added', [
                'participant_id' => $participant['id']
            ]);
        }
    }

    /**
     * Remove participant from consensus network
     *
     * @param string $participant_id Participant ID
     * @return void
     */
    public function removeParticipant($participant_id) {
        if (isset($this->participants[$participant_id])) {
            unset($this->participants[$participant_id]);
            $this->calculateQuorumSize();

            $this->logger->log('info', 'Participant removed', [
                'participant_id' => $participant_id
            ]);
        }
    }

    /**
     * Calculate quorum size based on number of participants
     *
     * BFT requires > 2/3 agreement (or 2f + 1 where f is max faulty nodes)
     *
     * @return void
     */
    private function calculateQuorumSize() {
        $total_nodes = count($this->participants);

        // BFT quorum: floor(2n/3) + 1
        // This tolerates up to floor((n-1)/3) Byzantine nodes
        $this->quorumSize = floor(($total_nodes * 2) / 3) + 1;

        $this->logger->log('debug', 'Quorum size calculated', [
            'total_nodes' => $total_nodes,
            'quorum_size' => $this->quorumSize
        ]);
    }

    /**
     * Reset election timer
     *
     * @return void
     */
    private function resetElectionTimer() {
        $this->lastHeartbeat = time() * 1000;
    }

    /**
     * Check if election timeout elapsed
     *
     * @return bool Timeout elapsed
     */
    public function isElectionTimeoutElapsed() {
        $current_time = time() * 1000;
        $elapsed = $current_time - $this->lastHeartbeat;

        return $elapsed > $this->electionTimeout;
    }

    /**
     * Generate proposal ID
     *
     * @return string Proposal ID
     */
    private function generateProposalId() {
        return uniqid('proposal_', true);
    }

    /**
     * Generate node ID
     *
     * @return string Node ID
     */
    private function generateNodeId() {
        return hash('sha256', get_site_url() . time() . wp_rand());
    }

    /**
     * Get consensus statistics
     *
     * @return array Statistics
     */
    public function getStatistics() {
        return [
            'node_id' => $this->localNode['id'],
            'state' => $this->nodeState,
            'term' => $this->currentTerm,
            'leader_id' => $this->leaderId,
            'participants_count' => count($this->participants),
            'quorum_size' => $this->quorumSize,
            'commit_log_size' => count($this->commitLog),
            'pending_proposals' => count($this->pendingProposals),
            'partition_detected' => $this->partitionState['detected']
        ];
    }

    /**
     * Get current state
     *
     * @return string State
     */
    public function getState() {
        return $this->nodeState;
    }

    /**
     * Get commit log
     *
     * @return array Commit log
     */
    public function getCommitLog() {
        return $this->commitLog;
    }
}
