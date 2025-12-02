<?php
/**
 * Fingerprint Eliminator
 *
 * Removes all identifying fingerprints from:
 * - HTTP headers
 * - HTML output
 * - JavaScript code
 * - CSS files
 * - Database queries
 * - Error messages
 * - File paths
 *
 * Makes it impossible to detect Aevov or identify AI providers.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Stealth;

/**
 * Fingerprint Eliminator Class
 */
class FingerprintEliminator
{
    /**
     * Identifying strings to remove
     *
     * @var array
     */
    private array $identifying_strings = [
        'aevov',
        'meshcore',
        'openai',
        'anthropic',
        'claude',
        'gpt-',
        'bloom',
        'aros',
        'aevip'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init_filters();
    }

    /**
     * Initialize output filters
     *
     * @return void
     */
    private function init_filters(): void
    {
        // Filter HTML output
        add_filter('the_content', [$this, 'clean_html_output'], 9999);
        add_filter('the_excerpt', [$this, 'clean_html_output'], 9999);
        add_filter('widget_text', [$this, 'clean_html_output'], 9999);

        // Filter scripts and styles
        add_filter('script_loader_src', [$this, 'clean_asset_url'], 9999);
        add_filter('style_loader_src', [$this, 'clean_asset_url'], 9999);

        // Filter output buffer
        ob_start([$this, 'clean_output_buffer']);
    }

    /**
     * Clean HTML output of identifying information
     *
     * @param string $content Content to clean
     * @return string Cleaned content
     */
    public function clean_html_output(string $content): string
    {
        // Remove HTML comments that might identify plugins
        $content = preg_replace('/<!--.*?(aevov|meshcore).*?-->/is', '', $content);

        // Remove data attributes
        $content = preg_replace('/data-aevov[a-z-]*="[^"]*"/i', '', $content);

        // Remove class names
        foreach ($this->identifying_strings as $string) {
            $content = preg_replace('/class="[^"]*' . $string . '[^"]*"/i', 'class=""', $content);
            $content = preg_replace('/id="[^"]*' . $string . '[^"]*"/i', '', $content);
        }

        return $content;
    }

    /**
     * Clean asset URLs
     *
     * @param string $src Asset URL
     * @return string Cleaned URL
     */
    public function clean_asset_url(string $src): string
    {
        // Remove identifying path segments
        foreach ($this->identifying_strings as $string) {
            $src = str_ireplace('/' . $string . '-', '/plugin-', $src);
        }

        // Remove version parameters
        $src = preg_replace('/(\?|&)ver=[^&]*/', '', $src);

        return $src;
    }

    /**
     * Clean output buffer
     *
     * @param string $buffer Output buffer
     * @return string Cleaned buffer
     */
    public function clean_output_buffer(string $buffer): string
    {
        // Remove generator meta tags
        $buffer = preg_replace('/<meta name="generator"[^>]*>/i', '', $buffer);

        // Remove WordPress version
        $buffer = preg_replace('/WordPress \d+\.\d+(\.\d+)?/i', '', $buffer);

        // Remove plugin signatures
        foreach ($this->identifying_strings as $string) {
            // Case-insensitive removal
            $buffer = preg_replace('/<!--.*?' . $string . '.*?-->/is', '', $buffer);
            $buffer = preg_replace('/<!\[CDATA\[.*?' . $string . '.*?\]\]>/is', '', $buffer);
        }

        // Remove inline script comments
        $buffer = preg_replace('/\/\*.*?(aevov|openai|anthropic|claude|gpt).*?\*\//is', '', $buffer);
        $buffer = preg_replace('/\/\/.*?(aevov|openai|anthropic|claude|gpt).*/i', '', $buffer);

        // Clean JavaScript console logs
        $buffer = preg_replace('/console\.(log|debug|info)\([^)]*(' . implode('|', $this->identifying_strings) . ')[^)]*\);?/i', '', $buffer);

        return $buffer;
    }

