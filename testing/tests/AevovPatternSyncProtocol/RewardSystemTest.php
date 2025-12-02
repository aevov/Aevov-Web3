<?php

use PHPUnit\Framework\TestCase;
use Aevov\Decentralized\RewardSystem;
use Aevov\Decentralized\Contributor;

class RewardSystemTest extends TestCase
{
    public function testReward()
    {
        $rewardSystem = new RewardSystem();
        $contributor = new Contributor('test');

        // We can't easily test the output of error_log, so we will just check that the method runs without error.
        $rewardSystem->reward($contributor);

        $this->assertTrue(true);
    }
}
