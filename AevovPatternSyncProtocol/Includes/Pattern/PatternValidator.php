<?php
/**
 * Pattern validation and integrity checking
 * 
 * @package APS
 * @subpackage Pattern
 */

namespace APS\Pattern;

use APS\Core\Logger;

class PatternValidator {
    private $logger;
    private $validation_rules = [];
    private $error_messages = [];
    
    public function __construct() {
        $this->logger = Logger::get_instance();
        $this->init_validation_rules();
    }
    
    private function init_validation_rules() {
        $this->validation_rules = [
            'pattern_structure' => [$this, 'validate_pattern_structure'],
            'chunk_integrity' => [$this, 'validate_chunk_integrity'],
            'tensor_format' => [$this, 'validate_tensor_format'],
            'sequence_validity' => [$this, 'validate_sequence_validity'],
            'attention_mask' => [$this, 'validate_attention_mask'],
            'position_ids' => [$this, 'validate_position_ids'],
            'pattern_metadata' => [$this, 'validate_pattern_metadata'],
            'chunk_relationships' => [$this, 'validate_chunk_relationships'],
            'symbolic_pattern' => [$this, 'validate_symbolic_pattern']
        ];
    }
    
    public function validate_pattern($pattern_data, $rules = null) {
        $this->error_messages = [];
        
        if ($rules === null) {
            $rules = array_keys($this->validation_rules);
        }
        
        $validation_results = [];
        
        foreach ($rules as $rule) {
            if (isset($this->validation_rules[$rule])) {
                try {
                    $result = call_user_func($this->validation_rules[$rule], $pattern_data);
                    $validation_results[$rule] = $result;
                    
                    if (!$result['valid']) {
                        $this->error_messages[] = $result['message'];
                    }
                } catch (Exception $e) {
                    $validation_results[$rule] = [
                        'valid' => false,
                        'message' => "Validation error for rule '{$rule}': " . $e->getMessage()
                    ];
                    $this->error_messages[] = $validation_results[$rule]['message'];
                }
            }
        }
        
        $is_valid = empty($this->error_messages);
        
        $this->logger->debug("Pattern validation result: " . ($is_valid ? 'VALID' : 'INVALID'));
        if (!$is_valid) {
            $this->logger->warning("Pattern validation errors: " . implode('; ', $this->error_messages));
        }
        
        return [
            'valid' => $is_valid,
            'errors' => $this->error_messages,
            'details' => $validation_results
        ];
    }
    
    private function validate_pattern_structure($pattern_data) {
        // Check for required fields
        $required_fields = ['pattern_id', 'pattern_type'];
        
        foreach ($required_fields as $field) {
            if (!isset($pattern_data[$field])) {
                return [
                    'valid' => false,
                    'message' => "Missing required field: {$field}"
                ];
            }
        }
        
        // Different validation based on pattern type
        $pattern_type = $pattern_data['pattern_type'];
        
        switch ($pattern_type) {
            case 'symbolic_pattern':
                // Symbolic patterns don't require chunks
                $additional_required_fields = ['symbols', 'relations', 'rules'];
                break;
                
            case 'tensor_pattern':
            default:
                // Tensor patterns require chunks
                $additional_required_fields = ['chunks'];
                break;
        }
        
        foreach ($additional_required_fields as $field) {
            if (!isset($pattern_data[$field])) {
                return [
                    'valid' => false,
                    'message' => "Missing required field for {$pattern_type}: {$field}"
                ];
            }
        }
        
        // Validate chunks if they exist
        if (isset($pattern_data['chunks'])) {
            if (!is_array($pattern_data['chunks']) || empty($pattern_data['chunks'])) {
                return [
                    'valid' => false,
                    'message' => "Pattern must contain at least one chunk"
                ];
            }
        }
        
        // Validate symbolic fields if they exist
        if (isset($pattern_data['symbols'])) {
            if (!is_array($pattern_data['symbols'])) {
                return [
                    'valid' => false,
                    'message' => "Symbols field must be an array"
                ];
            }
        }
        
        if (isset($pattern_data['relations'])) {
            if (!is_array($pattern_data['relations'])) {
                return [
                    'valid' => false,
                    'message' => "Relations field must be an array"
                ];
            }
        }
        
        if (isset($pattern_data['rules'])) {
            if (!is_array($pattern_data['rules'])) {
                return [
                    'valid' => false,
                    'message' => "Rules field must be an array"
                ];
            }
        }
        
        return ['valid' => true, 'message' => 'Pattern structure is valid'];
    }
    
