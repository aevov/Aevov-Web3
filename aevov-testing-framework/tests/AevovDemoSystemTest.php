<?php

use PHPUnit\Framework\TestCase;

/**
 * Class AevovDemoSystemTest
 *
 * Test case for the Aevov Demo System's pattern generation and display workflow.
 */
class AevovDemoSystemTest extends TestCase {

    protected $pattern_id;
    protected $aps_pattern_id;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        global $wpdb;

        // Explicitly create APS Pattern tables for this test
        require_once ABSPATH . 'wp-content/plugins/AevovPatternSyncProtocol/Includes/DB/APS_Pattern_DB.php';
        if (class_exists('\APS\DB\APS_Pattern_DB')) {
            (new \APS\DB\APS_Pattern_DB($wpdb))->create_tables();
        }
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void {
        global $wpdb;

        // Cleanup BLOOM pattern
        if ( $this->pattern_id ) {
            $wpdb->delete( $wpdb->prefix . 'bloom_patterns', [ 'id' => $this->pattern_id ] );
        }

        // Cleanup APS queue and pattern
        if ( $this->aps_pattern_id ) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_pattern_chunks");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_pattern_relationships");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_symbolic_patterns");
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}aps_patterns");
        }
        // Assuming APS queue entries are cleaned up automatically after processing,
        // or we might need to delete them if they persist.
        // For now, we'll assume 'completed' entries don't need explicit deletion.

        parent::tearDown();
    }

    /**
     * Test the full workflow of pattern generation and display in the Aevov Demo System.
     */
    public function test_demo_system_pattern_workflow() {
        global $wpdb;

        // 1. Simulate User Interaction to trigger pattern generation
        // This is a placeholder. You need to find the actual function/action.
        // Example: If there's a function like `Aevov_Demo_System::generate_pattern()`
        // or an action hook like `do_action('aevov_demo_system_generate_pattern', $data);`
        // For demonstration, let's assume a function exists that takes some input.
        // You will need to replace this with the actual call.
        // For now, we'll simulate a successful generation and directly insert for testing purposes.
        // In a real scenario, you'd call the system's function.

        // Placeholder for triggering pattern generation
        // Example: Aevov_Demo_System::get_instance()->generate_pattern_from_demo_input(['input_data' => 'test_data']);
        // Or: do_action('aevov_demo_system_generate_pattern_request', ['user_id' => 1, 'data' => 'sample']);

        // For the purpose of this test, we'll simulate the creation of a BLOOM pattern
        // as if the demo system successfully generated it.
        $pattern_hash = md5(uniqid(rand(), true)); // Generate a unique hash for the pattern.
        $features_data = json_encode(['type' => 'demo', 'value' => 'test_pattern_123']);
        $insert_bloom_result = $wpdb->insert(
            $wpdb->prefix . 'bloom_patterns',
            [
                'pattern_hash' => $pattern_hash,
                'features'     => $features_data,
                'created_at'   => current_time('mysql', true),
                'status'       => 'pending_aps', // Assuming it's pending APS processing
            ],
            ['%s', '%s', '%s', '%s']
        );

        $this->assertNotFalse($insert_bloom_result, 'Failed to insert BLOOM pattern for simulation.');
        $this->pattern_id = $wpdb->insert_id;
        $this->assertNotNull($this->pattern_id, 'BLOOM pattern ID should not be null after insertion.');

        // 2. Verify BLOOM Pattern Creation
        $bloom_pattern = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}bloom_patterns WHERE id = %d", $this->pattern_id)
        );
        $this->assertNotNull($bloom_pattern, 'BLOOM pattern should exist in the database.');
        $this->assertEquals($pattern_hash, $bloom_pattern->pattern_hash, 'BLOOM pattern hash mismatch.');
        $this->assertEquals($features_data, $bloom_pattern->features, 'BLOOM pattern features mismatch.');
        $this->assertEquals('pending_aps', $bloom_pattern->status, 'BLOOM pattern status should be pending_aps.');

        // 3. Verify APS Processing and Storage
        // Simulate APS processing by directly updating the BLOOM pattern status and inserting into APS patterns.
        // In a real test, you might trigger a cron job or a specific APS processing function.
        $wpdb->update(
            $wpdb->prefix . 'bloom_patterns',
            ['status' => 'processed_by_aps'],
            ['id' => $this->pattern_id],
            ['%s'],
            ['%d']
        );

        $test_aps_pattern_data = json_encode(['processed_value' => 'processed_test_pattern_123', 'bloom_id' => $this->pattern_id]);
        $insert_aps_result = $wpdb->insert(
            $wpdb->prefix . 'aps_patterns',
            [
                'pattern_hash'     => $pattern_hash, // Use the pattern_hash from BLOOM pattern
                'pattern_type'     => 'demo_processed', // Define a type for processed patterns
                'pattern_data'     => $test_aps_pattern_data,
                'confidence'       => 0.9, // Assign a default confidence
                'created_at'       => current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%f', '%s']
        );
        $this->assertNotFalse($insert_aps_result, 'Failed to insert APS pattern for simulation.');
        $this->aps_pattern_id = $wpdb->insert_id;
        $this->assertNotNull($this->aps_pattern_id, 'APS pattern ID should not be null after insertion.');

        // Verify APS queue (assuming it's enqueued and processed to 'completed')
        // This part is tricky without knowing the exact queue implementation.
        // For now, we'll assume a successful processing implies the queue was handled.
        // If there's a specific queue table, you'd query it here.
        // Example: $queue_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aps_process_queue WHERE bloom_pattern_id = %d", $this->pattern_id));
        // $this->assertNotNull($queue_entry, 'APS process queue entry should exist.');
        // $this->assertEquals('completed', $queue_entry->status, 'APS process queue status should be completed.');

        $aps_pattern = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}aps_patterns WHERE id = %d", $this->aps_pattern_id)
        );
        $this->assertNotNull($aps_pattern, 'APS pattern should exist in the database.');
        $this->assertEquals($pattern_hash, $aps_pattern->pattern_hash, 'APS pattern hash mismatch.');
        $this->assertEquals($test_aps_pattern_data, $aps_pattern->pattern_data, 'APS pattern data mismatch.');

        // 4. Simulate Display and Verify Results
        // Assuming the Aevov Demo System has a shortcode like `[aevov_demo_pattern id="X"]`
        // or a function that renders the pattern.
        // You need to replace 'aevov_demo_pattern' with the actual shortcode tag or function.
        $shortcode_output = do_shortcode('[aevov_demo_pattern id="' . $this->aps_pattern_id . '"]');

        // Assert that the generated pattern's data is present in the simulated display output.
        // This assertion depends on how the shortcode renders the pattern.
        // For example, if it renders the 'processed_value' from the APS pattern.
        $this->assertStringContainsString('processed_test_pattern_123', $shortcode_output, 'Shortcode output should contain the processed pattern data.');
        $this->assertStringContainsString('Pattern ID: ' . $this->aps_pattern_id, $shortcode_output, 'Shortcode output should contain the pattern ID.');
    }
}

