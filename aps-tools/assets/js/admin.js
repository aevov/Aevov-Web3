(function ($) {
  'use strict';

  var ApsTools = {
    init: function () {
      this.bindEvents();
      this.initDashboard();
      this.initAnalysis();
      this.initComparison();
      this.initStatus();
    },

    bindEvents: function () {
      // Bind event handlers for common actions
      $(document).on('click', '#clear-form', this.clearForm);
      $(document).on('click', '.view-comparison', this.viewComparison);
      $(document).on('click', '#refresh-status', this.refreshStatus);
      $(document).on('click', '#download-report', this.downloadReport);
    },

    initDashboard: function () {
      this.initChart('system-status-chart', 'bar', this.getSystemStatusData);
      this.initChart('pattern-metrics-chart', 'bar', this.getPatternMetricsData);
      this.refreshDashboardData();
    },

    initAnalysis: function () {
      this.initChart('analysis-chart', 'line', this.getAnalysisData);
      this.loadAnalysisHistory();

      $('#pattern-analysis-form').on('submit', function (e) {
        e.preventDefault();
        ApsTools.analyzePattern();
      });

      $('#clear-form').on('click', function () {
        $('#pattern_data').val('');
      });
    },

    initComparison: function () {
      this.loadPatternSelector();
      this.loadComparisonHistory();

      $('#pattern-comparison-form').on('submit', function (e) {
        e.preventDefault();
        ApsTools.runComparison();
      });
    },

    initStatus: function () {
      this.initChart('resources-chart', 'bar', this.getResourcesData);
      this.initChart('queue-chart', 'line', this.getQueueData);
      this.initChart('distribution-chart', 'doughnut', this.getPatternDistribution);
      this.refreshStatusData();
    },

    refreshDashboardData: function () {
      this.makeAjaxRequest('get_system_metrics', {}, function (response) {
        ApsTools.updateDashboardMetrics(response.data);
        setTimeout(ApsTools.refreshDashboardData, 10000);
      });
    },

    analyzePattern: function () {
      var patternData = {
        pattern_type: $('#pattern_type').val(),
        pattern_data: $('#pattern_data').val()
      };

      this.makeAjaxRequest('analyze_pattern', patternData, function (response) {
        ApsTools.displayAnalysisResults(response.data);
        ApsTools.loadAnalysisHistory();
      });
    },

    runComparison: function () {
      var comparisonData = {
        patterns: this.getSelectedPatterns(),
        options: {
          type: $('#comparison_type').val()
        }
      };

      this.makeAjaxRequest('run_comparison', { comparison_data: comparisonData }, function (response) {
        ApsTools.displayComparisonResults(response.data);
        ApsTools.loadComparisonHistory();
      });
    },

    refreshStatusData: function () {
      this.makeAjaxRequest('get_system_metrics', {}, function (response) {
        ApsTools.updateStatusMetrics(response.data);
        setTimeout(ApsTools.refreshStatusData, 10000);
      });
    },

    makeAjaxRequest: function (action, data, callback) {
      $.ajax({
        url: apsTools.ajaxUrl,
        type: 'POST',
        data: {
          action: 'aps_tools_action',
          tool_action: action,
          nonce: apsTools.nonce,
          ...data
        },
        success: function (response) {
          if (response.success) {
            callback(response.data);
          } else {
            alert(response.data.message);
          }
        },
        error: function () {
          alert(apsTools.i18n.error);
        }
      });
    },

    initChart: function (chartId, type, dataCallback) {
      new Chart($('#' + chartId), {
        type: type,
        data: {
          labels: [],
          datasets: []
        },
        options: {
          responsive: true,
          maintainAspectRatio: false
        }
      });

      this.updateChart(chartId, dataCallback);
    },

    updateChart: function (chartId, dataCallback) {
      var chart = Chart.getChart(chartId);
      var data = dataCallback.call(this);
      chart.data.labels = data.labels;
      chart.data.datasets = data.datasets;
      chart.update();
    },

    updateDashboardMetrics: function (data) {
      $('#cpu-usage').text(data.system.cpu_usage.toFixed(1) + '%');
      $('#memory-usage').text(data.system.memory_usage.toFixed(1) + '%');
      $('#patterns-processed').text(data.processing.patterns_per_hour);
      $('#avg-confidence').text(data.processing.average_confidence.toFixed(2) + '%');

      this.updateChart('system-status-chart', this.getSystemStatusData);
      this.updateChart('pattern-metrics-chart', this.getPatternMetricsData);

      this.updateComparisonHistory(data.recent_comparisons);
    },

    updateStatusMetrics: function (data) {
      $('#system-health').attr('class', 'status-indicator ' + this.getHealthStatus(data.system.status));
      $('#processing-health').attr('class', 'status-indicator ' + this.getHealthStatus(data.processing.status));
      $('#network-health').attr('class', 'status-indicator ' + this.getHealthStatus(data.network.status));

      this.updateChart('resources-chart', this.getResourcesData);
      this.updateChart('queue-chart', this.getQueueData);
      this.updateChart('distribution-chart', this.getPatternDistribution);

      this.updateEventsList(data.events);
    },

    getSystemStatusData: function () {
      return {
        labels: ['CPU', 'Memory'],
        datasets: [
          {
            label: 'Usage',
            data: [this.getSystemMetric('cpu_usage'), this.getSystemMetric('memory_usage')],
            backgroundColor: ['#36A2EB', '#FF6384']
          }
        ]
      };
    },

    getPatternMetricsData: function () {
      return {
        labels: ['Patterns Processed', 'Average Confidence'],
        datasets: [
          {
            label: 'Metrics',
            data: [this.getSystemMetric('patterns_per_hour'), this.getSystemMetric('average_confidence')],
            backgroundColor: ['#4BC0C0', '#FFCE56']
          }
        ]
      };
    },

    getResourcesData: function () {
      return {
        labels: ['CPU', 'Memory', 'Disk'],
        datasets: [
          {
            label: 'Usage',
            data: [
              this.getSystemMetric('cpu_usage'),
              this.getSystemMetric('memory_usage'),
              this.getSystemMetric('disk_usage')
            ],
            backgroundColor: ['#36A2EB', '#FF6384', '#9B59B6']
          }
        ]
      };
    },

    getQueueData: function () {
      return {
        labels: ['Queue Size', 'Processing Rate'],
        datasets: [
          {
            label: 'Metrics',
            data: [
              this.getSystemMetric('queue_size'),
              this.getSystemMetric('processing_rate')
            ],
            backgroundColor: ['#4BC0C0', '#FFCE56']
          }
        ]
      };
    },

    getPatternDistribution: function () {
      return {
        labels: ['Sequential', 'Structural', 'Statistical'],
        datasets: [
          {
            data: [
              this.getSystemMetric('pattern_types.sequential.count'),
              this.getSystemMetric('pattern_types.structural.count'),
              this.getSystemMetric('pattern_types.statistical.count')
            ],
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56']
          }
        ]
      };
    },

    getSystemMetric: function (key) {
      var keys = key.split('.');
      var value = this.systemMetrics;

      for (var i = 0; i < keys.length; i++) {
        value = value[keys[i]];
      }

      return value;
    },

    getHealthStatus: function (status) {
      switch (status) {
        case 'healthy':
          return 'healthy';
        case 'warning':
          return 'warning';
        case 'critical':
          return 'critical';
        default:
          return 'warning';
      }
    },

    loadPatternSelector: function () {
      var $selector = $('#pattern-selector');

      this.makeAjaxRequest('get_available_patterns', {}, function (patterns) {
        $selector.empty();

        patterns.forEach(function (pattern) {
          $selector.append('<div class="pattern-item">' +
            '<input type="checkbox" class="pattern-checkbox" data-id="' + pattern.id + '">' +
            '<label>' + pattern.pattern_hash + '</label>' +
            '</div>');
        });

        $selector.find('.pattern-checkbox').on('change', function () {
          ApsTools.togglePatternSelection($(this));
        });
      });
    },

    getSelectedPatterns: function () {
      var selectedPatterns = [];
      $('#pattern-selector .pattern-checkbox:checked').each(function () {
        selectedPatterns.push({
          id: $(this).data('id'),
          pattern_data: $(this).closest('.pattern-item').find('label').text()
        });
      });
      return selectedPatterns;
    },

    togglePatternSelection: function ($checkbox) {
      $checkbox.closest('.pattern-item').toggleClass('selected', $checkbox.is(':checked'));
    },

    displayAnalysisResults: function (results) {
      $('#analysis-results').html(
        '<h3>Pattern Analysis Results</h3>' +
        '<div class="analysis-details">' +
        '<p>Type: ' + results.type + '</p>' +
        '<p>Confidence: ' + (results.confidence * 100).toFixed(2) + '%</p>' +
        '<pre>' + JSON.stringify(results, null, 2) + '</pre>' +
        '</div>'
      );
    },

    displayComparisonResults: function (results) {
      $('#comparison-results').html(
        '<h3>Comparison Results</h3>' +
        '<div class="comparison-details">' +
        '<p>Comparison Type: ' + results.type + '</p>' +
        '<p>Overall Score: ' + (results.score * 100).toFixed(2) + '%</p>' +
        '<pre>' + JSON.stringify(results, null, 2) + '</pre>' +
        '</div>'
      );
    },

    updateComparisonHistory: function (comparisons) {
      var $history = $('#comparison-history');
      $history.empty();

      comparisons.forEach(function (comparison) {
        $history.append(
          '<div class="history-item">' +
          '<div class="item-header">' +
          '<span class="item-id">#' + comparison.id + '</span>' +
          '<span class="item-date">' + apsTools.formatTime(comparison.created_at) + '</span>' +
          '</div>' +
          '<div class="item-content">' +
          '<span class="item-type">' + comparison.comparison_type + '</span>' +
          '<span class="item-score">' + (comparison.match_score * 100).toFixed(1) + '%</span>' +
          '</div>' +
          '<div class="item-actions">' +
          '<button class="button button-small view-comparison" data-id="' + comparison.id + '">' + apsTools.i18n.view + '</button>' +
          '</div>' +
          '</div>'
        );
      });
    },

    updateEventsList: function (events) {
      var $eventsList = $('#events-list');
      $eventsList.empty();

      events.forEach(function (event) {
        $eventsList.append(
          '<div class="event-item">' +
          '<span class="event-timestamp">' + apsTools.formatTime(event.timestamp) + '</span>' +
          '<span class="event-message">' + event.message + '</span>' +
          '</div>'
        );
      });
    },

    loadAnalysisHistory: function () {
      $('#analysis-history').html('<p>' + apsTools.i18n.loading + '</p>');

      this.makeAjaxRequest('get_analysis_history', {}, function (history) {
        var $history = $('#analysis-history');
        $history.empty();

        history.forEach(function (analysis) {
          $history.append(
            '<div class="history-item">' +
            '<div class="item-header">' +
            '<span class="item-id">#' + analysis.id + '</span>' +
            '<span class="item-date">' + apsTools.formatTime(analysis.created_at) + '</span>' +
            '</div>' +
            '<div class="item-content">' +
            '<span class="item-type">' + analysis.type + '</span>' +
            '<span class="item-score">' + (analysis.confidence * 100).toFixed(1) + '%</span>' +
            '</div>' +
            '</div>'
          );
        });
      });
    },

    loadComparisonHistory: function () {
      this.makeAjaxRequest('get_comparison_history', {}, function (history) {
        ApsTools.updateComparisonHistory(history);
      });
    },

    viewComparison: function (e) {
      var comparisonId = $(e.currentTarget).data('id');
      alert('Viewing comparison #' + comparisonId);
    },

    refreshStatus: function () {
      ApsTools.refreshStatusData();
    },

    downloadReport: function () {
      // Implement report download functionality
    },

    clearForm: function () {
      // Clear form fields
    }
  };

  $(document).ready(function () {
    ApsTools.init();
  });
})(jQuery);