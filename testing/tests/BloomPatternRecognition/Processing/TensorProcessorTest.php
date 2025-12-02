<?php

use PHPUnit\Framework\TestCase;
use BLOOM\Processing\TensorProcessor;

class TensorProcessorTest extends TestCase {
    private $processor;

    protected function setUp(): void {
        $this->processor = new TensorProcessor();
    }

    public function testProcessTensor() {
        $tensorData = [
            'values' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]
        ];

        $result = $this->processor->process_tensor($tensorData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tensor_sku', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('processed', $result['status']);
    }

    public function testProcessPattern() {
        $patternData = [
            'type' => 'test_pattern',
            'features' => [
                'mean' => 5.5,
                'max' => 10,
                'min' => 1,
                'variance' => 8.25
            ]
        ];

        $result = $this->processor->process_pattern($patternData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pattern_hash', $result);
        $this->assertArrayHasKey('confidence', $result);
    }
}
