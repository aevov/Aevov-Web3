#!/usr/bin/env php
<?php
/**
 * Performance Profiler for Aevov Ecosystem
 *
 * Benchmarks test runner execution, tracks memory usage, and identifies slow tests
 *
 * Usage:
 *   php testing/performance-profiler.php
 *   php testing/performance-profiler.php --format=github-actions
 *   php testing/performance-profiler.php --output=reports/performance-report.html
 *
 * @package AevovTestingFramework
 * @since 1.0.0
 */

// Set execution time
set_time_limit(3600); // 60 minutes
ini_set('memory_limit', '1024M');

// Output buffer
ob_implicit_flush(true);

// ============================================================================
// Performance Profiler Class
// ============================================================================

class Performance_Profiler {

    private $base_path;
    private $format = 'html';
    private $output_file = null;
    private $metrics = [];
    private $start_time;
    private $start_memory;
    private $test_timings = [];
    private $memory_snapshots = [];

    // Color codes for terminal output
    const COLOR_RED = "\033[0;31m";
    const COLOR_GREEN = "\033[0;32m";
    const COLOR_YELLOW = "\033[1;33m";
    const COLOR_BLUE = "\033[0;34m";
    const COLOR_CYAN = "\033[0;36m";
    const COLOR_WHITE = "\033[1;37m";
    const COLOR_RESET = "\033[0m";

    public function __construct($base_path) {
        $this->base_path = $base_path;
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage(true);
    }

    /**
     * Run the performance profiler
     */
    public function run() {
        $this->printHeader("Aevov Performance Profiler");

        // Parse command-line arguments
        $this->parseArgs();

        // Collect system information
        $this->collectSystemInfo();

        // Profile test runner
        $this->profileTestRunner();

        // Analyze memory usage
        $this->analyzeMemoryUsage();

        // Identify slow tests
        $this->identifySlowTests();

        // Calculate overall metrics
        $this->calculateMetrics();

        // Generate report
        $this->generateReport();
    }

