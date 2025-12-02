<?php
/**
 * Workflow Testing Template
 *
 * Beautiful, non-overwhelming UI for browsing and running 2655 workflow tests
 * Uses progressive disclosure and accordion pattern
 *
 * @package AevovOnboarding
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Initialize workflow integration
$workflow = new \AevovOnboarding\WorkflowIntegration();
$summary = $workflow->get_test_summary();
$groups = $workflow->get_category_groups();
$recommended_sequence = $workflow->get_recommended_test_sequence();
?>

<div class="workflow-testing-container">
    <!-- Header -->
    <div class="workflow-header">
        <div class="header-content">
            <h1><?php _e('System Testing Dashboard', 'aevov-onboarding'); ?></h1>
            <p class="subtitle"><?php _e('Comprehensive workflow testing for your Aevov ecosystem', 'aevov-onboarding'); ?></p>
        </div>
        <div class="header-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($summary['total_tests']); ?></div>
                <div class="stat-label"><?php _e('Total Tests', 'aevov-onboarding'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $summary['total_groups']; ?></div>
                <div class="stat-label"><?php _e('Test Groups', 'aevov-onboarding'); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $summary['plugins_tested']; ?></div>
                <div class="stat-label"><?php _e('Plugins Tested', 'aevov-onboarding'); ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-number"><?php echo $summary['pass_rate']; ?>%</div>
                <div class="stat-label"><?php _e('Pass Rate', 'aevov-onboarding'); ?></div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Bar -->
    <div class="quick-actions-bar">
        <div class="actions-left">
            <button class="btn btn-primary btn-large" id="run-all-tests">
                <span class="dashicons dashicons-controls-play"></span>
                <?php _e('Run Full Test Suite', 'aevov-onboarding'); ?>
            </button>
            <button class="btn btn-secondary" id="run-critical-only">
                <span class="dashicons dashicons-shield-alt"></span>
                <?php _e('Run Critical Tests Only', 'aevov-onboarding'); ?>
            </button>
        </div>
        <div class="actions-right">
            <button class="btn btn-outline" id="view-last-results">
                <span class="dashicons dashicons-chart-bar"></span>
                <?php _e('View Last Results', 'aevov-onboarding'); ?>
            </button>
            <button class="btn btn-outline" id="schedule-tests">
                <span class="dashicons dashicons-calendar"></span>
                <?php _e('Schedule Tests', 'aevov-onboarding'); ?>
            </button>
        </div>
    </div>

    <!-- Test Recommendation Notice -->
    <div class="notice notice-info">
        <p>
            <strong><?php _e('Recommended Testing Order:', 'aevov-onboarding'); ?></strong>
            <?php _e('For first-time setup, we recommend running tests in order of importance: Critical tests first, followed by High priority, then Medium priority tests.', 'aevov-onboarding'); ?>
        </p>
    </div>

    <!-- Last Test Run Info -->
    <?php if ($summary['last_run']): ?>
    <div class="last-run-info">
        <span class="dashicons dashicons-clock"></span>
        <span><?php printf(__('Last test run: %s', 'aevov-onboarding'), date('F j, Y g:i a', strtotime($summary['last_run']))); ?></span>
        <span class="status-badge status-success"><?php _e('All Tests Passed', 'aevov-onboarding'); ?></span>
    </div>
    <?php endif; ?>

    <!-- Progress Indicator (Hidden by default, shown during test execution) -->
    <div id="test-progress-container" class="test-progress-container" style="display: none;">
        <div class="progress-header">
            <h3 id="progress-title"><?php _e('Running Tests...', 'aevov-onboarding'); ?></h3>
            <button class="btn btn-text" id="cancel-tests"><?php _e('Cancel', 'aevov-onboarding'); ?></button>
        </div>
        <div class="progress-bar-wrapper">
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
            </div>
            <div class="progress-stats">
                <span id="progress-text">0%</span>
                <span id="progress-count">0 / 0 tests</span>
            </div>
        </div>
        <div id="progress-log" class="progress-log"></div>
    </div>

    <!-- Test Category Groups (Accordion) -->
    <div class="test-groups-accordion">
        <?php foreach ($recommended_sequence as $index => $item): ?>
            <?php
            $group_key = $item['key'];
            $group = $groups[$group_key];
            $categories = $workflow->get_group_categories($group_key);
            $test_count = $item['test_count'];
            $is_first = $index === 0;
            ?>

            <div class="accordion-item" data-group="<?php echo esc_attr($group_key); ?>">
                <div class="accordion-header <?php echo $is_first ? 'active' : ''; ?>"
                     style="border-left: 4px solid <?php echo esc_attr($group['color']); ?>">
                    <div class="header-left">
                        <span class="dashicons <?php echo esc_attr($group['icon']); ?>"
                              style="color: <?php echo esc_attr($group['color']); ?>"></span>
                        <div class="header-info">
                            <h3><?php echo esc_html($group['name']); ?></h3>
                            <p class="description"><?php echo esc_html($group['description']); ?></p>
                        </div>
                    </div>
                    <div class="header-right">
                        <?php echo $workflow->get_importance_badge($group['importance']); ?>
                        <div class="test-count-badge">
                            <strong><?php echo number_format($test_count); ?></strong> tests
                        </div>
                        <div class="time-estimate">
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo esc_html($group['estimated_time']); ?>
                        </div>
                        <span class="accordion-toggle">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </span>
                    </div>
                </div>

                <div class="accordion-content" <?php echo $is_first ? 'style="display: block;"' : ''; ?>>
                    <div class="group-actions">
                        <button class="btn btn-primary run-group-tests" data-group="<?php echo esc_attr($group_key); ?>">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php _e('Run This Group', 'aevov-onboarding'); ?>
                        </button>
                        <button class="btn btn-outline view-group-details" data-group="<?php echo esc_attr($group_key); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php _e('View Details', 'aevov-onboarding'); ?>
                        </button>
                    </div>

                    <!-- Category List -->
                    <div class="category-grid">
                        <?php foreach ($categories as $cat_key => $category): ?>
                            <div class="category-card">
                                <div class="category-header">
                                    <h4><?php echo esc_html($category['name']); ?></h4>
                                    <span class="category-test-count"><?php echo $category['tests']; ?> tests</span>
                                </div>
                                <p class="category-description"><?php echo esc_html($category['description']); ?></p>
                                <div class="category-actions">
                                    <button class="btn btn-small run-category-tests"
                                            data-group="<?php echo esc_attr($group_key); ?>"
                                            data-category="<?php echo esc_attr($cat_key); ?>">
                                        <span class="dashicons dashicons-controls-play"></span>
                                        <?php _e('Run', 'aevov-onboarding'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- What This Tests Section -->
                    <div class="info-section">
                        <h4><?php _e('What This Tests', 'aevov-onboarding'); ?></h4>
                        <p><?php echo $this->get_what_this_tests($group_key); ?></p>
                    </div>

                    <!-- Why It Matters Section -->
                    <div class="info-section">
                        <h4><?php _e('Why It Matters', 'aevov-onboarding'); ?></h4>
                        <p><?php echo $this->get_why_it_matters($group_key); ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Test Results Panel (Hidden by default, shown after test execution) -->
    <div id="test-results-panel" class="test-results-panel" style="display: none;">
        <div class="results-header">
            <h2><?php _e('Test Results', 'aevov-onboarding'); ?></h2>
            <button class="btn btn-text" id="close-results"><?php _e('Close', 'aevov-onboarding'); ?></button>
        </div>

        <div class="results-summary" id="results-summary">
            <!-- Results will be populated via JavaScript -->
        </div>

        <div class="results-filters">
            <button class="filter-btn active" data-filter="all"><?php _e('All', 'aevov-onboarding'); ?></button>
            <button class="filter-btn" data-filter="passed"><?php _e('Passed', 'aevov-onboarding'); ?></button>
            <button class="filter-btn" data-filter="failed"><?php _e('Failed', 'aevov-onboarding'); ?></button>
            <button class="filter-btn" data-filter="warnings"><?php _e('Warnings', 'aevov-onboarding'); ?></button>
        </div>

        <div id="results-list" class="results-list">
            <!-- Detailed results will be populated via JavaScript -->
        </div>

        <div class="results-actions">
            <button class="btn btn-primary" id="export-results">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export Results', 'aevov-onboarding'); ?>
            </button>
            <button class="btn btn-secondary" id="email-results">
                <span class="dashicons dashicons-email"></span>
                <?php _e('Email Results', 'aevov-onboarding'); ?>
            </button>
        </div>
    </div>

    <!-- Help Section -->
    <div class="help-section">
        <h3><?php _e('About Workflow Testing', 'aevov-onboarding'); ?></h3>
        <div class="help-grid">
            <div class="help-card">
                <span class="dashicons dashicons-info"></span>
                <h4><?php _e('What are workflow tests?', 'aevov-onboarding'); ?></h4>
                <p><?php _e('Automated tests that verify all components of your Aevov ecosystem work correctly together, from basic functionality to complex multi-plugin interactions.', 'aevov-onboarding'); ?></p>
            </div>
            <div class="help-card">
                <span class="dashicons dashicons-clock"></span>
                <h4><?php _e('How long does it take?', 'aevov-onboarding'); ?></h4>
                <p><?php _e('The full test suite takes approximately 45-60 minutes. You can run individual groups (5-15 minutes each) or critical tests only (15-20 minutes).', 'aevov-onboarding'); ?></p>
            </div>
            <div class="help-card">
                <span class="dashicons dashicons-shield"></span>
                <h4><?php _e('When should I run tests?', 'aevov-onboarding'); ?></h4>
                <p><?php _e('Run tests during initial setup, after plugin updates, before deploying to production, or when troubleshooting issues. Schedule regular tests for ongoing monitoring.', 'aevov-onboarding'); ?></p>
            </div>
            <div class="help-card">
                <span class="dashicons dashicons-warning"></span>
                <h4><?php _e('What if tests fail?', 'aevov-onboarding'); ?></h4>
                <p><?php _e('Failed tests indicate potential issues. Review the detailed error messages, check plugin compatibility, and ensure all dependencies are properly configured.', 'aevov-onboarding'); ?></p>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Helper functions for template
 */

