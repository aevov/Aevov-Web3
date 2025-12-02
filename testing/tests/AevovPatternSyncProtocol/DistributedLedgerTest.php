<?php

use PHPUnit\Framework\TestCase;
use Aevov\Decentralized\DistributedLedger;

class DistributedLedgerTest extends TestCase
{
    public function testNewBlock()
    {
        $ledger = new DistributedLedger();
        $ledger->newTransaction('sender', 'recipient', 1.0);
        $block = $ledger->newBlock(123, 'previous_hash');

        $this->assertEquals(2, $block['index']);
        $this->assertEquals(123, $block['proof']);
        $this->assertEquals('previous_hash', $block['previous_hash']);
    }

    public function testNewTransaction()
    {
        $ledger = new DistributedLedger();
        $index = $ledger->newTransaction('sender', 'recipient', 1.0);

        $this->assertEquals(2, $index);
    }

    public function testLastBlock()
    {
        $ledger = new DistributedLedger();
        $ledger->newBlock(123, 'previous_hash');
        $lastBlock = $ledger->lastBlock();

        $this->assertEquals(2, $lastBlock['index']);
    }

    public function testHash()
    {
        $block = [
            'index' => 1,
            'timestamp' => 1234567890,
            'transactions' => [],
            'proof' => 123,
            'previous_hash' => 'previous_hash',
        ];

        $hash = DistributedLedger::hash($block);

        $this->assertEquals('c2c2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2f2', $hash);
    }
}
