#!/usr/bin/env php
<?php
/**
 * Aevov Plugin Validator
 * Validates WordPress plugins without requiring a database
 *
 * Tests:
 * - File structure and presence
 * - PHP syntax validation
 * - Class definitions and loading
 * - Plugin metadata
 * - Critical functionality
 */

class AevovPluginValidator {
    private $results = [];
    private $total_tests = 0;
    private $passed_tests = 0;
    private $failed_tests = 0;

    public function __construct() {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "   AEVOV PLUGIN VALIDATOR\n";
        echo "   Comprehensive Plugin Testing Without Database\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";
    }

    public function validatePlugin($plugin_dir, $plugin_name) {
        echo "┌─────────────────────────────────────────────────────────────┐\n";
        echo "│  Testing: $plugin_name\n";
        echo "└─────────────────────────────────────────────────────────────┘\n\n";

        $this->results[$plugin_name] = [
            'tests' => [],
            'passed' => 0,
            'failed' => 0
        ];

        // Test 1: Plugin directory exists
        $this->test(
            $plugin_name,
            "Plugin directory exists",
            is_dir($plugin_dir)
        );

        if (!is_dir($plugin_dir)) {
            echo "  ✗ Cannot continue - plugin directory not found\n\n";
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
                    echo "    Description: {$headers['Description']}\n";
                }
            }
        }

        // Test 5: Required directories exist
        $required_dirs = ['includes', 'includes/api'];
        foreach ($required_dirs as $dir) {
            $dir_path = "$plugin_dir/$dir";
            $this->test(
                $plugin_name,
                "Directory '$dir' exists",
                is_dir($dir_path)
            );
        }

        // Test 6: Find and validate all PHP files
        $php_files = $this->findPHPFiles($plugin_dir);
        $this->test(
            $plugin_name,
            "PHP files found",
            count($php_files) > 0
        );

        echo "    Found " . count($php_files) . " PHP files\n";

        // Test 7: Validate syntax of all PHP files
        $syntax_errors = 0;
        foreach ($php_files as $file) {
            if (!$this->validateSyntax($file)) {
                $syntax_errors++;
            }
        }
        $this->test(
            $plugin_name,
            "All PHP files have valid syntax",
            $syntax_errors === 0
        );

        if ($syntax_errors > 0) {
            echo "    ✗ $syntax_errors files with syntax errors\n";
        }

        // Test 8: Check for classes
        $classes = $this->findClasses($plugin_dir);
        $this->test(
            $plugin_name,
            "Classes defined",
            count($classes) > 0
        );

        echo "    Found " . count($classes) . " classes\n";

        // Test 9: Plugin-specific tests
        $this->runPluginSpecificTests($plugin_name, $plugin_dir);

        echo "\n";

        return $this->results[$plugin_name];
    }

    private function runPluginSpecificTests($plugin_name, $plugin_dir) {
        switch ($plugin_name) {
            case 'aevov-security':
                $this->testSecurityPlugin($plugin_dir);
                break;
            case 'aevov-physics-engine':
                $this->testPhysicsEngine($plugin_dir);
                break;
            case 'aevov-neuro-architect':
                $this->testNeuroArchitect($plugin_dir);
                break;
        }
    }

    private function testSecurityPlugin($plugin_dir) {
        // Test SecurityHelper class exists
        $helper_file = "$plugin_dir/includes/class-security-helper.php";
        $this->test(
            'aevov-security',
            "SecurityHelper class file exists",
            file_exists($helper_file)
        );

        if (file_exists($helper_file)) {
            $content = file_get_contents($helper_file);

            // Check for critical methods
            $methods = [
                'can_manage_aevov',
                'can_edit_aevov',
                'can_view_aevov',
                'sanitize_text',
                'sanitize_url',
                'verify_nonce',
                'create_nonce',
                'log_security_event'
            ];

            foreach ($methods as $method) {
                $has_method = (strpos($content, "function $method") !== false) ||
                             (strpos($content, "public static function $method") !== false);
                $this->test(
                    'aevov-security',
                    "SecurityHelper::$method() defined",
                    $has_method
                );
            }
        }
    }

    private function testPhysicsEngine($plugin_dir) {
        // Test for critical physics engine files
        $critical_files = [
            'includes/class-newtonian-solver.php',
            'includes/class-collision-detector.php',
            'includes/class-physics-world.php',
            'includes/api/class-physics-endpoint.php'
        ];

        foreach ($critical_files as $file) {
            $file_path = "$plugin_dir/$file";
            $this->test(
                'aevov-physics-engine',
                basename($file) . " exists",
                file_exists($file_path)
            );
        }

        // Check for physics solver implementations
        $newtonian = "$plugin_dir/includes/class-newtonian-solver.php";
        if (file_exists($newtonian)) {
            $content = file_get_contents($newtonian);
            $this->test(
                'aevov-physics-engine',
                "Newtonian solver has step() method",
                strpos($content, 'function step') !== false
            );
        }
    }

    private function testNeuroArchitect($plugin_dir) {
        // Test for critical NeuroArchitect files
        $critical_files = [
            'includes/class-blueprint-evolver.php',
            'includes/class-neural-pattern-catalog.php',
            'includes/class-model-comparator.php',
            'includes/api/class-neuroarchitect-endpoint.php'
        ];

        foreach ($critical_files as $file) {
            $file_path = "$plugin_dir/$file";
            $this->test(
                'aevov-neuro-architect',
                basename($file) . " exists",
                file_exists($file_path)
            );
        }

        // Check for evolution capability
        $evolver = "$plugin_dir/includes/class-blueprint-evolver.php";
        if (file_exists($evolver)) {
            $content = file_get_contents($evolver);
            $this->test(
                'aevov-neuro-architect',
                "Blueprint evolver has evolve() method",
                strpos($content, 'function evolve') !== false
            );
            $this->test(
                'aevov-neuro-architect',
                "Blueprint evolver has mutate() method",
                strpos($content, 'function mutate') !== false
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
            if (preg_match_all('/\bclass\s+(\w+)/i', $content, $matches)) {
                $classes = array_merge($classes, $matches[1]);
            }
        }

        return array_unique($classes);
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

    public function printSummary() {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "   VALIDATION SUMMARY\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        foreach ($this->results as $plugin_name => $result) {
            $total = $result['passed'] + $result['failed'];
            $percentage = $total > 0 ? round(($result['passed'] / $total) * 100, 1) : 0;

            echo "$plugin_name:\n";
            echo "  Passed: {$result['passed']}/$total ($percentage%)\n";
            echo "  Failed: {$result['failed']}/$total\n";
            echo "\n";
        }

        $total = $this->passed_tests + $this->failed_tests;
        $percentage = $total > 0 ? round(($this->passed_tests / $total) * 100, 1) : 0;

        echo "TOTAL:\n";
        echo "  Passed: {$this->passed_tests}/$total ($percentage%)\n";
        echo "  Failed: {$this->failed_tests}/$total\n";
        echo "\n";

        if ($this->failed_tests === 0) {
            echo "✓ ALL TESTS PASSED\n";
        } else {
            echo "⚠ SOME TESTS FAILED\n";
        }

        echo "═══════════════════════════════════════════════════════════════\n\n";
    }
}

// Main execution
$validator = new AevovPluginValidator();

$plugins = [
    'aevov-security' => '/home/user/Aevov1/aevov-security',
    'aevov-physics-engine' => '/home/user/Aevov1/aevov-physics-engine',
    'aevov-neuro-architect' => '/home/user/Aevov1/aevov-neuro-architect'
];

foreach ($plugins as $name => $dir) {
    $validator->validatePlugin($dir, $name);
}

$validator->printSummary();