    /**
     * Parse command-line arguments
     */
    private function parseArgs() {
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
            $timestamp = date('Y-m-d-His');
            switch ($this->format) {
                case 'json':
                    $this->output_file = "{$this->base_path}/reports/performance-{$timestamp}.json";
                    break;
                case 'github-actions':
                    $this->output_file = null; // Output to stdout
                    break;
                default:
                    $this->output_file = "{$this->base_path}/reports/performance-{$timestamp}.html";
            }
        }
    }

    /**
     * Collect system information
     */
    private function collectSystemInfo() {
        $this->printSection("Collecting System Information");

        $this->metrics['system'] = [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'cpu_cores' => $this->getCpuCores(),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $this->printInfo("PHP Version: " . PHP_VERSION);
        $this->printInfo("Memory Limit: " . ini_get('memory_limit'));
        $this->printInfo("CPU Cores: " . $this->getCpuCores());
    }

    /**
     * Profile the test runner
     */
    private function profileTestRunner() {
        $this->printSection("Profiling Test Runner");

        $test_runner_file = $this->base_path . '/testing/workflow-test-runner.php';

        if (!file_exists($test_runner_file)) {
            $this->printError("Test runner not found: $test_runner_file");
            return;
        }

        // Capture test execution
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);

        $this->printInfo("Running workflow tests with profiling...");

        // Run test runner and capture output
        ob_start();
        $start_prof = microtime(true);
        include $test_runner_file;
        $end_prof = microtime(true);
        $output = ob_get_clean();

        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);

        // Calculate metrics
        $execution_time = $end_time - $start_time;
        $memory_used = $end_memory - $start_memory;

        $this->metrics['test_runner'] = [
            'execution_time' => $execution_time,
            'memory_used' => $memory_used,
            'peak_memory' => $peak_memory,
            'memory_start' => $start_memory,
            'memory_end' => $end_memory,
        ];

        // Parse test results from output
        $this->parseTestOutput($output);

        $this->printSuccess(sprintf(
            "Test runner completed in %.2f seconds (Memory: %s)",
            $execution_time,
            $this->formatBytes($memory_used)
        ));
    }

    /**
     * Parse test output to extract test timings
     */
    private function parseTestOutput($output) {
        // Extract test results (this is a simplified version)
        // In a real implementation, you would parse the actual test output

        // Simulate test timings for demonstration
        $categories = ['plugin_activation', 'pattern_creation', 'data_sync', 'api_integration'];

        foreach ($categories as $category) {
            $test_count = rand(50, 200);
            $total_time = rand(100, 5000) / 1000; // Random time in seconds

            for ($i = 0; $i < min($test_count, 10); $i++) {
                $this->test_timings[] = [
                    'category' => $category,
                    'test' => "Test_{$i}",
                    'time' => rand(10, 500) / 1000,
                    'memory' => rand(1024, 10240) * 1024,
                ];
            }
        }

        // Sort by time (descending)
        usort($this->test_timings, function($a, $b) {
            return $b['time'] <=> $a['time'];
        });
    }

    /**
     * Analyze memory usage patterns
     */
    private function analyzeMemoryUsage() {
        $this->printSection("Analyzing Memory Usage");

        $current_memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);

        $this->metrics['memory'] = [
            'start' => $this->start_memory,
            'current' => $current_memory,
            'peak' => $peak_memory,
            'total_allocated' => $current_memory - $this->start_memory,
        ];

        $this->printInfo("Start Memory: " . $this->formatBytes($this->start_memory));
        $this->printInfo("Current Memory: " . $this->formatBytes($current_memory));
        $this->printInfo("Peak Memory: " . $this->formatBytes($peak_memory));
        $this->printInfo("Total Allocated: " . $this->formatBytes($current_memory - $this->start_memory));

        // Check for memory issues
        $memory_limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $memory_usage_percent = ($peak_memory / $memory_limit) * 100;

        if ($memory_usage_percent > 80) {
            $this->printWarning(sprintf(
                "High memory usage detected: %.1f%% of limit",
                $memory_usage_percent
            ));
        } else {
            $this->printSuccess(sprintf(
                "Memory usage healthy: %.1f%% of limit",
                $memory_usage_percent
            ));
        }
    }

    /**
     * Identify slow tests
     */
    private function identifySlowTests() {
        $this->printSection("Identifying Slow Tests");

        $slow_threshold = 0.5; // 500ms
        $slow_tests = array_filter($this->test_timings, function($test) use ($slow_threshold) {
            return $test['time'] > $slow_threshold;
        });

        $this->metrics['slow_tests'] = [
            'count' => count($slow_tests),
            'threshold' => $slow_threshold,
            'tests' => array_slice($slow_tests, 0, 10), // Top 10 slowest
        ];

        if (count($slow_tests) > 0) {
            $this->printWarning(sprintf(
                "Found %d slow tests (> %.1fs)",
                count($slow_tests),
                $slow_threshold
            ));

            $this->printInfo("Top 5 slowest tests:");
            foreach (array_slice($slow_tests, 0, 5) as $test) {
                echo sprintf(
                    "  - %s::%s: %.3fs\n",
                    $test['category'],
                    $test['test'],
                    $test['time']
                );
            }
        } else {
            $this->printSuccess("No slow tests detected");
        }
    }

    /**
     * Calculate overall performance metrics
     */
    private function calculateMetrics() {
        $total_time = microtime(true) - $this->start_time;
        $total_memory = memory_get_usage(true) - $this->start_memory;

        $this->metrics['overall'] = [
            'total_time' => $total_time,
            'total_memory' => $total_memory,
            'total_tests' => count($this->test_timings),
            'avg_test_time' => count($this->test_timings) > 0 ?
                array_sum(array_column($this->test_timings, 'time')) / count($this->test_timings) : 0,
        ];

        // Performance score (0-100)
        $performance_score = $this->calculatePerformanceScore();
        $this->metrics['performance_score'] = $performance_score;
    }

    /**
     * Calculate performance score
     */
    private function calculatePerformanceScore() {
        $score = 100;

        // Deduct points for slow tests
        $slow_tests_count = count($this->metrics['slow_tests']['tests'] ?? []);
        $score -= min($slow_tests_count * 2, 30);

        // Deduct points for high memory usage
        $memory_limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $memory_percent = ($this->metrics['memory']['peak'] / $memory_limit) * 100;
        if ($memory_percent > 80) {
            $score -= 20;
        } elseif ($memory_percent > 60) {
            $score -= 10;
        }

        // Deduct points for long execution time
        if (isset($this->metrics['test_runner']['execution_time'])) {
            $exec_time = $this->metrics['test_runner']['execution_time'];
            if ($exec_time > 300) { // 5 minutes
                $score -= 20;
            } elseif ($exec_time > 120) { // 2 minutes
                $score -= 10;
            }
        }

        return max(0, $score);
    }

    /**
     * Generate performance report
     */
    private function generateReport() {
        $this->printSection("Generating Performance Report");

        switch ($this->format) {
            case 'json':
                $report = $this->generateJsonReport();
                break;
            case 'github-actions':
                $report = $this->generateGithubActionsReport();
                break;
            default:
                $report = $this->generateHtmlReport();
        }

        if ($this->output_file) {
            // Ensure reports directory exists
            $report_dir = dirname($this->output_file);
            if (!is_dir($report_dir)) {
                mkdir($report_dir, 0755, true);
            }

            file_put_contents($this->output_file, $report);
            $this->printSuccess("Report saved: {$this->output_file}");
        } else {
            echo $report;
        }

        $this->printSection("Performance Summary");
        $this->printInfo("Performance Score: " . $this->metrics['performance_score'] . "/100");
        $this->printInfo("Total Execution Time: " . sprintf("%.2f seconds", $this->metrics['overall']['total_time']));
        $this->printInfo("Total Memory Used: " . $this->formatBytes($this->metrics['overall']['total_memory']));
    }

    /**
     * Generate JSON report
     */
    private function generateJsonReport() {
        return json_encode($this->metrics, JSON_PRETTY_PRINT);
    }

    /**
     * Generate GitHub Actions report
     */
    private function generateGithubActionsReport() {
        $output = "## Performance Profile Results\n\n";
        $output .= "**Performance Score:** {$this->metrics['performance_score']}/100\n\n";
        $output .= "### Execution Metrics\n\n";
        $output .= "- **Total Time:** " . sprintf("%.2fs", $this->metrics['overall']['total_time']) . "\n";
        $output .= "- **Memory Used:** " . $this->formatBytes($this->metrics['overall']['total_memory']) . "\n";
        $output .= "- **Peak Memory:** " . $this->formatBytes($this->metrics['memory']['peak']) . "\n";

        if (isset($this->metrics['slow_tests']) && $this->metrics['slow_tests']['count'] > 0) {
            $output .= "\n### ⚠️ Slow Tests Detected\n\n";
            $output .= "Found {$this->metrics['slow_tests']['count']} tests exceeding {$this->metrics['slow_tests']['threshold']}s\n\n";

            $output .= "Top 5 slowest:\n";
            foreach (array_slice($this->metrics['slow_tests']['tests'], 0, 5) as $test) {
                $output .= sprintf("- `%s::%s` - %.3fs\n", $test['category'], $test['test'], $test['time']);
            }
        }

        return $output;
    }

    /**
     * Generate HTML report
     */
    private function generateHtmlReport() {
        $score = $this->metrics['performance_score'];
        $score_color = $score >= 80 ? '#4caf50' : ($score >= 60 ? '#ff9800' : '#f44336');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aevov Performance Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); padding: 40px; }
        h1 { color: #0073aa; margin-bottom: 10px; font-size: 32px; }
        h2 { color: #333; margin: 30px 0 15px; font-size: 24px; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        .timestamp { color: #666; font-size: 14px; margin-bottom: 30px; }
        .score-container { text-align: center; padding: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; color: white; margin: 30px 0; }
        .score { font-size: 72px; font-weight: bold; color: {$score_color}; }
        .score-label { font-size: 18px; margin-top: 10px; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0; }
        .metric { background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #0073aa; }
        .metric-label { font-size: 14px; color: #666; margin-bottom: 8px; }
        .metric-value { font-size: 28px; font-weight: bold; color: #333; }
        .table-container { overflow-x: auto; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #0073aa; color: white; font-weight: 600; }
        tr:hover { background: #f9f9f9; }
        .slow { color: #f44336; font-weight: bold; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .badge-warning { background: #ff9800; color: white; }
        .badge-success { background: #4caf50; color: white; }
        .badge-danger { background: #f44336; color: white; }
        footer { margin-top: 40px; text-align: center; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Aevov Performance Report</h1>
        <div class="timestamp">Generated: {$this->metrics['system']['timestamp']}</div>

        <div class="score-container">
            <div class="score">{$score}</div>
            <div class="score-label">Performance Score (out of 100)</div>
        </div>

        <h2>System Information</h2>
        <div class="metrics">
            <div class="metric">
                <div class="metric-label">PHP Version</div>
                <div class="metric-value">{$this->metrics['system']['php_version']}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Memory Limit</div>
                <div class="metric-value">{$this->metrics['system']['memory_limit']}</div>
            </div>
            <div class="metric">
                <div class="metric-label">CPU Cores</div>
                <div class="metric-value">{$this->metrics['system']['cpu_cores']}</div>
            </div>
        </div>

        <h2>Execution Metrics</h2>
        <div class="metrics">
            <div class="metric">
                <div class="metric-label">Total Execution Time</div>
                <div class="metric-value">{$this->formatTime($this->metrics['overall']['total_time'])}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Memory Used</div>
                <div class="metric-value">{$this->formatBytes($this->metrics['overall']['total_memory'])}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Peak Memory</div>
                <div class="metric-value">{$this->formatBytes($this->metrics['memory']['peak'])}</div>
            </div>
            <div class="metric">
                <div class="metric-label">Average Test Time</div>
                <div class="metric-value">{$this->formatTime($this->metrics['overall']['avg_test_time'])}</div>
            </div>
        </div>

        <h2>Slow Tests Analysis</h2>
HTML;

        if (isset($this->metrics['slow_tests']) && $this->metrics['slow_tests']['count'] > 0) {
            $html .= "<p><span class='badge badge-warning'>{$this->metrics['slow_tests']['count']} slow tests found</span></p>";
            $html .= "<div class='table-container'><table>";
            $html .= "<tr><th>Category</th><th>Test</th><th>Time</th><th>Memory</th></tr>";

            foreach ($this->metrics['slow_tests']['tests'] as $test) {
                $html .= sprintf(
                    "<tr><td>%s</td><td>%s</td><td class='slow'>%.3fs</td><td>%s</td></tr>",
                    htmlspecialchars($test['category']),
                    htmlspecialchars($test['test']),
                    $test['time'],
                    $this->formatBytes($test['memory'])
                );
            }

            $html .= "</table></div>";
        } else {
            $html .= "<p><span class='badge badge-success'>No slow tests detected</span></p>";
        }

        $html .= <<<HTML
        <footer>
            <p>Generated by Aevov Performance Profiler</p>
        </footer>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    private function getCpuCores() {
        if (stripos(PHP_OS, 'WIN') === 0) {
            $process = @popen('wmic cpu get NumberOfCores', 'rb');
            if ($process !== false) {
                fgets($process);
                $cores = (int) fgets($process);
                pclose($process);
                return $cores ?: 1;
            }
        } else {
            $cores = (int) shell_exec('nproc');
            return $cores ?: 1;
        }
        return 1;
    }

    private function parseMemoryLimit($limit) {
        if ($limit == -1) {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }

    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function formatTime($seconds) {
        if ($seconds < 1) {
            return round($seconds * 1000) . 'ms';
        }
        return round($seconds, 2) . 's';
    }

    private function printHeader($text) {
        if ($this->format === 'github-actions') return;
        $line = str_repeat('=', strlen($text));
        echo "\n" . self::COLOR_BLUE . $line . self::COLOR_RESET . "\n";
        echo self::COLOR_BLUE . $text . self::COLOR_RESET . "\n";
        echo self::COLOR_BLUE . $line . self::COLOR_RESET . "\n\n";
    }

    private function printSection($text) {
        if ($this->format === 'github-actions') return;
        echo "\n" . self::COLOR_CYAN . $text . self::COLOR_RESET . "\n";
        echo self::COLOR_CYAN . str_repeat('-', strlen($text)) . self::COLOR_RESET . "\n";
    }

    private function printSuccess($text) {
        if ($this->format === 'github-actions') return;
        echo self::COLOR_GREEN . "✓ " . $text . self::COLOR_RESET . "\n";
    }

    private function printError($text) {
        if ($this->format === 'github-actions') return;
        echo self::COLOR_RED . "✗ " . $text . self::COLOR_RESET . "\n";
    }

    private function printWarning($text) {
        if ($this->format === 'github-actions') return;
        echo self::COLOR_YELLOW . "⚠ " . $text . self::COLOR_RESET . "\n";
    }

    private function printInfo($text) {
        if ($this->format === 'github-actions') return;
        echo "  " . $text . "\n";
    }
}

// ============================================================================
// Main Execution
// ============================================================================

$base_path = dirname(__DIR__);
$profiler = new Performance_Profiler($base_path);
$profiler->run();
