<?php
/**
 * Sister Plugin Testing Framework
 *
 * Tests all sister plugins for compatibility and integration with main three plugins:
 * - AevovPatternSyncProtocol
 * - Bloom Pattern Recognition
 * - APS Tools
 *
 * @package AevovTestingFramework
 * @since 1.0.0
 */

// Set execution time
set_time_limit(600); // 10 minutes

// Output buffer
ob_implicit_flush(true);

class Sister_Plugin_Tester {

    private $base_path;
    private $main_plugins = [
        'AevovPatternSyncProtocol',
        'bloom-pattern-recognition',
        'aps-tools',
    ];

    private $sister_plugins = [];
    private $test_results = [];
    private $bugs_found = [];

    public function __construct($base_path) {
        $this->base_path = $base_path;
        $this->discover_sister_plugins();
    }

    private function discover_sister_plugins() {
        $dirs = glob($this->base_path . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $plugin_name = basename($dir);

            // Skip main plugins, testing framework, and non-plugin directories
            if (in_array($plugin_name, $this->main_plugins) ||
                $plugin_name === 'aevov-testing-framework' ||
                $plugin_name === 'aevov-vision-depth' ||
                $plugin_name === 'aevov-core') { // Not a WordPress plugin, just JavaScript
                continue;
            }

            // Only include aevov-*, bloom-*, aps-* plugins
            if (preg_match('/^(aevov|bloom|aps)-/', $plugin_name)) {
                $this->sister_plugins[] = [
                    'name' => $plugin_name,
                    'path' => $dir,
                ];
            }
        }
    }

    public function run_all_tests() {
        $this->print_header();

        $total_plugins = count($this->sister_plugins);
        $tested = 0;

        foreach ($this->sister_plugins as $plugin) {
            $tested++;
            $this->echo_progress("Testing {$tested}/{$total_plugins}: {$plugin['name']}", 'info');

            $result = $this->test_plugin($plugin);
            $this->test_results[$plugin['name']] = $result;

            // Show summary
            $pass_rate = $result['tests_passed'] > 0
                ? round(($result['tests_passed'] / $result['tests_total']) * 100, 1)
                : 0;

            $status = $pass_rate >= 90 ? 'success' : ($pass_rate >= 70 ? 'warning' : 'error');
            $this->echo_progress("  → {$pass_rate}% passed ({$result['tests_passed']}/{$result['tests_total']})", $status);

            if (!empty($result['bugs'])) {
                foreach ($result['bugs'] as $bug) {
                    $this->bugs_found[] = [
                        'plugin' => $plugin['name'],
                        'bug' => $bug,
                    ];
                    $this->echo_progress("    ✗ {$bug['description']}", 'error');
                }
            }
        }

        $this->print_summary();
        $this->save_results();
    }

