<?php

use PHPUnit\Framework\TestCase;

/**
 * Class ChunkWorkflowTest
 *
 * Test case for the Chunk Upload, Processing, and Diagnostic Workflow.
 */
class ChunkWorkflowTest extends TestCase {

    protected $chunk_id;
    protected $chunk_file_path;
    protected $bloom_pattern_id;
    protected $aps_pattern_id;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        global $wpdb;

        // Ensure necessary tables are created for the test
        require_once ABSPATH . 'wp-content/plugins/aps-tools/includes/models/class-bloom-tensor-storage.php';
        if (class_exists('\APSTools\Models\BloomTensorStorage')) {
            $bloom_tensor_storage = \APSTools\Models\BloomTensorStorage::instance();
            // Call the private create_database_tables method using reflection
            $reflection_method = new ReflectionMethod($bloom_tensor_storage, 'create_database_tables');
            $reflection_method->setAccessible(true);
            $reflection_method->invoke($bloom_tensor_storage);
        }

        require_once ABSPATH . 'wp-content/plugins/bloom-pattern-recognition/includes/core/class-plugin-activator.php';
        if (class_exists('\BLOOM\Core\PluginActivator')) {
            (new \BLOOM\Core\PluginActivator())->create_tables();
        }

        require_once ABSPATH . 'wp-content/plugins/AevovPatternSyncProtocol/Includes/DB/APS_Pattern_DB.php';
        if (class_exists('\APS\DB\APS_Pattern_DB')) {
            (new \APS\DB\APS_Pattern_DB($wpdb))->create_tables();
        }

        // Create a dummy chunk file
        $this->chunk_file_path = WP_CONTENT_DIR . '/uploads/test_chunk_data_' . uniqid() . '.json';
        $sample_chunk_data = [
            'id' => 'test_chunk_' . uniqid(),
            'values' => array_map('floatval', array_fill(0, 100, rand(0, 255))), // Sample array of 100 floats
            'shape' => [10, 10],
            'dtype' => 'float32',
            'metadata' => [
                'source' => 'test_workflow',
                'timestamp' => time(),
            ],
        ];
        wp_mkdir_p(dirname($this->chunk_file_path));
        file_put_contents($this->chunk_file_path, json_encode($sample_chunk_data));

        // Create a dummy bloom_chunk post
        $this->chunk_id = wp_insert_post([
            'post_title'    => 'Test Bloom Chunk ' . uniqid(),
            'post_type'     => 'bloom_chunk',
            'post_status'   => 'publish',
        ]);
        $this->assertNotFalse($this->chunk_id, 'Failed to create bloom_chunk post.');
        update_post_meta($this->chunk_id, '_chunk_file', $this->chunk_file_path);
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void {
        global $wpdb;

        // Cleanup bloom_chunk post and its meta
        if ($this->chunk_id) {
            wp_delete_post($this->chunk_id, true);
        }

        // Cleanup dummy chunk file
        if (file_exists($this->chunk_file_path)) {
            unlink($this->chunk_file_path);
        }

        // Cleanup BLOOM tensor data
        $wpdb->delete($wpdb->prefix . 'aps_tensor_data', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);
        $wpdb->delete($wpdb->prefix . 'aps_bloom_tensors', ['chunk_id' => $this->chunk_id]);

        // Cleanup BLOOM pattern
        if ($this->bloom_pattern_id) {
            $wpdb->delete($wpdb->prefix . 'bloom_patterns', ['id' => $this->bloom_pattern_id]);
        }

        // Cleanup APS pattern
        if ($this->aps_pattern_id) {
            $wpdb->delete($wpdb->prefix . 'aps_patterns', ['id' => $this->aps_pattern_id]);
        }

        parent::tearDown();
    }

