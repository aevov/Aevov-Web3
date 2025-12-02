<?php
/**
 * Database Handler for Vision Depth
 *
 * @package AevovVisionDepth
 * @since 1.0.0
 */

namespace AevovVisionDepth;

class Database {

    /**
     * Create plugin database tables
     *
     * @return void
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Scraped data table
        $table_scraped_data = $wpdb->prefix . 'avd_scraped_data';
        $sql_scraped = "CREATE TABLE IF NOT EXISTS {$table_scraped_data} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            url_hash VARCHAR(64) NOT NULL,
            content_hash VARCHAR(64) NOT NULL,
            encrypted_data LONGTEXT NOT NULL,
            privacy_mode ENUM('maximum', 'balanced', 'minimal') DEFAULT 'balanced',
            data_type VARCHAR(50) NOT NULL,
            metadata LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX user_id (user_id),
            INDEX url_hash (url_hash),
            INDEX privacy_mode (privacy_mode),
            INDEX created_at (created_at)
        ) {$charset_collate};";

        // User consent table
        $table_consent = $wpdb->prefix . 'avd_user_consent';
        $sql_consent = "CREATE TABLE IF NOT EXISTS {$table_consent} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL UNIQUE,
            consent_given TINYINT(1) DEFAULT 0,
            privacy_mode ENUM('maximum', 'balanced', 'minimal') DEFAULT 'balanced',
            consent_date DATETIME NULL,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address VARCHAR(50) NULL,
            user_agent TEXT NULL,
            INDEX user_id (user_id),
            INDEX consent_given (consent_given)
        ) {$charset_collate};";

        // Scrape jobs queue table
        $table_jobs = $wpdb->prefix . 'avd_scrape_jobs';
        $sql_jobs = "CREATE TABLE IF NOT EXISTS {$table_jobs} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            url VARCHAR(500) NOT NULL,
            url_hash VARCHAR(64) NOT NULL,
            priority INT DEFAULT 5,
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 3,
            result_data LONGTEXT NULL,
            error_message TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            INDEX user_id (user_id),
            INDEX url_hash (url_hash),
            INDEX status (status),
            INDEX priority (priority),
            INDEX created_at (created_at)
        ) {$charset_collate};";

        // Rate limiting table
        $table_rate_limits = $wpdb->prefix . 'avd_rate_limits';
        $sql_rate_limits = "CREATE TABLE IF NOT EXISTS {$table_rate_limits} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            count INT DEFAULT 1,
            window_start DATETIME NOT NULL,
            INDEX user_id_action (user_id, action_type),
            INDEX window_start (window_start)
        ) {$charset_collate};";

        // Behavioral patterns table (integration with Bloom)
        $table_patterns = $wpdb->prefix . 'avd_behavioral_patterns';
        $sql_patterns = "CREATE TABLE IF NOT EXISTS {$table_patterns} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            pattern_hash VARCHAR(64) NOT NULL,
            pattern_type VARCHAR(100) NOT NULL,
            pattern_data LONGTEXT NOT NULL,
            confidence_score DECIMAL(5,4) DEFAULT 0.0000,
            occurrences INT DEFAULT 1,
            first_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_seen DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            bloom_pattern_id BIGINT UNSIGNED NULL,
            aps_pattern_id BIGINT UNSIGNED NULL,
            INDEX user_id (user_id),
            INDEX pattern_hash (pattern_hash),
            INDEX pattern_type (pattern_type),
            INDEX confidence_score (confidence_score),
            INDEX bloom_pattern_id (bloom_pattern_id),
            INDEX aps_pattern_id (aps_pattern_id)
        ) {$charset_collate};";

        // Reward tracking table (AevCoin integration)
        $table_rewards = $wpdb->prefix . 'avd_user_rewards';
        $sql_rewards = "CREATE TABLE IF NOT EXISTS {$table_rewards} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            reward_type VARCHAR(50) NOT NULL,
            amount DECIMAL(10,4) DEFAULT 0.0000,
            description TEXT NULL,
            scrape_data_id BIGINT UNSIGNED NULL,
            pattern_id BIGINT UNSIGNED NULL,
            status ENUM('pending', 'distributed', 'failed') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            distributed_at DATETIME NULL,
            INDEX user_id (user_id),
            INDEX reward_type (reward_type),
            INDEX status (status),
            INDEX created_at (created_at)
        ) {$charset_collate};";

        // Activity log table
        $table_activity = $wpdb->prefix . 'avd_activity_log';
        $sql_activity = "CREATE TABLE IF NOT EXISTS {$table_activity} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            action_type VARCHAR(100) NOT NULL,
            action_description TEXT NULL,
            entity_type VARCHAR(100) NULL,
            entity_id BIGINT UNSIGNED NULL,
            metadata LONGTEXT NULL,
            ip_address VARCHAR(50) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX user_id (user_id),
            INDEX action_type (action_type),
            INDEX entity_type (entity_type),
            INDEX created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_scraped);
        dbDelta($sql_consent);
        dbDelta($sql_jobs);
        dbDelta($sql_rate_limits);
        dbDelta($sql_patterns);
        dbDelta($sql_rewards);
        dbDelta($sql_activity);
    }

    /**
     * Drop plugin tables (used on uninstall)
     *
     * @return void
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'avd_scraped_data',
            $wpdb->prefix . 'avd_user_consent',
            $wpdb->prefix . 'avd_scrape_jobs',
            $wpdb->prefix . 'avd_rate_limits',
            $wpdb->prefix . 'avd_behavioral_patterns',
            $wpdb->prefix . 'avd_user_rewards',
            $wpdb->prefix . 'avd_activity_log',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }
}
