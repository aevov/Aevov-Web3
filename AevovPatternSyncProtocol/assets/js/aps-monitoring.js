/**
 * APS Monitoring Module
 * Handles real-time system monitoring and metrics visualization
 */

const APS = APS || {};

APS.Monitoring = (function() {
    // Private variables
    let updateInterval = 5000; // 5 seconds
    let metricsHistory = [];
    let charts = {};
    let isMonitoring = false;

    // Chart configurations
    const chartConfigs = {
        cpu: {
            type: 'line',
            options: {
                responsive: true,
                animation: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        },
        memory: {
            type: 'line',
            options: {
                responsive: true,
                animation: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        },
        network: {
            type: 'bar',
            options: {
                responsive: true,
                animation: false
            }
        }
    };

    /**
     * Initialize monitoring system
     */
    function init(options = {}) {
        updateInterval = options.interval || updateInterval;
        
        // Initialize charts
        initializeCharts();
        
        // Start monitoring if autoStart is true
        if (options.autoStart) {
            startMonitoring();
        }
    }

    /**
     * Initialize monitoring charts
     */
    function initializeCharts() {
        // CPU Usage Chart
        charts.cpu = new Chart(
            document.getElementById('cpu-usage-chart').getContext('2d'),
            {
                type: chartConfigs.cpu.type,
                data: {
                    labels: [],
                    datasets: [{
                        label: 'CPU Usage %',
                        data: [],
                        borderColor: '#FF6384',
                        fill: false
                    }]
                },
                options: chartConfigs.cpu.options
            }
        );

        // Memory Usage Chart
        charts.memory = new Chart(
            document.getElementById('memory-usage-chart').getContext('2d'),
            {
                type: chartConfigs.memory.type,
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Memory Usage %',
                        data: [],
                        borderColor: '#36A2EB',
                        fill: false
                    }]
                },
                options: chartConfigs.memory.options
            }
        );

        // Network Status Chart
        charts.network = new Chart(
            document.getElementById('network-status-chart').getContext('2d'),
            {
                type: chartConfigs.network.type,
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Network Latency (ms)',
                        data: [],
                        backgroundColor: '#4BC0C0'
                    }]
                },
                options: chartConfigs.network.options
            }
        );
    }

    /**
     * Start monitoring system
     */
    function startMonitoring() {
        if (isMonitoring) return;
        
        isMonitoring = true;
        updateMetrics();
        
        // Set up interval for continuous monitoring
        setInterval(updateMetrics, updateInterval);
    }

    /**
     * Stop monitoring system
     */
    function stopMonitoring() {
        isMonitoring = false;
    }

    /**
     * Update metrics data
     */
    function updateMetrics() {
        fetch(apsMonitoring.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'aps_get_monitor_metrics',
                nonce: apsMonitoring.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCharts(data.data);
                updateStatusIndicators(data.data.status);
                storeMetricsHistory(data.data);
            }
        })
        .catch(error => {
            console.error('Error fetching metrics:', error);
            updateStatusIndicators({ system: 'error' });
        });
    }

    /**
     * Update chart displays
     */
    function updateCharts(data) {
        // Update CPU chart
        updateChartData(charts.cpu, data.cpu);

        // Update Memory chart
        updateChartData(charts.memory, data.memory);

        // Update Network chart
        updateChartData(charts.network, data.network);
    }

    /**
     * Update individual chart data
     */
    function updateChartData(chart, data) {
        const maxDataPoints = 60;

        chart.data.labels.push(new Date().toLocaleTimeString());
        chart.data.datasets[0].data.push(data);

        // Remove old data points if exceeded max
        if (chart.data.labels.length > maxDataPoints) {
            chart.data.labels.shift();
            chart.data.datasets[0].data.shift();
        }

        chart.update();
    }

    /**
     * Update status indicators
     */
    function updateStatusIndicators(status) {
        const indicators = {
            system: document.getElementById('system-status'),
            network: document.getElementById('network-status'),
            processing: document.getElementById('processing-status')
        };

        Object.keys(indicators).forEach(key => {
            const indicator = indicators[key];
            if (indicator) {
                // Remove existing status classes
                indicator.className = 'status-indicator';
                // Add new status class
                indicator.classList.add(status[key]);
            }
        });
    }

    /**
     * Store metrics history
     */
    function storeMetricsHistory(data) {
        const maxHistory = 1000;
        
        metricsHistory.push({
            timestamp: new Date(),
            data: data
        });

        // Remove old history if exceeded max
        if (metricsHistory.length > maxHistory) {
            metricsHistory.shift();
        }
    }

    /**
     * Get metrics history
     */
    function getMetricsHistory(duration = '1hour') {
        const now = new Date();
        const cutoff = new Date(now - getDurationMilliseconds(duration));

        return metricsHistory.filter(item => item.timestamp >= cutoff);
    }

    /**
     * Convert duration string to milliseconds
     */
    function getDurationMilliseconds(duration) {
        const durations = {
            '1hour': 60 * 60 * 1000,
            '24hours': 24 * 60 * 60 * 1000,
            '7days': 7 * 24 * 60 * 60 * 1000,
            '30days': 30 * 24 * 60 * 60 * 1000
        };

        return durations[duration] || durations['1hour'];
    }

    // Public API
    return {
        init,
        startMonitoring,
        stopMonitoring,
        getMetricsHistory
    };
})();

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = APS.Monitoring;
}