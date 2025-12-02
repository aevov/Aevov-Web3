/**
 * Aevov AI Core Admin JavaScript
 *
 * @package AevovAICore
 */

(function($) {
    'use strict';

    /**
     * Initialize admin functionality
     */
    $(document).ready(function() {
        // Initialize tooltips
        initTooltips();

        // Initialize charts if on dashboard
        if ($('.aevov-ai-dashboard').length) {
            initDashboard();
        }

        // Initialize provider testing
        if ($('.aevov-ai-providers').length) {
            initProviders();
        }

        // Initialize model management
        if ($('.aevov-ai-models').length) {
            initModels();
        }

        // Initialize debug dashboard
        if ($('.aevov-ai-debug').length) {
            initDebug();
        }
    });

    /**
     * Initialize tooltips
     */
    function initTooltips() {
        $('[data-tooltip]').each(function() {
            $(this).attr('title', $(this).data('tooltip'));
        });
    }

    /**
     * Initialize dashboard
     */
    function initDashboard() {
        // Real-time statistics update
        setInterval(function() {
            updateDashboardStats();
        }, 30000); // Every 30 seconds
    }

    /**
     * Update dashboard statistics
     */
    function updateDashboardStats() {
        $.get(ajaxurl, {
            action: 'aevov_get_dashboard_stats',
            nonce: aevovAdmin.nonce
        }, function(response) {
            if (response.success && response.data) {
                // Update stats
                updateStatValue('total-requests', response.data.total_requests);
                updateStatValue('total-cost', '$' + response.data.total_cost.toFixed(4));
            }
        });
    }

    /**
     * Update stat value
     */
    function updateStatValue(statId, value) {
        var element = $('#' + statId);
        if (element.length) {
            var currentValue = element.text();
            if (currentValue !== value) {
                element.fadeOut(200, function() {
                    $(this).text(value).fadeIn(200);
                });
            }
        }
    }

    /**
     * Initialize providers page
     */
    function initProviders() {
        // Test individual provider
        $('.aevov-test-provider').on('click', function(e) {
            e.preventDefault();
            testProvider($(this));
        });

        // Test all providers
        $('#aevov-test-all-providers').on('click', function(e) {
            e.preventDefault();
            testAllProviders();
        });

        // Toggle API key visibility
        $('.aevov-toggle-api-key').on('click', function(e) {
            e.preventDefault();
            var input = $(this).siblings('input');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                $(this).text('Hide');
            } else {
                input.attr('type', 'password');
                $(this).text('Show');
            }
        });
    }

    /**
     * Test provider connection
     */
    function testProvider(button) {
        var provider = button.data('provider');
        var originalText = button.text();

        button.prop('disabled', true).text('Testing...');

        $.post(ajaxurl, {
            action: 'aevov_test_provider',
            provider: provider,
            nonce: aevovAdmin.nonce
        }, function(response) {
            if (response.success) {
                showNotice('success', provider + ' connection successful!');
            } else {
                showNotice('error', provider + ' test failed: ' + response.data.message);
            }
        }).fail(function() {
            showNotice('error', 'Test request failed');
        }).always(function() {
            button.prop('disabled', false).text(originalText);
        });
    }

    /**
     * Test all providers
     */
    function testAllProviders() {
        var buttons = $('.aevov-test-provider');
        var index = 0;

        function testNext() {
            if (index < buttons.length) {
                testProvider($(buttons[index]));
                index++;
                setTimeout(testNext, 1000);
            }
        }

        testNext();
    }

    /**
     * Initialize models page
     */
    function initModels() {
        // View model details
        $('.aevov-view-model').on('click', function(e) {
            e.preventDefault();
            viewModel($(this).data('model-id'));
        });

        // Clone model
        $('.aevov-clone-model').on('click', function(e) {
            e.preventDefault();
            cloneModel($(this).data('model-id'));
        });

        // Delete model
        $('.aevov-delete-model').on('click', function(e) {
            e.preventDefault();
            deleteModel($(this).data('model-id'), $(this).closest('tr'));
        });
    }

    /**
     * View model details
     */
    function viewModel(modelId) {
        $.get(ajaxurl, {
            action: 'aevov_get_model',
            model_id: modelId,
            nonce: aevovAdmin.nonce
        }, function(response) {
            if (response.success) {
                showModelModal(response.data);
            }
        });
    }

    /**
     * Show model details modal
     */
    function showModelModal(modelData) {
        var modal = $('<div class="aevov-modal">').html(
            '<div class="aevov-modal-content">' +
            '<span class="aevov-modal-close">&times;</span>' +
            '<h2>' + modelData.name + '</h2>' +
            '<p><strong>Version:</strong> ' + modelData.version + '</p>' +
            '<p><strong>Provider:</strong> ' + modelData.base_provider + '</p>' +
            '<p><strong>Base Model:</strong> ' + modelData.base_model + '</p>' +
            '<p><strong>Training Examples:</strong> ' + modelData.training_count + '</p>' +
            '<p><strong>System Prompt:</strong></p>' +
            '<pre>' + modelData.system_prompt + '</pre>' +
            '</div>'
        );

        $('body').append(modal);
        modal.show();

        modal.find('.aevov-modal-close').on('click', function() {
            modal.remove();
        });
    }

    /**
     * Clone model
     */
    function cloneModel(modelId) {
        var newName = prompt('Enter name for cloned model:');
        if (!newName) return;

        $.post(ajaxurl, {
            action: 'aevov_clone_model',
            model_id: modelId,
            new_name: newName,
            nonce: aevovAdmin.nonce
        }, function(response) {
            if (response.success) {
                showNotice('success', 'Model cloned successfully!');
                location.reload();
            } else {
                showNotice('error', 'Clone failed: ' + response.data.message);
            }
        });
    }

    /**
     * Delete model
     */
    function deleteModel(modelId, row) {
        if (!confirm('Delete this model? This cannot be undone.')) {
            return;
        }

        $.post(ajaxurl, {
            action: 'aevov_delete_model',
            model_id: modelId,
            nonce: aevovAdmin.nonce
        }, function(response) {
            if (response.success) {
                row.fadeOut(400, function() {
                    $(this).remove();
                });
                showNotice('success', 'Model deleted');
            } else {
                showNotice('error', 'Delete failed: ' + response.data.message);
            }
        });
    }

    /**
     * Initialize debug dashboard
     */
    function initDebug() {
        // Auto-refresh logs
        var autoRefresh = true;
        var refreshInterval;

        function startAutoRefresh() {
            refreshInterval = setInterval(function() {
                if (autoRefresh) {
                    refreshLogs();
                }
            }, 5000);
        }

        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        }

        $('#aevov-toggle-auto-refresh').on('change', function() {
            autoRefresh = $(this).is(':checked');
            if (autoRefresh) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });

        startAutoRefresh();

        // Manual refresh
        $('#aevov-refresh-logs').on('click', function() {
            refreshLogs();
        });

        // Filter changes
        $('#aevov-log-level, #aevov-log-component').on('change', function() {
            refreshLogs();
        });
    }

    /**
     * Refresh logs
     */
    function refreshLogs() {
        var level = $('#aevov-log-level').val();
        var component = $('#aevov-log-component').val();

        $.get(ajaxurl, {
            action: 'aevov_get_logs',
            level: level,
            component: component,
            limit: 50,
            nonce: aevovAdmin.nonce
        }, function(response) {
            if (response.success && response.data.logs) {
                updateLogsTable(response.data.logs);
            }
        });
    }

    /**
     * Update logs table
     */
    function updateLogsTable(logs) {
        var tbody = $('.aevov-logs-table tbody');
        tbody.empty();

        logs.forEach(function(log) {
            var row = $('<tr>').addClass('aevov-log-' + log.level);
            row.append($('<td>').text(formatTime(log.created_at)));
            row.append($('<td>').html('<span class="aevov-log-badge">' + log.level.toUpperCase() + '</span>'));
            row.append($('<td>').text(log.component));

            var messageCell = $('<td>').text(log.message);
            if (log.context) {
                var viewButton = $('<button>')
                    .addClass('button button-small aevov-view-context')
                    .text('View Context')
                    .data('context', log.context);
                messageCell.append(' ').append(viewButton);
            }
            row.append(messageCell);

            tbody.append(row);
        });
    }

    /**
     * Format time
     */
    function formatTime(timestamp) {
        var date = new Date(timestamp);
        return date.toLocaleTimeString();
    }

    /**
     * Show notice
     */
    function showNotice(type, message) {
        var notice = $('<div>')
            .addClass('notice notice-' + type + ' is-dismissible')
            .html('<p>' + message + '</p>');

        $('.wrap h1').after(notice);

        setTimeout(function() {
            notice.fadeOut(400, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Export functions for global use
    window.AevovAdmin = {
        showNotice: showNotice,
        testProvider: testProvider,
        refreshLogs: refreshLogs
    };

})(jQuery);