    private function test_plugin($plugin) {
        $result = [
            'plugin_name' => $plugin['name'],
            'tests_total' => 0,
            'tests_passed' => 0,
            'tests_failed' => 0,
            'bugs' => [],
        ];

        // Test 1: Plugin structure
        $structure_test = $this->test_plugin_structure($plugin);
        $result['tests_total']++;
        if ($structure_test['passed']) {
            $result['tests_passed']++;
        } else {
            $result['tests_failed']++;
            $result['bugs'][] = $structure_test;
        }

        // Test 2: PHP syntax
        $syntax_test = $this->test_php_syntax($plugin);
        $result['tests_total']++;
        if ($syntax_test['passed']) {
            $result['tests_passed']++;
        } else {
            $result['tests_failed']++;
            $result['bugs'][] = $syntax_test;
        }

        // Test 3: Dependencies on main plugins
        $dependency_test = $this->test_dependencies($plugin);
        $result['tests_total']++;
        if ($dependency_test['passed']) {
            $result['tests_passed']++;
        } else {
            $result['tests_failed']++;
            if (isset($dependency_test['severity']) && $dependency_test['severity'] !== 'info') {
                $result['bugs'][] = $dependency_test;
            }
        }

        // Test 4: APS integration
        $aps_test = $this->test_aps_integration($plugin);
        $result['tests_total']++;
        if ($aps_test['passed']) {
            $result['tests_passed']++;
        } else {
            $result['tests_failed']++;
            if (isset($aps_test['severity']) && $aps_test['severity'] !== 'info') {
                $result['bugs'][] = $aps_test;
            }
        }

        // Test 5: Bloom integration
        $bloom_test = $this->test_bloom_integration($plugin);
        $result['tests_total']++;
        if ($bloom_test['passed']) {
            $result['tests_passed']++;
        } else {
            $result['tests_failed']++;
            if (isset($bloom_test['severity']) && $bloom_test['severity'] !== 'info') {
                $result['bugs'][] = $bloom_test;
            }
        }

        // Test 6: APS Tools integration
        $tools_test = $this->test_aps_tools_integration($plugin);
        $result['tests_total']++;
        if ($tools_test['passed']) {
            $result['tests_passed']++;
        } else {
            $result['tests_failed']++;
            if (isset($tools_test['severity']) && $tools_test['severity'] !== 'info') {
                $result['bugs'][] = $tools_test;
            }
        }

        // Test 7: Database tables
        $db_test = $this->test_database_tables($plugin);
        $result['tests_total']++;
        if ($db_test['passed']) {
            $result['tests_passed']++;
        } else {
            $result['tests_failed']++;
            if (isset($db_test['severity']) && $db_test['severity'] !== 'info') {
                $result['bugs'][] = $db_test;
            }
        }

        // Test 8: Namespace usage
        $namespace_test = $this->test_namespace_usage($plugin);
        $result['tests_total']++;
        if ($namespace_test['passed']) {
            $result['tests_passed']++;
        } else {
            $result['tests_failed']++;
            if (isset($namespace_test['severity']) && $namespace_test['severity'] === 'high') {
                $result['bugs'][] = $namespace_test;
            }
        }

        return $result;
    }

    private function test_plugin_structure($plugin) {
        // Special case handling for plugins with different naming conventions
        $special_cases = [
            'aevov-onboarding-system' => 'aevov-onboarding.php',
        ];

        // Check for main plugin file
        if (isset($special_cases[$plugin['name']])) {
            $main_file = $plugin['path'] . '/' . $special_cases[$plugin['name']];
        } else {
            $main_file = $plugin['path'] . '/' . $plugin['name'] . '.php';
        }

        if (!file_exists($main_file)) {
            return [
                'passed' => false,
                'description' => 'Main plugin file missing',
                'severity' => 'critical',
                'file' => $main_file,
            ];
        }

        // Check for includes directory
        $includes_dir = $plugin['path'] . '/includes';
        if (!is_dir($includes_dir)) {
            return [
                'passed' => false,
                'description' => 'Includes directory missing',
                'severity' => 'medium',
                'expected' => $includes_dir,
            ];
        }

        return ['passed' => true];
    }

    private function test_php_syntax($plugin) {
        $php_files = $this->get_php_files($plugin['path']);

        foreach ($php_files as $file) {
            $output = [];
            $return_var = 0;
            exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);

            if ($return_var !== 0) {
                return [
                    'passed' => false,
                    'description' => 'PHP syntax error in ' . basename($file),
                    'severity' => 'critical',
                    'file' => $file,
                    'error' => implode("\n", $output),
                ];
            }
        }

