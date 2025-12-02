<?php
/**
 * includes/rules/class-aps-rules.php
 */

class APS_Rules {
    private $rules = [];
    
    public function __construct() {
        $this->register_default_rules();
    }

    private function register_default_rules() {
        $this->add_rule('confidence_threshold', [$this, 'check_confidence_threshold']);
        $this->add_rule('minimum_similarity', [$this, 'check_minimum_similarity']);
        $this->add_rule('pattern_validity', [$this, 'validate_pattern']);
        $this->add_rule('dimensional_match', [$this, 'check_dimensional_match']);
    }

    public function add_rule($name, $callback) {
        $this->rules[$name] = $callback;
    }

    public function validate_comparison_result($result) {
        foreach ($this->rules as $name => $rule) {
            if (!call_user_func($rule, $result)) {
                throw new Exception("Comparison failed rule: {$name}");
            }
        }
        return $result;
    }
    
    public function get_pattern_type($pattern) {
        if (isset($pattern['type']) && $pattern['type'] === 'symbolic_pattern') {
            return 'symbolic';
        } elseif (isset($pattern['tensor_data']['features']) && 
                  is_array($pattern['tensor_data']['features']) && 
                  count($pattern['tensor_data']['features']) > 0 && 
                  is_numeric($pattern['tensor_data']['features'][0])) {
            return 'neural';
        } elseif (isset($pattern['tensor_name']) && 
                  isset($pattern['tensor_data']) && 
                  isset($pattern['shape']) &&
                  isset($pattern['dtype'])) {
            return 'tensor';
        }
        return 'unknown';
    }
    
    public function validate_pattern($pattern) {
        // Check basic structure first
        $basic_validation = $this->validate_basic_structure($pattern);
        if ($basic_validation !== true) {
            return $basic_validation;
        }
        
        // Then validate based on type
        $type = $this->get_pattern_type($pattern);
        switch ($type) {
            case 'symbolic':
                return $this->validate_symbolic_pattern($pattern);
            case 'neural':
                return $this->validate_neural_pattern($pattern);
            case 'tensor':
                return $this->validate_tensor_pattern($pattern);
            default:
                return ['error' => 'Unknown pattern type'];
        }
    }
    
