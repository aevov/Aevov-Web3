<?php

use PHPUnit\Framework\TestCase;
use Aevov\Decentralized\ProofOfContribution;
use Aevov\Decentralized\Contribution;
use Aevov\Decentralized\Contributor;

class ProofOfContributionTest extends TestCase
{
    public function testSubmitContribution()
    {
        $poc = new ProofOfContribution();
        $contributor = new Contributor('test');
        $contribution = new Contribution($contributor, 'test data');

        $result = $poc->submitContribution($contribution);

        $this->assertTrue($result);
    }
}
