<?php

use PHPUnit\Framework\TestCase;
use Aevov\Decentralized\Contribution;
use Aevov\Decentralized\Contributor;

class ContributionTest extends TestCase
{
    public function testGetContributor()
    {
        $contributor = new Contributor('test');
        $contribution = new Contribution($contributor, 'test data');

        $this->assertEquals($contributor, $contribution->getContributor());
    }

    public function testGetData()
    {
        $contributor = new Contributor('test');
        $contribution = new Contribution($contributor, 'test data');

        $this->assertEquals('test data', $contribution->getData());
    }
}
