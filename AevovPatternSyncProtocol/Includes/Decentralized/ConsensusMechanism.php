<?php

namespace Aevov\Decentralized;

use APS\DB\APS_Pattern_DB; // To query pattern data
use APS\Core\Logger;

class ConsensusMechanism
{
    private $lastProof;
    private $proposals = []; // Stores active proposals
    private $votes = [];     // Stores votes for proposals
    private $logger;
    private $pattern_db; // To access pattern data for contribution

    public function __construct()
    {
        $this->lastProof = 0; 
        $this->logger = Logger::get_instance();
        $this->pattern_db = new APS_Pattern_DB(); // Instantiate Pattern DB
    }

    /**
     * Proof of Contribution Algorithm:
     * - Selects a proof based on the contributor's historical contribution.
     *
     * @param string $contributor_id The ID of the contributor.
     * @param int $lastProof The proof of the previous block.
     *
     * @return int The new proof.
     */
    public function proofOfContribution(string $contributor_id, int $lastProof): int
    {
        // In a real system, this would be a complex calculation based on:
        // - Number of valid patterns identified by the contributor
        // - Number of useful inferences made by the contributor
        // - Network activity, etc.
        // For now, a simplified placeholder based on a dummy contribution score.

        $contribution_score = $this->get_contributor_contribution_score($contributor_id);
        
        // A simple way to derive a proof from contribution score and lastProof
        // This needs to be deterministic and verifiable by other nodes.
        $proof = $lastProof + (int)($contribution_score * 100); // Example: higher score, higher proof

        $this->logger->log('info', 'Generated proof based on contribution', [
            'contributor_id' => $contributor_id,
            'contribution_score' => $contribution_score,
            'last_proof' => $lastProof,
            'new_proof' => $proof
        ]);

        return $proof;
    }

    /**
     * Validates the proof of contribution.
     *
     * @param int $lastProof The proof of the previous block.
     * @param int $proof The current proof.
     * @param string $contributor_id The ID of the contributor who generated the proof.
     *
     * @return bool
     */
    public static function validProof(int $lastProof, int $proof, string $contributor_id): bool
    {
        // This method needs to be static if called from DistributedLedger::validChain
        // and thus cannot directly access $this->pattern_db.
        // A real implementation would require passing necessary context or
        // making the contribution score calculation static/global.
        // For now, a dummy validation.
        
        // Example: Check if the proof is within a reasonable range of the lastProof
        // based on an expected contribution.
        return ($proof > $lastProof && $proof <= ($lastProof + 1000)); // Placeholder
    }

    private function get_contributor_contribution_score(string $contributor_id): float {
        // Retrieve patterns associated with the contributor
        $patterns = $this->pattern_db->get_patterns_by_contributor($contributor_id);
        $num_patterns = count($patterns);

        $total_useful_inferences = 0;
        foreach ($patterns as $pattern) {
            $total_useful_inferences += (int)($pattern['useful_inference_count'] ?? 0);
        }

        // Define scoring weights
        $pattern_weight = 0.05; // Each pattern contributes 0.05 to the score
        $inference_weight = 0.01; // Each useful inference contributes 0.01 to the score
        $max_score = 10.0; // Maximum possible contribution score

        // Calculate raw score
        $raw_score = ($num_patterns * $pattern_weight) + ($total_useful_inferences * $inference_weight);

        // Cap the score at max_score
        $score = min($max_score, $raw_score);

        $this->logger->log('info', 'Calculated contribution score', [
            'contributor_id' => $contributor_id,
            'num_patterns' => $num_patterns,
            'total_useful_inferences' => $total_useful_inferences,
            'score' => $score
        ]);

        return $score;
    }

    /**
     * Get the current status of the consensus mechanism.
     *
     * @return array
     */
    public function get_status() {
        return [
            'last_proof' => $this->lastProof,
            'active_proposals_count' => count($this->proposals),
            'total_votes_cast' => count($this->votes),
            'health' => 'operational' // Placeholder for more complex health checks
        ];
    }

