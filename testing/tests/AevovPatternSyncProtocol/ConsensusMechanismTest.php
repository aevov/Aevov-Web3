<?php

use PHPUnit\Framework\TestCase;
use Aevov\Decentralized\ConsensusMechanism;

class ConsensusMechanismTest extends TestCase
{
    public function testProofOfWork()
    {
        $consensus = new ConsensusMechanism(1);
        $proof = $consensus->proofOfWork(1);

        $this->assertTrue($consensus->validProof(1, $proof));
    }

    public function testValidProof()
    {
        $consensus = new ConsensusMechanism(1);

        $this->assertFalse($consensus->validProof(1, 2));
        $this->assertTrue($consensus->validProof(1, 35293));
    }
}
