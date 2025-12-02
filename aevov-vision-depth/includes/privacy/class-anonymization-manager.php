<?php
/**
 * Anonymization Manager
 *
 * Handles data anonymization based on privacy modes, including PII removal,
 * URL sanitization, and content filtering.
 *
 * @package AevovVisionDepth\Privacy
 * @since 1.0.0
 */

namespace AevovVisionDepth\Privacy;

class Anonymization_Manager {

    /**
     * PII patterns for detection and removal
     *
     * @var array
     */
    private $pii_patterns = [];

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_pii_patterns();
    }

    /**
     * Initialize anonymization manager
     *
     * @return void
     */
    public function init() {
        // Add filters for data processing
        add_filter('avd_before_encryption', [$this, 'anonymize_before_encryption'], 10, 2);
    }

    /**
     * Anonymize data based on privacy mode
     *
     * @param array $data Data to anonymize
     * @param string $privacy_mode Privacy mode (maximum|balanced|minimal)
     * @return array Anonymized data
     */
    public function anonymize_data($data, $privacy_mode) {
        switch ($privacy_mode) {
            case 'maximum':
                return $this->maximum_privacy($data);

            case 'balanced':
                return $this->balanced_privacy($data);

            case 'minimal':
                return $this->minimal_privacy($data);

            default:
                return $this->balanced_privacy($data);
        }
    }

    /**
     * Maximum privacy anonymization
     *
     * Removes ALL identifying information, keeps only basic structure
     *
     * @param array $data Data to anonymize
     * @return array Anonymized data
     */
    private function maximum_privacy($data) {
        return [
            // Remove URL completely, store only hash
            'url' => null,
            'url_hash' => isset($data['url']) ? hash('sha256', $data['url']) : null,
            'domain_hash' => isset($data['url']) ? hash('sha256', parse_url($data['url'], PHP_URL_HOST)) : null,

            // Keep only page title
            'title' => isset($data['title']) ? $this->remove_pii($data['title']) : null,

            // Remove all content
            'content' => null,

            // Remove all meta tags
            'meta_tags' => null,

            // Remove all links
            'links' => null,

            // Remove all forms
            'forms' => null,

            // Keep basic metadata
            'timestamp' => $data['timestamp'] ?? time(),
            'privacy_mode' => 'maximum',

            // Keep status for analytics
            'status_code' => $data['status_code'] ?? null,

            // Anonymized user agent
            'user_agent_hash' => isset($data['user_agent']) ? hash('sha256', $data['user_agent']) : null,
        ];
    }

    /**
     * Balanced privacy anonymization
     *
     * Sanitizes URLs, removes PII, keeps useful data
     *
     * @param array $data Data to anonymize
     * @return array Anonymized data
     */
    private function balanced_privacy($data) {
        return [
            // Sanitize URL - keep domain and path, remove query params
            'url' => isset($data['url']) ? $this->sanitize_url($data['url']) : null,
            'url_hash' => isset($data['url']) ? hash('sha256', $data['url']) : null,

            // Keep title with PII removed
            'title' => isset($data['title']) ? $this->remove_pii($data['title']) : null,

            // Keep main content with PII removed
            'content' => isset($data['content']) ? $this->remove_pii($data['content']) : null,

            // Keep meta tags with PII removed
            'meta_tags' => isset($data['meta_tags']) ? $this->anonymize_meta_tags($data['meta_tags']) : null,

            // Keep link structure (domains only, no full URLs)
            'links' => isset($data['links']) ? $this->anonymize_links($data['links']) : null,

            // Keep form field names only (no values)
            'forms' => isset($data['forms']) ? $this->anonymize_forms($data['forms'], 'field_names_only') : null,

            // Keep headers with sensitive data removed
            'headers' => isset($data['headers']) ? $this->anonymize_headers($data['headers']) : null,

            // Keep metadata
            'timestamp' => $data['timestamp'] ?? time(),
            'status_code' => $data['status_code'] ?? null,
            'privacy_mode' => 'balanced',
        ];
    }

    /**
     * Minimal privacy anonymization
     *
     * Removes only PII, keeps most data
     *
     * @param array $data Data to anonymize
     * @return array Anonymized data
     */
    private function minimal_privacy($data) {
        return [
            // Keep full URL
            'url' => $data['url'] ?? null,

            // Keep title with PII removed
            'title' => isset($data['title']) ? $this->remove_pii($data['title']) : null,

            // Keep full content with PII removed
            'content' => isset($data['content']) ? $this->remove_pii($data['content']) : null,

            // Keep all meta tags with PII removed
            'meta_tags' => isset($data['meta_tags']) ? array_map([$this, 'remove_pii'], $data['meta_tags']) : null,

            // Keep all links with PII removed
            'links' => isset($data['links']) ? $this->remove_pii_from_links($data['links']) : null,

            // Keep form structure and hashed values
            'forms' => isset($data['forms']) ? $this->anonymize_forms($data['forms'], 'hashed_values') : null,

            // Keep all headers
            'headers' => $data['headers'] ?? null,

            // Keep all metadata
            'timestamp' => $data['timestamp'] ?? time(),
            'status_code' => $data['status_code'] ?? null,
            'privacy_mode' => 'minimal',

            // Keep body (with PII removed)
            'body' => isset($data['body']) ? $this->remove_pii($data['body']) : null,
        ];
    }

    /**
     * Remove personally identifiable information from text
     *
     * @param string $text Text to process
     * @return string Processed text
     */
    public function remove_pii($text) {
        if (!is_string($text)) {
            return $text;
        }

        foreach ($this->pii_patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /**
     * Sanitize URL - remove tracking parameters and sensitive data
     *
     * @param string $url URL to sanitize
     * @return string Sanitized URL
     */
    private function sanitize_url($url) {
        $parsed = parse_url($url);

        if (!$parsed) {
            return hash('sha256', $url); // If URL is malformed, return hash
        }

        // Tracking parameters to remove
        $tracking_params = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'fbclid', 'gclid', 'msclkid', '_ga', 'mc_cid', 'mc_eid',
            'ref', 'referrer', 'source', 'campaign', 'sid', 'ssid',
        ];

        // Build clean URL
        $clean_url = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        // Add path if exists
        if (isset($parsed['path'])) {
            $clean_url .= $parsed['path'];
        }

        // Parse and filter query parameters
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);

            // Remove tracking parameters
            foreach ($tracking_params as $param) {
                unset($params[$param]);
            }

            // Rebuild query string if params remain
            if (!empty($params)) {
                $clean_url .= '?' . http_build_query($params);
            }
        }

        return $clean_url;
    }

    /**
     * Anonymize meta tags
     *
     * @param array $meta_tags Meta tags to anonymize
     * @return array Anonymized meta tags
     */
    private function anonymize_meta_tags($meta_tags) {
        if (!is_array($meta_tags)) {
            return $meta_tags;
        }

        $anonymized = [];

        foreach ($meta_tags as $key => $value) {
            // Skip sensitive meta tags
            if (in_array($key, ['author', 'creator', 'publisher'])) {
                continue;
            }

            // Remove PII from value
            $anonymized[$key] = is_string($value) ? $this->remove_pii($value) : $value;
        }

        return $anonymized;
    }

    /**
     * Anonymize links
     *
     * @param array $links Links to anonymize
     * @return array Anonymized links
     */
    private function anonymize_links($links) {
        if (!is_array($links)) {
            return [];
        }

        $anonymized = [];

        foreach ($links as $link) {
            $parsed = parse_url($link);

            // Keep only domain
            if (isset($parsed['host'])) {
                $domain = $parsed['host'];

                // Count occurrences
                if (isset($anonymized[$domain])) {
                    $anonymized[$domain]++;
                } else {
                    $anonymized[$domain] = 1;
                }
            }
        }

        return $anonymized;
    }

    /**
     * Remove PII from links
     *
     * @param array $links Links to process
     * @return array Processed links
     */
    private function remove_pii_from_links($links) {
        if (!is_array($links)) {
            return [];
        }

        return array_map(function($link) {
            return $this->sanitize_url($link);
        }, $links);
    }

    /**
     * Anonymize forms
     *
     * @param array $forms Forms to anonymize
     * @param string $mode Anonymization mode (field_names_only|hashed_values)
     * @return array Anonymized forms
     */
    private function anonymize_forms($forms, $mode = 'field_names_only') {
        if (!is_array($forms)) {
            return [];
        }

        $anonymized = [];

        foreach ($forms as $form) {
            $anonymized_form = [
                'action' => isset($form['action']) ? $this->sanitize_url($form['action']) : null,
                'method' => $form['method'] ?? 'GET',
                'fields' => [],
            ];

            if (isset($form['fields']) && is_array($form['fields'])) {
                foreach ($form['fields'] as $field) {
                    $field_data = [
                        'name' => $field['name'] ?? null,
                        'type' => $field['type'] ?? 'text',
                    ];

                    if ($mode === 'hashed_values' && isset($field['value'])) {
                        // Hash the value instead of storing it
                        $field_data['value_hash'] = hash('sha256', $field['value']);
                    }

                    $anonymized_form['fields'][] = $field_data;
                }
            }

            $anonymized[] = $anonymized_form;
        }

        return $anonymized;
    }

    /**
     * Anonymize headers
     *
     * @param array $headers Headers to anonymize
     * @return array Anonymized headers
     */
    private function anonymize_headers($headers) {
        if (!is_array($headers)) {
            return [];
        }

        // Headers to remove completely
        $sensitive_headers = [
            'cookie',
            'set-cookie',
            'authorization',
            'proxy-authorization',
            'x-forwarded-for',
            'x-real-ip',
        ];

        $anonymized = [];

        foreach ($headers as $key => $value) {
            $key_lower = strtolower($key);

            // Skip sensitive headers
            if (in_array($key_lower, $sensitive_headers)) {
                continue;
            }

            // Keep safe headers
            $anonymized[$key] = $value;
        }

        return $anonymized;
    }

    /**
     * Initialize PII detection patterns
     *
     * @return void
     */
    private function init_pii_patterns() {
        $this->pii_patterns = [
            // Email addresses
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' => '[EMAIL_REDACTED]',

            // Phone numbers (various formats)
            '/\b(\+?1[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}\b/' => '[PHONE_REDACTED]',
            '/\b\d{3}-\d{3}-\d{4}\b/' => '[PHONE_REDACTED]',
            '/\b\(\d{3}\)\s*\d{3}-\d{4}\b/' => '[PHONE_REDACTED]',

            // Social Security Numbers
            '/\b\d{3}-\d{2}-\d{4}\b/' => '[SSN_REDACTED]',

            // Credit Card Numbers (basic pattern)
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/' => '[CC_REDACTED]',

            // IP Addresses (IPv4)
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/' => '[IP_REDACTED]',

            // IP Addresses (IPv6) - simplified pattern
            '/\b(?:[A-Fa-f0-9]{1,4}:){7}[A-Fa-f0-9]{1,4}\b/' => '[IP_REDACTED]',

            // Passport numbers (basic pattern - varies by country)
            '/\b[A-Z]{1,2}\d{6,9}\b/' => '[PASSPORT_REDACTED]',

            // Driver's License (varies by state - basic pattern)
            '/\b[A-Z]\d{7,8}\b/' => '[DL_REDACTED]',

            // Dates of birth (MM/DD/YYYY or DD/MM/YYYY)
            '/\b(0?[1-9]|1[0-2])[\/\-](0?[1-9]|[12][0-9]|3[01])[\/\-](19|20)\d{2}\b/' => '[DOB_REDACTED]',

            // Street addresses (basic pattern)
            '/\b\d{1,5}\s+([A-Za-z]+\s+){1,3}(Street|St|Avenue|Ave|Road|Rd|Boulevard|Blvd|Lane|Ln|Drive|Dr|Court|Ct)\b/i' => '[ADDRESS_REDACTED]',

            // ZIP codes (US)
            '/\b\d{5}(?:-\d{4})?\b/' => '[ZIP_REDACTED]',

            // Bank account numbers (basic pattern)
            '/\b\d{8,17}\b/' => '[ACCOUNT_REDACTED]',
        ];
    }

    /**
     * Anonymize before encryption filter
     *
     * @param array $data Data to anonymize
     * @param int $user_id User ID
     * @return array Anonymized data
     */
    public function anonymize_before_encryption($data, $user_id) {
        // Get user's privacy mode
        $consent_manager = new Consent_Manager();
        $privacy_mode = $consent_manager->get_privacy_mode($user_id);

        return $this->anonymize_data($data, $privacy_mode);
    }

    /**
     * Test anonymization
     *
     * @return array Test results
     */
    public function test_anonymization() {
        $test_data = [
            'email' => 'test@example.com should be redacted',
            'phone' => 'Call me at 555-123-4567 or (555) 987-6543',
            'ssn' => 'SSN: 123-45-6789',
            'credit_card' => 'Card: 1234-5678-9012-3456',
            'ip' => 'IP: 192.168.1.1',
            'address' => 'Lives at 123 Main Street',
        ];

        $results = [];

        foreach ($test_data as $key => $text) {
            $anonymized = $this->remove_pii($text);
            $results[$key] = [
                'original' => $text,
                'anonymized' => $anonymized,
                'redacted' => $text !== $anonymized,
            ];
        }

        return $results;
    }

    /**
     * Get anonymization statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        return [
            'pii_patterns_count' => count($this->pii_patterns),
            'supported_pii_types' => [
                'email_addresses',
                'phone_numbers',
                'social_security_numbers',
                'credit_card_numbers',
                'ip_addresses_v4',
                'ip_addresses_v6',
                'passport_numbers',
                'drivers_licenses',
                'dates_of_birth',
                'street_addresses',
                'zip_codes',
                'bank_accounts',
            ],
            'privacy_modes' => ['maximum', 'balanced', 'minimal'],
        ];
    }
}
