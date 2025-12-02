<?php
/**
 * Main Dashboard Template
 *
 * @package AevovUnifiedDashboard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap aud-dashboard-wrap">
    <!-- Header -->
    <div class="aud-header">
        <div class="aud-header-content">
            <div>
                <h1 class="aud-header-title">
                    <span class="aud-header-title-icon">🚀</span>
                    Aevov Unified Dashboard
                </h1>
                <p class="aud-header-subtitle">
                    Complete control center for your Neurosymbolic Intelligence Platform
                </p>
            </div>
            <div class="aud-header-stats aud-summary-stats">
                <div class="aud-stat">
                    <p class="aud-stat-value">29</p>
                    <p class="aud-stat-label">Total Plugins</p>
                </div>
                <div class="aud-stat">
                    <p class="aud-stat-value">-</p>
                    <p class="aud-stat-label">Active</p>
                </div>
                <div class="aud-stat">
                    <p class="aud-stat-value">-</p>
                    <p class="aud-stat-label">Patterns</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="aud-nav">
        <button class="aud-nav-tab active" data-tab="overview">
            📊 Overview
        </button>
        <button class="aud-nav-tab" data-tab="plugins">
            🔌 Plugins
        </button>
        <button class="aud-nav-tab" data-tab="patterns">
            ✨ Patterns
        </button>
        <button class="aud-nav-tab" data-tab="monitor">
            🔍 Monitor
        </button>
        <button class="aud-nav-tab" data-tab="quick-actions">
            ⚡ Quick Actions
        </button>
    </nav>

    <!-- Overview Tab -->
    <div id="aud-overview-tab" class="aud-tab-content">
        <div class="aud-stats-container">
            <div style="text-align: center; padding: 60px 0;">
                <div class="aud-loading"></div>
                <p>Loading dashboard statistics...</p>
            </div>
        </div>

        <h2 style="margin-top: 40px; margin-bottom: 20px; color: var(--aud-gray-900); font-size: 1.75rem; font-weight: 600;">
            Core Plugins (3)
        </h2>
        <div class="aud-grid aud-grid-3 aud-core-plugins">
            <!-- Core plugins will be loaded here -->
            <div class="aud-skeleton" style="height: 200px;"></div>
            <div class="aud-skeleton" style="height: 200px;"></div>
            <div class="aud-skeleton" style="height: 200px;"></div>
        </div>

        <h2 style="margin-top: 40px; margin-bottom: 20px; color: var(--aud-gray-900); font-size: 1.75rem; font-weight: 600;">
            Recently Active Plugins
        </h2>
        <div class="aud-grid aud-grid-4">
            <!-- Recent plugins will be loaded here -->
            <div class="aud-skeleton" style="height: 180px;"></div>
            <div class="aud-skeleton" style="height: 180px;"></div>
            <div class="aud-skeleton" style="height: 180px;"></div>
            <div class="aud-skeleton" style="height: 180px;"></div>
        </div>

        <div class="aud-card" style="margin-top: 40px;">
            <div class="aud-card-header">
                <h3 class="aud-card-title">
                    <span class="aud-card-icon">🎯</span>
                    Quick Start Guide
                </h3>
            </div>
            <div class="aud-card-body">
                <p style="font-size: 1.125rem; line-height: 1.7; margin-bottom: 24px;">
                    Welcome to the Aevov Unified Dashboard! Here's how to get started:
                </p>
                <ol style="font-size: 1rem; line-height: 1.8; color: var(--aud-gray-700);">
                    <li><strong>Activate All Plugins:</strong> Go to the Plugins tab and click "Activate All 29 Plugins" to enable the complete ecosystem.</li>
                    <li><strong>Create Your First Pattern:</strong> Navigate to the Patterns tab to create and sync your first pattern across the network.</li>
                    <li><strong>Monitor System Health:</strong> Check the Monitor tab to view real-time system statistics and performance metrics.</li>
                    <li><strong>Explore Features:</strong> Each plugin offers unique capabilities - explore them all!</li>
                </ol>
            </div>
            <div class="aud-card-footer">
                <a href="admin.php?page=aevov-onboarding" class="aud-button aud-button-primary">
                    📚 Start Onboarding Tour
                </a>
                <a href="admin.php?page=aevov-plugin-manager" class="aud-button aud-button-secondary">
                    🔌 Manage Plugins
                </a>
            </div>
        </div>
    </div>

    <!-- Plugins Tab -->
    <div id="aud-plugins-tab" class="aud-tab-content" style="display: none;">
        <div class="aud-card" style="margin-bottom: 24px;">
            <div class="aud-card-body">
                <div style="display: flex; gap: 16px; align-items: center;">
                    <input
                        type="text"
                        class="aud-form-input aud-plugin-search"
                        placeholder="🔍 Search plugins..."
                        style="flex: 1;"
                    >
                    <select class="aud-form-select aud-plugin-category-filter" style="width: 200px;">
                        <option value="all">All Categories</option>
                        <option value="Core System">Core System</option>
                        <option value="AI Engines">AI Engines</option>
                        <option value="Media Engines">Media Engines</option>
                        <option value="Creation Tools">Creation Tools</option>
                        <option value="Data Management">Data Management</option>
                        <option value="Infrastructure">Infrastructure</option>
                        <option value="User Interface">User Interface</option>
                        <option value="Development">Development</option>
                        <option value="Monitoring">Monitoring</option>
                        <option value="Simulation">Simulation</option>
                    </select>
                    <button class="aud-button aud-button-success aud-activate-all">
                        ⚡ Activate All 29 Plugins
                    </button>
                    <button class="aud-button aud-button-secondary aud-refresh-plugins">
                        🔄 Refresh
                    </button>
                </div>
            </div>
        </div>

        <h2 style="margin-bottom: 20px; color: var(--aud-gray-900); font-size: 1.75rem; font-weight: 600;">
            Core Plugins (3)
        </h2>
        <div class="aud-grid aud-grid-3 aud-core-plugins">
            <!-- Core plugins will be loaded here -->
        </div>

        <h2 style="margin-top: 40px; margin-bottom: 20px; color: var(--aud-gray-900); font-size: 1.75rem; font-weight: 600;">
            Sister Plugins (26)
        </h2>
        <div class="aud-grid aud-grid-4 aud-sister-plugins">
            <!-- Sister plugins will be loaded here -->
        </div>
    </div>

    <!-- Patterns Tab -->
    <div id="aud-patterns-tab" class="aud-tab-content" style="display: none;">
        <div class="aud-grid aud-grid-2">
            <div class="aud-card">
                <div class="aud-card-header">
                    <h3 class="aud-card-title">
                        <span class="aud-card-icon">✨</span>
                        Create New Pattern
                    </h3>
                </div>
                <div class="aud-card-body">
                    <form id="aud-pattern-form">
                        <div class="aud-form-group">
                            <label class="aud-form-label" for="aud-pattern-name">Pattern Name</label>
                            <input
                                type="text"
                                id="aud-pattern-name"
                                class="aud-form-input"
                                placeholder="Enter pattern name..."
                                required
                            >
                        </div>

                        <div class="aud-form-group">
                            <label class="aud-form-label" for="aud-pattern-description">Description</label>
                            <textarea
                                id="aud-pattern-description"
                                class="aud-form-textarea"
                                placeholder="Describe your pattern..."
                            ></textarea>
                        </div>

                        <div class="aud-form-group">
                            <label class="aud-form-label" for="aud-pattern-data">Pattern Data (JSON)</label>
                            <textarea
                                id="aud-pattern-data"
                                class="aud-form-textarea"
                                placeholder='{"type": "example", "value": "data"}'
                                required
                            ></textarea>
                            <p class="aud-form-help">Enter valid JSON data for the pattern</p>
                        </div>

                        <button type="submit" class="aud-button aud-button-primary aud-button-large aud-create-pattern-btn" style="width: 100%;">
                            ✨ Create Pattern
                        </button>
                    </form>
                </div>
            </div>

            <div class="aud-card">
                <div class="aud-card-header">
                    <h3 class="aud-card-title">
                        <span class="aud-card-icon">📚</span>
                        Pattern Information
                    </h3>
                </div>
                <div class="aud-card-body">
                    <h4 style="font-weight: 600; margin-bottom: 12px; color: var(--aud-gray-900);">What are Patterns?</h4>
                    <p style="margin-bottom: 16px; line-height: 1.7;">
                        Patterns are the fundamental units of data in the Aevov ecosystem. They represent structured information that can be:
                    </p>
                    <ul style="line-height: 1.8; color: var(--aud-gray-700);">
                        <li>Synchronized across the network via APS</li>
                        <li>Recognized and analyzed by Bloom</li>
                        <li>Used by all 29 plugins in the ecosystem</li>
                        <li>Stored on the distributed ledger</li>
                        <li>Rewarded through Proof of Contribution</li>
                    </ul>

                    <div class="aud-alert aud-alert-info" style="margin-top: 24px;">
                        <span style="font-size: 1.25rem;">💡</span>
                        <div>
                            <strong>Pro Tip:</strong> Start with simple patterns and gradually increase complexity as you explore the system.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monitor Tab -->
    <div id="aud-monitor-tab" class="aud-tab-content" style="display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h2 style="margin: 0; color: var(--aud-gray-900); font-size: 1.75rem; font-weight: 600;">
                System Monitor
            </h2>
            <button class="aud-button aud-button-secondary aud-refresh-stats">
                🔄 Refresh Stats
            </button>
        </div>

        <div class="aud-stats-container">
            <!-- Stats will be loaded here -->
        </div>

        <div class="aud-grid aud-grid-2" style="margin-top: 32px;">
            <div class="aud-card">
                <div class="aud-card-header">
                    <h3 class="aud-card-title">
                        <span class="aud-card-icon">📈</span>
                        Performance Metrics
                    </h3>
                </div>
                <div class="aud-card-body">
                    <p style="color: var(--aud-gray-600);">Real-time performance monitoring coming soon...</p>
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
                    <p style="color: var(--aud-gray-600);">Pattern synchronization status coming soon...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Tab -->
    <div id="aud-quick-actions-tab" class="aud-tab-content" style="display: none;">
        <h2 style="margin-bottom: 24px; color: var(--aud-gray-900); font-size: 1.75rem; font-weight: 600;">
            Quick Actions
        </h2>

        <div class="aud-grid aud-grid-3">
            <div class="aud-card">
                <div class="aud-card-header">
                    <h3 class="aud-card-title">
                        <span class="aud-card-icon">⚡</span>
                        System Actions
                    </h3>
                </div>
                <div class="aud-card-body">
                    <p style="margin-bottom: 16px;">Manage your Aevov ecosystem:</p>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <button class="aud-button aud-button-primary aud-activate-all">
                            Activate All Plugins
                        </button>
                        <button class="aud-button aud-button-secondary aud-refresh-plugins">
                            Refresh Plugin Status
                        </button>
                        <button class="aud-button aud-button-secondary aud-refresh-stats">
                            Refresh System Stats
                        </button>
                    </div>
                </div>
            </div>

            <div class="aud-card">
                <div class="aud-card-header">
                    <h3 class="aud-card-title">
                        <span class="aud-card-icon">📚</span>
                        Learning Resources
                    </h3>
                </div>
                <div class="aud-card-body">
                    <p style="margin-bottom: 16px;">Get started with Aevov:</p>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <a href="admin.php?page=aevov-onboarding" class="aud-button aud-button-primary">
                            Start Onboarding
                        </a>
                        <a href="https://aevov.com/docs" class="aud-button aud-button-secondary" target="_blank">
                            Documentation
                        </a>
                        <a href="https://aevov.com/tutorials" class="aud-button aud-button-secondary" target="_blank">
                            Video Tutorials
                        </a>
                    </div>
                </div>
            </div>

            <div class="aud-card">
                <div class="aud-card-header">
                    <h3 class="aud-card-title">
                        <span class="aud-card-icon">🔗</span>
                        Quick Links
                    </h3>
                </div>
                <div class="aud-card-body">
                    <p style="margin-bottom: 16px;">Navigate the ecosystem:</p>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <a href="admin.php?page=aevov-plugin-manager" class="aud-button aud-button-secondary">
                            Plugin Manager
                        </a>
                        <a href="admin.php?page=aevov-pattern-creator" class="aud-button aud-button-secondary">
                            Pattern Creator
                        </a>
                        <a href="admin.php?page=aevov-system-monitor" class="aud-button aud-button-secondary">
                            System Monitor
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
