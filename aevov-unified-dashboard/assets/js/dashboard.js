/**
 * Aevov Unified Dashboard - Interactive JavaScript
 * Handles all dashboard functionality, API calls, and real-time updates
 */

(function($) {
    'use strict';

    /**
     * Main Dashboard Controller
     */
    const AevovDashboard = {
        // State management
        state: {
            plugins: {},
            stats: {},
            currentTab: 'overview',
            onboarding: {
                complete: false,
                currentStep: 'welcome',
                steps: []
            }
        },

        /**
         * Initialize dashboard
         */
        init: function() {
            console.log('🚀 Initializing Aevov Unified Dashboard...');

            // Load initial data from localized script
            if (typeof audData !== 'undefined') {
                this.state.plugins = audData.plugins || {};
                this.state.onboarding.complete = audData.isOnboardingComplete || false;
                this.state.onboarding.currentStep = audData.currentStep || 'welcome';
                this.state.onboarding.steps = audData.onboardingSteps || {};
            }

            // Initialize components
            this.bindEvents();
            this.loadDashboardStats();
            this.loadPluginStatus();
            this.initializeOnboarding();
            this.startAutoRefresh();

            console.log('✅ Dashboard initialized successfully!');
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            const self = this;

            // Tab navigation
            $(document).on('click', '.aud-nav-tab', function(e) {
                e.preventDefault();
                const tab = $(this).data('tab');
                self.switchTab(tab);
            });

            // Plugin activation
            $(document).on('click', '.aud-activate-plugin', function(e) {
                e.preventDefault();
                const plugin = $(this).data('plugin');
                self.activatePlugin(plugin);
            });

            // Pattern creation
            $(document).on('submit', '#aud-pattern-form', function(e) {
                e.preventDefault();
                self.createPattern();
            });

            // Onboarding navigation
            $(document).on('click', '.aud-onboarding-next', function(e) {
                e.preventDefault();
                self.nextOnboardingStep();
            });

            $(document).on('click', '.aud-onboarding-prev', function(e) {
                e.preventDefault();
                self.prevOnboardingStep();
            });

            $(document).on('click', '.aud-onboarding-skip', function(e) {
                e.preventDefault();
                self.skipOnboarding();
            });

            // Refresh buttons
            $(document).on('click', '.aud-refresh-stats', function(e) {
                e.preventDefault();
                self.loadDashboardStats();
            });

            $(document).on('click', '.aud-refresh-plugins', function(e) {
                e.preventDefault();
                self.loadPluginStatus();
            });

            // Bulk actions
            $(document).on('click', '.aud-activate-all', function(e) {
                e.preventDefault();
                self.activateAllPlugins();
            });

            // Search and filter
            $(document).on('input', '.aud-plugin-search', function() {
                self.filterPlugins($(this).val());
            });

            $(document).on('change', '.aud-plugin-category-filter', function() {
                self.filterPluginsByCategory($(this).val());
            });
        },

        /**
         * Switch between tabs
         */
        switchTab: function(tab) {
            console.log(`📑 Switching to tab: ${tab}`);

            this.state.currentTab = tab;

            // Update nav
            $('.aud-nav-tab').removeClass('active');
            $(`.aud-nav-tab[data-tab="${tab}"]`).addClass('active');

            // Update content
            $('.aud-tab-content').hide();
            $(`#aud-${tab}-tab`).fadeIn(300);

            // Load tab-specific data
            if (tab === 'plugins') {
                this.loadPluginStatus();
            } else if (tab === 'monitor') {
                this.loadDashboardStats();
            }
        },

        /**
         * Load dashboard statistics
         */
        loadDashboardStats: function() {
            console.log('📊 Loading dashboard statistics...');

            const self = this;
            const $container = $('.aud-stats-container');

            // Show loading state
            $container.html('<div class="aud-loading"></div>');

            $.ajax({
                url: audData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aud_get_dashboard_stats',
                    nonce: audData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Stats loaded:', response.data);
                        self.state.stats = response.data;
                        self.renderDashboardStats(response.data);
                    } else {
                        self.showError('Failed to load statistics');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Error loading stats:', error);
                    self.showError('Network error loading statistics');
                }
            });
        },

        /**
         * Render dashboard statistics
         */
        renderDashboardStats: function(stats) {
            const html = `
                <div class="aud-monitor-grid">
                    <div class="aud-metric">
                        <div class="aud-metric-label">Active Plugins</div>
                        <div class="aud-metric-value">${stats.plugins.active}/<span style="font-size: 1.5rem;">${stats.plugins.total}</span></div>
                        <div class="aud-metric-trend">
                            ${Math.round((stats.plugins.active / stats.plugins.total) * 100)}% of ecosystem
                        </div>
                    </div>
                    <div class="aud-metric">
                        <div class="aud-metric-label">Total Patterns</div>
                        <div class="aud-metric-value">${stats.patterns.total}</div>
                        <div class="aud-metric-trend">${stats.patterns.synced} synced</div>
                    </div>
                    <div class="aud-metric">
                        <div class="aud-metric-label">Memory Usage</div>
                        <div class="aud-metric-value">${this.formatBytes(stats.system.memory_usage)}</div>
                        <div class="aud-metric-trend">Peak: ${this.formatBytes(stats.system.peak_memory)}</div>
                    </div>
                    <div class="aud-metric">
                        <div class="aud-metric-label">PHP Version</div>
                        <div class="aud-metric-value">${stats.system.php_version.substring(0, 3)}</div>
                        <div class="aud-metric-trend">WP ${stats.system.wp_version}</div>
                    </div>
                </div>
            `;

            $('.aud-stats-container').html(html);
        },

        /**
         * Load plugin status
         */
        loadPluginStatus: function() {
            console.log('🔌 Loading plugin status...');

            const self = this;

            $.ajax({
                url: audData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aud_get_plugin_status',
                    nonce: audData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Plugin status loaded');
                        self.state.plugins = response.data;
                        self.renderPlugins(response.data);
                    } else {
                        self.showError('Failed to load plugin status');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Error loading plugin status:', error);
                    self.showError('Network error loading plugins');
                }
            });
        },

        /**
         * Render plugins
         */
        renderPlugins: function(pluginData) {
            // Render core plugins
            let coreHtml = '';
            $.each(pluginData.core, function(slug, plugin) {
                coreHtml += this.renderPluginCard(slug, plugin, true);
            }.bind(this));
            $('.aud-core-plugins').html(coreHtml);

            // Render sister plugins
            let sisterHtml = '';
            $.each(pluginData.sister, function(slug, plugin) {
                sisterHtml += this.renderPluginCard(slug, plugin, false);
            }.bind(this));
            $('.aud-sister-plugins').html(sisterHtml);

            // Update summary
            const summary = pluginData.summary;
            $('.aud-summary-stats').html(`
                <div class="aud-stat">
                    <p class="aud-stat-value">${summary.total}</p>
                    <p class="aud-stat-label">Total Plugins</p>
                </div>
                <div class="aud-stat">
                    <p class="aud-stat-value" style="color: var(--aud-success);">${summary.active}</p>
                    <p class="aud-stat-label">Active</p>
                </div>
                <div class="aud-stat">
                    <p class="aud-stat-value" style="color: var(--aud-gray-500);">${summary.inactive}</p>
                    <p class="aud-stat-label">Inactive</p>
                </div>
            `);
        },

        /**
         * Render individual plugin card
         */
        renderPluginCard: function(slug, plugin, isCore) {
            const statusClass = plugin.active ? 'active' : 'inactive';
            const statusText = plugin.active ? 'Active' : 'Inactive';
            const actionButton = plugin.active
                ? ''
                : `<button class="aud-button aud-button-success aud-button-small aud-activate-plugin" data-plugin="${plugin.file}">
                    Activate
                </button>`;

            let featuresHtml = '';
            if (plugin.features && plugin.features.length > 0) {
                featuresHtml = '<div class="aud-plugin-features">';
                plugin.features.forEach(feature => {
                    featuresHtml += `<span class="aud-feature-tag">${feature}</span>`;
                });
                featuresHtml += '</div>';
            }

            return `
                <div class="aud-card aud-plugin-card ${statusClass}" data-slug="${slug}">
                    <div class="aud-card-header">
                        <h3 class="aud-card-title">
                            <span class="aud-card-icon">${plugin.icon}</span>
                            ${plugin.name}
                        </h3>
                        <span class="aud-plugin-status ${statusClass}">
                            <span class="aud-plugin-status-dot"></span>
                            ${statusText}
                        </span>
                    </div>
                    <div class="aud-card-body">
                        ${plugin.category ? `<div class="aud-plugin-category">${plugin.category}</div>` : ''}
                        <p class="aud-plugin-description">${plugin.description}</p>
                        ${featuresHtml}
                    </div>
                    ${actionButton ? `<div class="aud-card-footer">${actionButton}</div>` : ''}
                </div>
            `;
        },

        /**
         * Activate a plugin
         */
        activatePlugin: function(pluginFile) {
            console.log(`🔌 Activating plugin: ${pluginFile}`);

            const self = this;
            const $button = $(`.aud-activate-plugin[data-plugin="${pluginFile}"]`);

            $button.prop('disabled', true).html('<span class="aud-loading"></span> Activating...');

            $.ajax({
                url: audData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aud_activate_plugin',
                    nonce: audData.nonce,
                    plugin: pluginFile
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Plugin activated successfully');
                        self.showSuccess('Plugin activated successfully!');
                        self.loadPluginStatus();
                        self.loadDashboardStats();
                    } else {
                        console.error('❌ Activation failed:', response.data);
                        self.showError(response.data);
                        $button.prop('disabled', false).html('Activate');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Network error:', error);
                    self.showError('Network error during activation');
                    $button.prop('disabled', false).html('Activate');
                }
            });
        },

        /**
         * Activate all plugins
         */
        activateAllPlugins: function() {
            if (!confirm('Activate all 29 plugins at once? This may take a moment.')) {
                return;
            }

            console.log('🚀 Activating all plugins...');

            const self = this;
            const plugins = [];

            // Collect all inactive plugins
            $.each(this.state.plugins.core, function(slug, plugin) {
                if (!plugin.active) plugins.push(plugin.file);
            });
            $.each(this.state.plugins.sister, function(slug, plugin) {
                if (!plugin.active) plugins.push(plugin.file);
            });

            if (plugins.length === 0) {
                this.showInfo('All plugins are already active!');
                return;
            }

            this.showInfo(`Activating ${plugins.length} plugins...`);

            // Activate plugins sequentially
            let activated = 0;
            function activateNext() {
                if (activated >= plugins.length) {
                    self.showSuccess(`Successfully activated ${activated} plugins!`);
                    self.loadPluginStatus();
                    self.loadDashboardStats();
                    return;
                }

                const plugin = plugins[activated];
                $.ajax({
                    url: audData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'aud_activate_plugin',
                        nonce: audData.nonce,
                        plugin: plugin
                    },
                    success: function() {
                        activated++;
                        activateNext();
                    },
                    error: function() {
                        console.warn(`Failed to activate: ${plugin}`);
                        activated++;
                        activateNext();
                    }
                });
            }

            activateNext();
        },

        /**
         * Create a pattern
         */
        createPattern: function() {
            console.log('✨ Creating pattern...');

            const self = this;
            const formData = {
                name: $('#aud-pattern-name').val(),
                description: $('#aud-pattern-description').val(),
                data: $('#aud-pattern-data').val()
            };

            if (!formData.name || !formData.data) {
                this.showError('Pattern name and data are required');
                return;
            }

            const $button = $('.aud-create-pattern-btn');
            $button.prop('disabled', true).html('<span class="aud-loading"></span> Creating...');

            $.ajax({
                url: audData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aud_create_pattern',
                    nonce: audData.nonce,
                    name: formData.name,
                    description: formData.description,
                    data: formData.data
                },
                success: function(response) {
                    if (response.success) {
                        console.log('✅ Pattern created:', response.data);
                        self.showSuccess('Pattern created successfully!');
                        $('#aud-pattern-form')[0].reset();
                        self.loadDashboardStats();
                    } else {
                        self.showError(response.data);
                    }
                    $button.prop('disabled', false).html('Create Pattern');
                },
                error: function(xhr, status, error) {
                    console.error('❌ Error creating pattern:', error);
                    self.showError('Network error creating pattern');
                    $button.prop('disabled', false).html('Create Pattern');
                }
            });
        },

        /**
         * Initialize onboarding
         */
        initializeOnboarding: function() {
            if (this.state.onboarding.complete) {
                console.log('✅ Onboarding already completed');
                return;
            }

            console.log('👋 Initializing onboarding...');

            // Show onboarding if not completed
            if ($('.aud-onboarding-page').length > 0) {
                this.renderOnboardingStep(this.state.onboarding.currentStep);
            }
        },

        /**
         * Render onboarding step
         */
        renderOnboardingStep: function(stepKey) {
            const step = this.state.onboarding.steps[stepKey];
            if (!step) return;

            console.log(`📍 Onboarding step: ${stepKey}`);

            const stepKeys = Object.keys(this.state.onboarding.steps);
            const currentIndex = stepKeys.indexOf(stepKey);
            const isFirst = currentIndex === 0;
            const isLast = currentIndex === stepKeys.length - 1;

            $('.aud-onboarding-content').html(`
                <span class="aud-onboarding-icon">${step.icon}</span>
                <h1 class="aud-onboarding-title">${step.title}</h1>
                <h2 class="aud-onboarding-subtitle">${step.subtitle}</h2>
                <p class="aud-onboarding-description">${step.description}</p>
                <p class="aud-onboarding-duration">⏱️ Estimated time: ${step.duration}</p>
                <div class="aud-onboarding-actions">
                    ${!isFirst ? '<button class="aud-button aud-button-secondary aud-onboarding-prev">← Previous</button>' : ''}
                    <button class="aud-button aud-button-secondary aud-onboarding-skip">Skip Tour</button>
                    <button class="aud-button aud-button-primary aud-button-large aud-onboarding-next">
                        ${isLast ? 'Finish →' : 'Next →'}
                    </button>
                </div>
            `);

            // Update progress indicators
            $('.aud-onboarding-step').removeClass('active completed');
            $('.aud-onboarding-step').each(function(index) {
                if (index < currentIndex) {
                    $(this).addClass('completed');
                } else if (index === currentIndex) {
                    $(this).addClass('active');
                }
            });
        },

        /**
         * Next onboarding step
         */
        nextOnboardingStep: function() {
            const stepKeys = Object.keys(this.state.onboarding.steps);
            const currentIndex = stepKeys.indexOf(this.state.onboarding.currentStep);
            const nextStep = stepKeys[currentIndex + 1];

            if (nextStep) {
                this.completeOnboardingStep(nextStep);
            } else {
                this.completeOnboarding();
            }
        },

        /**
         * Previous onboarding step
         */
        prevOnboardingStep: function() {
            const stepKeys = Object.keys(this.state.onboarding.steps);
            const currentIndex = stepKeys.indexOf(this.state.onboarding.currentStep);
            const prevStep = stepKeys[currentIndex - 1];

            if (prevStep) {
                this.completeOnboardingStep(prevStep);
            }
        },

        /**
         * Complete onboarding step
         */
        completeOnboardingStep: function(nextStep) {
            const self = this;

            $.ajax({
                url: audData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aud_complete_onboarding_step',
                    nonce: audData.nonce,
                    step: nextStep
                },
                success: function(response) {
                    if (response.success) {
                        self.state.onboarding.currentStep = nextStep;
                        self.renderOnboardingStep(nextStep);
                    }
                }
            });
        },

        /**
         * Complete onboarding
         */
        completeOnboarding: function() {
            console.log('🎉 Onboarding completed!');

            this.completeOnboardingStep('completion');
            this.state.onboarding.complete = true;

            setTimeout(() => {
                window.location.href = 'admin.php?page=aevov-unified-dashboard';
            }, 2000);
        },

        /**
         * Skip onboarding
         */
        skipOnboarding: function() {
            if (!confirm('Skip the onboarding tour? You can restart it later from the Onboarding menu.')) {
                return;
            }

            this.completeOnboarding();
        },

        /**
         * Filter plugins
         */
        filterPlugins: function(searchTerm) {
            const term = searchTerm.toLowerCase();

            $('.aud-plugin-card').each(function() {
                const $card = $(this);
                const text = $card.text().toLowerCase();

                if (text.includes(term)) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
        },

        /**
         * Filter plugins by category
         */
        filterPluginsByCategory: function(category) {
            if (!category || category === 'all') {
                $('.aud-plugin-card').show();
                return;
            }

            $('.aud-plugin-card').each(function() {
                const $card = $(this);
                const cardCategory = $card.find('.aud-plugin-category').text();

                if (cardCategory === category) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
        },

        /**
         * Start auto-refresh
         */
        startAutoRefresh: function() {
            const self = this;

            // Refresh stats every 30 seconds
            setInterval(function() {
                if (self.state.currentTab === 'monitor' || self.state.currentTab === 'overview') {
                    self.loadDashboardStats();
                }
            }, 30000);
        },

        /**
         * Utility Functions
         */
        formatBytes: function(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },

        showSuccess: function(message) {
            this.showNotification(message, 'success');
        },

        showError: function(message) {
            this.showNotification(message, 'error');
        },

        showInfo: function(message) {
            this.showNotification(message, 'info');
        },

        showWarning: function(message) {
            this.showNotification(message, 'warning');
        },

        showNotification: function(message, type = 'info') {
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };

            const notification = $(`
                <div class="aud-alert aud-alert-${type}" style="position: fixed; top: 20px; right: 20px; z-index: 10000; min-width: 300px; box-shadow: var(--aud-shadow-xl);">
                    <span style="font-size: 1.25rem;">${icons[type]}</span>
                    <span>${message}</span>
                </div>
            `);

            $('body').append(notification);

            setTimeout(() => {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('.aud-dashboard-wrap').length > 0 || $('.aud-onboarding-page').length > 0) {
            AevovDashboard.init();
        }
    });

    // Make available globally
    window.AevovDashboard = AevovDashboard;

})(jQuery);
