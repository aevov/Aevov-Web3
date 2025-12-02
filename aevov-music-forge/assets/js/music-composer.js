(function ($) {
  'use strict';

  var MusicComposer = {
    init: function () {
      $('#music-composer-form').on('submit', this.startComposition);
    },

    startComposition: function (e) {
      e.preventDefault();
      var genre = $('#genre').val();
      var mood = $('#mood').val();

      $.ajax({
        url: '/wp-json/aevov-music/v1/compose',
        method: 'POST',
        data: {
          genre: genre,
          mood: mood
        },
        beforeSend: function (xhr) {
          // You might need a nonce if the endpoint requires authentication.
        }
      }).done(function (response) {
        if (response.job_id) {
          MusicComposer.pollJobStatus(response.job_id);
        } else {
          alert('Error starting composition.');
        }
      }).fail(function () {
        alert('Error starting composition.');
      });
    },

    pollJobStatus: function (jobId) {
      var interval = setInterval(function () {
        $.ajax({
          url: '/wp-json/aevov-music/v1/status/' + jobId,
          method: 'GET'
        }).done(function (response) {
          if (response.status === 'complete') {
            clearInterval(interval);
            MusicComposer.displayTrack(jobId);
          }
        });
      }, 5000);
    },

    displayTrack: function (jobId) {
      var trackUrl = '/wp-json/aevov-music/v1/track/' + jobId;
      $('#music-player-container').html('<audio controls src="' + trackUrl + '"></audio>');
    }
  };

  $(document).ready(function () {
    MusicComposer.init();
  });

})(jQuery);
