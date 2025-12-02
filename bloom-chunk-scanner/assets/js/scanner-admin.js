// assets/js/scanner-admin.js
(function($) {
    'use strict';

    const BloomScanner = {
        scanning: false,
        progressInterval: null,

        init: function() {
            this.bindEvents();
            this.updateStats();
        },

        bindEvents: function() {
            $('#start-scan').on('click', () => this.startScan());
            $('#clear-log').on('click', () => this.clearLog());
            $('#export-stats').on('click', () => this.exportStats());
        },

        startScan: function() {
            if (this.scanning) return;

            this.scanning = true;
            $('#start-scan').prop('disabled', true);
            $('#scan-progress').show();
            this.updateProgress(0);
            this.addLogMessage('Starting scan...');

            $.ajax({
                url: bloomScanner.ajax_url,
                type: 'POST',
                data: {
                    action: 'start_chunk_scan',
                    nonce: bloomScanner.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.startProgressPolling();
                    } else {
                        this.handleError('Failed to start scan: ' + response.data);
                    }
                },
                error: () => this.handleError('Failed to start scan')
            });
        },

        startProgressPolling: function() {
            this.progressInterval = setInterval(() => this.pollProgress(), 1000);
        },

        pollProgress: function() {
    console.log('Polling progress with nonce:', bloomScanner.nonce); // Debug log
    $.ajax({
        url: bloomScanner.ajax_url,
        type: 'POST',
        data: {
            action: 'get_scan_progress',
            nonce: bloomScanner.nonce
        },
        success: (response) => {
            console.log('Progress response:', response); // Debug log
            if (response.success) {
                const data = response.data;
                this.updateProgress(data.progress);
                this.updateStats(data);

                if (!data.scanning) {
                    this.completeScan();
                }
            }
        },
        error: (xhr, status, error) => {
            console.error('Progress error:', {
                status: xhr.status,
                error: error,
                response: xhr.responseText
            });
            this.handleError('Failed to get progress');
        }
    });
},

        updateProgress: function(progress) {
            const percent = Math.round(progress);
            $('.progress-bar-fill').css('width', percent + '%');
            $('.progress-text').text(percent + '%');

            // Add detailed progress info
            if (this.scanning) {
                this.addLogMessage(`Processed ${percent}% of files...`, true);
            }
        },

        updateStats: function(data) {
            if (data) {
                $('#total-scanned').text(data.processed);
                $('#chunks-found').text(data.found_chunks);
                
                // Update last scan time
                if (data.last_scan) {
                    $('#last-scan').text(this.formatTimeAgo(data.last_scan));
                }
            }
        },

        completeScan: function() {
            clearInterval(this.progressInterval);
            this.scanning = false;
            $('#start-scan').prop('disabled', false);
            
            setTimeout(() => {
                $('#scan-progress').fadeOut();
                this.addLogMessage('Scan completed successfully');
                this.refreshStats();
            }, 1000);
        },

        handleError: function(error) {
            this.scanning = false;
            clearInterval(this.progressInterval);
            $('#start-scan').prop('disabled', false);
            $('#scan-progress').hide();
            
            this.addLogMessage(`Error: ${error}`, false, 'error');
            
            // Show error notification
            this.showNotification(error, 'error');
        },

        addLogMessage: function(message, update = false, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = `<div class="log-entry ${type}">
                <span class="log-time">[${timestamp}]</span>
                <span class="log-message">${message}</span>
            </div>`;

            if (update) {
                $('.log-entry:last').html(logEntry);
            } else {
                $('#scan-log').prepend(logEntry);
            }

            // Keep only last 100 log entries
            if ($('.log-entry').length > 100) {
                $('.log-entry:last').remove();
            }
        },

        clearLog: function() {
            $('#scan-log').empty();
            this.addLogMessage('Log cleared');
        },

        refreshStats: function() {
            $.ajax({
                url: bloomScanner.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_scan_stats',
                    nonce: bloomScanner.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateStats(response.data);
                    }
                }
            });
        },

        exportStats: function() {
            const stats = {
                total_scanned: $('#total-scanned').text(),
                chunks_found: $('#chunks-found').text(),
                last_scan: $('#last-scan').text(),
                log: []
            };

            // Collect log entries
            $('.log-entry').each(function() {
                stats.log.push({
                    time: $(this).find('.log-time').text(),
                    message: $(this).find('.log-message').text(),
                    type: this.className.replace('log-entry ', '')
                });
            });

            // Create and download file
            const blob = new Blob([JSON.stringify(stats, null, 2)], {type: 'application/json'});
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = 'bloom-scanner-stats.json';
            
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            this.addLogMessage('Stats exported successfully');
        },

        formatTimeAgo: function(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) {
                return 'Just now';
            }
            
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) {
                return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
            }
            
            const hours = Math.floor(minutes / 60);
            if (hours < 24) {
                return `${hours} hour${hours !== 1 ? 's' : ''} ago`;
            }
            
            const days = Math.floor(hours / 24);
            return `${days} day${days !== 1 ? 's' : ''} ago`;
        },

        showNotification: function(message, type = 'info') {
            const notification = $(`<div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
            </div>`);
            
            $('.wrap').first().prepend(notification);
            
            // Make dismissible
            notification.find('button.notice-dismiss').on('click', function() {
                notification.fadeOut(300, function() { $(this).remove(); });
            });
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notification.fadeOut(300, function() { $(this).remove(); });
            }, 5000);
        }
    };
    
    

    // Initialize when document is ready
    $(document).ready(() => BloomScanner.init());

})(jQuery);

