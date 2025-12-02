<?php
namespace BLOOM\Utilities;

/**
 * Handles data validation and sanitization
 */
class DataValidator {
    private $validation_rules = [];
    
    public function __construct() {
        $this->init_validation_rules();
    }

    public function validate_tensor_data($data) {
        $required_fields = ['shape', 'dtype', 'values'];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        $this->validate_tensor_shape($data['shape']);
        $this->validate_tensor_dtype($data['dtype']);
        $this->validate_tensor_data_format($data['values']);
        
        return true;
    }

    public function validate_pattern_data($pattern) {
        $rules = $this->validation_rules['pattern'];
        
        foreach ($rules as $field => $validators) {
            if (!$this->validate_field($pattern[$field] ?? null, $validators)) {
                throw new Exception("Invalid pattern field: {$field}");
            }
        }
        
        return true;
    }

    private function init_validation_rules() {
        $this->validation_rules = [
            'pattern' => [
                'type' => ['required', 'in:sequential,structural,statistical'],
                'features' => ['required', 'array'],
                'confidence' => ['required', 'float', 'min:0', 'max:1'],
                'metadata' => ['array']
            ],
            'tensor' => [
                'dtype' => ['required', 'in:float16,float32,float64'],
                'shape' => ['required', 'array'],
                'values' => ['required', 'array'] // Changed from 'data' to 'values' and type from 'base64' to 'array'
            ]
        ];
    }

    private function validate_field($value, $validators) {
        foreach ($validators as $validator) {
            if (strpos($validator, ':') !== false) {
                [$rule, $param] = explode(':', $validator);
                if (!$this->{"validate_{$rule}"}($value, $param)) {
                    return false;
                }
            } else {
                if (!$this->{"validate_{$validator}"}($value)) {
                    return false;
                }
            }
        }
        return true;
    }

    private function validate_required($value) {
        return isset($value) && $value !== '' && $value !== null;
    }

    private function validate_tensor_shape($shape) {
        if (!is_array($shape)) {
            throw new \Exception("Shape must be an array");
        }

        foreach ($shape as $dim) {
            if (!is_int($dim) || $dim <= 0) {
                throw new \Exception("Invalid shape dimension");
            }
        }
        
        return true;
    }

    private function validate_tensor_dtype($dtype) {
        $valid_dtypes = ['float16', 'float32', 'float64', 'int8', 'int16', 'int32', 'int64'];
        
        if (!in_array($dtype, $valid_dtypes)) {
            throw new \Exception("Invalid tensor dtype: {$dtype}");
        }
        
        return true;
    }

    private function validate_tensor_data_format($values) {
        if (!is_array($values)) {
            throw new \Exception("Tensor data must be a numeric array");
        }
        
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                throw new \Exception("Tensor data must contain only numeric values");
            }
        }
        
        return true;
    }

    private function validate_array($value) {
        return is_array($value);
    }

    private function validate_float($value) {
        return is_float($value) || is_numeric($value);
    }

    private function validate_in($value, $options) {
        $valid_options = explode(',', $options);
        return in_array($value, $valid_options);
    }

    private function validate_min($value, $min) {
        return is_numeric($value) && $value >= $min;
    }

    private function validate_max($value, $max) {
        return is_numeric($value) && $value <= $max;
    }

    private function validate_base64($value) {
        return is_string($value) && base64_decode($value, true) !== false;
    }
}