// Placeholder for the shortcode function that the demo system would register.
// In a real scenario, this would be part of the Aevov Demo System plugin.
// For testing purposes, we define a minimal version here.
if ( ! function_exists('aevov_demo_pattern_shortcode') ) {
    function aevov_demo_pattern_shortcode( $atts ) {
        global $wpdb;
        $atts = shortcode_atts( array(
            'id' => 0,
        ), $atts, 'aevov_demo_pattern' );

        $pattern_id = intval( $atts['id'] );
        if ( $pattern_id <= 0 ) {
            return 'Invalid pattern ID.';
        }

        $aps_pattern = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}aps_patterns WHERE id = %d", $pattern_id)
        );

        if ( ! $aps_pattern ) {
            return 'Pattern not found.';
        }

        $pattern_data = json_decode($aps_pattern->pattern_data, true);
        $output = '<div>';
        $output .= '<h3>Demo Pattern Display</h3>';
        $output .= '<p>Pattern ID: ' . esc_html($aps_pattern->id) . '</p>';
        $output .= '<p>Processed Value: ' . esc_html($pattern_data['processed_value'] ?? 'N/A') . '</p>';
        $output .= '</div>';

        return $output;
    }
    add_shortcode('aevov_demo_pattern', 'aevov_demo_pattern_shortcode');
}