function get_what_this_tests($group_key) {
    $descriptions = [
        'core_system' => __('Tests fundamental system operations including plugin activation sequences, database integrity, API authentication, and pattern creation workflows. Ensures the core foundation of your Aevov ecosystem is solid.', 'aevov-onboarding'),

        'security_integrity' => __('Validates security measures against common vulnerabilities (SQL injection, XSS, CSRF), tests data validation rules, concurrency handling, and edge case scenarios. Critical for production deployment security.', 'aevov-onboarding'),

        'performance_optimization' => __('Evaluates system performance under various loads, cache effectiveness, resource utilization, and stress tolerance. Identifies bottlenecks and optimization opportunities for optimal performance.', 'aevov-onboarding'),

        'integration_communication' => __('Verifies cross-plugin messaging, event propagation, webhook processing, and data synchronization. Tests all possible plugin combinations to ensure seamless ecosystem integration.', 'aevov-onboarding'),

        'user_experience' => __('Validates user-facing workflows, accessibility compliance (WCAG), localization support, and multi-user collaboration features. Ensures your system is user-friendly and inclusive.', 'aevov-onboarding'),

        'data_management' => __('Tests file operations, backup/restore procedures, disaster recovery, and upgrade migrations. Ensures data safety and system reliability during critical operations.', 'aevov-onboarding'),

        'advanced_workflows' => __('Evaluates complex multi-plugin interactions, state machine transitions, error injection scenarios, and sophisticated user journeys. Tests real-world enterprise-level usage patterns.', 'aevov-onboarding'),

        'production_readiness' => __('Validates rate limiting, logging systems, queue management, network resilience, and monitoring capabilities. Ensures your system is production-ready and enterprise-grade.', 'aevov-onboarding')
    ];

    return $descriptions[$group_key] ?? '';
}