    private function validate_chunk_integrity($pattern_data) {
        if (!isset($pattern_data['chunks']) || !is_array($pattern_data['chunks'])) {
            return [
                'valid' => false,
                'message' => "No chunks found for integrity validation"
            ];
        }
        
        $chunk_ids = [];
        foreach ($pattern_data['chunks'] as $chunk) {
            if (!isset($chunk['chunk_id'])) {
                return [
                    'valid' => false,
                    'message' => "Chunk missing chunk_id"
                ];
            }
            
            if (in_array($chunk['chunk_id'], $chunk_ids)) {
                return [
                    'valid' => false,
                    'message' => "Duplicate chunk_id found: {$chunk['chunk_id']}"
                ];
            }
            
            $chunk_ids[] = $chunk['chunk_id'];
        }
        
        return ['valid' => true, 'message' => 'Chunk integrity validated'];
    }
    
    private function validate_tensor_format($pattern_data) {
        if (!isset($pattern_data['chunks'])) {
            return ['valid' => true, 'message' => 'No chunks to validate tensor format'];
        }
        
        foreach ($pattern_data['chunks'] as $chunk) {
            $tensor_fields = ['sequence', 'attention_mask', 'position_ids'];
            
            foreach ($tensor_fields as $field) {
                if (!isset($chunk[$field])) {
                    return [
                        'valid' => false,
                        'message' => "Chunk {$chunk['chunk_id']} missing tensor field: {$field}"
                    ];
                }
                
                if (!is_array($chunk[$field])) {
                    return [
                        'valid' => false,
                        'message' => "Chunk {$chunk['chunk_id']} tensor field {$field} must be an array"
                    ];
                }
            }
        }
        
        return ['valid' => true, 'message' => 'Tensor format validated'];
    }
    
    private function validate_sequence_validity($pattern_data) {
        if (!isset($pattern_data['chunks'])) {
            return ['valid' => true, 'message' => 'No chunks to validate sequences'];
        }
        
        foreach ($pattern_data['chunks'] as $chunk) {
            if (!isset($chunk['sequence']) || !is_array($chunk['sequence'])) {
                continue;
            }
            
            // Check for valid token IDs (should be integers)
            foreach ($chunk['sequence'] as $token) {
                if (!is_int($token) || $token < 0) {
                    return [
                        'valid' => false,
                        'message' => "Invalid token in chunk {$chunk['chunk_id']}: {$token}"
                    ];
                }
            }
            
            // Check sequence length
            if (count($chunk['sequence']) === 0) {
                return [
                    'valid' => false,
                    'message' => "Empty sequence in chunk {$chunk['chunk_id']}"
                ];
            }
            
            if (count($chunk['sequence']) > 10000) { // Max reasonable sequence length
                return [
                    'valid' => false,
                    'message' => "Sequence too long in chunk {$chunk['chunk_id']}"
                ];
            }
        }
        
        return ['valid' => true, 'message' => 'Sequence validity validated'];
    }
    
    private function validate_attention_mask($pattern_data) {
        if (!isset($pattern_data['chunks'])) {
            return ['valid' => true, 'message' => 'No chunks to validate attention masks'];
        }
        
        foreach ($pattern_data['chunks'] as $chunk) {
            if (!isset($chunk['attention_mask']) || !isset($chunk['sequence'])) {
                continue;
            }
            
            $mask_length = count($chunk['attention_mask']);
            $sequence_length = count($chunk['sequence']);
            
            if ($mask_length !== $sequence_length) {
                return [
                    'valid' => false,
                    'message' => "Attention mask length mismatch in chunk {$chunk['chunk_id']}: mask={$mask_length}, sequence={$sequence_length}"
                ];
            }
            
            // Check mask values (should be 0 or 1)
            foreach ($chunk['attention_mask'] as $mask_value) {
                if (!in_array($mask_value, [0, 1], true)) {
                    return [
                        'valid' => false,
                        'message' => "Invalid attention mask value in chunk {$chunk['chunk_id']}: {$mask_value}"
                    ];
                }
            }
        }
        
        return ['valid' => true, 'message' => 'Attention mask validated'];
    }
    