        return ['passed' => true];
    }

    private function test_dependencies($plugin) {
        // Special case handling for plugins with different naming conventions
        $special_cases = [
            'aevov-onboarding-system' => 'aevov-onboarding.php',
        ];

        if (isset($special_cases[$plugin['name']])) {
            $main_file = $plugin['path'] . '/' . $special_cases[$plugin['name']];
        } else {
            $main_file = $plugin['path'] . '/' . $plugin['name'] . '.php';
        }

        if (!file_exists($main_file)) {
            return ['passed' => false, 'description' => 'Cannot test dependencies - main file missing', 'severity' => 'critical'];
        }

        $content = file_get_contents($main_file);

        $has_dependencies = false;
        $dependencies_list = [];

        // Check for APS references
        if (preg_match('/APS\\\|APS_|aps_/i', $content)) {
            $has_dependencies = true;
            $dependencies_list[] = 'APS';
        }

        // Check for Bloom references
        if (preg_match('/BLOOM|bloom_/i', $content)) {
            $has_dependencies = true;
            $dependencies_list[] = 'Bloom';
        }

        return [
            'passed' => true, // Not a failure if no dependencies
            'has_dependencies' => $has_dependencies,
            'dependencies' => $dependencies_list,
            'severity' => 'info',
        ];
    }

    private function test_aps_integration($plugin) {
        $php_files = $this->get_php_files($plugin['path']);

        foreach ($php_files as $file) {
            $content = file_get_contents($file);

            // Check for APS namespace usage
            if (preg_match('/namespace\s+APS\\\\/i', $content) ||
                preg_match('/use\s+APS\\\\/i', $content) ||
                preg_match('/class_exists\(["\']APS\\\/i', $content)) {
                return [
                    'passed' => true,
                    'integration_found' => true,
                    'file' => basename($file),
                ];
            }
        }

        return [
            'passed' => true, // Not a failure
            'integration_found' => false,
            'severity' => 'info',
        ];
    }

    private function test_bloom_integration($plugin) {
        $php_files = $this->get_php_files($plugin['path']);

        foreach ($php_files as $file) {
            $content = file_get_contents($file);

            // Check for Bloom integration
            if (preg_match('/BLOOM_Pattern_System|bloom_/i', $content)) {
                return [
                    'passed' => true,
                    'integration_found' => true,
                    'file' => basename($file),
                ];
            }
        }

        return [
            'passed' => true, // Not a failure
            'integration_found' => false,
            'severity' => 'info',
        ];
    }

    private function test_aps_tools_integration($plugin) {
        $php_files = $this->get_php_files($plugin['path']);

        foreach ($php_files as $file) {
            $content = file_get_contents($file);

            // Check for APS Tools integration
            if (preg_match('/APSTools|aps_tools/i', $content)) {
                return [
                    'passed' => true,
                    'integration_found' => true,
                    'file' => basename($file),
                ];
            }
        }

        return [
            'passed' => true, // Not a failure
            'integration_found' => false,
            'severity' => 'info',
        ];
    }

    private function test_database_tables($plugin) {
        $php_files = $this->get_php_files($plugin['path']);
        $tables_found = [];

        foreach ($php_files as $file) {
            $content = file_get_contents($file);

            // Find table creation patterns
            if (preg_match_all('/\$wpdb->prefix\s*\.\s*["\'](\w+)["\']/i', $content, $matches)) {
                $tables_found = array_merge($tables_found, $matches[1]);
            }
        }

        $tables_found = array_unique($tables_found);

        return [
            'passed' => true,
            'tables_found' => count($tables_found),
            'tables' => $tables_found,
            'severity' => 'info',
        ];
    }

    private function test_namespace_usage($plugin) {
        $php_files = $this->get_php_files($plugin['path']);
        $namespace_count = 0;

        foreach ($php_files as $file) {
            $content = file_get_contents($file);

            if (preg_match('/namespace\s+/i', $content)) {
                $namespace_count++;
            }
        }

        $total_files = count($php_files);
        $namespace_ratio = $total_files > 0 ? ($namespace_count / $total_files) : 0;

        return [
            'passed' => $namespace_ratio >= 0.5, // At least 50% of files should use namespaces
            'namespace_ratio' => round($namespace_ratio * 100, 1),
            'severity' => $namespace_ratio < 0.3 ? 'medium' : 'low',
        ];
    }

    private function get_php_files($directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $php_files = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $php_files[] = $file->getPathname();
            }
        }

        return $php_files;
    }

    private function print_header() {
        echo "\n";
        echo str_repeat("=", 100) . "\n";
        echo "   AEVOV SISTER PLUGIN TESTING FRAMEWORK\n";
        echo "   Testing " . count($this->sister_plugins) . " Sister Plugins Against Main Three\n";
        echo str_repeat("=", 100) . "\n\n";
    }

    private function print_summary() {
        echo "\n" . str_repeat("=", 100) . "\n";
        echo "   TEST SUMMARY\n";
        echo str_repeat("=", 100) . "\n\n";

        $total_passed = 0;
        $total_tests = 0;

        foreach ($this->test_results as $plugin => $result) {
            $total_passed += $result['tests_passed'];
            $total_tests += $result['tests_total'];
        }

        echo "Total Plugins Tested: " . count($this->test_results) . "\n";
        echo "Total Tests Run: {$total_tests}\n";
        echo "Total Tests Passed: {$total_passed}\n";
        echo "Total Tests Failed: " . ($total_tests - $total_passed) . "\n";
        echo "Overall Pass Rate: " . round(($total_passed / $total_tests) * 100, 1) . "%\n";
        echo "\nTotal Bugs Found: " . count($this->bugs_found) . "\n";

        if (!empty($this->bugs_found)) {
            echo "\n" . str_repeat("-", 100) . "\n";
            echo "CRITICAL BUGS SUMMARY:\n";
            echo str_repeat("-", 100) . "\n";

            $critical = array_filter($this->bugs_found, function($bug) {
                return ($bug['bug']['severity'] ?? 'low') === 'critical';
            });

            foreach ($critical as $bug) {
                echo "- {$bug['plugin']}: {$bug['bug']['description']}\n";
            }
        }

        echo "\n" . str_repeat("=", 100) . "\n";
    }

    private function save_results() {
        $output_file = __DIR__ . '/sister-plugin-test-results.json';
        file_put_contents($output_file, json_encode([
            'test_date' => date('Y-m-d H:i:s'),
            'total_plugins' => count($this->test_results),
            'results' => $this->test_results,
            'bugs' => $this->bugs_found,
        ], JSON_PRETTY_PRINT));

        echo "\nResults saved to: {$output_file}\n";

        // Also save bugs to separate file
        if (!empty($this->bugs_found)) {
            $bugs_file = __DIR__ . '/SISTER-PLUGIN-BUGS.md';
            $this->save_bugs_markdown($bugs_file);
            echo "Bugs documented in: {$bugs_file}\n";
        }
    }

    private function save_bugs_markdown($filename) {
        $content = "# Sister Plugin Bugs Report\n\n";
        $content .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $content .= "Total Bugs Found: " . count($this->bugs_found) . "\n\n";

        $by_severity = [
            'critical' => [],
            'high' => [],
            'medium' => [],
            'low' => [],
        ];

        foreach ($this->bugs_found as $bug) {
            $severity = $bug['bug']['severity'] ?? 'low';
            $by_severity[$severity][] = $bug;
        }

        foreach (['critical', 'high', 'medium', 'low'] as $severity) {
            if (empty($by_severity[$severity])) continue;

            $content .= "## " . strtoupper($severity) . " Priority (" . count($by_severity[$severity]) . ")\n\n";

            foreach ($by_severity[$severity] as $bug) {
                $content .= "### {$bug['plugin']}\n\n";
                $content .= "**Description:** {$bug['bug']['description']}\n\n";

                if (isset($bug['bug']['file'])) {
                    $content .= "**File:** `{$bug['bug']['file']}`\n\n";
                }

                if (isset($bug['bug']['error'])) {
                    $content .= "**Error:**\n```\n{$bug['bug']['error']}\n```\n\n";
                }

                $content .= "---\n\n";
            }
        }

        file_put_contents($filename, $content);
    }

    private function echo_progress($message, $type = 'info') {
        $colors = [
            'info' => "\033[0;36m",    // Cyan
            'success' => "\033[0;32m", // Green
            'warning' => "\033[0;33m", // Yellow
            'error' => "\033[0;31m",   // Red
        ];

        $reset = "\033[0m";
        $color = $colors[$type] ?? '';

        echo "{$color}{$message}{$reset}\n";
    }
}

// Run tests
$tester = new Sister_Plugin_Tester('/home/user/Aevov1');
$tester->run_all_tests();
