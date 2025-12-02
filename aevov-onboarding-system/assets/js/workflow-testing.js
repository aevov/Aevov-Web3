/**
 * Workflow Testing Dashboard JavaScript
 * Interactive features and AJAX handlers
 *
 * @package AevovOnboarding
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Workflow Testing Dashboard
     */
    const WorkflowTesting = {

        /**
         * Progress polling interval
         */
        progressInterval: null,

        /**
         * Current test execution ID
         */
        currentTestId: null,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.checkRunningTests();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Accordion toggle
            $('.accordion-header').on('click', this.toggleAccordion);

            // Run test buttons
            $('#run-all-tests').on('click', () => this.runTests('all'));
            $('#run-critical-only').on('click', () => this.runTests('critical'));
            $('.run-group-tests').on('click', this.runGroupTests);
            $('.run-category-tests').on('click', this.runCategoryTests);

            // Action buttons
            $('#view-last-results').on('click', this.viewLastResults);
            $('#schedule-tests').on('click', this.showScheduleDialog);
            $('#cancel-tests').on('click', this.cancelTests);
            $('#close-results').on('click', this.closeResults);

            // Results actions
            $('#export-results').on('click', this.exportResults);
            $('#email-results').on('click', this.emailResults);

            // Filter buttons
            $('.filter-btn').on('click', this.filterResults);

            // View details
            $('.view-group-details').on('click', this.viewGroupDetails);
        },

        /**
         * Toggle accordion sections
         */
        toggleAccordion: function(e) {
            e.preventDefault();
            const $header = $(this);
            const $content = $header.next('.accordion-content');
            const $item = $header.parent('.accordion-item');

            // Close other accordions
            $('.accordion-header').not($header).removeClass('active');
            $('.accordion-content').not($content).slideUp(300);

            // Toggle this accordion
            $header.toggleClass('active');
            $content.slideToggle(300);
        },

        /**
         * Run tests
         */
        runTests: function(testType, group = '', category = '') {
            // Confirm before running
            const message = testType === 'all'
                ? 'This will run all 2,655 workflow tests. This may take 45-60 minutes. Continue?'
                : 'This will run the selected tests. Continue?';

            if (!confirm(message)) {
                return;
            }

            // Show progress container
            $('#test-progress-container').slideDown(300);

            // Reset progress
            this.resetProgress();

            // Make AJAX request
            $.ajax({
                url: aevovOnboarding.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aevov_run_workflow_tests',
                    nonce: aevovOnboarding.nonce,
                    test_type: testType,
                    group: group,
                    category: category
                },
                success: (response) => {
                    if (response.success) {
                        // Start polling for progress
                        this.startProgressPolling();
                        this.showNotice('success', 'Tests started successfully!');
                    } else {
                        this.showNotice('error', response.data.message || 'Failed to start tests');
                        $('#test-progress-container').slideUp(300);
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotice('error', 'Error starting tests: ' + error);
                    $('#test-progress-container').slideUp(300);
                }
            });
        },

        /**
         * Run group tests
         */
        runGroupTests: function(e) {
            e.preventDefault();
            const group = $(this).data('group');
            WorkflowTesting.runTests('group', group, '');
        },

        /**
         * Run category tests
         */
        runCategoryTests: function(e) {
            e.preventDefault();
            const group = $(this).data('group');
            const category = $(this).data('category');
            WorkflowTesting.runTests('category', group, category);
        },

        /**
         * Reset progress display
         */
        resetProgress: function() {
            $('#progress-fill').css('width', '0%');
            $('#progress-text').text('0%');
            $('#progress-count').text('0 / 0 tests');
            $('#progress-log').empty();
            $('#progress-title').text('Running Tests...');
        },

        /**
         * Start polling for progress
         */
        startProgressPolling: function() {
            // Clear any existing interval
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
            }

            // Poll every 2 seconds
            this.progressInterval = setInterval(() => {
                this.updateProgress();
            }, 2000);
        },

        /**
         * Update progress
         */
        updateProgress: function() {
            $.ajax({
                url: aevovOnboarding.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aevov_get_test_progress',
                    nonce: aevovOnboarding.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const progress = response.data;

                        // Update progress bar
                        $('#progress-fill').css('width', progress.percentage + '%');
                        $('#progress-text').text(progress.percentage + '%');
                        $('#progress-count').text(
                            progress.completed_tests + ' / ' + progress.total_tests + ' tests'
                        );

                        // Update current test
                        if (progress.current_test) {
                            this.addProgressLog(progress.current_test);
                        }

                        // Check if completed
                        if (progress.status === 'completed') {
                            this.handleTestCompletion(progress);
                        } else if (progress.status === 'cancelled') {
                            this.handleTestCancellation();
                        }
                    }
                },
                error: () => {
                    // Silent fail - will retry on next interval
                }
            });
        },

        /**
         * Add log entry
         */
        addProgressLog: function(message) {
            const $log = $('#progress-log');
            const timestamp = new Date().toLocaleTimeString();
            $log.append(`<div>[${timestamp}] ${message}</div>`);

            // Auto-scroll to bottom
            $log.scrollTop($log[0].scrollHeight);

            // Limit log entries
            const entries = $log.find('div');
            if (entries.length > 100) {
                entries.first().remove();
            }
        },

        /**
         * Handle test completion
         */
        handleTestCompletion: function(progress) {
            // Stop polling
            clearInterval(this.progressInterval);

            // Update UI
            $('#progress-title').text('Tests Completed!');

            // Show success message
            this.showNotice('success',
                `All tests completed! ${progress.passed_tests} passed, ${progress.failed_tests} failed.`
            );

            // Load results
            setTimeout(() => {
                this.viewLastResults();
            }, 2000);
        },

        /**
         * Handle test cancellation
         */
        handleTestCancellation: function() {
            // Stop polling
            clearInterval(this.progressInterval);

            // Update UI
            $('#progress-title').text('Tests Cancelled');
            $('#test-progress-container').slideUp(300);

            this.showNotice('warning', 'Test execution was cancelled.');
        },

        /**
         * Cancel tests
         */
        cancelTests: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to cancel the running tests?')) {
                return;
            }

            $.ajax({
                url: aevovOnboarding.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aevov_cancel_tests',
                    nonce: aevovOnboarding.nonce
                },
                success: (response) => {
                    if (response.success) {
                        WorkflowTesting.handleTestCancellation();
                    }
                }
            });
        },

        /**
         * View last results
         */
        viewLastResults: function(e) {
            if (e) e.preventDefault();

            $.ajax({
                url: aevovOnboarding.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aevov_get_test_results',
                    nonce: aevovOnboarding.nonce
                },
                success: (response) => {
                    if (response.success) {
                        WorkflowTesting.displayResults(response.data);
                        $('#test-results-panel').slideDown(300);
                    } else {
                        WorkflowTesting.showNotice('error',
                            response.data.message || 'No test results available'
                        );
                    }
                },
                error: () => {
                    WorkflowTesting.showNotice('error', 'Error loading test results');
                }
            });
        },

        /**
         * Display results
         */
        displayResults: function(results) {
            // Build summary HTML
            const summaryHtml = `
                <div class="results-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                    <div class="result-stat">
                        <div class="stat-number">${results.total_tests.toLocaleString()}</div>
                        <div class="stat-label">Total Tests</div>
                    </div>
                    <div class="result-stat">
                        <div class="stat-number" style="color: #28a745;">${results.passed.toLocaleString()}</div>
                        <div class="stat-label">Passed</div>
                    </div>
                    <div class="result-stat">
                        <div class="stat-number" style="color: ${results.failed > 0 ? '#dc3545' : '#28a745'};">
                            ${results.failed.toLocaleString()}
                        </div>
                        <div class="stat-label">Failed</div>
                    </div>
                    <div class="result-stat">
                        <div class="stat-number" style="color: ${results.pass_rate === 100 ? '#28a745' : '#ffc107'};">
                            ${results.pass_rate}%
                        </div>
                        <div class="stat-label">Pass Rate</div>
                    </div>
                </div>
                <div style="margin-top: 20px; text-align: center;">
                    <p><strong>Test Date:</strong> ${new Date(results.test_date).toLocaleString()}</p>
                </div>
            `;

            $('#results-summary').html(summaryHtml);

            // Build results list
            let listHtml = '';

            if (results.pass_rate === 100) {
                listHtml = `
                    <div style="text-align: center; padding: 40px; color: #28a745;">
                        <div style="font-size: 64px;">✓</div>
                        <h3>All Tests Passed!</h3>
                        <p>Your Aevov ecosystem is production-ready.</p>
                    </div>
                `;
            } else if (results.bugs && results.bugs.length > 0) {
                listHtml = '<div class="bugs-list">';
                results.bugs.forEach(bug => {
                    listHtml += `
                        <div class="bug-item" style="padding: 15px; margin-bottom: 10px; border-left: 4px solid #dc3545; background: #f8d7da;">
                            <h4 style="margin: 0 0 5px 0; color: #721c24;">${bug.test || 'Unknown Test'}</h4>
                            <p style="margin: 0; color: #721c24;">${bug.message || 'No details available'}</p>
                        </div>
                    `;
                });
                listHtml += '</div>';
            }

            $('#results-list').html(listHtml);
        },

        /**
         * Close results panel
         */
        closeResults: function(e) {
            e.preventDefault();
            $('#test-results-panel').slideUp(300);
        },

        /**
         * Filter results
         */
        filterResults: function(e) {
            e.preventDefault();
            const filter = $(this).data('filter');

            // Update active button
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');

            // Apply filter (implementation depends on results structure)
            // For now, this is a placeholder
            console.log('Filter:', filter);
        },

        /**
         * Export results
         */
        exportResults: function(e) {
            e.preventDefault();

            $.ajax({
                url: aevovOnboarding.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aevov_export_test_results',
                    nonce: aevovOnboarding.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Download file
                        window.location.href = response.data.file_url;
                        WorkflowTesting.showNotice('success', 'Results exported successfully!');
                    } else {
                        WorkflowTesting.showNotice('error',
                            response.data.message || 'Failed to export results'
                        );
                    }
                },
                error: () => {
                    WorkflowTesting.showNotice('error', 'Error exporting results');
                }
            });
        },

        /**
         * Email results
         */
        emailResults: function(e) {
            e.preventDefault();

            const email = prompt('Enter email address:', aevovOnboarding.adminEmail || '');

            if (!email) return;

            $.ajax({
                url: aevovOnboarding.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aevov_email_test_results',
                    nonce: aevovOnboarding.nonce,
                    email: email
                },
                success: (response) => {
                    if (response.success) {
                        WorkflowTesting.showNotice('success', response.data.message);
                    } else {
                        WorkflowTesting.showNotice('error',
                            response.data.message || 'Failed to send email'
                        );
                    }
                },
                error: () => {
                    WorkflowTesting.showNotice('error', 'Error sending email');
                }
            });
        },

        /**
         * Show schedule dialog
         */
        showScheduleDialog: function(e) {
            e.preventDefault();

            const html = `
                <div class="schedule-dialog" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 10000; max-width: 500px;">
                    <h2 style="margin-top: 0;">Schedule Automated Tests</h2>
                    <form id="schedule-form">
                        <p>
                            <label><strong>Frequency:</strong></label><br>
                            <select name="frequency" style="width: 100%; padding: 8px; margin-top: 5px;">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="email_notifications" checked>
                                Send email notifications
                            </label>
                        </p>
                        <p>
                            <label><strong>Email Address:</strong></label><br>
                            <input type="email" name="email_address" value="${aevovOnboarding.adminEmail || ''}" style="width: 100%; padding: 8px; margin-top: 5px;">
                        </p>
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">Schedule Tests</button>
                            <button type="button" class="btn btn-secondary close-dialog" style="flex: 1;">Cancel</button>
                        </div>
                    </form>
                </div>
                <div class="schedule-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999;"></div>
            `;

            $('body').append(html);

            // Bind events
            $('.close-dialog, .schedule-overlay').on('click', function() {
                $('.schedule-dialog, .schedule-overlay').remove();
            });

            $('#schedule-form').on('submit', function(e) {
                e.preventDefault();
                WorkflowTesting.scheduleTests($(this).serialize());
            });
        },

        /**
         * Schedule tests
         */
        scheduleTests: function(formData) {
            $.ajax({
                url: aevovOnboarding.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aevov_schedule_tests',
                    nonce: aevovOnboarding.nonce,
                    ...Object.fromEntries(new URLSearchParams(formData))
                },
                success: (response) => {
                    $('.schedule-dialog, .schedule-overlay').remove();

                    if (response.success) {
                        WorkflowTesting.showNotice('success', response.data.message);
                    } else {
                        WorkflowTesting.showNotice('error',
                            response.data.message || 'Failed to schedule tests'
                        );
                    }
                },
                error: () => {
                    $('.schedule-dialog, .schedule-overlay').remove();
                    WorkflowTesting.showNotice('error', 'Error scheduling tests');
                }
            });
        },

        /**
         * View group details
         */
        viewGroupDetails: function(e) {
            e.preventDefault();
            const group = $(this).data('group');

            // Scroll to group
            const $item = $(`.accordion-item[data-group="${group}"]`);

            // Open accordion
            $item.find('.accordion-header').trigger('click');

            // Scroll into view
            $('html, body').animate({
                scrollTop: $item.offset().top - 100
            }, 500);
        },

        /**
         * Check for running tests on page load
         */
        checkRunningTests: function() {
            $.ajax({
                url: aevovOnboarding.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aevov_get_test_progress',
                    nonce: aevovOnboarding.nonce
                },
                success: (response) => {
                    if (response.success && response.data.status === 'running') {
                        $('#test-progress-container').show();
                        this.startProgressPolling();
                    }
                }
            });
        },

        /**
         * Show notice
         */
        showNotice: function(type, message) {
            const noticeClass = `notice-${type}`;
            const $notice = $(`
                <div class="notice ${noticeClass}" style="position: fixed; top: 50px; right: 20px; z-index: 10000; max-width: 400px; animation: slideInRight 0.3s ease;">
                    <p><strong>${message}</strong></p>
                </div>
            `);

            $('body').append($notice);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // Add click to dismiss
            $notice.on('click', function() {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        WorkflowTesting.init();
    });

})(jQuery);
