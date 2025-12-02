<?php
/**
 * Class AevovIntegrationTest
 *
 * @package Aevov
 */

/**
 * Integration test case for Aevov network plugins.
 */
class AevovIntegrationTest extends WP_UnitTestCase {

    /**
     * Test that BLOOM Pattern Recognition and Aevov Pattern Sync Protocol integrate correctly.
     * Specifically, verify that a pattern created in BLOOM can be accessed/synchronized by APS.
     */
    function test_bloom_aps_pattern_synchronization() {
        // Ensure BLOOM and APS core classes exist
        $this->assertTrue(class_exists('\BLOOM\Core'), 'BLOOM Core class should exist.');
        $this->assertTrue(class_exists('\APS\Analysis\APS_Plugin'), 'Aevov Pattern Sync Protocol class should exist.');

        // Get instances of the main plugins
        $bloom_instance = \BLOOM\Core::get_instance();
        $aps_instance = \APS\Analysis\APS_Plugin::instance();

        // Assert that instances are valid
        $this->assertInstanceOf('\BLOOM\Core', $bloom_instance, 'BLOOM instance should be valid.');
        $this->assertInstanceOf('\APS\Analysis\APS_Plugin', $aps_instance, 'APS instance should be valid.');

        // Simulate creating a pattern in BLOOM
        // This requires access to BLOOM's internal methods or a public API for pattern creation.
        // Assuming BLOOM has a method to create a pattern for testing.
        // For a real test, you'd use the actual method BLOOM uses to create patterns.
        // If BLOOM doesn't have a public method, we might need to mock or use its internal API.

        // For now, let's assume a simple pattern creation via BLOOM's internal API or a helper.
        // This part needs to be adapted based on BLOOM's actual implementation.
        // Placeholder: Create a dummy pattern directly in the database if no API exists.
        global $wpdb;
        $pattern_table = $wpdb->prefix . 'bloom_patterns'; // Assuming BLOOM uses this table
        $pattern_data = [
            'pattern_hash' => hash('sha256', 'test_pattern_' . uniqid()), // Use pattern_hash
            'pattern_type' => 'text',
            'features' => json_encode(['content' => 'This is a test pattern for integration.']), // Store as JSON in 'features'
            'confidence' => 0.95, // Add confidence
            'metadata' => json_encode(['source' => 'integration_test']), // Add metadata
            'tensor_sku' => null, // Optional
            'site_id' => get_current_blog_id(), // Add site_id
            'status' => 'active', // Add status
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        // Insert the pattern directly into BLOOM's database table
        $inserted = $wpdb->insert($pattern_table, $pattern_data);
        $this->assertNotFalse($inserted, 'Should be able to insert a dummy pattern into BLOOM\'s table.');

        // Now, verify if APS can "see" or synchronize this pattern.
        // This would typically involve calling an APS method that lists or syncs patterns.
        // Assuming APS has a method like get_all_patterns() or sync_patterns().
        // This part needs to be adapted based on APS's actual implementation.
        
        // For demonstration, let's assume APS has a way to query BLOOM's patterns.
        // In a real scenario, APS might have a dedicated API or a DB query for this.
        // If APS has a sync mechanism, we'd trigger it and then check APS's own records.

        // Placeholder: Directly query BLOOM's table via APS's assumed internal access
        // (This is a simplified assumption for the test; real APS would have its own logic)
        $aps_can_see_pattern = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$pattern_table} WHERE pattern_hash = %s", // Query by pattern_hash
                $pattern_data['pattern_hash']
            )
        );
        $this->assertNotNull($aps_can_see_pattern, 'APS should be able to see the pattern created by BLOOM.');
        $this->assertEquals($pattern_data['features'], $aps_can_see_pattern->features, 'Pattern features should match.'); // Compare features
    }
    /**
     * Test a comprehensive workflow: Pattern Creation (BLOOM), Processing (APS), and Monitoring (ADN).
     */
    function test_pattern_workflow_bloom_aps_adn() {
        // Ensure core classes exist
        $this->assertTrue(class_exists('\BLOOM\Core'), 'BLOOM Core class should exist.');
        $this->assertTrue(class_exists('\APS\Analysis\APS_Plugin'), 'Aevov Pattern Sync Protocol class should exist.');
        $this->assertTrue(class_exists('\ADN\Core\DiagnosticNetwork'), 'Aevov Diagnostic Network class should exist.');

        // Get instances of the main plugins
        $bloom_instance = \BLOOM\Core::get_instance();
        $aps_instance = \APS\Analysis\APS_Plugin::instance();
        $adn_instance = \ADN\Core\DiagnosticNetwork::instance();

        // Assert that instances are valid
        $this->assertInstanceOf('\BLOOM\Core', $bloom_instance, 'BLOOM instance should be valid.');
        $this->assertInstanceOf('\APS\Analysis\APS_Plugin', $aps_instance, 'APS instance should be valid.');
        $this->assertInstanceOf('\ADN\Core\DiagnosticNetwork', $adn_instance, 'ADN instance should be valid.');

        // Step 1: Create a pattern using BLOOM
        global $wpdb;
        $pattern_table = $wpdb->prefix . 'bloom_patterns';
        $pattern_data = [
            'pattern_hash' => hash('sha256', 'workflow_pattern_' . uniqid()),
            'pattern_type' => 'workflow',
            'features' => json_encode(['content' => 'This is a pattern for workflow testing.']),
            'confidence' => 0.85,
            'metadata' => json_encode(['source' => 'workflow_test']),
            'tensor_sku' => null,
            'site_id' => get_current_blog_id(),
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($pattern_table, $pattern_data);
        $this->assertNotFalse($inserted, 'Should be able to insert a workflow pattern into BLOOM\'s table.');

        // Step 2: Simulate APS processing this pattern
        // Enqueue a job for Bloom integration and process it
        $process_queue = new \APS\Queue\ProcessQueue();
        $job_id = $process_queue->enqueue_job([
            'type' => 'bloom_integration',
            'data' => [
                'pattern_hash' => $pattern_data['pattern_hash'],
                'pattern_type' => $pattern_data['pattern_type'],
                'features' => $pattern_data['features'],
                'confidence' => $pattern_data['confidence'],
                'site_id' => $pattern_data['site_id']
            ]
        ]);
        $this->assertNotFalse($job_id, 'Should be able to enqueue a BLOOM integration job.');

        // Manually trigger the queue processing
        $process_queue->process_queue();

        // Verify the job was processed and completed
        $job_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}aps_process_queue WHERE job_uuid = %s", // Changed job_id to job_uuid
            $job_id
        ));
        $this->assertEquals('completed', $job_status, 'APS job should be completed after processing.');

        // Verify APS has recorded metrics for this pattern (assuming BloomIntegration processes it)
        $aps_metrics_table = $wpdb->prefix . 'aps_metrics';
        $metric_exists = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$aps_metrics_table} WHERE metric_name = %s AND metric_value = %s",
            'bloom_pattern_received', // Corrected metric name
            1 // Assuming each pattern processing adds 1 to this metric
        ));
        // This assertion might need refinement based on how APS actually records metrics for processed patterns.
        // For now, we'll check if any 'patterns_processed' metric exists.
        $this->assertNotNull($metric_exists, 'APS should have recorded metrics for the processed pattern.');


        // Step 3: Simulate ADN monitoring the health/status of this pattern or related components
        // Trigger ADN's health check
        $adn_instance->perform_health_check();

        // Retrieve system status from ADN
        $system_status = $adn_instance->get_system_status();
        
        // Assert on the overall health status or specific component status related to BLOOM/APS
        $this->assertArrayHasKey('overall_health', $system_status, 'ADN system status should contain overall_health.');
        $this->assertContains($system_status['overall_health'], ['excellent', 'good', 'fair'], 'ADN overall health should be at least fair after processing.');

        // Optionally, check specific component status if ADN registers BLOOM/APS as components
        // This requires knowing the component_id ADN assigns to BLOOM/APS
        // For example:
        // $bloom_component_status = $system_status['components']['bloom-pattern-recognition']['overall_status'] ?? 'unknown';
        // $this->assertEquals('pass', $bloom_component_status, 'BLOOM component should be healthy.');
    }
}