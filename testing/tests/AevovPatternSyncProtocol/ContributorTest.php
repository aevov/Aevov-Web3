<?php

use PHPUnit\Framework\TestCase;
use Aevov\Decentralized\Contributor;

class ContributorTest extends TestCase
{
    public function testGetId()
    {
        $contributor = new Contributor('test');

        $this->assertEquals('test', $contributor->getId());
    }
}
