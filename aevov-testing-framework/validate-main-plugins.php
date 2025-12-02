#!/usr/bin/env php
<?php
/**
 * Aevov Main Plugin Validator
 * Validates the three core Aevov WordPress plugins
 *
 * Priority Plugins:
 * 1. AevovPatternSyncProtocol
 * 2. Bloom Pattern Recognition
 * 3. APS Tools
 */

class AevovMainPluginValidator {
    private $results = [];
    private $total_tests = 0;
    private $passed_tests = 0;
    private $failed_tests = 0;
    private $issues = [];

    public function __construct() {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "   AEVOV MAIN PLUGIN VALIDATOR\n";
        echo "   Testing Core Aevov Ecosystem Plugins\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";
    }

    public function validatePlugin($plugin_dir, $plugin_name) {
        echo "┌─────────────────────────────────────────────────────────────┐\n";
        echo "│  Testing: $plugin_name\n";
        echo "└─────────────────────────────────────────────────────────────┘\n\n";

        $this->results[$plugin_name] = [
            'tests' => [],
            'passed' => 0,
            'failed' => 0,
            'issues' => []
        ];

        // Test 1: Plugin directory exists
        $this->test(
            $plugin_name,
            "Plugin directory exists",
            is_dir($plugin_dir)
        );

        if (!is_dir($plugin_dir)) {
            echo "  ✗ Cannot continue - plugin directory not found: $plugin_dir\n\n";
            return;
        }

        // Test 2: Main plugin file exists
        $main_file = $this->findMainPluginFile($plugin_dir);
        $this->test(
            $plugin_name,
            "Main plugin file exists",
            $main_file !== null
        );

        if ($main_file) {
            // Test 3: PHP syntax is valid
            $this->test(
                $plugin_name,
                "Main file has valid PHP syntax",
                $this->validateSyntax($main_file)
            );

            // Test 4: Plugin header exists
            $headers = $this->getPluginHeaders($main_file);
            $this->test(
                $plugin_name,
                "Plugin headers present",
                !empty($headers['Name'])
            );

            if (!empty($headers['Name'])) {
                echo "    Plugin: {$headers['Name']}\n";
                if (!empty($headers['Version'])) {
                    echo "    Version: {$headers['Version']}\n";
                }
                if (!empty($headers['Description'])) {
                    $desc = substr($headers['Description'], 0, 80);
                    echo "    Description: $desc" . (strlen($headers['Description']) > 80 ? '...' : '') . "\n";
                }
            }
        }

        // Test 5: Required directories exist
        $required_dirs = ['Includes', 'includes'];
        $has_includes = false;
        foreach ($required_dirs as $dir) {
            $dir_path = "$plugin_dir/$dir";
            if (is_dir($dir_path)) {
                $has_includes = true;
                break;
            }
        }
        $this->test(
            $plugin_name,
            "Includes directory exists",
            $has_includes
        );

        // Test 6: Find and validate all PHP files
        $php_files = $this->findPHPFiles($plugin_dir);
        $this->test(
            $plugin_name,
            "PHP files found",
            count($php_files) > 0
        );

        echo "    Found " . count($php_files) . " PHP files\n";

        // Test 7: Validate syntax of all PHP files
        $syntax_errors = [];
        foreach ($php_files as $file) {
            if (!$this->validateSyntax($file)) {
                $syntax_errors[] = str_replace($plugin_dir . '/', '', $file);
            }
        }
        $this->test(
            $plugin_name,
            "All PHP files have valid syntax",
            count($syntax_errors) === 0
        );

        if (count($syntax_errors) > 0) {
            echo "    ✗ " . count($syntax_errors) . " file(s) with syntax errors:\n";
            foreach (array_slice($syntax_errors, 0, 5) as $file) {
                echo "      - $file\n";
                $this->addIssue($plugin_name, 'syntax_error', "Syntax error in: $file");
            }
            if (count($syntax_errors) > 5) {
                echo "      ... and " . (count($syntax_errors) - 5) . " more\n";
            }
        }

        // Test 8: Check for classes
        $classes = $this->findClasses($plugin_dir);
        $this->test(
            $plugin_name,
            "Classes defined",
            count($classes) > 0
        );

        echo "    Found " . count($classes) . " classes\n";

        // Test 9: Check for namespace usage
        $has_namespaces = $this->checkNamespaceUsage($plugin_dir);
        $this->test(
            $plugin_name,
            "Namespaces used",
            $has_namespaces
        );

        // Test 10: Check for database tables
        $db_tables = $this->findDatabaseTables($plugin_dir);
        if (count($db_tables) > 0) {
            echo "    Database tables: " . count($db_tables) . " detected\n";
            foreach (array_slice($db_tables, 0, 3) as $table) {
                echo "      - $table\n";
            }
        }

        // Test 11: Plugin-specific tests
        $this->runPluginSpecificTests($plugin_name, $plugin_dir);

        echo "\n";

        return $this->results[$plugin_name];
    }

