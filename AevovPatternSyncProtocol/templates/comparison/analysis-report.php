<?php
/**
 * Template for pattern analysis report
 */

defined('ABSPATH') || exit;

$report_id = isset($_GET['report_id']) ? sanitize_text_field($_GET['report_id']) : '';
$report = $this->get_analysis_report($report_id);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if ($report): ?>
        <div class="report-container">
            <div class="report-header">
                <div class="report-meta">
                    <h2><?php echo esc_html(sprintf(__('Analysis Report: %s', 'aps'), $report_id)); ?></h2>
                    <div class="meta-info">
                        <span class="meta-item">
                            <?php _e('Generated:', 'aps'); ?> 
                            <?php echo esc_html($report['generated_at']); ?>
                        </span>
                        <span class="meta-item">
                            <?php _e('Patterns Analyzed:', 'aps'); ?> 
                            <?php echo esc_html($report['pattern_count']); ?>
                        </span>
                        <span class="meta-item">
                            <?php _e('Duration:', 'aps'); ?> 
                            <?php echo esc_html($report['duration']); ?>s
                        </span>
                    </div>
                </div>
                <div class="report-actions">
                    <button id="export-pdf" class="button button-secondary">
                        <?php _e('Export PDF', 'aps'); ?>
                    </button>
                    <button id="export-json" class="button button-secondary">
                        <?php _e('Export JSON', 'aps'); ?>
                    </button>
                </div>
            </div>

            <div class="report-content">
                <!-- Executive Summary -->
                <div class="report-section">
                    <h3><?php _e('Executive Summary', 'aps'); ?></h3>
                    <div class="summary-grid">
                        <div class="summary-card">
                            <div class="card-title"><?php _e('Overall Confidence', 'aps'); ?></div>
                            <div class="card-value">
                                <?php echo esc_html(number_format($report['summary']['confidence'] * 100, 1)); ?>%
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="card-title"><?php _e('Pattern Quality', 'aps'); ?></div>
                            <div class="card-value">
                                <?php echo esc_html($report['summary']['quality']); ?>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="card-title"><?php _e('Match Rate', 'aps'); ?></div>
                            <div class="card-value">
                                <?php echo esc_html($report['summary']['match_rate']); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="summary-text">
                        <?php echo wp_kses_post($report['summary']['text']); ?>
                    </div>
                </div>

                <!-- Detailed Analysis -->
                <div class="report-section">
                    <h3><?php _e('Detailed Analysis', 'aps'); ?></h3>
                    <div class="analysis-tabs">
                        <div class="tab-headers">
                            <button class="tab-header active" data-tab="features">
                                <?php _e('Feature Analysis', 'aps'); ?>
                            </button>
                            <button class="tab-header" data-tab="relationships">
                                <?php _e('Pattern Relationships', 'aps'); ?>
                            </button>
                            <button class="tab-header" data-tab="distribution">
                                <?php _e('Distribution Analysis', 'aps'); ?>
                            </button>
                        </div>
                        <div class="tab-content">
                            <div id="features" class="tab-pane active">
                                <div id="feature-analysis-chart" class="analysis-chart"></div>
                                <div class="analysis-details">
                                    <?php echo wp_kses_post($report['analysis']['features']); ?>
                                </div>
                            </div>
                            <div id="relationships" class="tab-pane">
                                <div id="relationship-chart" class="analysis-chart"></div>
                                <div class="analysis-details">
                                    <?php echo wp_kses_post($report['analysis']['relationships']); ?>
                                </div>
                            </div>
                            <div id="distribution" class="tab-pane">
                                <div id="distribution-chart" class="analysis-chart"></div>
                                <div class="analysis-details">
                                    <?php echo wp_kses_post($report['analysis']['distribution']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recommendations -->
                <div class="report-section">
                    <h3><?php _e('Recommendations', 'aps'); ?></h3>
                    <div class="recommendations-list">
                        <?php foreach ($report['recommendations'] as $recommendation): ?>
                            <div class="recommendation-item <?php echo esc_attr($recommendation['priority']); ?>">
                                <div class="recommendation-header">
                                    <span class="priority-badge">
                                        <?php echo esc_html($recommendation['priority']); ?>
                                    </span>
                                    <h4><?php echo esc_html($recommendation['title']); ?></h4>
                                </div>
                                <div class="recommendation-content">
                                    <?php echo wp_kses_post($recommendation['description']); ?>
                                </div>
                                <?php if (!empty($recommendation['action'])): ?>
                                    <div class="recommendation-action">
                                        <button class="button button-secondary implement-recommendation"
                                                data-action="<?php echo esc_attr($recommendation['action']); ?>"
                                                data-id="<?php echo esc_attr($recommendation['id']); ?>">
                                            <?php _e('Implement', 'aps'); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="notice notice-error">
            <p><?php _e('Analysis report not found.', 'aps'); ?></p>
        </div>
    <?php endif; ?>
</div>