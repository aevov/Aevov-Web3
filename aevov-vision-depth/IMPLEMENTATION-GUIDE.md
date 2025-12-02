# Vision Depth Implementation Guide

## ✅ Completed Components

### Core Plugin Structure
- [x] Main plugin file (`aevov-vision-depth.php`)
- [x] Database schema (`includes/class-database.php`)
- [x] Activator (`includes/class-activator.php`)
- [x] Privacy Manager (`includes/privacy/class-privacy-manager.php`)

## 📋 Remaining Components to Implement

### 1. Privacy System (Priority: HIGH)

#### `includes/privacy/class-consent-manager.php`
```php
class Consent_Manager {
    public function init() { /* Hook into WordPress */ }
    public function has_consent($user_id) { /* Check user consent */ }
    public function get_privacy_mode($user_id) { /* Get: maximum/balanced/minimal */ }
    public function update_consent($user_id, $consent, $privacy_mode) { /* Update DB */ }
    public function get_consent_info($user_id) { /* Return consent details */ }
}
```

#### `includes/privacy/class-encryption-manager.php`
```php
class Encryption_Manager {
    public function init() { /* Initialize encryption keys */ }
    public function encrypt_data($data, $user_id) {
        // Use user-specific entropy for encryption
        // Implement AES-256-GCM encryption
        // Return base64 encoded encrypted data
    }
    public function decrypt_data($encrypted_data, $user_id) {
        // Decrypt using user-specific key
        // Return decrypted array or null
    }
    private function get_user_encryption_key($user_id) {
        // Generate key from user metadata + WordPress salt
        // Cache in memory for performance
    }
}
```

#### `includes/privacy/class-anonymization-manager.php`
```php
class Anonymization_Manager {
    public function init() { /* Setup anonymization rules */ }
    public function anonymize_data($data, $privacy_mode) {
        switch ($privacy_mode) {
            case 'maximum':
                return $this->maximum_privacy($data);
            case 'balanced':
                return $this->balanced_privacy($data);
            case 'minimal':
                return $this->minimal_privacy($data);
        }
    }

    private function maximum_privacy($data) {
        // Remove ALL URLs
        // Strip ALL query parameters
        // Remove ALL form data
        // Keep only: page title, basic structure
    }

    private function balanced_privacy($data) {
        // Sanitize URLs (remove tracking params)
        // Hash sensitive form fields
        // Keep: domain, path structure, content type
    }

    private function minimal_privacy($data) {
        // Remove PII (emails, phones, SSNs)
        // Keep most data for analysis
    }

    private function remove_pii($text) {
        // Regex patterns for email, phone, SSN, credit cards
        // Replace with [REDACTED]
    }
}
```

### 2. Scraper System (Priority: HIGH)

#### `includes/scraper/class-scraper-manager.php`
```php
class Scraper_Manager {
    private $web_browser; // Ultimate Web Scraper instance
    private $rate_limiter;
    private $data_extractor;

    public function init() {
        // Initialize Ultimate Web Scraper
        if (class_exists('\\WebBrowser')) {
            $this->web_browser = new \\WebBrowser();
        }
        $this->rate_limiter = new Rate_Limiter();
        $this->data_extractor = new Data_Extractor();
    }

    public function scrape_url($url, $user_id, $options = []) {
        // 1. Check rate limits
        if (!$this->rate_limiter->check_limit($user_id, 'scrape')) {
            return new \\WP_Error('rate_limit', 'Rate limit exceeded');
        }

        // 2. Check consent
        $privacy = vision_depth()->privacy;
        if (!$privacy->user_has_consented($user_id)) {
            return new \\WP_Error('no_consent', 'User consent required');
        }

        // 3. Perform scrape
        $result = $this->web_browser->Process($url);

        // 4. Extract data
        $extracted = $this->data_extractor->extract($result);

        // 5. Process through privacy system
        $processed = $privacy->process_scraped_data($extracted, $user_id);

        // 6. Store in database
        $this->store_scraped_data($processed, $user_id);

        // 7. Queue for pattern analysis
        $this->queue_for_analysis($processed, $user_id);

        // 8. Calculate and award rewards
        $this->award_scrape_reward($user_id);

        return $processed;
    }

    public function process_job($job_id) { /* Process queued scrape job */ }
    public function render_settings_page() { /* Admin UI */ }
    public function rest_scrape_url($request) { /* REST API endpoint */ }
}
```

