<?php

namespace AevovWorkflowEngine\API;

if (!defined('ABSPATH')) {
    exit;
}

class WorkflowExecutor {

    private array $capabilities;
    private array $node_outputs = [];
    private array $execution_log = [];
    private int $max_execution_time;
    private float $start_time;

    public function __construct(array $capabilities) {
        $this->capabilities = $capabilities;
        $this->max_execution_time = (int)get_option('aevov_workflow_max_execution_time', 300);
    }

    public function execute(array $workflow, array $inputs = []): array {
        $this->start_time = microtime(true);
        $this->node_outputs = [];
        $this->execution_log = [];

        $nodes = $workflow['nodes'] ?? [];
        $edges = $workflow['edges'] ?? [];

        if (empty($nodes)) {
            return [
                'success' => false,
                'error' => 'Workflow has no nodes',
                'log' => $this->execution_log,
            ];
        }

        $node_map = [];
        foreach ($nodes as $node) {
            $node_map[$node['id']] = $node;
        }

        $incoming_edges = [];
        $outgoing_edges = [];
        foreach ($edges as $edge) {
            $incoming_edges[$edge['target']][] = $edge;
            $outgoing_edges[$edge['source']][] = $edge;
        }

        $execution_order = $this->topological_sort($nodes, $edges);
        if ($execution_order === null) {
            return [
                'success' => false,
                'error' => 'Workflow contains circular dependencies',
                'log' => $this->execution_log,
            ];
        }

        // Initialize with provided inputs
        $this->node_outputs = $inputs;

        foreach ($execution_order as $node_id) {
            if ($this->is_timeout()) {
                return [
                    'success' => false,
                    'error' => 'Workflow execution timed out',
                    'partial_outputs' => $this->node_outputs,
                    'log' => $this->execution_log,
                ];
            }

            $node = $node_map[$node_id];
            $label = $node['data']['label'] ?? $node_id;
            $this->log("Executing node: {$label}");

            try {
                $node_inputs = $this->gather_inputs($node_id, $incoming_edges);
                $output = $this->execute_node($node, $node_inputs);
                $this->node_outputs[$node_id] = $output;
                $this->log("Node {$label} completed", ['output_keys' => array_keys($output)]);
            } catch (\Exception $e) {
                $this->log("Node {$label} failed: " . $e->getMessage(), [], 'error');
                return [
                    'success' => false,
                    'error' => "Node '{$label}' failed: " . $e->getMessage(),
                    'failed_node' => $node_id,
                    'partial_outputs' => $this->node_outputs,
                    'log' => $this->execution_log,
                ];
            }
        }

        // Collect outputs from output nodes
        $output_nodes = array_filter($nodes, function($n) {
            return ($n['data']['nodeType'] ?? $n['type'] ?? '') === 'output';
        });

        $final_outputs = [];
        foreach ($output_nodes as $node) {
            $final_outputs[$node['id']] = $this->node_outputs[$node['id']] ?? null;
        }

        if (empty($final_outputs)) {
            $last_node = end($execution_order);
            $final_outputs['result'] = $this->node_outputs[$last_node] ?? null;
        }

        $execution_time = microtime(true) - $this->start_time;
        $this->log("Workflow completed in " . round($execution_time, 3) . "s");

        return [
            'success' => true,
            'outputs' => $final_outputs,
            'all_outputs' => $this->node_outputs,
            'execution_time' => $execution_time,
            'log' => $this->execution_log,
        ];
    }

