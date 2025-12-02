<?php

use PHPUnit\Framework\TestCase;
use APS\Analysis\SymbolicPatternAnalyzer;

class SymbolicPatternAnalyzerTest extends TestCase {
    private $analyzer;

    protected function setUp(): void {
        $this->analyzer = new SymbolicPatternAnalyzer();
    }

    public function testComparePatternsWithStrings() {
        $pattern1 = "This is a test pattern.";
        $pattern2 = "This is a test pattern.";
        $this->assertEquals(1.0, $this->analyzer->comparePatterns($pattern1, $pattern2));

        $pattern3 = "This is a different pattern.";
        $this->assertLessThan(1.0, $this->analyzer->comparePatterns($pattern1, $pattern3));
    }

    public function testComparePatternsWithArrays() {
        $pattern1 = [1, 2, 3, 4, 5];
        $pattern2 = [1, 2, 3, 4, 5];
        $this->assertEquals(1.0, $this->analyzer->comparePatterns($pattern1, $pattern2));

        $pattern3 = [1, 2, 3, 6, 7];
        $this->assertLessThan(1.0, $this->analyzer->comparePatterns($pattern1, $pattern3));
    }

    public function testComparePatternsWithObjects() {
        $pattern1 = (object) ['a' => 1, 'b' => 2, 'c' => 3];
        $pattern2 = (object) ['a' => 1, 'b' => 2, 'c' => 3];
        $this->assertEquals(1.0, $this->analyzer->comparePatterns($pattern1, $pattern2));

        $pattern3 = (object) ['a' => 1, 'b' => 2, 'd' => 4];
        $this->assertLessThan(1.0, $this->analyzer->comparePatterns($pattern1, $pattern3));
    }
}
