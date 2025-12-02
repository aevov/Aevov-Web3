/**
 * Aevov Onboarding System JavaScript
 * Handles all interactive functionality for the onboarding process
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Global variables
    window.aevovOnboardingJS = {
        isProcessing: false,
        
        // Initialize the onboarding system
        init: function() {
            this.bindEvents();
            this.checkSystemStatus();
        },
        
        // Bind event handlers
        bindEvents: function() {
            // Handle step card clicks
            $('.step-card').on('click', function() {
                if (!$(this).hasClass('active')) {
                    return;
                }
                
                var stepKey = $(this).data('step');
                if (stepKey) {
                    aevovOnboardingJS.startStep(stepKey);
                }
            });
            
            // Handle button clicks with event delegation
            $(document).on('click', '[onclick*="startStep"]', function(e) {
                e.preventDefault();
                var onclick = $(this).attr('onclick');
                var stepMatch = onclick.match(/startStep\('([^']+)'\)/);
                if (stepMatch) {
                    aevovOnboardingJS.startStep(stepMatch[1]);
                }
            });
            
            $(document).on('click', '[onclick*="completeStep"]', function(e) {
                e.preventDefault();
                var onclick = $(this).attr('onclick');
                var stepMatch = onclick.match(/completeStep\('([^']+)'\)/);
                if (stepMatch) {
                    aevovOnboardingJS.completeStep(stepMatch[1]);
                }
            });
            
            $(document).on('click', '[onclick*="runSystemCheck"]', function(e) {
                e.preventDefault();
                aevovOnboardingJS.runSystemCheck();
            });
            
            $(document).on('click', '[onclick*="activatePlugin"]', function(e) {
                e.preventDefault();
                var onclick = $(this).attr('onclick');
                var pluginMatch = onclick.match(/activatePlugin\('([^']+)'\)/);
                if (pluginMatch) {
                    aevovOnboardingJS.activatePlugin(pluginMatch[1]);
                }
            });
        },
        
        // Start a specific onboarding step
        startStep: function(stepKey) {
            if (this.isProcessing) {
                return;
            }
            
            this.isProcessing = true;
            this.showLoading('Starting step...');
            
            $.post(aevovOnboarding.ajaxUrl, {
                action: 'aevov_onboarding_action',
                step: stepKey,
                action_type: 'start',
                nonce: aevovOnboarding.nonce
            })
            .done(function(response) {
                if (response.success) {
                    aevovOnboardingJS.showSuccess('Step started successfully!');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    aevovOnboardingJS.showError('Failed to start step: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function(xhr, status, error) {
                aevovOnboardingJS.showError('AJAX error: ' + error);
            })
            .always(function() {
                aevovOnboardingJS.isProcessing = false;
            });
        },
        
        // Complete a specific onboarding step
        completeStep: function(stepKey) {
            if (this.isProcessing) {
                return;
            }
            
            this.isProcessing = true;
            this.showLoading('Completing step...');
            
            $.post(aevovOnboarding.ajaxUrl, {
                action: 'aevov_onboarding_action',
                step: stepKey,
                action_type: 'complete',
                nonce: aevovOnboarding.nonce
            })
            .done(function(response) {
                if (response.success) {
                    aevovOnboardingJS.showSuccess('Step completed successfully!');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    aevovOnboardingJS.showError('Failed to complete step: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function(xhr, status, error) {
                aevovOnboardingJS.showError('AJAX error: ' + error);
            })
            .always(function() {
                aevovOnboardingJS.isProcessing = false;
            });
        },
        
        // Run system check
        runSystemCheck: function() {
            if (this.isProcessing) {
                return;
            }
            
            this.isProcessing = true;
            $('#system-status-results, #system-check-results').html('<div class="loading">Checking system...</div>');
            
            $.post(aevovOnboarding.ajaxUrl, {
                action: 'aevov_get_system_status',
                full_check: true,
                nonce: aevovOnboarding.nonce
            })
            .done(function(response) {
                if (response.success) {
                    $('#system-status-results, #system-check-results').html(response.data.html);
                } else {
                    aevovOnboardingJS.showError('System check failed: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function(xhr, status, error) {
                aevovOnboardingJS.showError('AJAX error: ' + error);
            })
            .always(function() {
                aevovOnboardingJS.isProcessing = false;
            });
        },
        
        // Activate a plugin
        activatePlugin: function(pluginFile) {
            if (this.isProcessing) {
                return;
            }
            
            this.isProcessing = true;
            this.showLoading('Activating plugin...');
            
            $.post(aevovOnboarding.ajaxUrl, {
                action: 'aevov_activate_plugin',
                plugin: pluginFile,
                nonce: aevovOnboarding.nonce
            })
            .done(function(response) {
                if (response.success) {
                    aevovOnboardingJS.showSuccess('Plugin activated successfully!');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    aevovOnboardingJS.showError('Failed to activate plugin: ' + (response.data || 'Unknown error'));
                }
            })
            .fail(function(xhr, status, error) {
                aevovOnboardingJS.showError('AJAX error: ' + error);
            })
            .always(function() {
                aevovOnboardingJS.isProcessing = false;
            });
        },
        
        // Check initial system status
        checkSystemStatus: function() {
            // Only run on system status page
            if ($('#system-status-results').length > 0) {
                this.runSystemCheck();
            }
        },
        
        // Show loading message
        showLoading: function(message) {
            this.showNotification(message || aevovOnboarding.i18n.loading, 'loading');
        },
        
        // Show success message
        showSuccess: function(message) {
            this.showNotification(message || aevovOnboarding.i18n.success, 'success');
        },
        
        // Show error message
        showError: function(message) {
            this.showNotification(message || aevovOnboarding.i18n.error, 'error');
        },
        
        // Show notification
        showNotification: function(message, type) {
            // Remove existing notifications
            $('.aevov-notification').remove();
            
            var className = 'aevov-notification aevov-notification-' + type;
            var notification = $('<div class="' + className + '">' + message + '</div>');
            
            // Add to page
            $('.aevov-onboarding-container').prepend(notification);
            
            // Auto-hide success and error messages
            if (type === 'success' || type === 'error') {
                setTimeout(function() {
                    notification.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
    };
    
    // Initialize when document is ready
    window.aevovOnboardingJS.init();
});

// Global functions for backward compatibility with inline onclick handlers
function startStep(stepKey) {
    if (window.aevovOnboardingJS) {
        window.aevovOnboardingJS.startStep(stepKey);
    }
}

function completeStep(stepKey) {
    if (window.aevovOnboardingJS) {
        window.aevovOnboardingJS.completeStep(stepKey);
    }
}

function reviewStep(stepKey) {
    // Load step review content
    jQuery('#step-content').html('<div class="loading">Loading step details...</div>');
}

function runSystemCheck() {
    if (window.aevovOnboardingJS) {
        window.aevovOnboardingJS.runSystemCheck();
    }
}

function runFullSystemCheck() {
    if (window.aevovOnboardingJS) {
        window.aevovOnboardingJS.runSystemCheck();
    }
}

function activatePlugin(pluginFile) {
    if (window.aevovOnboardingJS) {
        window.aevovOnboardingJS.activatePlugin(pluginFile);
    }
}