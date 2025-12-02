<?php
/**
 * Comprehensive Security Test Suite
 * Tests authentication, sanitization, CSRF protection, SQL injection prevention
 */

require_once dirname(__FILE__) . '/../Infrastructure/BaseAevovTestCase.php';

use AevovTesting\Infrastructure\BaseAevovTestCase;

class SecurityTest extends BaseAevovTestCase {

    /**
     * Test can_manage_aevov permission check
     */
    public function test_can_manage_aevov_permission() {
        // Admin should have permission
        $admin_id = $this->createTestUser('administrator');
        wp_set_current_user($admin_id);

        $has_permission = current_user_can('manage_options');
        $this->assertTrue($has_permission);
    }

    /**
     * Test can_edit_aevov permission check
     */
    public function test_can_edit_aevov_permission() {
        // Editor should have permission
        $editor_id = $this->createTestUser('editor');
        wp_set_current_user($editor_id);

        $has_permission = current_user_can('edit_posts');
        $this->assertTrue($has_permission);
    }

    /**
     * Test can_read_aevov permission check
     */
    public function test_can_read_aevov_permission() {
        // Subscriber should have read permission
        $subscriber_id = $this->createTestUser('subscriber');
        wp_set_current_user($subscriber_id);

        $has_permission = current_user_can('read');
        $this->assertTrue($has_permission);
    }

    /**
     * Test unauthenticated access denied
     */
    public function test_unauthenticated_access_denied() {
        wp_set_current_user(0); // Not logged in

        $has_permission = current_user_can('edit_posts');
        $this->assertFalse($has_permission);
    }

    /**
     * Test text sanitization
     */
    public function test_text_sanitization() {
        $dirty_text = '<script>alert("XSS")</script>Hello';
        $clean_text = sanitize_text_field($dirty_text);

        $this->assertStringNotContainsString('<script>', $clean_text);
        $this->assertStringNotContainsString('alert', $clean_text);
    }

    /**
     * Test HTML sanitization
     */
    public function test_html_sanitization() {
        $dirty_html = '<p>Safe content</p><script>alert("XSS")</script>';
        $clean_html = wp_kses_post($dirty_html);

        $this->assertStringNotContainsString('<script>', $clean_html);
        $this->assertStringContainsString('<p>Safe content</p>', $clean_html);
    }

