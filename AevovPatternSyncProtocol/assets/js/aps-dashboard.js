jQuery(document).ready(function($) {
    const API_NAMESPACE = 'aps/v1';
    const DASHBOARD_ENDPOINT = '/admin/dashboard-data';
    const LOGS_ENDPOINT = '/admin/logs';
    const CLEAR_LOGS_ENDPOINT = '/admin/logs';

    let updateInterval = $('#update-interval').val();
    let monitoringEnabled = true;
    let intervalId;

    // Chart instances
    let cpuChart, memoryChart, networkChart;

    // Data for charts
    let cpuData = [];
    let memoryData = [];
    let networkData = [];
    let chartLabels = [];

    const MAX_DATA_POINTS = 20; // Max data points to show on charts

    function fetchDashboardData() {
        if (!monitoringEnabled) {
            return;
        }

        $.ajax({
            url: aps_admin_vars.rest_url + API_NAMESPACE + DASHBOARD_ENDPOINT,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', aps_admin_vars.nonce);
            },
            success: function(response) {
                if (response.success) {
                    updateDashboardUI(response.data);
                } else {
                    console.error('Failed to fetch dashboard data:', response.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error fetching dashboard data:', textStatus, errorThrown, jqXHR.responseText);
            }
        });
    }

    function updateDashboardUI(data) {
        // Update status indicators
        $('#system-status').attr('class', 'status-indicator ' + data.system_info.status);
        $('#network-status').attr('class', 'status-indicator ' + (data.network_status && data.network_status.status ? data.network_status.status : 'unknown'));
        $('#processing-status').attr('class', 'status-indicator ' + (data.consensus_status && data.consensus_status.health ? data.consensus_status.health : 'unknown'));

        // Update metric summary
        $('#patterns-processed').text(data.pattern_stats.total);
        $('#success-rate').text((data.performance_summary && data.performance_summary.sync_success_rate ? (data.performance_summary.sync_success_rate * 100).toFixed(2) : 0) + '%');
        $('#active-nodes').text(data.network_status && data.network_status.active_nodes ? data.network_status.active_nodes : 0);
        $('#queue-size').text(data.queue_info.pending);

        // Update charts
        const now = new Date().toLocaleTimeString();
        chartLabels.push(now);
        cpuData.push(data.system_info.cpu_usage);
        memoryData.push(data.system_info.memory_usage);
        networkData.push(data.network_status && data.network_status.network_traffic ? data.network_status.network_traffic : 0);

        // Keep only MAX_DATA_POINTS
        if (chartLabels.length > MAX_DATA_POINTS) {
            chartLabels.shift();
            cpuData.shift();
            memoryData.shift();
            networkData.shift();
        }

        updateChart(cpuChart, chartLabels, cpuData);
        updateChart(memoryChart, chartLabels, memoryData);
        updateChart(networkChart, chartLabels, networkData);

        // Update system events (fetch recent logs)
        fetchSystemEvents();

        // Update pattern distribution (placeholder for now)
        $('#pattern-distribution').html('<p>Pattern distribution data will be displayed here.</p>');
    }

    function fetchSystemEvents() {
        $.ajax({
            url: aps_admin_vars.rest_url + API_NAMESPACE + LOGS_ENDPOINT,
            method: 'GET',
            data: { limit: 5, orderby: 'timestamp', order: 'desc' }, // Fetch last 5 logs
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', aps_admin_vars.nonce);
            },
            success: function(response) {
                if (response.success) {
                    const eventList = $('#event-list');
                    eventList.empty();
                    response.logs.forEach(log => {
                        const template = $('#event-item-template').html();
                        const rendered = template.replace('<%- timestamp %>', new Date(log.timestamp * 1000).toLocaleString())
                                                 .replace('<%- message %>', log.message);
                        eventList.append(rendered);
                    });
                } else {
                    console.error('Failed to fetch system events:', response.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error fetching system events:', textStatus, errorThrown, jqXHR.responseText);
            }
        });
    }

    function initCharts() {
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        };

        cpuChart = new Chart($('#cpu-usage-chart'), {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'CPU Usage (%)',
                    data: cpuData,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: chartOptions
        });

        memoryChart = new Chart($('#memory-usage-chart'), {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Memory Usage (MB)',
                    data: memoryData,
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                }]
            },
            options: chartOptions
        });

        networkChart = new Chart($('#network-status-chart'), {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Network Traffic (KB/s)',
                    data: networkData,
                    borderColor: 'rgb(54, 162, 235)',
                    tension: 0.1
                }]
            },
            options: chartOptions
        });
    }

    function updateChart(chart, labels, data) {
        chart.data.labels = labels;
        chart.data.datasets[0].data = data;
        chart.update();
    }

    function startMonitoring() {
        if (intervalId) {
            clearInterval(intervalId);
        }
        intervalId = setInterval(fetchDashboardData, updateInterval);
        monitoringEnabled = true;
        $('#toggle-monitoring').text(aps_admin_vars.pause_text);
    }

    function stopMonitoring() {
        clearInterval(intervalId);
        monitoringEnabled = false;
        $('#toggle-monitoring').text(aps_admin_vars.resume_text);
    }

    // Event Listeners
    $('#update-interval').on('change', function() {
        updateInterval = $(this).val();
        if (monitoringEnabled) {
            startMonitoring(); // Restart with new interval
        }
    });

    $('#toggle-monitoring').on('click', function() {
        if (monitoringEnabled) {
            stopMonitoring();
        } else {
            startMonitoring();
        }
    });

    $('#clear-data').on('click', function() {
        if (confirm(aps_admin_vars.confirm_clear_data)) {
            $.ajax({
                url: aps_admin_vars.rest_url + API_NAMESPACE + CLEAR_LOGS_ENDPOINT,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', aps_admin_vars.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        alert(aps_admin_vars.data_cleared_text);
                        // Optionally clear charts and re-fetch data
                        cpuData = [];
                        memoryData = [];
                        networkData = [];
                        chartLabels = [];
                        updateChart(cpuChart, chartLabels, cpuData);
                        updateChart(memoryChart, chartLabels, memoryData);
                        updateChart(networkChart, chartLabels, networkData);
                        fetchSystemEvents(); // Refresh event log
                    } else {
                        alert('Failed to clear data: ' + response.message);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('AJAX Error clearing data: ' + textStatus + ' ' + errorThrown);
                }
            });
        }
    });

    // Initial setup
    initCharts();
    startMonitoring();
    fetchDashboardData(); // Initial fetch
});