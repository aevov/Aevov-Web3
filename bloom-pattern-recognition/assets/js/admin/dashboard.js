// 1. /assets/js/admin/dashboard.js
// Main dashboard functionality and data visualization

const BloomDashboard = {
    charts: {},
    updateInterval: 5000,
    metricsHistory: [],
    maxHistoryPoints: 100,

    init: function() {
        this.initCharts();
        this.bindEvents();
        this.startMetricsUpdates();
    },

    initCharts: function() {
        // Pattern Distribution Chart
        this.charts.patternDistribution = new Chart(
            document.getElementById('pattern-distribution-chart').getContext('2d'),
            {
                type: 'doughnut',
                data: {
                    labels: ['Sequential', 'Structural', 'Statistical', 'Clustered'],
                    datasets: [{
                        data: [0, 0, 0, 0],
                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4CAF50'] // Added a new color for clustered
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            }
        );

        // Processing Performance Chart
        this.charts.performance = new Chart(
            document.getElementById('performance-chart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Processing Time (ms)',
                        data: [],
                        borderColor: '#36A2EB',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            }
        );

        // Network Health Chart
        this.charts.network = new Chart(
            document.getElementById('network-health-chart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Site Health Score',
                        data: [],
                        backgroundColor: '#4BC0C0'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            }
        );
    },

    bindEvents: function() {
        // Refresh button
        document.getElementById('refresh-metrics').addEventListener('click', () => {
            this.updateMetrics();
        });

        // Time range selector
        document.getElementById('time-range').addEventListener('change', (e) => {
            this.updateChartTimeRange(e.target.value);
        });

        // Pattern type filter
        document.getElementById('pattern-type-filter').addEventListener('change', (e) => {
            this.filterPatternData(e.target.value);
        });
    },

    startMetricsUpdates: function() {
        this.updateMetrics();
        setInterval(() => this.updateMetrics(), this.updateInterval);
    },

    updateMetrics: function() {
        fetch(bloomAdmin.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'bloom_get_dashboard_metrics',
                nonce: bloomAdmin.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateDashboardData(data.data);
            }
        })
        .catch(error => {
            console.error('Error fetching metrics:', error);
        });
    },

    updateDashboardData: function(data) {
        // Update metrics history
        this.metricsHistory.push(data);
        if (this.metricsHistory.length > this.maxHistoryPoints) {
            this.metricsHistory.shift();
        }

        // Update charts
        this.updatePatternDistribution(data.patterns);
        this.updatePerformanceChart(data.performance);
        this.updateNetworkHealth(data.network);

        // Update summary stats
        this.updateSummaryStats(data.summary);
    },

    updatePatternDistribution: function(patterns) {
        const chart = this.charts.patternDistribution;
        chart.data.datasets[0].data = [
            patterns.sequential_count,
            patterns.structural_count,
            patterns.statistical_count,
            patterns.clustered_count // Added clustered count
        ];
        chart.update();
    },
 
    updateRecentActivity: function(activity) {
        const activityList = document.getElementById('recent-activity');
        activityList.innerHTML = activity.map(item => `
            <div class="activity-item">
                <span class="activity-timestamp">${item.timestamp}</span>
                <span class="activity-message">${item.message}</span>
            </div>
        `).join('');
    },

    updatePerformanceChart: function(performance) {
        const chart = this.charts.performance;
        chart.data.labels = performance.timestamps;
        chart.data.datasets[0].data = performance.processing_times;
        chart.update();
    },

    updateNetworkHealth: function(network) {
        const chart = this.charts.network;
        chart.data.labels = network.sites.map(site => site.name);
        chart.data.datasets[0].data = network.sites.map(site => site.health_score);
        chart.update();
    },

    updateSummaryStats: function(summary) {
        document.getElementById('total-patterns').textContent = summary.total_patterns;
        document.getElementById('active-sites').textContent = summary.active_sites;
        document.getElementById('avg-processing-time').textContent = summary.avg_processing_time + 'ms';
        document.getElementById('success-rate').textContent = summary.success_rate + '%';
    }
};

// Initialize dashboard when document is ready
document.addEventListener('DOMContentLoaded', () => {
    BloomDashboard.init();
});