<?php
/**
 * Rule Engine for APS
 * 
 * Provides a centralized way to manage and apply rules to patterns
 * 
 * @package APS
 * @subpackage Rules
 */

class APS_Rule_Engine {
    private $rules;
    
    public function __construct(APS_Rules $rules) {
        $this->rules = new APS_Rules();
    }
    
    public function validate_pattern($pattern) {
        return $this->rules->validate_pattern($pattern);
    }
    
    public function validate_comparison_result($result) {
        return $this->rules->validate_comparison_result($result);
    }
    
    public function add_rule($name, $callback) {
        $this->rules->add_rule($name, $callback);
    }
}