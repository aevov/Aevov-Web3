(function($) {
    'use strict';

    $(function() {
        // System Status Chart
        var systemStatusCtx = document.getElementById('system-status-chart');
        if (systemStatusCtx) {
            var systemStatusChart = new Chart(systemStatusCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'CPU Usage',
                        data: [],
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Memory Usage',
                        data: [],
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }

        // Pattern Metrics Chart
        var patternMetricsCtx = document.getElementById('pattern-metrics-chart');
        if (patternMetricsCtx) {
            var patternMetricsChart = new Chart(patternMetricsCtx, {
                type: 'bar',
                data: {
                    labels: ['Sequential', 'Structural', 'Statistical', 'File'],
                    datasets: [{
                        label: 'Patterns Processed',
                        data: [0, 0, 0],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Pattern Type Chart
        var patternTypeCtx = document.getElementById('pattern-type-chart');
        if (patternTypeCtx) {
            var patternTypeChart = new Chart(patternTypeCtx, {
                type: 'doughnut',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Pattern Types',
                        data: [],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)',
                            'rgba(255, 159, 64, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)'
                        ],
                        borderWidth: 1
                    }]
                }
            });
        }

        // Function to update the charts with new data
        function updateCharts() {
            // Fetch new data from the server
            $.ajax({
                url: apsTools.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_system_metrics',
                    nonce: apsTools.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;

                        // Update System Status Chart
                        if (systemStatusChart) {
                            systemStatusChart.data.labels.push(new Date().toLocaleTimeString());
                            systemStatusChart.data.datasets[0].data.push(data.cpu_usage);
                            systemStatusChart.data.datasets[1].data.push(data.memory_usage);
                            systemStatusChart.update();
                        }

                        // Update Pattern Metrics Chart
                        if (patternMetricsChart) {
                            patternMetricsChart.data.datasets[0].data = [
                                data.patterns_processed.sequential,
                                data.patterns_processed.structural,
                                data.patterns_processed.statistical
                            ];
                            patternMetricsChart.update();
                        }

                        // Update Pattern Type Chart
                        if (patternTypeChart) {
                            patternTypeChart.data.labels = Object.keys(data.pattern_types);
                            patternTypeChart.data.datasets[0].data = Object.values(data.pattern_types);
                            patternTypeChart.update();
                        }

                        // Update summary metrics
                        $('#cpu-usage').text(data.cpu_usage + '%');
                        $('#memory-usage').text(data.memory_usage + '%');
                        $('#patterns-processed').text(data.patterns_processed.total);
                        $('#avg-confidence').text(data.avg_confidence + '%');

                        // Update recent patterns table
                        var recentPatternsTable = $('#recent-patterns-table');
                        if (recentPatternsTable.length) {
                            recentPatternsTable.empty();
                            if (data.recent_patterns.length) {
                                $.each(data.recent_patterns, function(index, pattern) {
                                    var row = '<tr>';
                                    row += '<td>' + pattern.id + '</td>';
                                    row += '<td>' + pattern.pattern_type + '</td>';
                                    row += '<td>' + pattern.confidence + '</td>';
                                    row += '<td>' + pattern.created_at + '</td>';
                                    row += '</tr>';
                                    recentPatternsTable.append(row);
                                });
                            } else {
                                recentPatternsTable.append('<tr><td colspan="4">' + apsTools.i18n.noRecentPatterns + '</td></tr>');
                            }
                        }
                    }
                }
            });
        }

        // Update the charts every 5 seconds
        setInterval(updateCharts, 5000);
    });

})(jQuery);
