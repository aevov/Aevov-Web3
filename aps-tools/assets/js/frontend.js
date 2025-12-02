// assets/js/frontend.js
(function($) {
    'use strict';

    const APSToolsFrontend = {
        init: function() {
            this.analysisForm = $('#aps-analysis-form');
            this.comparisonForm = $('#aps-comparison-form');
            this.analysisResult = $('#aps-analysis-result');
            this.comparisonResult = $('#aps-comparison-result');
            this.bindEvents();
        },

        bindEvents: function() {
            this.analysisForm.on('submit', (e) => this.handleAnalysisSubmit(e));
            this.comparisonForm.on('submit', (e) => this.handleComparisonSubmit(e));
        },

        handleAnalysisSubmit: function(e) {
            e.preventDefault();
            const formData = this.analysisForm.serialize();

            $.ajax({
                url: apsToolsFrontend.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'aps_analyze_pattern',
                    nonce: apsToolsFrontend.nonce,
                    ...this.serializeObject(formData)
                },
                beforeSend: () => {
                    this.analysisForm.find('button').prop('disabled', true);
                    this.analysisResult.html('Analyzing...');
                }
            })
            .done((response) => {
                if (response.success) {
                    this.showAnalysisResult(response.data);
                } else {
                    this.analysisResult.html(`<p class="error">${response.data}</p>`);
                }
            })
            .fail((jqXHR, textStatus, errorThrown) => {
                console.error('Analysis error:', errorThrown);
                this.analysisResult.html('<p class="error">An error occurred. Please try again.</p>');
            })
            .always(() => {
                this.analysisForm.find('button').prop('disabled', false);
            });
        },

        handleComparisonSubmit: function(e) {
            e.preventDefault();
            const formData = this.comparisonForm.serialize();

            $.ajax({
                url: apsToolsFrontend.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'aps_compare_patterns',
                    nonce: apsToolsFrontend.nonce,
                    ...this.serializeObject(formData)
                },
                beforeSend: () => {
                    this.comparisonForm.find('button').prop('disabled', true);
                    this.comparisonResult.html('Comparing...');
                }
            })
            .done((response) => {
                if (response.success) {
                    this.showComparisonResult(response.data);
                } else {
                    this.comparisonResult.html(`<p class="error">${response.data}</p>`);
                }
            })
            .fail((jqXHR, textStatus, errorThrown) => {
                console.error('Comparison error:', errorThrown);
                this.comparisonResult.html('<p class="error">An error occurred. Please try again.</p>');
            })
            .always(() => {
                this.comparisonForm.find('button').prop('disabled', false);
            });
        },

        showAnalysisResult: function(result) {
            const html = `
                <h3>Analysis Result</h3>
                <p>Type: ${result.type}</p>
                <p>Confidence: ${(result.confidence * 100).toFixed(2)}%</p>
                <pre>${JSON.stringify(result, null, 2)}</pre>
            `;
            this.analysisResult.html(html);
        },

        showComparisonResult: function(result) {
            const html = `
                <h3>Comparison Result</h3>
                <p>Type: ${result.type}</p>  
                <p>Similarity: ${(result.similarity * 100).toFixed(2)}%</p>
                <pre>${JSON.stringify(result, null, 2)}</pre>
            `;
            this.comparisonResult.html(html);  
        },

        serializeObject: function(data) {
            const queryParams = new URLSearchParams(data);
            return Array.from(queryParams.entries()).reduce((acc, [key, value]) => ({
                ...acc,
                [key]: value,
            }), {});
        }
    };

    $(document).ready(() => APSToolsFrontend.init());

})(jQuery);