<?php
/**
 * Code Obfuscator
 *
 * Obfuscates JavaScript and CSS code to prevent detection.
 * Randomizes variable names, class names, and function names
 * while maintaining functionality.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Stealth;

/**
 * Code Obfuscator Class
 */
class CodeObfuscator
{
    /**
     * Obfuscation map (original => obfuscated)
     *
     * @var array
     */
    private array $obfuscation_map = [];

    /**
     * Seed for deterministic obfuscation
     *
     * @var string
     */
    private string $seed;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->seed = get_option('aevov_stealth_seed', bin2hex(random_bytes(16)));
        $this->load_obfuscation_map();
    }

    /**
     * Load or generate obfuscation map
     *
     * @return void
     */
    private function load_obfuscation_map(): void
    {
        $map = get_option('aevov_obfuscation_map');

        if ($map) {
            $this->obfuscation_map = json_decode($map, true);
        } else {
            $this->generate_obfuscation_map();
        }
    }

    /**
     * Generate new obfuscation map
     *
     * @return void
     */
    private function generate_obfuscation_map(): void
    {
        // Common identifiers to obfuscate
        $identifiers = [
            'meshcore',
            'aevov',
            'bloom',
            'aros',
            'nodeManager',
            'connectionManager',
            'dhtService',
            'meshRouter',
            'relayManager',
            'stealthManager',
            'onionRelay',
            'peerDiscovery'
        ];

        foreach ($identifiers as $identifier) {
            $this->obfuscation_map[$identifier] = $this->generate_random_name($identifier);
        }

        update_option('aevov_obfuscation_map', wp_json_encode($this->obfuscation_map));
    }

    /**
     * Generate random but valid identifier name
     *
     * @param string $original Original name
     * @return string Obfuscated name
     */
    private function generate_random_name(string $original): string
    {
        // Generate deterministic but random-looking name
        $hash = hash('sha256', $this->seed . $original);
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $name = '';
        for ($i = 0; $i < 8; $i++) {
            $index = hexdec(substr($hash, $i * 2, 2)) % strlen($chars);
            $name .= $chars[$index];
        }

        // Ensure starts with letter
        if (!ctype_alpha($name[0])) {
            $name = 'a' . substr($name, 1);
        }

        return $name;
    }

    /**
     * Obfuscate JavaScript code
     *
     * @param string $js JavaScript code
     * @return string Obfuscated code
     */
    public function obfuscate_javascript(string $js): string
    {
        // Remove comments
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        $js = preg_replace('/\/\/.*$/m', '', $js);

        // Replace identifiers
        foreach ($this->obfuscation_map as $original => $obfuscated) {
            // Word boundary replacement
            $js = preg_replace('/\b' . preg_quote($original, '/') . '\b/', $obfuscated, $js);
        }

        // Minify
        $js = $this->minify_javascript($js);

        return $js;
    }

    /**
     * Minify JavaScript
     *
     * @param string $js JavaScript code
     * @return string Minified code
     */
    private function minify_javascript(string $js): string
    {
        // Remove extra whitespace
        $js = preg_replace('/\s+/', ' ', $js);

        // Remove whitespace around operators
        $js = preg_replace('/\s*([=+\-*\/<>!&|,;:?{}()\[\]])\s*/', '$1', $js);

        return trim($js);
    }

    /**
     * Obfuscate CSS code
     *
     * @param string $css CSS code
     * @return string Obfuscated code
     */
    public function obfuscate_css(string $css): string
    {
        // Remove comments
        $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);

        // Replace class names
        foreach ($this->obfuscation_map as $original => $obfuscated) {
            $css = str_replace('.' . $original, '.' . $obfuscated, $css);
            $css = str_replace('#' . $original, '#' . $obfuscated, $css);
        }

        // Minify
        $css = $this->minify_css($css);

        return $css;
    }

    /**
     * Minify CSS
     *
     * @param string $css CSS code
     * @return string Minified code
     */
    private function minify_css(string $css): string
    {
        // Remove extra whitespace
        $css = preg_replace('/\s+/', ' ', $css);

        // Remove whitespace around braces and semicolons
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);

        return trim($css);
    }

    /**
     * Obfuscate HTML class and ID attributes
     *
     * @param string $html HTML code
     * @return string Obfuscated HTML
     */
    public function obfuscate_html_attributes(string $html): string
    {
        // Replace class attributes
        $html = preg_replace_callback('/class="([^"]+)"/', function($matches) {
            $classes = explode(' ', $matches[1]);
            $obfuscated_classes = [];

            foreach ($classes as $class) {
                $obfuscated_classes[] = $this->obfuscation_map[$class] ?? $class;
            }

            return 'class="' . implode(' ', $obfuscated_classes) . '"';
        }, $html);

        // Replace ID attributes
        $html = preg_replace_callback('/id="([^"]+)"/', function($matches) {
            $id = $matches[1];
            return 'id="' . ($this->obfuscation_map[$id] ?? $id) . '"';
        }, $html);

        return $html;
    }

    /**
     * Obfuscate WordPress hook names
     *
     * @param string $hook_name Original hook name
     * @return string Obfuscated hook name
     */
    public function obfuscate_hook_name(string $hook_name): string
    {
        // Generate consistent obfuscated name
        if (!isset($this->obfuscation_map[$hook_name])) {
            $this->obfuscation_map[$hook_name] = $this->generate_random_name($hook_name);
            update_option('aevov_obfuscation_map', wp_json_encode($this->obfuscation_map));
        }

        return $this->obfuscation_map[$hook_name];
    }

    /**
     * Get original name from obfuscated
     *
     * @param string $obfuscated Obfuscated name
     * @return string|null Original name
     */
    public function get_original_name(string $obfuscated): ?string
    {
        $reversed = array_flip($this->obfuscation_map);
        return $reversed[$obfuscated] ?? null;
    }

    /**
     * Clear obfuscation map (regenerate on next load)
     *
     * @return void
     */
    public function clear_obfuscation_map(): void
    {
        delete_option('aevov_obfuscation_map');
        $this->obfuscation_map = [];
        $this->generate_obfuscation_map();
    }

    /**
     * Get obfuscation statistics
     *
     * @return array
     */
    public function get_stats(): array
    {
        return [
            'mapped_identifiers' => count($this->obfuscation_map),
            'seed' => substr($this->seed, 0, 8) . '...',
            'obfuscation_active' => true
        ];
    }
}
