// /assets/js/pattern-list.js
(function($) {
    'use strict';

    const PatternList = {
        init: function() {
            this.page = 1;
            this.perPage = 20;
            this.bindEvents();
            this.loadPatterns();
        },

        bindEvents: function() {
            $('#apply-filters').on('click', () => this.loadPatterns());
            $('.prev-page').on('click', () => this.prevPage());
            $('.next-page').on('click', () => this.nextPage());
            $('#pattern-list').on('click', '.view-pattern', (e) => {
                this.viewPattern($(e.target).data('id'));
            });
            $('#pattern-list').on('click', '.analyze-pattern', (e) => {
                this.analyzePattern($(e.target).data('id'));
            });
        },

        loadPatterns: function() {
            const params = {
                page: this.page,
                per_page: this.perPage,
                type: $('#pattern-type-filter').val(),
                confidence: $('#confidence-filter').val()
            };

            $.get(apsTools.restUrl + '/patterns', params, (response) => {
                this.renderPatterns(response.data);
                this.updatePagination(response.total, response.total_pages);
            });
        },

        renderPatterns: function(patterns) {
            const template = _.template($('#pattern-row-template').html());
            const html = patterns.map(pattern => template(pattern)).join('');
            $('#pattern-list').html(html);
        },

        updatePagination: function(total, totalPages) {
            $('.displaying-num').text(`${total} items`);
            $('.total-pages').text(totalPages);
            $('.current-page').val(this.page);
            
            $('.prev-page').prop('disabled', this.page === 1);
            $('.next-page').prop('disabled', this.page === totalPages);
        },

        prevPage: function() {
            if (this.page > 1) {
                this.page--;
                this.loadPatterns();
            }
        },

        nextPage: function() {
            this.page++;
            this.loadPatterns();
        },

        viewPattern: function(id) {
            window.location.href = 'admin.php?page=aevov-view-pattern&id=' + id;
        },

        analyzePattern: function(id) {
            const $button = $(`.analyze-pattern[data-id="${id}"]`);
            $button.prop('disabled', true).text(apsTools.i18n.analyzing);

            $.post(apsTools.restUrl + '/analyze', { id: id })
                .done(response => {
                    if (response.success) {
                        this.loadPatterns();
                    }
                })
                .fail(error => {
                    alert(error.responseJSON?.message || apsTools.i18n.error);
                })
                .always(() => {
                    $button.prop('disabled', false).text('Analyze');
                });
        }
    };

    $(document).ready(() => PatternList.init());

})(jQuery);