#### `includes/scraper/class-rate-limiter.php`
```php
class Rate_Limiter {
    public function check_limit($user_id, $action_type) {
        // Check per-second limit (10/sec)
        // Check per-minute limit (100/min)
        // Check per-hour limit (1000/hour)
        // Use sliding window algorithm
        // Store in avd_rate_limits table
    }

    public function record_action($user_id, $action_type) {
        // Record action in database
        // Clean up old records
    }
}
```

#### `includes/scraper/class-data-extractor.php`
```php
class Data_Extractor {
    public function extract($scrape_result) {
        return [
            'url' => $scrape_result['url'],
            'status_code' => $scrape_result['response']['code'],
            'headers' => $scrape_result['response']['headers'],
            'body' => $scrape_result['body'],
            'title' => $this->extract_title($scrape_result['body']),
            'meta_tags' => $this->extract_meta_tags($scrape_result['body']),
            'links' => $this->extract_links($scrape_result['body']),
            'forms' => $this->extract_forms($scrape_result['body']),
            'content' => $this->extract_content($scrape_result['body']),
            'timestamp' => time(),
        ];
    }

    private function extract_title($html) { /* Use TagFilter */ }
    private function extract_meta_tags($html) { /* Parse meta tags */ }
    private function extract_links($html) { /* Extract all <a> tags */ }
    private function extract_forms($html) { /* Ultimate Web Scraper form extraction */ }
    private function extract_content($html) { /* Main content extraction */ }
}
```

### 3. Dashboard & Monitoring (Priority: MEDIUM)

#### `includes/dashboard/class-dashboard-manager.php`
```php
class Dashboard_Manager {
    public function init() {
        add_action('wp_footer', [$this, 'render_monitoring_button']);
    }

    public function render_main_page() {
        // Show statistics:
        // - Total scrapes performed
        // - Patterns discovered
        // - AevCoin earned
        // - Privacy mode distribution
        // - Recent activity

        include AVD_PATH . 'templates/dashboard-main.php';
    }

    public function render_monitoring_button() {
        if (!is_user_logged_in()) return;
        if (!vision_depth()->privacy->user_has_consented()) return;

        // Add "Vision Depth Field" floating button
        include AVD_PATH . 'templates/monitoring-button.php';
    }
}
```

#### `includes/dashboard/class-monitoring-widget.php`
```php
class Monitoring_Widget {
    public function render() {
        // Real-time monitoring dashboard
        // Shows:
        // - Current scraping activity
        // - Data being collected
        // - Privacy protections active
        // - Rate limit status
        // - Rewards earned

        include AVD_PATH . 'templates/monitoring-widget.php';
    }

    public function get_live_stats($user_id) {
        // Return JSON with current stats
    }
}
```

### 4. Integration System (Priority: HIGH)

#### `includes/integrations/class-integration-manager.php`
```php
class Integration_Manager {
    private $aps_integration;
    private $bloom_integration;
    private $aps_tools_integration;

    public function init() {
        if (class_exists('\\APS\\Core\\APS_Core')) {
            $this->aps_integration = new APS_Integration();
            $this->aps_integration->init();
        }

        if (class_exists('\\BLOOM_Pattern_System')) {
            $this->bloom_integration = new Bloom_Integration();
            $this->bloom_integration->init();
        }

        if (class_exists('\\APSTools\\APSTools')) {
            $this->aps_tools_integration = new APS_Tools_Integration();
            $this->aps_tools_integration->init();
        }
    }

    public function render_settings_page() { /* Integration status page */ }
}
```