    private function runPluginSpecificTests($plugin_name, $plugin_dir) {
        switch ($plugin_name) {
            case 'AevovPatternSyncProtocol':
                $this->testAevovPatternSyncProtocol($plugin_dir);
                break;
            case 'Bloom Pattern Recognition':
                $this->testBloomPatternRecognition($plugin_dir);
                break;
            case 'APS Tools':
                $this->testAPSTools($plugin_dir);
                break;
        }
    }

    private function testAevovPatternSyncProtocol($plugin_dir) {
        // Test for critical APSP files
        $critical_files = [
            'Includes/Core/Loader.php',
            'Includes/DB/APS_Pattern_DB.php',
            'Includes/DB/APS_Queue_DB.php',
            'Includes/Consensus/ConsensusMechanism.php',
            'Includes/Proof/ProofOfContribution.php'
        ];

        foreach ($critical_files as $file) {
            $file_path = "$plugin_dir/$file";
            $exists = file_exists($file_path);
            $this->test(
                'AevovPatternSyncProtocol',
                basename($file) . " exists",
                $exists
            );

            if (!$exists) {
                $this->addIssue('AevovPatternSyncProtocol', 'missing_file', "Missing critical file: $file");
            }
        }

        // Check for REST API endpoints
        $api_file = "$plugin_dir/Includes/API/ProofOfContributionEndpoint.php";
        if (file_exists($api_file)) {
            $content = file_get_contents($api_file);
            $has_rest_routes = strpos($content, 'register_rest_route') !== false;
            $this->test(
                'AevovPatternSyncProtocol',
                "REST API endpoints defined",
                $has_rest_routes
            );
        }

        // Check for consensus mechanism
        $consensus_file = "$plugin_dir/Includes/Consensus/ConsensusMechanism.php";
        if (file_exists($consensus_file)) {
            $content = file_get_contents($consensus_file);
            $this->test(
                'AevovPatternSyncProtocol',
                "Consensus mechanism has validate() method",
                strpos($content, 'function validate') !== false
            );
        }
    }

    private function testBloomPatternRecognition($plugin_dir) {
        // Test for critical Bloom files
        $critical_files = [
            'includes/core/class-plugin-activator.php',
            'includes/models/class-chunk-model.php',
            'includes/models/class-pattern-model.php',
            'includes/models/class-tensor-model.php',
            'includes/network/class-message-queue.php'
        ];

        foreach ($critical_files as $file) {
            $file_path = "$plugin_dir/$file";
            $exists = file_exists($file_path);
            $this->test(
                'Bloom Pattern Recognition',
                basename($file) . " exists",
                $exists
            );

            if (!$exists) {
                $this->addIssue('Bloom Pattern Recognition', 'missing_file', "Missing critical file: $file");
            }
        }

        // Check for chunk processing
        $chunk_file = "$plugin_dir/includes/models/class-chunk-model.php";
        if (file_exists($chunk_file)) {
            $content = file_get_contents($chunk_file);
            $this->test(
                'Bloom Pattern Recognition',
                "Chunk model has process() method",
                strpos($content, 'function process') !== false
            );
        }

        // Check for pattern recognition
        $pattern_file = "$plugin_dir/includes/models/class-pattern-model.php";
        if (file_exists($pattern_file)) {
            $content = file_get_contents($pattern_file);
            $this->test(
                'Bloom Pattern Recognition',
                "Pattern model has recognize() method",
                strpos($content, 'function recognize') !== false ||
                strpos($content, 'function detect') !== false
            );
        }

        // Check for tensor operations
        $tensor_file = "$plugin_dir/includes/models/class-tensor-model.php";
        if (file_exists($tensor_file)) {
            $content = file_get_contents($tensor_file);
            $this->test(
                'Bloom Pattern Recognition',
                "Tensor model has mathematical operations",
                strpos($content, 'function multiply') !== false ||
                strpos($content, 'function dot') !== false ||
                strpos($content, 'function transform') !== false
            );
        }
    }

    private function testAPSTools($plugin_dir) {
        // Test for critical APS Tools files
        $critical_files = [
            'includes/class-aps-tools-activator.php',
            'includes/class-aps-tools-admin.php',
            'includes/db/class-aps-bloom-tensors-db.php'
        ];

        foreach ($critical_files as $file) {
            $file_path = "$plugin_dir/$file";
            $exists = file_exists($file_path);
            $this->test(
                'APS Tools',
                basename($file) . " exists",
                $exists
            );

            if (!$exists) {
                $this->addIssue('APS Tools', 'missing_file', "Missing critical file: $file");
            }
        }

        // Check for database integration
        $db_file = "$plugin_dir/includes/db/class-aps-bloom-tensors-db.php";
        if (file_exists($db_file)) {
            $content = file_get_contents($db_file);
            $this->test(
                'APS Tools',
                "Database class has create_tables() method",
                strpos($content, 'function create_tables') !== false
            );

            $this->test(
                'APS Tools',
                "Database class handles tensors",
                strpos($content, 'tensor') !== false
            );
        }

        // Check for admin interface
        $admin_file = "$plugin_dir/includes/class-aps-tools-admin.php";
        if (file_exists($admin_file)) {
            $content = file_get_contents($admin_file);
            $this->test(
                'APS Tools',
                "Admin class registers menu",
                strpos($content, 'add_menu_page') !== false ||
                strpos($content, 'add_submenu_page') !== false
            );
        }
    }

