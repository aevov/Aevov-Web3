#!/usr/bin/env php
<?php
/**
 * API Documentation Generator for Aevov Ecosystem
 *
 * Automatically generates OpenAPI/Swagger documentation for all REST endpoints
 *
 * Usage:
 *   php api-documentation.php
 *   php api-documentation.php --format=openapi
 *   php api-documentation.php --format=markdown
 *   php api-documentation.php --output=docs/api.json
 *
 * @package AevovDocumentation
 * @since 1.0.0
 */

// Set execution time
set_time_limit(300);
ini_set('memory_limit', '512M');

// ============================================================================
// API Documentation Generator Class
// ============================================================================

class API_Documentation_Generator {

    private $base_path;
    private $format = 'openapi'; // openapi, swagger, markdown, html
    private $output_file = null;
    private $endpoints = [];
    private $plugins = [];

    public function __construct($base_path) {
        $this->base_path = $base_path;
    }

    /**
     * Run the documentation generator
     */
    public function run() {
        $this->print_header("Aevov API Documentation Generator");

        // Parse command-line arguments
        $this->parse_args();

        // Discover all plugins
        $this->discover_plugins();

        // Scan for REST API endpoints
        $this->scan_endpoints();

        // Generate documentation
        $this->generate_documentation();

        $this->print_success("Documentation generated successfully!");
    }

    /**
     * Parse command-line arguments
     */
    private function parse_args() {
        global $argv;

        foreach ($argv as $arg) {
            if (strpos($arg, '--format=') === 0) {
                $this->format = substr($arg, 9);
            } elseif (strpos($arg, '--output=') === 0) {
                $this->output_file = substr($arg, 9);
            }
        }

        // Default output file based on format
        if (!$this->output_file) {
            $ext = $this->format === 'markdown' ? 'md' : ($this->format === 'html' ? 'html' : 'json');
            $this->output_file = "{$this->base_path}/documentation/api-reference.{$ext}";
        }
    }

    /**
     * Discover all plugins
     */
    private function discover_plugins() {
        $this->print_info("Discovering plugins...");

        $plugin_dirs = glob($this->base_path . '/aevov-*', GLOB_ONLYDIR);
        $plugin_dirs = array_merge($plugin_dirs, glob($this->base_path . '/bloom-*', GLOB_ONLYDIR));
        $plugin_dirs = array_merge($plugin_dirs, glob($this->base_path . '/aps-*', GLOB_ONLYDIR));

        foreach ($plugin_dirs as $dir) {
            $plugin_name = basename($dir);
            $this->plugins[$plugin_name] = $dir;
        }

        $this->print_success("Found " . count($this->plugins) . " plugins");
    }

    /**
     * Scan for REST API endpoints
     */
    private function scan_endpoints() {
        $this->print_info("Scanning for REST API endpoints...");

        foreach ($this->plugins as $plugin_name => $plugin_path) {
            $endpoints = $this->scan_plugin_endpoints($plugin_name, $plugin_path);
            $this->endpoints = array_merge($this->endpoints, $endpoints);
        }

        $this->print_success("Found " . count($this->endpoints) . " endpoints");
    }

    /**
     * Scan a single plugin for endpoints
     */
    private function scan_plugin_endpoints($plugin_name, $plugin_path) {
        $endpoints = [];

        // Find PHP files that might contain REST endpoints
        $files = $this->find_php_files($plugin_path);

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // Look for register_rest_route calls
            if (preg_match_all('/register_rest_route\s*\(\s*[\'"]([^\'"]*)[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]\s*,\s*(\[.*?\])/s', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $namespace = $match[1];
                    $route = $match[2];
                    $args_str = $match[3];

                    // Parse the arguments array
                    $endpoint_data = $this->parse_endpoint_args($namespace, $route, $args_str, $file);
                    $endpoint_data['plugin'] = $plugin_name;

                    $endpoints[] = $endpoint_data;
                }
            }
        }

