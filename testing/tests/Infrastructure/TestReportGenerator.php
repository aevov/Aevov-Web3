<?php
/**
 * Test Report Generator
 * Generates comprehensive HTML and JSON reports for test results
 */

namespace AevovTesting\Infrastructure;

class TestReportGenerator {

    private $results = [];
    private $performance_data = [];
    private $output_dir;

    public function __construct($output_dir = null) {
        $this->output_dir = $output_dir ?? dirname(__FILE__) . '/../../reports';

        if (!file_exists($this->output_dir)) {
            mkdir($this->output_dir, 0755, true);
        }
    }

    /**
     * Add test result
     */
    public function addResult($test_name, $status, $details = []) {
        $this->results[] = [
            'test' => $test_name,
            'status' => $status,
            'details' => $details,
            'timestamp' => current_time('mysql'),
        ];
    }

    /**
     * Add performance data
     */
    public function addPerformanceData($data) {
        $this->performance_data = array_merge($this->performance_data, $data);
    }

    /**
     * Generate HTML report
     */
    public function generateHTMLReport() {
        $total_tests = count($this->results);
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'passed'));
        $failed = count(array_filter($this->results, fn($r) => $r['status'] === 'failed'));
        $skipped = count(array_filter($this->results, fn($r) => $r['status'] === 'skipped'));
        $pass_rate = $total_tests > 0 ? round(($passed / $total_tests) * 100, 2) : 0;

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aevov Test Report</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card.passed {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stat-card.failed {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }
        .stat-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stat-number {
            font-size: 48px;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            font-size: 14px;
            text-transform: uppercase;
            opacity: 0.9;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        .results-table th {
            background: #3498db;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .results-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        .results-table tr:hover {
            background: #f8f9fa;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-passed {
            background: #d4edda;
            color: #155724;
        }
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        .status-skipped {
            background: #fff3cd;
            color: #856404;
        }
        .performance-section {
            margin: 40px 0;
        }
        .perf-metric {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-left: 4px solid #3498db;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #ecf0f1;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #11998e 0%, #38ef7d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Aevov Test Report</h1>
        <p><strong>Generated:</strong> {$this->getTimestamp()}</p>

        <div class="summary">
            <div class="stat-card total">
                <div class="stat-label">Total Tests</div>
                <div class="stat-number">{$total_tests}</div>
            </div>
            <div class="stat-card passed">
                <div class="stat-label">Passed</div>
                <div class="stat-number">{$passed}</div>
            </div>
            <div class="stat-card failed">
                <div class="stat-label">Failed</div>
                <div class="stat-number">{$failed}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pass Rate</div>
                <div class="stat-number">{$pass_rate}%</div>
            </div>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" style="width: {$pass_rate}%">{$pass_rate}%</div>
        </div>

        <h2>📋 Test Results</h2>
        <table class="results-table">
            <thead>
                <tr>
                    <th>Test Name</th>
                    <th>Status</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($this->results as $result) {
            $status_class = 'status-' . $result['status'];
            $html .= <<<HTML
                <tr>
                    <td>{$result['test']}</td>
                    <td><span class="status-badge {$status_class}">{$result['status']}</span></td>
                    <td>{$result['timestamp']}</td>
                </tr>
HTML;
        }

        $html .= <<<HTML
            </tbody>
        </table>

        <div class="performance-section">
            <h2>⚡ Performance Metrics</h2>
            {$this->generatePerformanceHTML()}
        </div>
    </div>
</body>
</html>
HTML;

        $filename = $this->output_dir . '/test-report-' . date('Y-m-d-His') . '.html';
        file_put_contents($filename, $html);

        return $filename;
    }

    /**
     * Generate performance metrics HTML
     */
    private function generatePerformanceHTML() {
        if (empty($this->performance_data)) {
            return '<p>No performance data available.</p>';
        }

        $html = '';

        foreach ($this->performance_data as $test_name => $metrics) {
            if (isset($metrics['execution_time'])) {
                $time_ms = round($metrics['execution_time'] * 1000, 2);
                $memory_mb = isset($metrics['memory_used']) ? round($metrics['memory_used'] / 1024 / 1024, 2) : 0;

                $html .= <<<HTML
                <div class="perf-metric">
                    <strong>{$test_name}</strong><br>
                    ⏱️ Execution Time: {$time_ms}ms |
                    💾 Memory: {$memory_mb}MB
                </div>
HTML;
            }
        }

        return $html;
    }

    /**
     * Generate JSON report
     */
    public function generateJSONReport() {
        $report = [
            'generated_at' => $this->getTimestamp(),
            'summary' => [
                'total_tests' => count($this->results),
                'passed' => count(array_filter($this->results, fn($r) => $r['status'] === 'passed')),
                'failed' => count(array_filter($this->results, fn($r) => $r['status'] === 'failed')),
                'skipped' => count(array_filter($this->results, fn($r) => $r['status'] === 'skipped')),
            ],
            'results' => $this->results,
            'performance' => $this->performance_data,
        ];

        $filename = $this->output_dir . '/test-report-' . date('Y-m-d-His') . '.json';
        file_put_contents($filename, json_encode($report, JSON_PRETTY_PRINT));

        return $filename;
    }

    /**
     * Get current timestamp
     */
    private function getTimestamp() {
        return date('Y-m-d H:i:s');
    }

    /**
     * Clear all data
     */
    public function clear() {
        $this->results = [];
        $this->performance_data = [];
    }
}
