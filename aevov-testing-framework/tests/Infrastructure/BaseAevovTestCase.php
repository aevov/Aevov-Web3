<?php
/**
 * Base Test Case for all Aevov tests
 * Provides common utilities, fixtures, and helper methods
 */

namespace AevovTesting\Infrastructure;

use WP_UnitTestCase;

abstract class BaseAevovTestCase extends WP_UnitTestCase {

    protected $performance_metrics = [];
    protected $test_data_cleanup = [];

    /**
     * Set up test environment before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->performance_metrics = [];
        $this->test_data_cleanup = [];

        // Start performance tracking
        $this->startPerformanceTracking();
    }

    /**
     * Clean up after each test
     */
    public function tearDown(): void {
        // Record performance metrics
        $this->endPerformanceTracking();

        // Clean up test data
        $this->cleanupTestData();

        parent::tearDown();
    }

    /**
     * Start tracking performance for this test
     */
    protected function startPerformanceTracking() {
        $this->performance_metrics['start_time'] = microtime(true);
        $this->performance_metrics['start_memory'] = memory_get_usage(true);
        $this->performance_metrics['start_peak_memory'] = memory_get_peak_usage(true);
    }

    /**
     * End performance tracking and record metrics
     */
    protected function endPerformanceTracking() {
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        $end_peak_memory = memory_get_peak_usage(true);

        $this->performance_metrics['execution_time'] = $end_time - $this->performance_metrics['start_time'];
        $this->performance_metrics['memory_used'] = $end_memory - $this->performance_metrics['start_memory'];
        $this->performance_metrics['peak_memory'] = $end_peak_memory - $this->performance_metrics['start_peak_memory'];

        // Store metrics globally for reporting
        global $aevov_test_metrics;
        if (!isset($aevov_test_metrics)) {
            $aevov_test_metrics = [];
        }

        $test_name = get_class($this) . '::' . $this->getName();
        $aevov_test_metrics[$test_name] = $this->performance_metrics;
    }

    /**
     * Mark data for cleanup after test
     */
    protected function markForCleanup($type, $id) {
        $this->test_data_cleanup[] = ['type' => $type, 'id' => $id];
    }

    /**
     * Clean up all test data
     */
    protected function cleanupTestData() {
        global $wpdb;

        foreach ($this->test_data_cleanup as $item) {
            switch ($item['type']) {
                case 'post':
                    wp_delete_post($item['id'], true);
                    break;
                case 'user':
                    wp_delete_user($item['id']);
                    break;
                case 'option':
                    delete_option($item['id']);
                    break;
                case 'transient':
                    delete_transient($item['id']);
                    break;
                case 'table_row':
                    if (isset($item['table']) && isset($item['where'])) {
                        $wpdb->delete($item['table'], $item['where']);
                    }
                    break;
            }
        }
    }

    /**
     * Assert performance is within threshold
     */
    protected function assertPerformanceWithinThreshold($max_time_seconds, $max_memory_mb) {
        $this->assertLessThan(
            $max_time_seconds,
            $this->performance_metrics['execution_time'],
            "Test execution time exceeded threshold"
        );

        $memory_mb = $this->performance_metrics['memory_used'] / 1024 / 1024;
        $this->assertLessThan(
            $max_memory_mb,
            $memory_mb,
            "Test memory usage exceeded threshold"
        );
    }

    /**
     * Create a test user with specific capabilities
     */
    protected function createTestUser($role = 'administrator') {
        $user_id = $this->factory->user->create([
            'role' => $role,
            'user_login' => 'test_user_' . uniqid(),
            'user_email' => 'test_' . uniqid() . '@example.com',
        ]);

        $this->markForCleanup('user', $user_id);
        return $user_id;
    }

    /**
     * Create a test post with metadata
     */
    protected function createTestPost($args = []) {
        $defaults = [
            'post_title' => 'Test Post ' . uniqid(),
            'post_content' => 'Test content',
            'post_status' => 'publish',
            'post_type' => 'post',
        ];

        $post_id = $this->factory->post->create(array_merge($defaults, $args));
        $this->markForCleanup('post', $post_id);

        return $post_id;
    }

    /**
     * Assert array contains specific keys
     */
    protected function assertArrayHasKeys($keys, $array, $message = '') {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array missing key: $key");
        }
    }

    /**
     * Assert valid JSON structure
     */
    protected function assertValidJson($json_string, $message = '') {
        $this->assertIsString($json_string, $message ?: "Expected string");
        json_decode($json_string);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error(), $message ?: "Invalid JSON");
    }

    /**
     * Assert REST response is successful
     */
    protected function assertRestResponseSuccess($response, $message = '') {
        $this->assertInstanceOf('WP_REST_Response', $response, $message ?: "Expected WP_REST_Response");
        $this->assertEquals(200, $response->get_status(), $message ?: "Expected 200 status");
    }

    /**
     * Assert REST response is error
     */
    protected function assertRestResponseError($response, $expected_code = null, $message = '') {
        $this->assertInstanceOf('WP_Error', $response, $message ?: "Expected WP_Error");

        if ($expected_code !== null) {
            $this->assertEquals($expected_code, $response->get_error_code(), $message ?: "Unexpected error code");
        }
    }

    /**
     * Simulate REST API request
     */
    protected function simulateRestRequest($endpoint, $method = 'GET', $params = [], $headers = []) {
        $request = new \WP_REST_Request($method, $endpoint);

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $request->set_param($key, $value);
            }
        }

        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $request->set_header($key, $value);
            }
        }

        $server = rest_get_server();
        return $server->dispatch($request);
    }

    /**
     * Benchmark a callable and return execution time
     */
    protected function benchmark($callable, $iterations = 1) {
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            call_user_func($callable);
        }

        $end = microtime(true);

        return [
            'total_time' => $end - $start,
            'avg_time' => ($end - $start) / $iterations,
            'iterations' => $iterations,
        ];
    }
}
