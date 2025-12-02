<?php

namespace Aevov\Decentralized;

use APS\DB\APS_Reward_DB;
use Aevov\Decentralized\Contributor;

class RewardSystem
{
    /**
     * The reward amount.
     *
     * @var float
     */
    private $rewardAmount;
    private $reward_db;

    /**
     * Constructor.
     *
     * @param float $rewardAmount
     */
    public function __construct(APS_Reward_DB $reward_db, float $rewardAmount = 1.0)
    {
        $this->rewardAmount = $rewardAmount;
        $this->reward_db = $reward_db;
    }

    /**
     * Rewards a contributor.
     *
     * @param Contributor $contributor
     */
    public function reward(Contributor $contributor): bool
    {
        $contributor_id = $contributor->getId();
        $success = $this->reward_db->update_contributor_balance($contributor_id, $this->rewardAmount);

        if ($success) {
            error_log("Rewarding contributor {$contributor_id} with {$this->rewardAmount}. New balance: {$this->reward_db->get_contributor_balance($contributor_id)}");
            return true;
        } else {
            error_log("Failed to reward contributor {$contributor_id} with {$this->rewardAmount}.");
            return false;
        }
    }
}
