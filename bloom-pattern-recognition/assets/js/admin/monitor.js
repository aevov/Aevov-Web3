
// 3. /assets/js/admin/monitor.js
// System monitoring and real-time updates

const BloomMonitor = {
    charts: {},
    websocket: null,
    updateInterval: 1000,
    isPaused: false,

    init: function() {
        this.initCharts();
        this.initWebSocket();
        this.bindEvents();
        this.startMonitoring();
    },

    initCharts: function() {
        // CPU Usage Chart
        this.charts.cpu = new Chart(
            document.getElementById('cpu-usage-chart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'CPU Usage %',
                        data: [],
                        borderColor: '#FF6384',
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    animation: false
                }
            }
        );

        // Memory Usage Chart
        this.charts.memory = new Chart(
            document.getElementById('memory-usage-chart').getContext('2d'),
            {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Memory Usage %',
                        data: [],
                        borderColor: '#36A2EB',
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    animation: false
                }
            }
        );

        // Network Status Chart
        this.charts.network = new Chart(
            document.getElementById('network-status-chart').getContext('2d'),
            {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Network Latency (ms)',
                        data: [],
                        backgroundColor: '#4BC0C0'
                    }]
                },
                options: {
                    responsive: true,
                    animation: false
                }
            }
        );
    },

    initWebSocket: function() {
        this.websocket = new WebSocket(bloomAdmin.wsUrl);
        
        this.websocket.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.updateMetrics(data);
        };

        this.websocket.onerror = (error) => {
            console.error('WebSocket Error:', error);
            this.fallbackToPolling();
        };
    },

    bindEvents: function() {
        // Pause/Resume button
        document.getElementById('toggle-monitoring').addEventListener('click', () => {
            this.toggleMonitoring();
        });

        // Update interval selector
        document.getElementById('update-interval').addEventListener('change', (e) => {
            this.updateInterval = parseInt(e.target.value);
            this.restartMonitoring();
        });

        // Clear data button
        document.getElementById('clear-data').addEventListener('click', () => {
            this.clearMonitoringData();
        });
    },

    startMonitoring: function() {
        if (!this.isPaused) {
            this.monitoringInterval = setInterval(() => {
                this.fetchMetrics();
            }, this.updateInterval);
        }
    },

    fetchMetrics: function() {
        fetch(bloomAdmin.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'bloom_get_monitor_metrics',
                nonce: bloomAdmin.nonce
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateMetrics(data.data);
            }
        })
        .catch(error => {
            console.error('Error fetching metrics:', error);
        });
    },

    updateMetrics: function(data) {
        this.updateCPUChart(data.cpu);
        this.updateMemoryChart(data.memory);
        this.updateNetworkChart(data.network);
        this.updateStatusIndicators(data.status);
        this.updateSystemEvents(data.events); // New call to update system events
    },
 
    updateCPUChart: function(cpuData) {
        const chart = this.charts.cpu;
        chart.data.labels.push(new Date().toLocaleTimeString());
        chart.data.datasets[0].data.push(cpuData.usage);
 
        if (chart.data.labels.length > 60) { // Keep last 60 data points
            chart.data.labels.shift();
            chart.data.datasets[0].data.shift();
        }
 
        chart.update();
    },
 
    updateMemoryChart: function(memoryData) {
        const chart = this.charts.memory;
        chart.data.labels.push(new Date().toLocaleTimeString());
        chart.data.datasets[0].data.push(memoryData.usage);
 
        if (chart.data.labels.length > 60) { // Keep last 60 data points
            chart.data.labels.shift();
            chart.data.datasets[0].data.shift();
        }
 
        chart.update();
    },
 
    updateNetworkChart: function(networkData) {
        const chart = this.charts.network;
        chart.data.labels = networkData.sites.map(site => site.name);
        chart.data.datasets[0].data = networkData.sites.map(site => site.latency);
        chart.update();
    },
 
    updateStatusIndicators: function(status) {
        document.getElementById('system-status').className = `status-indicator ${status.system}`;
        document.getElementById('network-status').className = `status-indicator ${status.network}`;
        document.getElementById('processing-status').className = `status-indicator ${status.processing}`;
    },

    updateSystemEvents: function(events) {
        const eventLog = document.getElementById('system-events').querySelector('.event-list');
        eventLog.innerHTML = events.map(event => `
            <div class="event-item">
                <span class="event-timestamp">${event.timestamp}</span>
                <span class="event-message">${event.message}</span>
            </div>
        `).join('');
    },

    toggleMonitoring: function() {
        this.isPaused = !this.isPaused;
        if (this.isPaused) {
            clearInterval(this.monitoringInterval);
        } else {
            this.startMonitoring();
        }
    },

    clearMonitoringData: function() {
        Object.values(this.charts).forEach(chart => {
            chart.data.labels = [];
            chart.data.datasets[0].data = [];
            chart.update();
        });
    }
};

// Initialize monitor when document is ready
document.addEventListener('DOMContentLoaded', () => {
    BloomMonitor.init();
});