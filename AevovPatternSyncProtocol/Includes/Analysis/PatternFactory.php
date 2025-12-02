<?php
// In includes/factory/class-pattern-factory.php
namespace APS\Analysis;

use APS\Analysis\PatternAnalyzer;
use APS\Analysis\SymbolicPatternAnalyzer;

class PatternFactory {
    private $pattern_analyzer;
    private $symbolic_analyzer;
    private $rule_engine;
    
    public function __construct(PatternAnalyzer $pattern_analyzer, SymbolicPatternAnalyzer $symbolic_analyzer, APS_Rule_Engine $rule_engine) {
        $this->pattern_analyzer = $pattern_analyzer;
        $this->symbolic_analyzer = $symbolic_analyzer;
        $this->rule_engine = $rule_engine;
    }
    
    public function create_pattern($data, $type = null) {
        // If type is explicitly specified, use that
        if ($type === 'symbolic') {
            $pattern = $this->symbolic_analyzer->analyze_pattern($data);
            $pattern['type'] = 'symbolic_pattern';
        } 
        // Otherwise, try to determine the best pattern type based on data
        elseif ($this->is_symbolic_data($data)) {
            $pattern = $this->symbolic_analyzer->analyze_pattern($data);
            $pattern['type'] = 'symbolic_pattern';
        }
        // Default to standard pattern analysis
        else {
            $pattern = $this->pattern_analyzer->analyze_pattern($data);
            // Ensure type is set
            if (!isset($pattern['type'])) {
                $pattern['type'] = 'neural_pattern';
            }
        }
        
        // Validate the pattern before returning
        $validation = $this->rule_engine->validate_pattern($pattern);
        if ($validation !== true) {
            // Log validation errors but still return the pattern
            error_log('Pattern validation failed: ' . json_encode($validation));
        }
        
        return $pattern;
    }
    
    private function is_symbolic_data($data) {
        // Determine if data is better represented as a symbolic pattern
        // This could check for structured data with meaningful keys, etc.
        if (is_array($data) && !$this->is_numeric_array($data)) {
            return true;
        }
        
        return false;
    }
    
    private function is_numeric_array($array) {
        if (!is_array($array)) {
            return false;
        }
        
        // Check if array keys are sequential integers
        return array_keys($array) === range(0, count($array) - 1);
    }
}