#### `includes/integrations/class-aps-integration.php`
```php
class APS_Integration {
    public function init() {
        add_action('avd_pattern_discovered', [$this, 'sync_to_aps'], 10, 2);
    }

    public function sync_to_aps($pattern_data, $user_id) {
        // Convert Vision Depth pattern to APS pattern format
        // Use APS\DB\APS_Pattern_DB to store
        // Trigger consensus validation if needed

        $aps_pattern_db = new \\APS\\DB\\APS_Pattern_DB();
        $pattern_id = $aps_pattern_db->insert_pattern([
            'pattern_hash' => $pattern_data['pattern_hash'],
            'pattern_data' => json_encode($pattern_data),
            'source' => 'vision_depth',
            'contributor_id' => $user_id,
        ]);

        // Update Vision Depth pattern with APS ID
        $this->link_pattern($pattern_data['id'], $pattern_id);

        return $pattern_id;
    }

    private function link_pattern($avd_pattern_id, $aps_pattern_id) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'avd_behavioral_patterns',
            ['aps_pattern_id' => $aps_pattern_id],
            ['id' => $avd_pattern_id],
            ['%d'],
            ['%d']
        );
    }
}
```

#### `includes/integrations/class-bloom-integration.php`
```php
class Bloom_Integration {
    public function init() {
        add_action('avd_data_scraped', [$this, 'analyze_with_bloom'], 10, 2);
    }

    public function analyze_with_bloom($scraped_data, $user_id) {
        // Convert scraped data to BLOOM tensor format
        // Use BLOOM pattern recognition to analyze
        // Detect behavioral patterns

        if (!class_exists('\\BLOOM\\Models\\Pattern_Model')) {
            return;
        }

        $pattern_model = new \\BLOOM\\Models\\Pattern_Model();

        // Create tensor from scraped data
        $tensor = $this->create_tensor_from_data($scraped_data);

        // Run pattern recognition
        $patterns = $pattern_model->recognize($tensor);

        // Store discovered patterns
        foreach ($patterns as $pattern) {
            $this->store_behavioral_pattern($pattern, $user_id);
        }

        return $patterns;
    }

    private function create_tensor_from_data($data) {
        // Convert scraped data to numerical tensor
        // Use embeddings for text content
        // Return tensor array
    }

    private function store_behavioral_pattern($pattern, $user_id) {
        global $wpdb;

        $pattern_hash = hash('sha256', json_encode($pattern));

        // Check if pattern exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}avd_behavioral_patterns
             WHERE user_id = %d AND pattern_hash = %s",
            $user_id,
            $pattern_hash
        ));

        if ($existing) {
            // Increment occurrences
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}avd_behavioral_patterns
                 SET occurrences = occurrences + 1,
                     last_seen = NOW()
                 WHERE id = %d",
                $existing
            ));
        } else {
            // Insert new pattern
            $wpdb->insert(
                $wpdb->prefix . 'avd_behavioral_patterns',
                [
                    'user_id' => $user_id,
                    'pattern_hash' => $pattern_hash,
                    'pattern_type' => $pattern['type'],
                    'pattern_data' => json_encode($pattern),
                    'confidence_score' => $pattern['confidence'],
                ],
                ['%d', '%s', '%s', '%s', '%f']
            );

            $pattern_id = $wpdb->insert_id;

            // Trigger reward
            $this->award_pattern_reward($user_id, $pattern_id);

            // Trigger action for APS integration
            do_action('avd_pattern_discovered', $pattern, $user_id);
        }
    }

    private function award_pattern_reward($user_id, $pattern_id) {
        global $wpdb;

        $reward_amount = get_option('avd_reward_per_pattern', 0.01);

        $wpdb->insert(
            $wpdb->prefix . 'avd_user_rewards',
            [
                'user_id' => $user_id,
                'reward_type' => 'pattern_discovery',
                'amount' => $reward_amount,
                'pattern_id' => $pattern_id,
                'status' => 'pending', // Will be distributed by AevCoin system
            ],
            ['%d', '%s', '%f', '%d', '%s']
        );
    }
}
```

