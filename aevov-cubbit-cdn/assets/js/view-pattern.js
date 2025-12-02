(function ($) {
  'use strict';

  var ViewPattern = {
    init: function () {
      var patternContainer = $('#pattern-container');
      var patternId = patternContainer.data('pattern-id');

      if (patternId) {
        this.loadPattern(patternId);
      }
    },

    loadPattern: function (patternId) {
      var patternContainer = $('#pattern-container');

      $.ajax({
        url: '/wp-json/aevov-cubbit-cdn/v1/get-chunk-url/' + patternId,
        method: 'GET',
        beforeSend: function (xhr) {
          // You might need a nonce if the endpoint requires authentication,
          // but for now it's public.
        }
      }).done(function (response) {
        if (response.url) {
          $.ajax({
            url: response.url,
            method: 'GET'
          }).done(function (patternData) {
            patternContainer.html('<pre>' + JSON.stringify(patternData, null, 2) + '</pre>');
          }).fail(function () {
            patternContainer.html('<p>Error loading pattern data from Cubbit.</p>');
          });
        } else {
          patternContainer.html('<p>Error getting pre-signed URL.</p>');
        }
      }).fail(function () {
        patternContainer.html('<p>Error loading pattern.</p>');
      });
    }
  };

  $(document).ready(function () {
    ViewPattern.init();
  });

})(jQuery);