    /**
     * Submit a vote for a proposal.
     *
     * @param string $proposal_id
     * @param bool $vote True for approval, false for disapproval.
     * @param string $contributor_id
     * @return bool|\WP_Error
     */
    public function submit_vote($proposal_id, $vote, $contributor_id) {
        if (!isset($this->proposals[$proposal_id])) {
            $this->logger->log('error', 'Attempted to vote on non-existent proposal', ['proposal_id' => $proposal_id]);
            return new \WP_Error('proposal_not_found', 'Proposal not found.');
        }

        // Prevent duplicate votes from the same contributor on the same proposal
        if (isset($this->votes[$proposal_id][$contributor_id])) {
            $this->logger->log('warning', 'Duplicate vote attempted', ['proposal_id' => $proposal_id, 'contributor_id' => $contributor_id]);
            return new \WP_Error('duplicate_vote', 'You have already voted on this proposal.');
        }

        $this->votes[$proposal_id][$contributor_id] = $vote;
        $this->logger->log('info', 'Vote submitted', ['proposal_id' => $proposal_id, 'vote' => $vote, 'contributor_id' => $contributor_id]);

        // In a real system, this would trigger a check for consensus
        $this->check_consensus($proposal_id);

        return true;
    }

    /**
     * Get a paginated list of proposals.
     *
     * @param array $args Query arguments (page, per_page, status)
     * @return array
     */
    public function get_proposals($args) {
        $page = $args['page'] ?? 1;
        $per_page = $args['per_page'] ?? 10;
        $status_filter = $args['status'] ?? null;

        $filtered_proposals = $this->proposals;
        if ($status_filter) {
            $filtered_proposals = array_filter($filtered_proposals, function($proposal) use ($status_filter) {
                return $proposal['status'] === $status_filter;
            });
        }

        $offset = ($page - 1) * $per_page;
        return array_slice($filtered_proposals, $offset, $per_page);
    }

    /**
     * Get the total count of proposals.
     *
     * @param array $args Query arguments (for filtering, if implemented)
     * @return int
     */
    public function get_proposal_count($args) {
        $status_filter = $args['status'] ?? null;
        $filtered_proposals = $this->proposals;
        if ($status_filter) {
            $filtered_proposals = array_filter($filtered_proposals, function($proposal) use ($status_filter) {
                return $proposal['status'] === $status_filter;
            });
        }
        return count($filtered_proposals);
    }

    /**
     * Get details for a specific proposal.
     *
     * @param string $proposal_id
     * @return array|null
     */
    public function get_proposal($proposal_id) {
        return $this->proposals[$proposal_id] ?? null;
    }

    /**
     * Placeholder for adding a new proposal.
     * In a real system, proposals would be created through a specific process.
     *
     * @param array $proposal_data
     * @return string The proposal ID.
     */
    public function add_proposal($proposal_data) {
        $proposal_id = uniqid('proposal_');
        $this->proposals[$proposal_id] = array_merge($proposal_data, [
            'id' => $proposal_id,
            'created_at' => time(),
            'status' => 'open',
            'votes' => []
        ]);
        $this->logger->log('info', 'New proposal added', ['proposal_id' => $proposal_id, 'data' => $proposal_data]);
        return $proposal_id;
    }

    /**
     * Check for consensus on a proposal.
     * This is a simplified example. Real consensus would be more complex.
     *
     * @param string $proposal_id
     */
    private function check_consensus($proposal_id) {
        if (!isset($this->proposals[$proposal_id])) {
            return;
        }

        $yes_votes = 0;
        $no_votes = 0;
        foreach ($this->votes[$proposal_id] ?? [] as $contributor_id => $vote) {
            if ($vote) {
                $yes_votes++;
            } else {
                $no_votes++;
            }
        }

        // Simple majority consensus
        if (($yes_votes + $no_votes) >= 3) { // Example: require at least 3 votes
            if ($yes_votes > $no_votes) {
                $this->proposals[$proposal_id]['status'] = 'approved';
                $this->logger->log('info', 'Proposal approved by consensus', ['proposal_id' => $proposal_id]);
            } else {
                $this->proposals[$proposal_id]['status'] = 'rejected';
                $this->logger->log('info', 'Proposal rejected by consensus', ['proposal_id' => $proposal_id]);
            }
            // Clear votes for this proposal after consensus is reached
            unset($this->votes[$proposal_id]);
        }
    }
}