    private function findMainPluginFile($plugin_dir) {
        $files = glob("$plugin_dir/*.php");
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match('/Plugin Name:/i', $content)) {
                return $file;
            }
        }
        return null;
    }

    private function getPluginHeaders($file) {
        $headers = [];
        $content = file_get_contents($file);

        $header_fields = [
            'Name' => 'Plugin Name',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author'
        ];

        foreach ($header_fields as $key => $field) {
            if (preg_match('/' . $field . ':\s*(.+)$/im', $content, $matches)) {
                $headers[$key] = trim($matches[1]);
            }
        }

        return $headers;
    }

    private function validateSyntax($file) {
        $output = [];
        $return_var = 0;
        exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
        return $return_var === 0;
    }

    private function findPHPFiles($dir) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function findClasses($dir) {
        $classes = [];
        $files = $this->findPHPFiles($dir);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match_all('/\b(?:class|interface|trait)\s+(\w+)/i', $content, $matches)) {
                $classes = array_merge($classes, $matches[1]);
            }
        }

        return array_unique($classes);
    }

    private function checkNamespaceUsage($dir) {
        $files = $this->findPHPFiles($dir);
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match('/\bnamespace\s+/', $content)) {
                return true;
            }
        }
        return false;
    }

    private function findDatabaseTables($dir) {
        $tables = [];
        $files = $this->findPHPFiles($dir);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            // Look for CREATE TABLE statements
            if (preg_match_all('/CREATE TABLE.*?`?(\w+)`?/i', $content, $matches)) {
                $tables = array_merge($tables, $matches[1]);
            }
            // Look for table prefix usage
            if (preg_match_all('/\$wpdb->prefix\s*\.\s*[\'"](\w+)[\'"]/i', $content, $matches)) {
                $tables = array_merge($tables, $matches[1]);
            }
        }

        return array_unique($tables);
    }

    private function test($plugin_name, $description, $passed) {
        $this->total_tests++;

        if ($passed) {
            $this->passed_tests++;
            $this->results[$plugin_name]['passed']++;
            echo "  ✓ $description\n";
        } else {
            $this->failed_tests++;
            $this->results[$plugin_name]['failed']++;
            echo "  ✗ $description\n";
        }

        $this->results[$plugin_name]['tests'][] = [
            'description' => $description,
            'passed' => $passed
        ];
    }

    private function addIssue($plugin_name, $type, $description) {
        $this->issues[] = [
            'plugin' => $plugin_name,
            'type' => $type,
            'description' => $description
        ];
        $this->results[$plugin_name]['issues'][] = [
            'type' => $type,
            'description' => $description
        ];
    }

    public function printSummary() {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "   VALIDATION SUMMARY\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        foreach ($this->results as $plugin_name => $result) {
            $total = $result['passed'] + $result['failed'];
            $percentage = $total > 0 ? round(($result['passed'] / $total) * 100, 1) : 0;

            echo "$plugin_name:\n";
            echo "  Tests Run: $total\n";
            echo "  Passed: {$result['passed']} ($percentage%)\n";
            echo "  Failed: {$result['failed']}\n";
            echo "  Issues Found: " . count($result['issues']) . "\n";
            echo "\n";
        }

        $total = $this->passed_tests + $this->failed_tests;
        $percentage = $total > 0 ? round(($this->passed_tests / $total) * 100, 1) : 0;

        echo "OVERALL:\n";
        echo "  Total Tests: $total\n";
        echo "  Passed: {$this->passed_tests} ($percentage%)\n";
        echo "  Failed: {$this->failed_tests}\n";
        echo "  Total Issues: " . count($this->issues) . "\n";
        echo "\n";

        if ($this->failed_tests === 0) {
            echo "✓ ALL TESTS PASSED\n";
        } else {
            echo "⚠ SOME TESTS FAILED\n";
        }

        echo "═══════════════════════════════════════════════════════════════\n\n";

        return [
            'results' => $this->results,
            'issues' => $this->issues
        ];
    }
}

// Main execution
$validator = new AevovMainPluginValidator();

$plugins = [
    'AevovPatternSyncProtocol' => '/home/user/Aevov1/AevovPatternSyncProtocol',
    'Bloom Pattern Recognition' => '/home/user/Aevov1/bloom-pattern-recognition',
    'APS Tools' => '/home/user/Aevov1/aps-tools'
];

foreach ($plugins as $name => $dir) {
    $validator->validatePlugin($dir, $name);
}

$summary = $validator->printSummary();

// Export results for bug fix todo
file_put_contents(
    '/home/user/Aevov1/aevov-testing-framework/test-results.json',
    json_encode($summary, JSON_PRETTY_PRINT)
);

echo "Results saved to: test-results.json\n\n";
