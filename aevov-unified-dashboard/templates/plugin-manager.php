<?php
/**
 * Plugin Manager Template
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
                    <span class="aud-header-title-icon">🔌</span>
                    Plugin Manager
                </h1>
                <p class="aud-header-subtitle">
                    Manage all 29 Aevov ecosystem plugins from one place
                </p>
            </div>
        </div>
    </div>

    <div class="aud-card" style="margin-bottom: 24px;">
        <div class="aud-card-body">
            <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                <input
                    type="text"
                    class="aud-form-input aud-plugin-search"
                    placeholder="🔍 Search plugins by name, description, or feature..."
                    style="flex: 1; min-width: 300px;"
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
                    ⚡ Activate All (29)
                </button>
                <button class="aud-button aud-button-secondary aud-refresh-plugins">
                    🔄 Refresh
                </button>
            </div>
        </div>
    </div>

    <h2 style="margin-bottom: 20px; color: var(--aud-gray-900); font-size: 1.75rem; font-weight: 600;">
        🌟 Core Plugins (3)
    </h2>
    <div class="aud-grid aud-grid-3 aud-core-plugins">
        <div class="aud-skeleton" style="height: 200px;"></div>
        <div class="aud-skeleton" style="height: 200px;"></div>
        <div class="aud-skeleton" style="height: 200px;"></div>
    </div>

    <h2 style="margin-top: 40px; margin-bottom: 20px; color: var(--aud-gray-900); font-size: 1.75rem; font-weight: 600;">
        🚀 Sister Plugins (26)
    </h2>
    <div class="aud-grid aud-grid-4 aud-sister-plugins">
        <?php for ($i = 0; $i < 26; $i++) : ?>
            <div class="aud-skeleton" style="height: 200px;"></div>
        <?php endfor; ?>
    </div>
</div>