        return $endpoints;
    }

    /**
     * Parse endpoint arguments
     */
    private function parse_endpoint_args($namespace, $route, $args_str, $file) {
        $endpoint = [
            'namespace' => $namespace,
            'route' => $route,
            'full_path' => "/{$namespace}{$route}",
            'methods' => [],
            'args' => [],
            'description' => '',
            'file' => str_replace($this->base_path . '/', '', $file),
        ];

        // Extract methods
        if (preg_match('/[\'"]methods[\'"]\s*=>\s*(?:WP_REST_Server::)?([A-Z_]+)/', $args_str, $method_match)) {
            $methods_map = [
                'READABLE' => 'GET',
                'CREATABLE' => 'POST',
                'EDITABLE' => 'POST,PUT,PATCH',
                'DELETABLE' => 'DELETE',
                'ALLMETHODS' => 'GET,POST,PUT,DELETE,PATCH',
            ];

            $method = $method_match[1];
            $endpoint['methods'] = explode(',', $methods_map[$method] ?? $method);
        }

        // Extract callback to find documentation
        if (preg_match('/[\'"]callback[\'"]\s*=>\s*[\'"]?([a-zA-Z_][a-zA-Z0-9_]*)/', $args_str, $callback_match)) {
            $callback = $callback_match[1];

            // Try to find the callback function and extract PHPDoc
            $file_content = file_get_contents($file);
            $pattern = '/\/\*\*(.*?)\*\/\s*(?:public\s+)?function\s+' . preg_quote($callback, '/') . '/s';

            if (preg_match($pattern, $file_content, $doc_match)) {
                $phpdoc = $doc_match[1];

                // Extract description
                if (preg_match('/@description\s+(.+?)(?=@|$)/s', $phpdoc, $desc_match)) {
                    $endpoint['description'] = trim($desc_match[1]);
                } elseif (preg_match('/^\s*\*\s*(.+?)(?=@|$)/s', $phpdoc, $desc_match)) {
                    $endpoint['description'] = trim($desc_match[1]);
                }

                // Extract parameters
                if (preg_match_all('/@param\s+(\S+)\s+\$(\S+)\s+(.+)/s', $phpdoc, $param_matches, PREG_SET_ORDER)) {
                    foreach ($param_matches as $param) {
                        $endpoint['args'][] = [
                            'name' => $param[2],
                            'type' => $param[1],
                            'description' => trim($param[3]),
                        ];
                    }
                }
            }
        }

        // Extract schema/args definition
        if (preg_match('/[\'"]args[\'"]\s*=>\s*\[(.*?)\]/s', $args_str, $schema_match)) {
            // This would parse the args array - simplified for now
        }

        return $endpoint;
    }

    /**
     * Find all PHP files in a directory
     */
    private function find_php_files($dir) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Generate documentation
     */
    private function generate_documentation() {
        $this->print_info("Generating {$this->format} documentation...");

        $doc_content = '';

        switch ($this->format) {
            case 'openapi':
            case 'swagger':
                $doc_content = $this->generate_openapi();
                break;
            case 'markdown':
                $doc_content = $this->generate_markdown();
                break;
            case 'html':
                $doc_content = $this->generate_html();
                break;
        }

        // Ensure output directory exists
        $output_dir = dirname($this->output_file);
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }

        file_put_contents($this->output_file, $doc_content);

        $this->print_success("Documentation saved to: {$this->output_file}");
    }

    /**
     * Generate OpenAPI documentation
     */
    private function generate_openapi() {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Aevov Ecosystem API',
                'description' => 'REST API documentation for the Aevov WordPress plugin ecosystem',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Aevov Development Team',
                    'url' => 'https://aevov.dev',
                ],
            ],
            'servers' => [
                [
                    'url' => 'http://localhost:8080/wp-json',
                    'description' => 'Development server',
                ],
                [
                    'url' => 'https://your-domain.com/wp-json',
                    'description' => 'Production server',
                ],
            ],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'cookieAuth' => [
                        'type' => 'apiKey',
                        'in' => 'cookie',
                        'name' => 'wordpress_logged_in',
                    ],
                    'basicAuth' => [
                        'type' => 'http',
                        'scheme' => 'basic',
                    ],
                ],
            ],
        ];

        // Group endpoints by plugin
        $grouped_endpoints = [];
        foreach ($this->endpoints as $endpoint) {
            $grouped_endpoints[$endpoint['plugin']][] = $endpoint;
        }

        // Convert endpoints to OpenAPI paths
        foreach ($this->endpoints as $endpoint) {
            $path = $endpoint['full_path'];

            // Convert WordPress route params to OpenAPI format
            $path = preg_replace('/\(\?P<([^>]+)>[^)]+\)/', '{$1}', $path);

            foreach ($endpoint['methods'] as $method) {
                $method_lower = strtolower($method);

                $spec['paths'][$path][$method_lower] = [
                    'summary' => $endpoint['description'] ?: "Endpoint for {$endpoint['route']}",
                    'description' => $endpoint['description'],
                    'tags' => [$endpoint['plugin']],
                    'operationId' => $endpoint['plugin'] . '_' . str_replace(['/', '{', '}'], '_', $endpoint['route']),
                    'parameters' => [],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                    ],
                                ],
                            ],
                        ],
                        '400' => [
                            'description' => 'Bad request',
                        ],
                        '401' => [
                            'description' => 'Unauthorized',
                        ],
                        '404' => [
                            'description' => 'Not found',
                        ],
                    ],
                    'security' => [
                        ['cookieAuth' => []],
                        ['basicAuth' => []],
                    ],
                ];

                // Add parameters
                foreach ($endpoint['args'] as $arg) {
                    $spec['paths'][$path][$method_lower]['parameters'][] = [
                        'name' => $arg['name'],
                        'in' => $method === 'GET' ? 'query' : 'body',
                        'description' => $arg['description'],
                        'required' => false,
                        'schema' => [
                            'type' => $this->map_type_to_openapi($arg['type']),
                        ],
                    ];
                }
            }
        }

        return json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate Markdown documentation
     */
    private function generate_markdown() {
        $md = "# Aevov API Documentation\n\n";
        $md .= "REST API documentation for the Aevov WordPress plugin ecosystem.\n\n";
        $md .= "**Base URL:** `/wp-json`\n\n";
        $md .= "**Authentication:** WordPress Cookie or Basic Auth\n\n";
        $md .= "---\n\n";

        // Group by plugin
        $grouped = [];
        foreach ($this->endpoints as $endpoint) {
            $grouped[$endpoint['plugin']][] = $endpoint;
        }

        foreach ($grouped as $plugin => $endpoints) {
            $md .= "## {$plugin}\n\n";

            foreach ($endpoints as $endpoint) {
                $methods = implode(', ', $endpoint['methods']);
                $md .= "### `{$methods}` {$endpoint['full_path']}\n\n";

                if ($endpoint['description']) {
                    $md .= "{$endpoint['description']}\n\n";
                }

                if (!empty($endpoint['args'])) {
                    $md .= "**Parameters:**\n\n";
                    $md .= "| Name | Type | Description |\n";
                    $md .= "|------|------|-------------|\n";

                    foreach ($endpoint['args'] as $arg) {
                        $md .= "| `{$arg['name']}` | {$arg['type']} | {$arg['description']} |\n";
                    }

                    $md .= "\n";
                }

                $md .= "**Example Request:**\n\n";
                $md .= "```bash\n";
                $md .= "curl -X " . $endpoint['methods'][0] . " \\\n";
                $md .= "  'http://localhost:8080/wp-json{$endpoint['full_path']}' \\\n";
                $md .= "  -H 'Content-Type: application/json'\n";
                $md .= "```\n\n";

                $md .= "---\n\n";
            }
        }

        return $md;
    }

    /**
     * Generate HTML documentation
     */
    private function generate_html() {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aevov API Documentation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .endpoint { background: #f9f9f9; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .method { display: inline-block; padding: 4px 12px; border-radius: 3px; font-weight: bold; font-size: 12px; margin-right: 10px; }
        .method.get { background: #61affe; color: white; }
        .method.post { background: #49cc90; color: white; }
        .method.put { background: #fca130; color: white; }
        .method.delete { background: #f93e3e; color: white; }
        .path { font-family: 'Courier New', monospace; font-size: 18px; color: #333; }
        .description { margin: 15px 0; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; }
        h2 { color: #667eea; margin: 30px 0 15px; padding-bottom: 10px; border-bottom: 2px solid #667eea; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Aevov API Documentation</h1>
        <p>REST API Reference for the Aevov WordPress Plugin Ecosystem</p>
    </div>

    <div class="container">
HTML;

        // Group by plugin
        $grouped = [];
        foreach ($this->endpoints as $endpoint) {
            $grouped[$endpoint['plugin']][] = $endpoint;
        }

        foreach ($grouped as $plugin => $endpoints) {
            $html .= "<h2>{$plugin}</h2>";

            foreach ($endpoints as $endpoint) {
                $html .= '<div class="endpoint">';

                // Methods and path
                foreach ($endpoint['methods'] as $method) {
                    $html .= '<span class="method ' . strtolower($method) . '">' . $method . '</span>';
                }
                $html .= '<span class="path">' . htmlspecialchars($endpoint['full_path']) . '</span>';

                // Description
                if ($endpoint['description']) {
                    $html .= '<div class="description">' . htmlspecialchars($endpoint['description']) . '</div>';
                }

                // Parameters
                if (!empty($endpoint['args'])) {
                    $html .= '<h3>Parameters</h3>';
                    $html .= '<table>';
                    $html .= '<tr><th>Name</th><th>Type</th><th>Description</th></tr>';

                    foreach ($endpoint['args'] as $arg) {
                        $html .= '<tr>';
                        $html .= '<td><code>' . htmlspecialchars($arg['name']) . '</code></td>';
                        $html .= '<td>' . htmlspecialchars($arg['type']) . '</td>';
                        $html .= '<td>' . htmlspecialchars($arg['description']) . '</td>';
                        $html .= '</tr>';
                    }

                    $html .= '</table>';
                }

                $html .= '</div>';
            }
        }

        $html .= <<<HTML
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Map PHP type to OpenAPI type
     */
    private function map_type_to_openapi($type) {
        $type_map = [
            'int' => 'integer',
            'integer' => 'integer',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'float' => 'number',
            'double' => 'number',
            'string' => 'string',
            'array' => 'array',
            'object' => 'object',
        ];

        return $type_map[strtolower($type)] ?? 'string';
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    private function print_header($text) {
        echo "\n\033[1;34m" . str_repeat("=", strlen($text)) . "\033[0m\n";
        echo "\033[1;34m{$text}\033[0m\n";
        echo "\033[1;34m" . str_repeat("=", strlen($text)) . "\033[0m\n\n";
    }

    private function print_success($text) {
        echo "\033[0;32m✓ {$text}\033[0m\n";
    }

    private function print_info($text) {
        echo "\033[0;36mℹ {$text}\033[0m\n";
    }
}

// ============================================================================
// Main Execution
// ============================================================================

$base_path = dirname(__FILE__);
$generator = new API_Documentation_Generator($base_path);
$generator->run();