    private function validate_position_ids($pattern_data) {
        if (!isset($pattern_data['chunks'])) {
            return ['valid' => true, 'message' => 'No chunks to validate position IDs'];
        }
        
        foreach ($pattern_data['chunks'] as $chunk) {
            if (!isset($chunk['position_ids']) || !isset($chunk['sequence'])) {
                continue;
            }
            
            $position_length = count($chunk['position_ids']);
            $sequence_length = count($chunk['sequence']);
            
            if ($position_length !== $sequence_length) {
                return [
                    'valid' => false,
                    'message' => "Position IDs length mismatch in chunk {$chunk['chunk_id']}: positions={$position_length}, sequence={$sequence_length}"
                ];
            }
            
            // Check position values (should be sequential integers starting from 0)
            foreach ($chunk['position_ids'] as $index => $position) {
                if (!is_int($position) || $position !== $index) {
                    return [
                        'valid' => false,
                        'message' => "Invalid position ID in chunk {$chunk['chunk_id']} at index {$index}: expected {$index}, got {$position}"
                    ];
                }
            }
        }
        
        return ['valid' => true, 'message' => 'Position IDs validated'];
    }
    
    private function validate_pattern_metadata($pattern_data) {
        $metadata_fields = ['created_at', 'confidence_score', 'source'];
        
        foreach ($metadata_fields as $field) {
            if (isset($pattern_data[$field])) {
                switch ($field) {
                    case 'confidence_score':
                        $score = floatval($pattern_data[$field]);
                        if ($score < 0 || $score > 1) {
                            return [
                                'valid' => false,
                                'message' => "Invalid confidence score: {$score} (must be between 0 and 1)"
                            ];
                        }
                        break;
                        
                    case 'created_at':
                        if (!strtotime($pattern_data[$field])) {
                            return [
                                'valid' => false,
                                'message' => "Invalid created_at timestamp: {$pattern_data[$field]}"
                            ];
                        }
                        break;
                }
            }
        }
        
        return ['valid' => true, 'message' => 'Pattern metadata validated'];
    }
    
    private function validate_chunk_relationships($pattern_data) {
        if (!isset($pattern_data['chunks']) || count($pattern_data['chunks']) < 2) {
            return ['valid' => true, 'message' => 'Not enough chunks to validate relationships'];
        }
        
        $chunks = $pattern_data['chunks'];
        
        // Sort chunks by chunk_id for sequential validation
        usort($chunks, function($a, $b) {
            return $a['chunk_id'] <=> $b['chunk_id'];
        });
        
        for ($i = 1; $i < count($chunks); $i++) {
            $prev_chunk = $chunks[$i - 1];
            $curr_chunk = $chunks[$i];
            
            // Check for overlap validation if overlap field exists
            if (isset($prev_chunk['overlap']) && isset($curr_chunk['overlap'])) {
                $expected_overlap = $prev_chunk['overlap'];
                
                if (isset($prev_chunk['sequence']) && isset($curr_chunk['sequence'])) {
                    $prev_sequence = $prev_chunk['sequence'];
                    $curr_sequence = $curr_chunk['sequence'];
                    
                    if ($expected_overlap > 0) {
                        $prev_end = array_slice($prev_sequence, -$expected_overlap);
                        $curr_start = array_slice($curr_sequence, 0, $expected_overlap);
                        
                        if ($prev_end !== $curr_start) {
                            return [
                                'valid' => false,
                                'message' => "Chunk overlap mismatch between chunks {$prev_chunk['chunk_id']} and {$curr_chunk['chunk_id']}"
                            ];
                        }
                    }
                }
            }
        }
        
        return ['valid' => true, 'message' => 'Chunk relationships validated'];
    }
    
    public function get_validation_errors() {
        return $this->error_messages;
    }
    