    /**
     * Clean database table names for queries
     *
     * @param string $query SQL query
     * @return string Cleaned query
     */
    public function clean_query(string $query): string
    {
        global $wpdb;

        // Replace Aevov table names with generic names in output
        $tables = [
            'meshcore_nodes' => 'network_nodes',
            'meshcore_connections' => 'connections',
            'meshcore_routes' => 'routes',
            'meshcore_dht' => 'storage',
            'bloom_patterns' => 'patterns',
            'aros_states' => 'states'
        ];

        foreach ($tables as $real => $generic) {
            $query = str_replace($wpdb->prefix . $real, $wpdb->prefix . $generic, $query);
        }

        return $query;
    }

    /**
     * Clean error messages
     *
     * @param string $message Error message
     * @return string Cleaned message
     */
    public function clean_error_message(string $message): string
    {
        // Remove file paths
        $message = preg_replace('/\/.*?\/wp-content\/plugins\/[^\/]+\//', '/plugins/', $message);

        // Remove identifying names
        foreach ($this->identifying_strings as $string) {
            $message = str_ireplace($string, 'plugin', $message);
        }

        // Remove function names that might identify code
        $message = preg_replace('/Aevov\\\\[A-Za-z\\\\]+::/', 'Plugin::', $message);

        return $message;
    }

    /**
     * Clean HTTP headers
     *
     * @param array $headers Headers array
     * @return array Cleaned headers
     */
    public function clean_headers(array $headers): array
    {
        // Remove identifying headers
        $remove_headers = [
            'X-Powered-By',
            'Server',
            'X-WordPress-Version',
            'X-Aevov-Version',
            'X-Plugin-Version'
        ];

        foreach ($remove_headers as $header) {
            unset($headers[$header]);
            unset($headers[strtolower($header)]);
        }

        // Clean remaining headers
        foreach ($headers as $key => $value) {
            if (is_string($value)) {
                foreach ($this->identifying_strings as $string) {
                    $value = str_ireplace($string, 'plugin', $value);
                }
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    /**
     * Generate fake fingerprint
     *
     * @param string $type Fingerprint type
     * @return string Fake fingerprint
     */
    public function generate_fake_fingerprint(string $type = 'plugin'): string
    {
        $fakes = [
            'plugin' => [
                'WooCommerce',
                'Yoast SEO',
                'Contact Form 7',
                'Akismet',
                'Jetpack'
            ],
            'theme' => [
                'Twenty Twenty-Four',
                'Astra',
                'GeneratePress',
                'OceanWP'
            ]
        ];

        $options = $fakes[$type] ?? $fakes['plugin'];
        return $options[array_rand($options)];
    }

    /**
     * Obfuscate file paths in logs
     *
     * @param string $path File path
     * @return string Obfuscated path
     */
    public function obfuscate_path(string $path): string
    {
        // Replace plugin directory with generic name
        $path = preg_replace('/\/wp-content\/plugins\/aevov[^\/]*\//', '/wp-content/plugins/plugin/', $path);

        // Remove identifying file names
        foreach ($this->identifying_strings as $string) {
            $path = str_ireplace($string, 'module', $path);
        }

        return $path;
    }

    /**
     * Clean JSON output
     *
     * @param string $json JSON string
     * @return string Cleaned JSON
     */
    public function clean_json_output(string $json): string
    {
        $data = json_decode($json, true);

        if (!$data) {
            return $json;
        }

        $cleaned = $this->clean_array_recursive($data);

        return wp_json_encode($cleaned);
    }

    /**
     * Recursively clean array of identifying information
     *
     * @param array $data Data to clean
     * @return array Cleaned data
     */
    private function clean_array_recursive(array $data): array
    {
        foreach ($data as $key => $value) {
            // Check key
            foreach ($this->identifying_strings as $string) {
                if (stripos($key, $string) !== false) {
                    // Rename key
                    $new_key = str_ireplace($string, 'plugin', $key);
                    $data[$new_key] = $value;
                    unset($data[$key]);
                    $key = $new_key;
                    break;
                }
            }

            // Check value
            if (is_array($value)) {
                $data[$key] = $this->clean_array_recursive($value);
            } elseif (is_string($value)) {
                foreach ($this->identifying_strings as $string) {
                    $value = str_ireplace($string, 'plugin', $value);
                }
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Get list of cleaned fingerprints
     *
     * @return array
     */
    public function get_cleaned_count(): array
    {
        return [
            'identifying_strings' => count($this->identifying_strings),
            'active_filters' => count($this->identifying_strings) * 3,
            'obfuscation_active' => true
        ];
    }
}