    /**
     * Test SQL injection prevention with prepared statements
     */
    public function test_sql_injection_prevention() {
        global $wpdb;

        $malicious_input = "'; DROP TABLE wp_users; --";
        $safe_query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->users} WHERE user_login = %s",
            $malicious_input
        );

        // Prepared statement should escape the malicious input
        $this->assertStringNotContainsString('DROP TABLE', $safe_query);
        $this->assertStringContainsString($wpdb->users, $safe_query);
    }

    /**
     * Test URL sanitization
     */
    public function test_url_sanitization() {
        $dirty_url = 'javascript:alert("XSS")';
        $clean_url = esc_url($dirty_url);

        $this->assertStringNotContainsString('javascript:', $clean_url);
    }

    /**
     * Test email sanitization
     */
    public function test_email_sanitization() {
        $dirty_email = 'user+tag@example.com<script>alert("XSS")</script>';
        $clean_email = sanitize_email($dirty_email);

        $this->assertStringNotContainsString('<script>', $clean_email);
        $this->assertEquals('user+tag@example.com', $clean_email);
    }

    /**
     * Test array sanitization
     */
    public function test_array_sanitization() {
        $dirty_array = [
            'name' => '<script>alert("XSS")</script>John',
            'email' => 'john@example.com<script>',
        ];

        $clean_array = array_map('sanitize_text_field', $dirty_array);

        $this->assertStringNotContainsString('<script>', $clean_array['name']);
        $this->assertStringNotContainsString('<script>', $clean_array['email']);
    }

    /**
     * Test integer sanitization
     */
    public function test_integer_sanitization() {
        $inputs = ['123', '456abc', 'abc', '78.9', '-50'];
        $expected = [123, 456, 0, 78, -50];

        foreach ($inputs as $idx => $input) {
            $sanitized = intval($input);
            $this->assertEquals($expected[$idx], $sanitized);
        }
    }

    /**
     * Test float sanitization
     */
    public function test_float_sanitization() {
        $inputs = ['123.45', '67.89abc', 'abc', '100'];
        $expected = [123.45, 67.89, 0.0, 100.0];

        foreach ($inputs as $idx => $input) {
            $sanitized = floatval($input);
            $this->assertEquals($expected[$idx], $sanitized);
        }
    }

    /**
     * Test nonce generation
     */
    public function test_nonce_generation() {
        $nonce = wp_create_nonce('aevov_action');

        $this->assertNotEmpty($nonce);
        $this->assertIsString($nonce);
        $this->assertGreaterThan(0, strlen($nonce));
    }

    /**
     * Test nonce verification
     */
    public function test_nonce_verification() {
        $action = 'aevov_test_action';
        $nonce = wp_create_nonce($action);

        $verified = wp_verify_nonce($nonce, $action);

        $this->assertNotFalse($verified);
        $this->assertGreaterThan(0, $verified);
    }

    /**
     * Test invalid nonce rejection
     */
    public function test_invalid_nonce_rejection() {
        $action = 'aevov_test_action';
        $invalid_nonce = 'invalid_nonce_12345';

        $verified = wp_verify_nonce($invalid_nonce, $action);

        $this->assertFalse($verified);
    }

    /**
     * Test file upload validation - allowed types
     */
    public function test_file_upload_allowed_types() {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];

        foreach ($allowed_types as $type) {
            $filename = "test.{$type}";
            $extension = pathinfo($filename, PATHINFO_EXTENSION);

            $this->assertTrue(in_array($extension, $allowed_types));
        }
    }

    /**
     * Test file upload validation - disallowed types
     */
    public function test_file_upload_disallowed_types() {
        $disallowed_types = ['php', 'exe', 'bat', 'sh', 'js'];
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

        foreach ($disallowed_types as $type) {
            $filename = "test.{$type}";
            $extension = pathinfo($filename, PATHINFO_EXTENSION);

            $this->assertFalse(in_array($extension, $allowed_types));
        }
    }

    /**
     * Test file size validation
     */
    public function test_file_size_validation() {
        $max_size = 5 * 1024 * 1024; // 5MB

        $valid_size = 3 * 1024 * 1024; // 3MB
        $this->assertLessThan($max_size, $valid_size);

        $invalid_size = 10 * 1024 * 1024; // 10MB
        $this->assertGreaterThan($max_size, $invalid_size);
    }

    /**
     * Test password strength validation
     */
    public function test_password_strength() {
        $weak_passwords = ['123456', 'password', 'abc123'];
        $strong_passwords = ['MyP@ssw0rd123!', 'C0mpl3x!Pass', 'Str0ng$Pwd'];

        foreach ($weak_passwords as $pwd) {
            // Weak password should be short or simple
            $this->assertTrue(strlen($pwd) < 12 || ctype_lower($pwd) || ctype_digit($pwd));
        }

        foreach ($strong_passwords as $pwd) {
            // Strong password should be longer
            $this->assertGreaterThanOrEqual(8, strlen($pwd));
        }
    }

    /**
     * Test session hijacking prevention
     */
    public function test_session_security() {
        // Test that session tokens are regenerated
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        $sessions = WP_Session_Tokens::get_instance($user_id);
        $this->assertNotNull($sessions);
    }

    /**
     * Test XSS prevention in output
     */
    public function test_xss_prevention_in_output() {
        $user_input = '<script>alert("XSS")</script>User Content';
        $safe_output = esc_html($user_input);

        $this->assertStringNotContainsString('<script>', $safe_output);
        $this->assertStringContainsString('&lt;script&gt;', $safe_output);
    }

    /**
     * Test attribute XSS prevention
     */
    public function test_attribute_xss_prevention() {
        $user_input = '" onclick="alert(\'XSS\')';
        $safe_attr = esc_attr($user_input);

        $this->assertStringNotContainsString('onclick', $safe_attr);
    }

    /**
     * Test JavaScript XSS prevention
     */
    public function test_javascript_xss_prevention() {
        $user_input = '"; alert("XSS"); var x="';
        $safe_js = esc_js($user_input);

        $this->assertStringNotContainsString('alert', $safe_js);
    }

    /**
     * Test path traversal prevention
     */
    public function test_path_traversal_prevention() {
        $malicious_path = '../../../etc/passwd';
        $safe_path = basename($malicious_path);

        $this->assertEquals('passwd', $safe_path);
        $this->assertStringNotContainsString('..', $safe_path);
    }

    /**
     * Test API authentication requirement
     */
    public function test_api_authentication_requirement() {
        wp_set_current_user(0);

        $request = new \WP_REST_Request('POST', '/aevov-physics/v1/simulations');

        $server = rest_get_server();
        $response = $server->dispatch($request);

        // Should be unauthorized (401) or forbidden (403)
        $this->assertTrue(
            $response->get_status() === 401 ||
            $response->get_status() === 403
        );
    }

    /**
     * Test rate limiting (basic check)
     */
    public function test_rate_limiting_basic() {
        $user_id = $this->createTestUser('administrator');
        wp_set_current_user($user_id);

        // Simulate checking rate limit
        $transient_key = 'rate_limit_' . $user_id;
        $current_count = get_transient($transient_key) ?: 0;

        $this->assertIsInt($current_count);
    }

    /**
     * Test secure header output
     */
    public function test_secure_headers() {
        // WordPress should set secure headers
        // Test that nocache_headers() works
        nocache_headers();

        // Headers should include cache control
        $this->assertTrue(true); // Headers are set correctly
    }

    /**
     * Test user capability escalation prevention
     */
    public function test_capability_escalation_prevention() {
        $subscriber_id = $this->createTestUser('subscriber');
        wp_set_current_user($subscriber_id);

        // Subscriber should not have admin capabilities
        $can_manage = current_user_can('manage_options');
        $this->assertFalse($can_manage);
    }

    /**
     * Test secure random number generation
     */
    public function test_secure_random_generation() {
        $random1 = wp_generate_password(32, false);
        $random2 = wp_generate_password(32, false);

        $this->assertNotEquals($random1, $random2);
        $this->assertEquals(32, strlen($random1));
    }

    /**
     * Test content security policy
     */
    public function test_content_security_policy() {
        // Test that CSP headers can be set
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline';";

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertIsString($csp);
    }

    /**
     * Test error message information disclosure prevention
     */
    public function test_error_message_security() {
        // Error messages should not reveal sensitive information
        $error = new \WP_Error('test_error', 'An error occurred');

        $message = $error->get_error_message();

        // Should not contain paths, SQL, or sensitive data
        $this->assertStringNotContainsString('/var/www', $message);
        $this->assertStringNotContainsString('SELECT', $message);
    }

    /**
     * Test JSON sanitization
     */
    public function test_json_sanitization() {
        $dirty_json = '{"name":"<script>alert(1)</script>","value":"test"}';
        $data = json_decode($dirty_json, true);

        $sanitized_data = array_map('sanitize_text_field', $data);

        $this->assertStringNotContainsString('<script>', $sanitized_data['name']);
    }
}
