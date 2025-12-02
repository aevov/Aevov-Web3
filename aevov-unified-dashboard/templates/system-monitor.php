<?php
/**
 * System Monitor Template
 *
 * @package AevovUnifiedDashboard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap aud-dashboard-wrap">
    <div class="aud-header">
        <div class="aud-header-content">
            <div>
                <h1 class="aud-header-title">
                    <span class="aud-header-title-icon">🔍</span>
                    System Monitor
                </h1>
                <p class="aud-header-subtitle">
                    Real-time monitoring of your Aevov ecosystem
                </p>
            </div>
            <button class="aud-button aud-button-secondary aud-refresh-stats">
                🔄 Refresh Stats
            </button>
        </div>
    </div>

    <div class="aud-stats-container">
        <div style="text-align: center; padding: 60px 0;">
            <div class="aud-loading"></div>
            <p>Loading system statistics...</p>
        </div>
    </div>

    <div class="aud-grid aud-grid-2" style="margin-top: 32px;">
        <div class="aud-card">
            <div class="aud-card-header">
                <h3 class="aud-card-title">
                    <span class="aud-card-icon">🔌</span>
                    Plugin Status
                </h3>
            </div>
            <div class="aud-card-body">
                <p style="color: var(--aud-gray-600); margin-bottom: 16px;">
                    All plugins are being monitored in real-time.
                </p>
                <a href="admin.php?page=aevov-plugin-manager" class="aud-button aud-button-secondary">
                    View Plugin Manager
                </a>
            </div>
        </div>

        <div class="aud-card">
            <div class="aud-card-header">
                <h3 class="aud-card-title">
                    <span class="aud-card-icon">✨</span>
                    Pattern Statistics
                </h3>
            </div>
            <div class="aud-card-body">
                <p style="color: var(--aud-gray-600); margin-bottom: 16px;">
                    Pattern creation and synchronization metrics.
                </p>
                <a href="admin.php?page=aevov-pattern-creator" class="aud-button aud-button-secondary">
                    Create Pattern
                </a>
            </div>
        </div>

        <div class="aud-card">
            <div class="aud-card-header">
                <h3 class="aud-card-title">
                    <span class="aud-card-icon">📈</span>
                    Performance Metrics
                </h3>
            </div>
            <div class="aud-card-body">
                <ul style="line-height: 2; color: var(--aud-gray-700);">
                    <li><strong>API Response Time:</strong> Real-time monitoring</li>
                    <li><strong>Pattern Sync Speed:</strong> Network performance</li>
                    <li><strong>Database Queries:</strong> Optimization insights</li>
                    <li><strong>Cache Hit Rate:</strong> Efficiency metrics</li>
                </ul>
            </div>
        </div>

        <div class="aud-card">
            <div class="aud-card-header">
                <h3 class="aud-card-title">
                    <span class="aud-card-icon">🔄</span>
                    Sync Status
                </h3>
            </div>
            <div class="aud-card-body">
                <div class="aud-alert aud-alert-success">
                    <span style="font-size: 1.25rem;">✅</span>
                    <div>
                        <strong>System Healthy</strong>
                        <p style="margin: 4px 0 0 0;">All patterns are syncing normally across the network.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
