(function ($) {
  'use strict';

  var ImageGenerator = {
    init: function () {
      $('#image-generator-form').on('submit', this.startImageGeneration);
    },

    startImageGeneration: function (e) {
      e.preventDefault();
      var prompt = $('#prompt').val();

      $.ajax({
        url: '/wp-json/aevov-image/v1/generate',
        method: 'POST',
        data: {
          prompt: prompt
        },
        beforeSend: function (xhr) {
          // You might need a nonce if the endpoint requires authentication.
        }
      }).done(function (response) {
        if (response.job_id) {
          ImageGenerator.pollJobStatus(response.job_id);
        } else {
          alert('Error starting image generation.');
        }
      }).fail(function () {
        alert('Error starting image generation.');
      });
    },

    pollJobStatus: function (jobId) {
      var interval = setInterval(function () {
        $.ajax({
          url: '/wp-json/aevov-image/v1/status/' + jobId,
          method: 'GET'
        }).done(function (response) {
          if (response.status === 'complete') {
            clearInterval(interval);
            ImageGenerator.displayImage(jobId);
          }
        });
      }, 5000);
    },

    displayImage: function (jobId) {
      var imageUrl = '/wp-json/aevov-image/v1/image/' + jobId;
      $('#image-gallery-container').append('<img src="' + imageUrl + '">');
    }
  };

  $(document).ready(function () {
    ImageGenerator.init();
  });

})(jQuery);