    public function add_custom_rule($name, $callback) {
        if (is_callable($callback)) {
            $this->validation_rules[$name] = $callback;
            return true;
        }
        return false;
    }
    
    public function remove_rule($name) {
        if (isset($this->validation_rules[$name])) {
            unset($this->validation_rules[$name]);
            return true;
        }
        return false;
    }
    
    public function get_available_rules() {
        return array_keys($this->validation_rules);
    }
    
    public function generate_validation_report($pattern_data, $validation_result) {
        $report = [
            'pattern_id' => $pattern_data['pattern_id'] ?? 'unknown',
            'pattern_type' => $pattern_data['pattern_type'] ?? 'unknown',
            'validation_timestamp' => date('Y-m-d H:i:s'),
            'is_valid' => $validation_result['valid'],
            'total_errors' => count($validation_result['errors']),
            'total_warnings' => isset($validation_result['warnings']) ? count($validation_result['warnings']) : 0,
            'errors' => $validation_result['errors'],
            'warnings' => $validation_result['warnings'] ?? [],
            'validation_details' => $validation_result['details']
        ];
        
        // Add pattern statistics
        if (isset($pattern_data['chunks'])) {
            $report['statistics']['total_chunks'] = count($pattern_data['chunks']);
            
            $total_tokens = 0;
            foreach ($pattern_data['chunks'] as $chunk) {
                if (isset($chunk['sequence'])) {
                    $total_tokens += count($chunk['sequence']);
                }
            }
            $report['statistics']['total_tokens'] = $total_tokens;
        }
        
        if (isset($pattern_data['symbols'])) {
            $report['statistics']['total_symbols'] = count($pattern_data['symbols']);
        }
        
        if (isset($pattern_data['relations'])) {
            $report['statistics']['total_relations'] = count($pattern_data['relations']);
        }
        
        if (isset($pattern_data['rules'])) {
            $report['statistics']['total_rules'] = count($pattern_data['rules']);
        }
        
        // Log the report
        if ($validation_result['valid']) {
            $this->logger->info("Pattern validation report - VALID", $report);
        } else {
            $this->logger->warning("Pattern validation report - INVALID", $report);
        }
        
        return $report;
    }
    
    public function validate_single_chunk($chunk_data) {
        $pattern_data = [
            'pattern_id' => 'single_chunk_validation',
            'pattern_type' => 'validation',
            'chunks' => [$chunk_data]
        ];
        
        $rules = ['chunk_integrity', 'tensor_format', 'sequence_validity', 'attention_mask', 'position_ids'];
        return $this->validate_pattern($pattern_data, $rules);
    }
    
    public function validate_pattern_complexity($pattern_data, $max_complexity = 1000) {
        $complexity_score = 0;
        
        // Calculate complexity based on various factors
        if (isset($pattern_data['chunks']) && is_array($pattern_data['chunks'])) {
            $complexity_score += count($pattern_data['chunks']) * 10;
            
            foreach ($pattern_data['chunks'] as $chunk) {
                if (isset($chunk['sequence'])) {
                    $complexity_score += count($chunk['sequence']);
                }
            }
        }
        
        if (isset($pattern_data['symbols']) && is_array($pattern_data['symbols'])) {
            $complexity_score += count($pattern_data['symbols']) * 5;
        }
        
        if (isset($pattern_data['relations']) && is_array($pattern_data['relations'])) {
            $complexity_score += count($pattern_data['relations']) * 3;
        }
        
        if (isset($pattern_data['rules']) && is_array($pattern_data['rules'])) {
            $complexity_score += count($pattern_data['rules']) * 2;
        }
        
        $is_complex = $complexity_score > $max_complexity;
        
        return [
            'complexity_score' => $complexity_score,
            'is_complex' => $is_complex,
            'max_allowed' => $max_complexity
        ];
    }
    