    private function validate_basic_structure($pattern) {
        $required_fields = ['id', 'type', 'source'];
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (!isset($pattern[$field])) {
                $errors['missing_field'] = "Required field missing: {$field}";
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    public function validate_neural_pattern($pattern) {
        $errors = [];
        
        // Check for required neural pattern fields
        if (!isset($pattern['tensor_data']['features']) || !is_array($pattern['tensor_data']['features'])) {
            $errors['features'] = 'Neural pattern must contain features array';
        }
        
        return empty($errors) ? true : $errors;
    }
    
    public function validate_tensor_pattern($pattern) {
        $errors = [];
        
        // Check tensor structure
        $required_tensor_fields = [
            'tensor_name' => 'Tensor must have a name',
            'tensor_data' => 'Tensor must have data',
            'shape' => 'Tensor must have a shape',
            'dtype' => 'Tensor must have a data type'
        ];
        
        foreach ($required_tensor_fields as $field => $error) {
            if (!isset($pattern[$field]) || 
                (is_array($pattern[$field]) && empty($pattern[$field])) ||
                (!is_array($pattern[$field]) && empty($pattern[$field]))) {
                $errors[$field] = $error;
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    public function validate_symbolic_pattern($pattern) {
        $errors = [];
        
        // Validate each component
        $components = [
            'symbols' => ['id', 'type', 'properties'],
            'relations' => ['id', 'type', 'source', 'target'],
            'rules' => ['id', 'condition', 'consequence']
        ];
        
        foreach ($components as $component => $required_fields) {
            if (!isset($pattern[$component]) || !is_array($pattern[$component])) {
                $errors[$component] = "Symbolic pattern must contain {$component} array";
                continue;
            }
            
            // Special handling for relations
            $validators = [];
            if ($component === 'relations' && isset($pattern['symbols'])) {
                $symbol_ids = array_keys($pattern['symbols']);
                $validators[] = function($relation) use ($symbol_ids) {
                    return isset($relation['source']) && in_array($relation['source'], $symbol_ids) &&
                           isset($relation['target']) && in_array($relation['target'], $symbol_ids);
                };
            }
            
            $validation = $this->validate_component_items(
                $pattern[$component],
                $required_fields,
                $validators
            );
            
            if ($validation !== true) {
                $errors[$component] = $validation;
            }
        }
        
        // Check for rule contradictions if no other errors
        if (empty($errors) && isset($pattern['rules']) && is_array($pattern['rules'])) {
            $contradictions = $this->find_rule_contradictions($pattern['rules']);
            if (!empty($contradictions)) {
                $errors['rules'] = [
                    'error' => 'Contradictory rules found',
                    'contradictions' => $contradictions
                ];
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    private function validate_component_items($items, $required_fields, $validators = []) {
        $invalid_items = [];
        
        foreach ($items as $item_id => $item) {
            // Check required fields
            foreach ($required_fields as $field) {
                if (!isset($item[$field]) || 
                    (is_array($item[$field]) && empty($item[$field])) ||
                    (!is_array($item[$field]) && empty($item[$field]))) {
                    $invalid_items[] = $item_id;
                    continue 2; // Skip to next item
                }
            }
            
            // Apply additional validators
            foreach ($validators as $validator) {
                if (!call_user_func($validator, $item)) {
                    $invalid_items[] = $item_id;
                    break;
                }
            }
        }
        
        if (!empty($invalid_items)) {
            return [
                'error' => 'Invalid items found',
                'invalid_items' => $invalid_items
            ];
        }
        
        return true;
    }
    
    private function find_rule_contradictions($rules) {
        $contradictions = [];
        $conditions = [];
        
        // Normalize and group rules by condition
        foreach ($rules as $rule_id => $rule) {
            $condition_key = $this->normalize_string($rule['condition']);
            
            if (isset($conditions[$condition_key])) {
                // Check if consequences are contradictory
                if ($this->normalize_string($rule['consequence']) !== 
                    $this->normalize_string($rules[$conditions[$condition_key]]['consequence'])) {
                    $contradictions[] = [
                        'rule1' => $conditions[$condition_key],
                        'rule2' => $rule_id
                    ];
                }
            } else {
                $conditions[$condition_key] = $rule_id;
            }
        }
        
        return $contradictions;
    }
    
    private function normalize_string($string) {
        // Remove whitespace and convert to lowercase
        return strtolower(preg_replace('/\s+/', '', $string));
    }
    
    // Standard rule methods
    public function check_confidence_threshold($result) {
        $threshold = get_option('aps_confidence_threshold', 0.75);
        return $result['score'] >= $threshold;
    }

    public function check_minimum_similarity($result) {
        $threshold = get_option('aps_similarity_threshold', 0.6);
        return $result['similarity'] >= $threshold;
    }
    
    public function check_dimensional_match($result) {
        // Check if the result contains the source and target patterns
        if (!isset($result['source']) || !isset($result['target'])) {
            return false;
        }
    
        $source = $result['source'];
        $target = $result['target'];
    
        $source_type = $this->get_pattern_type($source);
        $target_type = $this->get_pattern_type($target);
    
        // Symbolic patterns don't require dimensional checks
        if ($source_type === 'symbolic' || $target_type === 'symbolic') {
            return true;
        }
    
        // Retrieve dimensions based on pattern type
        $source_dims = $this->get_dimensions($source, $source_type);
        $target_dims = $this->get_dimensions($target, $target_type);
    
        // Invalid if dimensions can't be determined
        if ($source_dims === null || $target_dims === null) {
            return false;
        }
    
        // Check cases based on pattern types
        if ($source_type === 'neural' && $target_type === 'neural') {
            // Both neural: compare feature vector lengths
            return $source_dims === $target_dims;
        } elseif ($source_type === 'tensor' && $target_type === 'tensor') {
            // Both tensors: compare shape arrays
            return $source_dims === $target_dims;
        } elseif (($source_type === 'neural' && $target_type === 'tensor') || ($source_type === 'tensor' && $target_type === 'neural')) {
            // Mixed types: check if tensor is 1D with matching length
            $neural_dims = $source_type === 'neural' ? $source_dims : $target_dims;
            $tensor_shape = $source_type === 'tensor' ? $source_dims : $target_dims;
            return count($tensor_shape) === 1 && $tensor_shape[0] === $neural_dims;
        }
    
        // Fallback for unknown cases
        return false;
    }
    
    private function get_dimensions($pattern, $type) {
        switch ($type) {
            case 'neural':
                return isset($pattern['tensor_data']['features']) ? count($pattern['tensor_data']['features']) : null;
            case 'tensor':
                return isset($pattern['shape']) ? $pattern['shape'] : null;
            default:
                return null;
        }
    }
}