    private function topological_sort(array $nodes, array $edges): ?array {
        $in_degree = [];
        $adjacency = [];

        foreach ($nodes as $node) {
            $in_degree[$node['id']] = 0;
            $adjacency[$node['id']] = [];
        }

        foreach ($edges as $edge) {
            if (isset($in_degree[$edge['target']])) {
                $in_degree[$edge['target']]++;
            }
            if (isset($adjacency[$edge['source']])) {
                $adjacency[$edge['source']][] = $edge['target'];
            }
        }

        $queue = [];
        foreach ($in_degree as $node_id => $degree) {
            if ($degree === 0) {
                $queue[] = $node_id;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $node_id = array_shift($queue);
            $sorted[] = $node_id;

            foreach ($adjacency[$node_id] as $neighbor) {
                $in_degree[$neighbor]--;
                if ($in_degree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        return count($sorted) === count($nodes) ? $sorted : null;
    }

    private function gather_inputs(string $node_id, array $incoming_edges): array {
        $inputs = [];

        if (isset($incoming_edges[$node_id])) {
            foreach ($incoming_edges[$node_id] as $edge) {
                $source_output = $this->node_outputs[$edge['source']] ?? [];
                $source_handle = $edge['sourceHandle'] ?? 'output';
                $target_handle = $edge['targetHandle'] ?? 'input';

                if (is_array($source_output) && isset($source_output[$source_handle])) {
                    $inputs[$target_handle] = $source_output[$source_handle];
                } else {
                    $inputs[$target_handle] = $source_output;
                }
            }
        }

        return $inputs;
    }

    private function execute_node(array $node, array $inputs): array {
        $node_type = $node['data']['nodeType'] ?? $node['type'] ?? 'unknown';
        $config = $node['data']['config'] ?? [];

        switch ($node_type) {
            case 'input':
                return $this->execute_input($inputs, $config);

            case 'output':
                return $this->execute_output($inputs, $config);

            case 'transform':
                return $this->execute_transform($inputs, $config);

            case 'condition':
                return $this->execute_condition($inputs, $config);

            case 'loop':
                return $this->execute_loop($inputs, $config);

            case 'merge':
                return ['output' => $inputs];

            case 'split':
                $data = $inputs['input'] ?? [];
                return is_array($data) ? $data : ['output' => $data];

            case 'delay':
                $seconds = min(30, max(0, intval($config['seconds'] ?? 1)));
                if ($seconds > 0) {
                    sleep($seconds);
                }
                return $inputs;

            case 'http':
                return $this->execute_http($inputs, $config);

            case 'code':
                return $this->execute_code($inputs, $config);

            default:
                // Check if it's an Aevov capability
                if (isset($this->capabilities[$node_type])) {
                    return $this->execute_capability($node_type, $inputs, $config);
                }
                throw new \Exception("Unknown node type: {$node_type}");
        }
    }

    private function execute_input(array $inputs, array $config): array {
        $value = $inputs['value'] ?? $config['defaultValue'] ?? null;

        // Parse JSON if configured
        if (is_string($value) && ($config['inputType'] ?? '') === 'json') {
            $parsed = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $parsed;
            }
        }

        return ['output' => $value];
    }

    private function execute_output(array $inputs, array $config): array {
        return ['result' => $inputs['input'] ?? $inputs];
    }

    private function execute_transform(array $inputs, array $config): array {
        $transform_type = $config['type'] ?? 'passthrough';
        $input_data = $inputs['input'] ?? $inputs;

        switch ($transform_type) {
            case 'json_parse':
                if (is_string($input_data)) {
                    $parsed = json_decode($input_data, true);
                    return ['output' => $parsed ?? $input_data];
                }
                return ['output' => $input_data];

            case 'json_stringify':
                return ['output' => json_encode($input_data)];

            case 'extract':
                $path = $config['path'] ?? '';
                return ['output' => $this->extract_path($input_data, $path)];

            case 'template':
                $template = $config['template'] ?? '{{input}}';
                $output = preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($inputs) {
                    $key = $matches[1];
                    $value = $inputs[$key] ?? '';
                    return is_scalar($value) ? $value : json_encode($value);
                }, $template);
                return ['output' => $output];

            case 'map':
                $expression = $config['expression'] ?? 'item';
                if (is_array($input_data)) {
                    $mapped = array_map(function($item) use ($expression) {
                        return $this->evaluate_expression($expression, ['item' => $item]);
                    }, $input_data);
                    return ['output' => $mapped];
                }
                return ['output' => $input_data];

            case 'filter':
                $condition = $config['condition'] ?? 'true';
                if (is_array($input_data)) {
                    $filtered = array_filter($input_data, function($item) use ($condition) {
                        return $this->evaluate_expression($condition, ['item' => $item]);
                    });
                    return ['output' => array_values($filtered)];
                }
                return ['output' => $input_data];

            case 'reduce':
                $expression = $config['expression'] ?? 'acc + item';
                $initial = $config['initial'] ?? 0;
                if (is_array($input_data)) {
                    $result = array_reduce($input_data, function($acc, $item) use ($expression) {
                        return $this->evaluate_expression($expression, ['acc' => $acc, 'item' => $item]);
                    }, $initial);
                    return ['output' => $result];
                }
                return ['output' => $input_data];

            default:
                return ['output' => $input_data];
        }
    }

    private function execute_condition(array $inputs, array $config): array {
        $condition = $config['condition'] ?? 'true';
        $input_data = $inputs['input'] ?? $inputs;

        $result = $this->evaluate_expression($condition, ['input' => $input_data, ...$inputs]);

        return $result
            ? ['true' => $input_data, 'output' => $input_data]
            : ['false' => $input_data, 'output' => $input_data];
    }

    private function execute_loop(array $inputs, array $config): array {
        $items = $inputs['items'] ?? $inputs['input'] ?? [];
        if (!is_array($items)) {
            $items = [$items];
        }

        $max = min(1000, intval($config['maxIterations'] ?? 100));
        $items = array_slice($items, 0, $max);

        $results = [];
        foreach ($items as $index => $item) {
            $results[] = ['index' => $index, 'item' => $item];
        }

        return ['output' => $results, 'count' => count($results)];
    }

    private function execute_http(array $inputs, array $config): array {
        $url = $config['url'] ?? '';
        $method = strtoupper($config['method'] ?? 'GET');
        $headers = $config['headers'] ?? [];
        $body = $inputs['body'] ?? $config['body'] ?? null;

        // Template substitution in URL
        $url = preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($inputs) {
            return urlencode($inputs[$matches[1]] ?? '');
        }, $url);

        if (empty($url)) {
            throw new \Exception('HTTP URL is required');
        }

        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => is_string($headers) ? json_decode($headers, true) : $headers,
        ];

        if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = is_string($body) ? $body : json_encode($body);
            if (!isset($args['headers']['Content-Type'])) {
                $args['headers']['Content-Type'] = 'application/json';
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \Exception('HTTP request failed: ' . $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $parsed = json_decode($body, true);

        return [
            'output' => $parsed ?? $body,
            'status' => $status,
            'headers' => wp_remote_retrieve_headers($response)->getAll(),
        ];
    }

    private function execute_code(array $inputs, array $config): array {
        // Limited code execution for safety
        $code = $config['code'] ?? '';
        $language = $config['language'] ?? 'expression';

        if ($language === 'expression') {
            return ['output' => $this->evaluate_expression($code, $inputs)];
        }

        // For security, only allow simple expressions
        throw new \Exception('Code execution is limited to expressions');
    }

    private function execute_capability(string $capability, array $inputs, array $config): array {
        $cap_config = $this->capabilities[$capability];

        if (!$cap_config['available']) {
            throw new \Exception("Capability '{$capability}' is not available");
        }

        $endpoint = $config['endpoint'] ?? ($cap_config['endpoints'][0]['route'] ?? '');
        $method = $config['method'] ?? 'POST';

        $params = array_merge($config['params'] ?? [], $inputs);

        $request = new \WP_REST_Request($method, '/' . $cap_config['namespace'] . $endpoint);

        if ($method === 'GET') {
            $request->set_query_params($params);
        } else {
            $request->set_body_params($params);
        }

        $response = rest_do_request($request);
        $data = $response->get_data();

        if ($response->get_status() >= 400) {
            $error = is_array($data) ? ($data['error'] ?? json_encode($data)) : $data;
            throw new \Exception("Capability {$capability} error: {$error}");
        }

        return ['output' => $data];
    }

    private function extract_path($data, string $path) {
        if (empty($path)) {
            return $data;
        }

        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            // Handle array index notation
            if (preg_match('/^(\w+)\[(\d+)\]$/', $segment, $matches)) {
                $key = $matches[1];
                $index = (int)$matches[2];
                if (is_array($current) && isset($current[$key][$index])) {
                    $current = $current[$key][$index];
                    continue;
                }
            }

            if (is_array($current) && isset($current[$segment])) {
                $current = $current[$segment];
            } elseif (is_object($current) && isset($current->$segment)) {
                $current = $current->$segment;
            } else {
                return null;
            }
        }

        return $current;
    }

    private function evaluate_expression(string $expression, array $context): mixed {
        if ($expression === 'true') return true;
        if ($expression === 'false') return false;
        if (is_numeric($expression)) return floatval($expression);

        // Simple comparison expressions
        if (preg_match('/^(\w+(?:\.\w+)*)\s*(===?|!==?|>=?|<=?|&&|\|\|)\s*(.+)$/', $expression, $matches)) {
            $left = $this->resolve_value($matches[1], $context);
            $operator = $matches[2];
            $right = $this->resolve_value(trim($matches[3]), $context);

            switch ($operator) {
                case '==':
                case '===':
                    return $left === $right || $left == $right;
                case '!=':
                case '!==':
                    return $left !== $right && $left != $right;
                case '>':
                    return $left > $right;
                case '>=':
                    return $left >= $right;
                case '<':
                    return $left < $right;
                case '<=':
                    return $left <= $right;
                case '&&':
                    return $left && $right;
                case '||':
                    return $left || $right;
            }
        }

        // Simple arithmetic
        if (preg_match('/^(\w+(?:\.\w+)*)\s*([+\-*\/])\s*(\w+(?:\.\w+)*)$/', $expression, $matches)) {
            $left = $this->resolve_value($matches[1], $context);
            $operator = $matches[2];
            $right = $this->resolve_value($matches[3], $context);

            switch ($operator) {
                case '+':
                    return $left + $right;
                case '-':
                    return $left - $right;
                case '*':
                    return $left * $right;
                case '/':
                    return $right != 0 ? $left / $right : 0;
            }
        }

        // Property access
        return $this->resolve_value($expression, $context);
    }

    private function resolve_value(string $expression, array $context): mixed {
        $expression = trim($expression, '"\'');

        // Numeric literal
        if (is_numeric($expression)) {
            return floatval($expression);
        }

        // Boolean literals
        if ($expression === 'true') return true;
        if ($expression === 'false') return false;
        if ($expression === 'null') return null;

        // Property path (e.g., input.data.name)
        return $this->extract_path($context, $expression) ?? $expression;
    }

    private function is_timeout(): bool {
        return (microtime(true) - $this->start_time) > $this->max_execution_time;
    }

    private function log(string $message, array $data = [], string $level = 'info'): void {
        $this->execution_log[] = [
            'timestamp' => microtime(true),
            'elapsed' => round(microtime(true) - $this->start_time, 3),
            'level' => $level,
            'message' => $message,
            'data' => $data,
        ];
    }
}
