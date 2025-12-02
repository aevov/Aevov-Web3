(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize system status
        initSystemStatus();

        // Initialize network status
        initNetworkStatus();

        // Initialize processing status
        initProcessingStatus();

        // Load pattern list
        loadPatterns();

        // Handle pattern actions
        $('#pattern-list').on('click', '.view-pattern', function() {
            var patternId = $(this).data('pattern-id');
            viewPattern(patternId);
        });

        $('#pattern-list').on('click', '.analyze-pattern', function() {
            var patternId = $(this).data('pattern-id');
            analyzePattern(patternId);
        });

        // Load system metrics
        loadSystemMetrics();

        // Load network metrics
        loadNetworkMetrics();

        // Load processing metrics
        loadProcessingMetrics();
    });

    function initSystemStatus() {
        $.ajax({
            url: apsAdmin.restUrl + '/status/system',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', apsAdmin.nonce);
            },
            success: function(response) {
                $('#system-status').html(response.status);
            },
            error: function(xhr, status, error) {
                console.error('Error fetching system status:', error);
            }
        });
    }

    function initNetworkStatus() {
        $.ajax({
            url: apsAdmin.restUrl + '/status/network',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', apsAdmin.nonce);
            },
            success: function(response) {
                $('#network-status').html(response.status);
            },
            error: function(xhr, status, error) {
                console.error('Error fetching network status:', error);
            }
        });
    }

    function initProcessingStatus() {
        $.ajax({
            url: apsAdmin.restUrl + '/status/processing',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', apsAdmin.nonce);
            },
            success: function(response) {
                $('#processing-status').html(response.status);
            },
            error: function(xhr, status, error) {
                console.error('Error fetching processing status:', error);
            }
        });
    }

    function loadPatterns() {
        $.ajax({
            url: apsAdmin.restUrl + '/patterns',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', apsAdmin.nonce);
            },
            success: function(response) {
                var $patternList = $('#pattern-list');
                $patternList.empty();

                $.each(response, function(index, pattern) {
                    var $row = $('<tr>');
                    $row.append($('<td>').text(pattern.id));
                    $row.append($('<td>').text(pattern.type));
                    $row.append($('<td>').text(pattern.confidence.toFixed(2)));
                    $row.append($('<td>').text(new Date(pattern.created_at).toLocaleString()));
                    $row.append($('<td>').html(
                        '<button class="button view-pattern" data-pattern-id="' + pattern.id + '">' +
                        apsAdmin.i18n.view +
                        '</button> ' +
                        '<button class="button analyze-pattern" data-pattern-id="' + pattern.id + '">' +
                        apsAdmin.i18n.analyze +
                        '</button>'
                    ));
                    $patternList.append($row);
                });
            },
            error: function(xhr, status, error) {
                console.error('Error loading patterns:', error);
            }
        });
    }

    function viewPattern(patternId) {
        window.location.href = apsAdmin.adminUrl + '?page=aps-patterns&action=view&id=' + patternId;
    }

    function analyzePattern(patternId) {
        if (!confirm(apsAdmin.i18n.confirmAnalyze)) {
            return;
        }

        $.ajax({
            url: apsAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'aps_analyze_pattern',
                nonce: apsAdmin.nonce,
                pattern_id: patternId
            },
            success: function(response) {
                alert(response.message);
            },
            error: function(xhr, status, error) {
                console.error('Error analyzing pattern:', error);
                alert(apsAdmin.i18n.analyzeError);
            }
        });
    }

    function loadSystemMetrics() {
        $.ajax({
            url: apsAdmin.restUrl + '/metrics/system',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', apsAdmin.nonce);
            },
            success: function(response) {
                var $container = $('#system-metrics');
                $container.empty();

                $.each(response, function(metric, value) {
                    var $metric = $('<div>').addClass('metric');
                    $metric.append($('<span>').addClass('metric-label').text(metric));
                    $metric.append($('<span>').addClass('metric-value').text(value));
                    $container.append($metric);
                });
            },
            error: function(xhr, status, error) {
                console.error('Error loading system metrics:', error);
            }
        });
    }

    function loadNetworkMetrics() {
        $.ajax({
            url: apsAdmin.restUrl + '/metrics/network',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', apsAdmin.nonce);
            },
            success: function(response) {
                var $container = $('#network-metrics');
                $container.empty();

                $.each(response, function(metric, value) {
                    var $metric = $('<div>').addClass('metric');
                    $metric.append($('<span>').addClass('metric-label').text(metric));
                    $metric.append($('<span>').addClass('metric-value').text(value));
                    $container.append($metric);
                });
            },
            error: function(xhr, status, error) {
                console.error('Error loading network metrics:', error);
            }
        });
    }

    function loadProcessingMetrics() {
        $.ajax({
            url: apsAdmin.restUrl + '/metrics/processing',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', apsAdmin.nonce);
            },
            success: function(response) {
                var $container = $('#processing-metrics');
                $container.empty();

                $.each(response, function(metric, value) {
                    var $metric = $('<div>').addClass('metric');
                    $metric.append($('<span>').addClass('metric-label').text(metric));
                    $metric.append($('<span>').addClass('metric-value').text(value));
                    $container.append($metric);
                });
            },
            error: function(xhr, status, error) {
                console.error('Error loading processing metrics:', error);
            }
        });
    }

    // Handle clear cache button click
    $('#aps-clear-cache').on('click', function() {
        if (!confirm(apsAdmin.i18n.confirm)) {
            return;
        }
 
        $.ajax({
            url: apsAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'aps_clear_cache',
                nonce: apsAdmin.nonce
            },
            success: function(response) {
                alert(response.message);
            },
            error: function(xhr, status, error) {
                console.error('Error clearing cache:', error);
                alert(apsAdmin.i18n.error);
            }
        });
    });

    // Global functions for onclick attributes
    window.apsRunDiagnostics = function() {
        if (!confirm(apsAdmin.i18n.confirm)) {
            return;
        }

        alert(apsAdmin.i18n.runningDiagnostics); // Provide feedback to the user

        $.ajax({
            url: apsAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'aps_run_diagnostics',
                nonce: apsAdmin.nonce
            },
            success: function(response) {
                $('#aps-diagnostics-results').html('<p>' + response.data.message + '</p>');
                if (response.data.results) {
                    let resultsHtml = '<ul>';
                    for (const category in response.data.results) {
                        resultsHtml += '<li><strong>' + category.replace(/_/g, ' ') + ':</strong><ul>';
                        for (const key in response.data.results[category]) {
                            const item = response.data.results[category][key];
                            resultsHtml += '<li>' + key.replace(/_/g, ' ') + ': ' + item.status + ' - ' + item.message + '</li>';
                        }
                        resultsHtml += '</ul></li>';
                    }
                    resultsHtml += '</ul>';
                    $('#aps-diagnostics-results').append(resultsHtml);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error running diagnostics:', error);
                alert(apsAdmin.i18n.errorDiagnostics);
            }
        });
    };

    window.apsRunPatternSync = function() {
        if (!confirm(apsAdmin.i18n.confirm)) {
            return;
        }

        alert(apsAdmin.i18n.runningSync); // Provide feedback to the user

        $.ajax({
            url: apsAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'aps_run_pattern_sync',
                nonce: apsAdmin.nonce
            },
            success: function(response) {
                $('#aps-sync-status').html('<p>' + response.data.message + '</p>');
            },
            error: function(xhr, status, error) {
                console.error('Error synchronizing patterns:', error);
                alert(apsAdmin.i18n.errorSync);
            }
        });
    };
 
})(jQuery);