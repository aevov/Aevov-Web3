(function ($) {
  'use strict';

  var ReasoningQuery = {
    init: function () {
      $('#reasoning-query-form').on('submit', this.submitQuery);
    },

    submitQuery: function (e) {
      e.preventDefault();
      var query = $('#query').val();

      $.ajax({
        url: '/wp-json/aevov-reasoning/v1/infer',
        method: 'POST',
        data: {
          pattern: {
            query: query
          }
        },
        beforeSend: function (xhr) {
          // You might need a nonce if the endpoint requires authentication.
        }
      }).done(function (response) {
        if (response.inference) {
          $('#reasoning-result-container').html(response.inference);
        } else {
          alert('Error making inference.');
        }
      }).fail(function () {
        alert('Error making inference.');
      });
    }
  };

  $(document).ready(function () {
    ReasoningQuery.init();
  });

})(jQuery);