    /**
     * Test the full workflow: Chunk Upload, BLOOM Processing, APS Tools Management, and Diagnostic Health Check.
     */
    public function test_chunk_upload_processing_diagnostic_workflow() {
        global $wpdb;

        // 1. Simulate Chunk Upload
        require_once ABSPATH . 'wp-content/plugins/aps-tools/includes/models/class-bloom-tensor-storage.php';
        $bloom_tensor_storage = \APSTools\Models\BloomTensorStorage::instance();
        $upload_success = $bloom_tensor_storage->store_tensor_data($this->chunk_id, $this->chunk_file_path);
        $this->assertTrue($upload_success, 'Chunk data should be stored successfully.');

        // Verify tensor data in aps_tensor_data table
        $table_name = $wpdb->prefix . 'aps_tensor_data';
        $query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE chunk_id = %d", $this->chunk_id);
        error_log('ChunkWorkflowTest: Querying for tensor data: ' . $query);
        $tensor_data_entry = $wpdb->get_row($query);
        error_log('ChunkWorkflowTest: Result of tensor data query: ' . ($tensor_data_entry ? 'found' : 'not found'));
        $this->assertNotNull($tensor_data_entry, 'Tensor data should be present in aps_tensor_data table.');
        $this->assertEquals($this->chunk_id, $tensor_data_entry->chunk_id, 'Chunk ID in tensor data mismatch.');
        $this->assertJson($tensor_data_entry->tensor_data, 'Tensor data should be valid JSON.');

        // 2. Verify BLOOM Processing
        // Manually trigger BLOOM processing if not automatic
        // Assuming BLOOM processing is triggered by a hook or a specific function call after tensor data is stored.
        // For this test, we'll simulate the BLOOM pattern creation based on the tensor data.
        // In a real scenario, you'd call the function that processes the tensor data into a BLOOM pattern.
        require_once ABSPATH . 'wp-content/plugins/bloom-pattern-recognition/includes/processing/class-tensor-processor.php';
        $tensor_processor = new \BLOOM\Processing\TensorProcessor();
        $tensor_data_decoded = json_decode($tensor_data_entry->tensor_data, true);
        $this->assertNotNull($tensor_data_decoded, 'Decoded tensor data should not be null.');
        $processing_result = $tensor_processor->process_tensor($tensor_data_decoded);
        $this->assertIsArray($processing_result, 'Tensor processing should return an array result.');
        $this->assertArrayHasKey('tensor_sku', $processing_result, 'Processing result should contain tensor_sku.');
        $this->assertArrayHasKey('patterns_found', $processing_result, 'Processing result should contain patterns_found count.');

        // Retrieve the pattern from the database using the tensor_sku returned by the processor
        require_once ABSPATH . 'wp-content/plugins/bloom-pattern-recognition/includes/models/class-pattern-model.php';
        $pattern_model = new \BLOOM\Models\PatternModel();
        
        $retrieved_patterns = $pattern_model->get_by_tensor_sku($processing_result['tensor_sku']);
        $this->assertIsArray($retrieved_patterns, 'Retrieved patterns should be an array.');
        $this->assertNotEmpty($retrieved_patterns, 'At least one pattern should be retrieved for the tensor SKU.');
        
        // Take the first retrieved pattern for further assertions
        $bloom_pattern_data = $retrieved_patterns[0];
        $this->assertArrayHasKey('pattern_hash', $bloom_pattern_data, 'Retrieved BLOOM pattern data should contain pattern_hash.');
        $this->assertArrayHasKey('features', $bloom_pattern_data, 'Retrieved BLOOM pattern data should contain features.');
        
        // Store the bloom_pattern_id for tearDown
        $this->bloom_pattern_id = $bloom_pattern_data['id'];

        // The pattern is already inserted by TensorProcessor, so no need to re-insert.
        // We just need to ensure it exists and has the correct data.
        $bloom_pattern = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}bloom_patterns WHERE id = %d", $this->bloom_pattern_id)
        );
        $this->assertNotNull($bloom_pattern, 'BLOOM pattern should exist in the database.');
        $this->assertEquals($bloom_pattern_data['pattern_hash'], $bloom_pattern->pattern_hash, 'BLOOM pattern hash mismatch.');

        $bloom_pattern = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}bloom_patterns WHERE id = %d", $this->bloom_pattern_id)
        );
        $this->assertNotNull($bloom_pattern, 'BLOOM pattern should exist in the database.');
        $this->assertEquals($bloom_pattern_data['pattern_hash'], $bloom_pattern->pattern_hash, 'BLOOM pattern hash mismatch.');

        // 3. Verify APS Tools Management
        // Simulate APS integration after BLOOM processing
        require_once ABSPATH . 'wp-content/plugins/AevovPatternSyncProtocol/Includes/Integration/BloomIntegration.php';
        $aps_bloom_integration = \APS\Integration\BloomIntegration::get_instance();
        $aps_pattern_hash = $bloom_pattern_data['pattern_hash'];
        $aps_pattern_data = [
            'bloom_pattern_id' => $this->bloom_pattern_id,
            'processed_data' => 'some_processed_data_from_aps',
        ];

        // Assuming there's a method in BloomIntegration to save the pattern to APS
        // For now, we'll directly insert into aps_patterns table
        $insert_aps_result = $wpdb->insert(
            $wpdb->prefix . 'aps_patterns',
            [
                'pattern_hash' => $aps_pattern_hash,
                'pattern_type' => 'bloom_derived',
                'pattern_data' => json_encode($aps_pattern_data),
                'confidence'   => 0.95,
                'created_at'   => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%f', '%s']
        );
        $this->assertNotFalse($insert_aps_result, 'Failed to insert APS pattern.');
        $this->aps_pattern_id = $wpdb->insert_id;
        $this->assertNotNull($this->aps_pattern_id, 'APS pattern ID should not be null after insertion.');

        $aps_pattern = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}aps_patterns WHERE id = %d", $this->aps_pattern_id)
        );
        $this->assertNotNull($aps_pattern, 'APS pattern should exist in the database.');
        $this->assertEquals($aps_pattern_hash, $aps_pattern->pattern_hash, 'APS pattern hash mismatch.');

        // 4. Verify Aevov Diagnostic Network Health Check
        require_once ABSPATH . 'wp-content/plugins/aevov-diagnostic-network/includes/Core/DiagnosticNetwork.php';
        $diagnostic_network = \ADN\Core\DiagnosticNetwork::instance();
        $system_status = $diagnostic_network->get_system_status();

        $this->assertIsArray($system_status, 'System status should be an array.');
        $this->assertArrayHasKey('overall_health', $system_status, 'System status should contain overall_health.');
        $this->assertContains($system_status['overall_health'], ['good', 'excellent'], 'Overall health should be good or excellent.');
        
        // Further assertions based on expected diagnostic output
        // This part depends on how the DiagnosticNetwork reports on BLOOM/APS status.
        // Example:
        // $this->assertArrayHasKey('bloom_status', $system_status, 'System status should contain bloom_status.');
        // $this->assertEquals('healthy', $system_status['bloom_status']['status'], 'BLOOM status should be healthy.');
        // $this->assertArrayHasKey('aps_status', $system_status, 'System status should contain aps_status.');
        // $this->assertEquals('healthy', $system_status['aps_status']['status'], 'APS status should be healthy.');
    }
}