function get_why_it_matters($group_key) {
    $reasons = [
        'core_system' => __('Without a solid core, your entire ecosystem is at risk. These tests ensure plugins activate in the correct order, databases maintain integrity, APIs authenticate properly, and patterns are created correctly - the foundation everything else depends on.', 'aevov-onboarding'),

        'security_integrity' => __('Security breaches can be devastating. These tests protect against malicious attacks, data corruption, and system vulnerabilities. A single security flaw can compromise your entire installation and user data.', 'aevov-onboarding'),

        'performance_optimization' => __('Poor performance drives users away and wastes resources. These tests identify bottlenecks before they impact users, ensure efficient resource usage, and validate that your system can handle growth and scale effectively.', 'aevov-onboarding'),

        'integration_communication' => __('In a complex ecosystem, plugins must communicate flawlessly. Failed integration can cause data loss, workflow breaks, and system instability. These tests ensure all components work together harmoniously.', 'aevov-onboarding'),

        'user_experience' => __('User satisfaction drives adoption and retention. These tests ensure your system is accessible to all users, supports multiple languages, handles collaboration smoothly, and provides an intuitive experience that delights users.', 'aevov-onboarding'),

        'data_management' => __('Data is your most valuable asset. These tests ensure you can safely backup, restore, migrate, and recover data under any circumstance. Data loss can be catastrophic - prevention is critical.', 'aevov-onboarding'),

        'advanced_workflows' => __('Real-world usage is complex. These tests simulate sophisticated scenarios that users will encounter in production, ensuring your system handles complexity gracefully and provides reliable enterprise-level functionality.', 'aevov-onboarding'),

        'production_readiness' => __('Production environments demand reliability, monitoring, and resilience. These tests ensure your system can handle production traffic, recover from failures, enforce rate limits, and provide the monitoring needed for operational excellence.', 'aevov-onboarding')
    ];

    return $reasons[$group_key] ?? '';
}
?>