    public function validate_pattern_consistency($pattern_data) {
        $validation_result = $this->validate_pattern($pattern_data);
        
        if (!$validation_result['valid']) {
            return $validation_result;
        }
        
        // Additional consistency checks
        $inconsistencies = [];
        
        // Check if tensor patterns have consistent chunk sizes
        if (isset($pattern_data['chunks']) && is_array($pattern_data['chunks'])) {
            $chunk_sizes = [];
            foreach ($pattern_data['chunks'] as $chunk) {
                if (isset($chunk['sequence'])) {
                    $chunk_sizes[] = count($chunk['sequence']);
                }
            }
            
            if (!empty($chunk_sizes)) {
                $unique_sizes = array_unique($chunk_sizes);
                if (count($unique_sizes) > 1) {
                    $inconsistencies[] = "Inconsistent chunk sizes: " . implode(', ', $unique_sizes);
                }
            }
        }
        
        // Check if symbolic patterns have consistent symbol types
        if (isset($pattern_data['symbols']) && is_array($pattern_data['symbols'])) {
            $symbol_types = [];
            foreach ($pattern_data['symbols'] as $symbol) {
                if (isset($symbol['type'])) {
                    $symbol_types[] = $symbol['type'];
                }
            }
            
            if (!empty($symbol_types)) {
                $unique_types = array_unique($symbol_types);
                if (count($unique_types) > 5) { // Arbitrary threshold
                    $inconsistencies[] = "High variety of symbol types: " . count($unique_types);
                }
            }
        }
        
        if (!empty($inconsistencies)) {
            $this->logger->warning("Pattern consistency issues found: " . implode('; ', $inconsistencies));
            
            return [
                'valid' => true, // Pattern is still valid, but with consistency warnings
                'errors' => [],
                'warnings' => $inconsistencies,
                'details' => $validation_result['details']
            ];
        }
        
        return $validation_result;
    }
    
    private function validate_symbolic_pattern($pattern_data) {
        // Check if this is a symbolic pattern
        if (!isset($pattern_data['pattern_type']) || $pattern_data['pattern_type'] !== 'symbolic_pattern') {
            return ['valid' => true, 'message' => 'Not a symbolic pattern, skipping validation'];
        }
        
        // Required fields for symbolic patterns
        $required_fields = ['symbols', 'relations', 'rules'];
        
        foreach ($required_fields as $field) {
            if (!isset($pattern_data[$field])) {
                return [
                    'valid' => false,
                    'message' => "Missing required field for symbolic pattern: {$field}"
                ];
            }
        }
        
        // Validate symbols structure
        if (!is_array($pattern_data['symbols'])) {
            return [
                'valid' => false,
                'message' => "Symbols field must be an array"
            ];
        }
        
        // Validate relations structure
        if (!is_array($pattern_data['relations'])) {
            return [
                'valid' => false,
                'message' => "Relations field must be an array"
            ];
        }
        
        // Validate rules structure
        if (!is_array($pattern_data['rules'])) {
            return [
                'valid' => false,
                'message' => "Rules field must be an array"
            ];
        }
        
        // Additional validation for symbols
        foreach ($pattern_data['symbols'] as $symbol_key => $symbol) {
            if (!is_array($symbol)) {
                return [
                    'valid' => false,
                    'message' => "Symbol '{$symbol_key}' must be an array"
                ];
            }
            
            if (!isset($symbol['type']) || !isset($symbol['value'])) {
                return [
                    'valid' => false,
                    'message' => "Symbol '{$symbol_key}' missing required fields 'type' or 'value'"
                ];
            }
        }
        
        // Additional validation for relations
        foreach ($pattern_data['relations'] as $relation) {
            if (!is_array($relation)) {
                return [
                    'valid' => false,
                    'message' => "Each relation must be an array"
                ];
            }
            
            if (!isset($relation['source']) || !isset($relation['target']) || !isset($relation['type'])) {
                return [
                    'valid' => false,
                    'message' => "Relation missing required fields 'source', 'target', or 'type'"
                ];
            }
        }
        
        // Additional validation for rules
        foreach ($pattern_data['rules'] as $rule) {
            if (!is_array($rule)) {
                return [
                    'valid' => false,
                    'message' => "Each rule must be an array"
                ];
            }
            
            if (!isset($rule['antecedent']) || !isset($rule['consequent'])) {
                return [
                    'valid' => false,
                    'message' => "Rule missing required fields 'antecedent' or 'consequent'"
                ];
            }
        }
        
        return ['valid' => true, 'message' => 'Symbolic pattern validated'];
    }
}