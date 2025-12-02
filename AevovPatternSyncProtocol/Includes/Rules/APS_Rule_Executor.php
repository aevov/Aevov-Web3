<?php

class APS_Rule_Executor {
    private $rule_engine;
    
    public function __construct(APS_Rule_Engine $rule_engine) {
        $this->rule_engine = $rule_engine;
    }

    public function execute_rules($pattern, $data) {
        $validation = $this->rule_engine->validate_pattern($pattern);
        if ($validation !== true) {
            throw new Exception("Invalid pattern: " . json_encode($validation));
        }

        if (empty($pattern['rules'])) {
            return ['error' => 'No rules to execute'];
        }

        return array_map(fn($rule) => $this->execute_rule($rule, $data), $pattern['rules']);
    }

    private function execute_rule($rule, $data) {
        $condition_result = $this->evaluate_condition($rule['condition'], $data);
        
        return $condition_result ? [
            'triggered' => true,
            'result' => $this->apply_consequence($rule['consequence'], $data)
        ] : ['triggered' => false];
    }

    private function evaluate_condition($condition, $data) {
        $condition = preg_replace('/^if\s+/i', '', trim($condition));
        return $this->evaluate_parsed_condition(
            $this->parse_condition_expression($condition), 
            $data
        );
    }

    private function evaluate_parsed_condition($parsed_condition, $data) {
        // Handle logical operators
        if (isset($parsed_condition['op'])) {
            switch ($parsed_condition['op']) {
                case 'AND':
                    return $this->evaluate_parsed_condition($parsed_condition['left'], $data) && 
                           $this->evaluate_parsed_condition($parsed_condition['right'], $data);
                
                case 'OR':
                    return $this->evaluate_parsed_condition($parsed_condition['left'], $data) || 
                           $this->evaluate_parsed_condition($parsed_condition['right'], $data);
                
                case 'NOT':
                    return !$this->evaluate_parsed_condition($parsed_condition['right'], $data);
                
                case 'EXISTS':
                    return $this->get_data_value($parsed_condition['left'], $data) !== null;
                
                // Comparison operators
                case '==':
                    $left_value = $this->get_data_value($parsed_condition['left'], $data);
                    return $left_value == $parsed_condition['right'];
                
                case '!=':
                    $left_value = $this->get_data_value($parsed_condition['left'], $data);
                    return $left_value != $parsed_condition['right'];
                
                case '>':
                    $left_value = $this->get_data_value($parsed_condition['left'], $data);
                    return $left_value > $parsed_condition['right'];
                
                case '<':
                    $left_value = $this->get_data_value($parsed_condition['left'], $data);
                    return $left_value < $parsed_condition['right'];
                
                case '>=':
                    $left_value = $this->get_data_value($parsed_condition['left'], $data);
                    return $left_value >= $parsed_condition['right'];
                
                case '<=':
                    $left_value = $this->get_data_value($parsed_condition['left'], $data);
                    return $left_value <= $parsed_condition['right'];
                
                default:
                    throw new Exception("Unknown operator: {$parsed_condition['op']}");
            }
        }
        
        throw new Exception("Invalid parsed condition structure");
    }

    private function parse_condition_expression($condition) {
        if (preg_match('/^\((.*)\)$/', $condition, $matches)) {
            return $this->parse_condition_expression($matches[1]);
        }

        foreach (['NOT', 'AND', 'OR'] as $op) {
            if (preg_match("/^(.*?)\s+{$op}\s+(.*)$/i", $condition, $matches)) {
                return [
                    'op' => strtoupper($op),
                    'left' => $this->parse_condition_expression($matches[1]),
                    'right' => $this->parse_condition_expression($matches[2])
                ];
            }
        }

        return $this->parse_simple_condition($condition);
    }

    private function parse_simple_condition($condition) {
        $operators = ['==', '!=', '>=', '<=', '>', '<'];
        
        foreach ($operators as $op) {
            if (preg_match('/^(.*?)\s+'.preg_quote($op).'\s+(.*)$/i', $condition, $matches)) {
                return [
                    'op' => $op,
                    'left' => trim($matches[1]),
                    'right' => $this->parse_value(trim($matches[2]))
                ];
            }
        }

        return ['op' => 'EXISTS', 'left' => trim($condition)];
    }

    private function parse_value($value_str) {
        if (preg_match('/^[\'"](.*)[\'"]$/', $value_str, $matches)) return $matches[1];
        if (is_numeric($value_str)) return strpos($value_str, '.') ? (float)$value_str : (int)$value_str;
        switch (strtolower($value_str)) {
            case 'true': return true;
            case 'false': return false;
            case 'null': return null;
            default: return $value_str;
        }
    }

    private function apply_consequence($consequence, $data) {
        $actions = array_filter(array_map('trim', explode(';', $consequence)));
        $modified = $data;

        foreach ($actions as $action) {
            if (preg_match('/^set\s+(.+?)\s*=\s*(.+)$/i', $action, $m)) {
                $modified = $this->set_data_value(trim($m[1]), $this->parse_value(trim($m[2])), $modified);
            }
            elseif (preg_match('/^delete\s+(.+)$/i', $action, $m)) {
                $modified = $this->delete_data_value(trim($m[1]), $modified);
            }
        }

        return $modified;
    }

    private function get_data_value($path, $data) {
        foreach (explode('.', $path) as $part) {
            if (!is_array($data) || !isset($data[$part])) return null;
            $data = $data[$part];
        }
        return $data;
    }

    private function set_data_value($path, $value, $data) {
        $keys = explode('.', $path);
        $current = &$data;
        
        foreach ($keys as $key) {
            $current = &$current[$key];
        }
        $current = $value;
        return $data;
    }

    private function delete_data_value($path, $data) {
        $keys = explode('.', $path);
        $last = array_pop($keys);
        $current = &$data;
        
        foreach ($keys as $key) {
            if (!isset($current[$key])) return $data;
            $current = &$current[$key];
        }
        unset($current[$last]);
        return $data;
    }
}