#### `includes/integrations/class-aps-tools-integration.php`
```php
class APS_Tools_Integration {
    public function init() {
        // Add Vision Depth data to APS Tools dashboard
        add_filter('aps_tools_dashboard_widgets', [$this, 'add_dashboard_widget']);

        // Add Vision Depth to system status
        add_filter('aps_tools_system_status', [$this, 'add_status_info']);
    }

    public function add_dashboard_widget($widgets) {
        $widgets['vision_depth'] = [
            'title' => __('Vision Depth Activity', 'aevov-vision-depth'),
            'callback' => [$this, 'render_widget'],
        ];
        return $widgets;
    }

    public function render_widget() {
        global $wpdb;

        // Show Vision Depth statistics
        $total_scrapes = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}avd_scraped_data"
        );

        $total_patterns = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}avd_behavioral_patterns"
        );

        $total_users = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}avd_user_consent
             WHERE consent_given = 1"
        );

        echo '<ul>';
        echo '<li><strong>' . __('Total Scrapes:', 'aevov-vision-depth') . '</strong> ' . number_format($total_scrapes) . '</li>';
        echo '<li><strong>' . __('Patterns Discovered:', 'aevov-vision-depth') . '</strong> ' . number_format($total_patterns) . '</li>';
        echo '<li><strong>' . __('Consented Users:', 'aevov-vision-depth') . '</strong> ' . number_format($total_users) . '</li>';
        echo '</ul>';
    }

    public function add_status_info($status) {
        $status['vision_depth'] = [
            'status' => 'operational',
            'version' => AVD_VERSION,
            'integrations' => [
                'aps' => class_exists('\\APS\\Core\\APS_Core'),
                'bloom' => class_exists('\\BLOOM_Pattern_System'),
            ],
        ];
        return $status;
    }
}
```

## 📦 Ultimate Web Scraper Integration

### Installation Steps:

1. Download Ultimate Web Scraper Toolkit:
```bash
cd /home/user/Aevov1/aevov-vision-depth/lib
git clone https://github.com/cubiclesoft/ultimate-web-scraper.git ultimate_web_scraper_toolkit
```

2. Required files from Ultimate Web Scraper:
- `web_browser.php` - Main scraping class
- `simple_html_dom.php` - HTML parsing
- `tag_filter.php` - CSS selector parsing
- `http.php` - HTTP utilities

## 🎨 Frontend Assets

### `assets/css/admin.css`
```css
/* Admin dashboard styles */
.avd-dashboard { /* Dashboard layout */ }
.avd-stats-grid { /* Statistics grid */ }
.avd-privacy-mode-selector { /* Privacy mode UI */ }
```

### `assets/css/frontend.css`
```css
/* Monitoring button and widget */
#avd-monitoring-button {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
}

#avd-monitoring-widget {
    position: fixed;
    bottom: 80px;
    right: 20px;
    width: 400px;
    max-height: 600px;
    z-index: 9998;
}
```

### `assets/js/admin.js`
```javascript
// Admin interface JavaScript
jQuery(document).ready(function($) {
    // Privacy mode selector
    // Data export/delete handlers
    // Real-time stats updates
});
```

### `assets/js/frontend.js`
```javascript
// Frontend monitoring JavaScript
jQuery(document).ready(function($) {
    // Initialize monitoring widget
    // Handle scrape requests with rate limiting
    // Real-time activity display
    // Privacy mode indicator
});
```

## 🔧 Configuration

### Default Privacy Modes:

**Maximum Privacy:**
- Data Retention: 7 days
- URL Collection: None (hashed only)
- Form Data: None
- Content: Page titles only
- Anonymization: Maximum

**Balanced Privacy:** (Default)
- Data Retention: 7 days
- URL Collection: Domain + sanitized path
- Form Data: Field names only (no values)
- Content: Title + main content (PII removed)
- Anonymization: Standard

**Minimal Privacy:**
- Data Retention: 30 days
- URL Collection: Full URLs
- Form Data: All data (encrypted)
- Content: Full page content
- Anonymization: PII removal only

## 🧪 Testing Checklist

- [ ] Privacy consent flow works
- [ ] Encryption/decryption functions correctly
- [ ] Anonymization removes PII
- [ ] Rate limiting prevents abuse
- [ ] Ultimate Web Scraper scrapes successfully
- [ ] APS integration stores patterns
- [ ] Bloom integration analyzes patterns
- [ ] APS Tools shows Vision Depth data
- [ ] GDPR data export works
- [ ] GDPR data deletion works
- [ ] Rewards are calculated correctly
- [ ] Auto-deletion cron works
- [ ] Monitoring dashboard displays correctly

## 📝 Next Steps

1. Complete remaining privacy classes
2. Implement scraper manager
3. Build dashboard and monitoring UI
4. Create integration classes
5. Add frontend assets
6. Test thoroughly
7. Document user-facing features
8. Deploy and monitor
