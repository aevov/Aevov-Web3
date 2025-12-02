<?php

use APS\Core\Logger; // Assuming a Logger class exists
use APS\DB\APS_Pattern_DB; // To query pattern data

class ProofOfContribution
{
    private $ledger;
    private $consensus;
    private $rewards;
    private $node_identifier;
    private $logger;
    private $pattern_db;

    public function __construct()
    {
        $this->ledger = new DistributedLedger();
        $this->consensus = new ConsensusMechanism(); // No longer needs initial proof
        $this->rewards = new RewardSystem();
        $this->node_identifier = uniqid();
        $this->logger = Logger::get_instance();
        $this->pattern_db = new APS_Pattern_DB();
    }

    /**
     * Submits a contribution to the network.
     *
     * @param Contribution $contribution
     *
     * @return bool
     */
    public function submitContribution(Contribution $contribution): bool
    {
        $contributor_id = $contribution->getContributor()->getId();

        if (!$this->isValidContribution($contribution)) {
            $this->logger->log('warning', 'Invalid contribution submitted', ['contributor_id' => $contributor_id]);
            return false;
        }

        // Add the contribution as a transaction
        $this->ledger->newTransaction(
            $contributor_id,
            'network', // Recipient for network contributions
            1.0 // Amount for this type of transaction
        );

        // Mine a new block using Proof of Contribution
        $lastBlock = $this->ledger->lastBlock();
        $lastProof = $lastBlock ? $lastBlock['proof'] : 0; // Get last proof, or 0 for genesis
        $previousHash = $lastBlock ? $this->ledger->hash($lastBlock) : '1'; // Get previous hash, or '1' for genesis

        $proof = $this->consensus->proofOfContribution($contributor_id, $lastProof);

        // Forge the new Block by adding it to the chain.
        $this->ledger->newBlock($proof, $previousHash);

        // Reward the contributor.
        $this->rewards->reward($contribution->getContributor());

        $this->logger->log('info', 'Contribution submitted and block forged', ['contributor_id' => $contributor_id, 'new_block_proof' => $proof]);

        return true;
    }

    /**
     * Validates a contribution based on whitepaper definition.
     * "A node's contribution is measured by the number of valid patterns it has identified
     * and the number of useful inferences it has made."
     *
     * @param Contribution $contribution
     *
     * @return bool
     */
    private function isValidContribution(Contribution $contribution): bool
    {
        $contributor_id = $contribution->getContributor()->getId();
        if (empty($contributor_id)) {
            $this->logger->log('error', 'Contribution validation failed: Contributor ID is empty.');
            return false;
        }

        // Example: Check if the contributor has identified at least one pattern
        // In a real system, this would be more complex, involving pattern quality,
        // inference usefulness, and potentially a time window.
        $contributor_patterns = $this->pattern_db->get_patterns_by_contributor($contributor_id); // Need to implement this method in APS_Pattern_DB
        
        if (empty($contributor_patterns)) {
            $this->logger->log('warning', 'Contribution validation failed: No patterns found for contributor.', ['contributor_id' => $contributor_id]);
            return false;
        }

        // Placeholder for checking useful inferences.
        // This would involve a more complex logic, potentially querying a separate
        // inference tracking system or analyzing the impact of the patterns.
        $useful_inferences_score = $this->get_contributor_useful_inferences_score($contributor_id);
        if ($useful_inferences_score < 0.5) { // Example threshold
            $this->logger->log('warning', 'Contribution validation failed: Insufficient useful inferences score.', ['contributor_id' => $contributor_id, 'score' => $useful_inferences_score]);
            return false;
        }
 
        return true;
    }

    /**
     * Placeholder for calculating a contributor's useful inferences score.
     *
     * @param string $contributor_id
     * @return float
     */
    private function get_contributor_useful_inferences_score(string $contributor_id): float {
        // Retrieve patterns associated with the contributor
        $patterns = $this->pattern_db->get_patterns_by_contributor($contributor_id);

        $total_useful_inferences = 0;
        foreach ($patterns as $pattern) {
            $total_useful_inferences += (int)($pattern['useful_inference_count'] ?? 0);
        }

        // Simple scoring: 0.1 for each useful inference, up to a max of 1.0
        $score = min(1.0, $total_useful_inferences * 0.1);

        $this->logger->log('info', 'Calculated useful inferences score', [
            'contributor_id' => $contributor_id,
            'total_useful_inferences' => $total_useful_inferences,
            'score' => $score
        ]);

        return $score;
    }

    /**
     * Registers a new node.
     *
     * @param string $address
     */
    public function registerNode(string $address): void
    {
        $this->ledger->registerNode($address);
    }

    /**
     * Resolves conflicts between nodes.
     *
     * @return bool
     */
    public function resolveConflicts(): bool
    {
        return $this->ledger->resolveConflicts();
    }
}
