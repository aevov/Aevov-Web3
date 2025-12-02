(function ($) {
  'use strict';

  var StreamPlayer = {
    init: function () {
      $('#play-stream-button').on('click', this.startStream);
    },

    startStream: function () {
      $.ajax({
        url: '/wp-json/aevov-stream/v1/start-session',
        method: 'POST',
        beforeSend: function (xhr) {
          // You might need a nonce if the endpoint requires authentication.
        }
      }).done(function (response) {
        if (response.playlist_url) {
          var video = videojs('stream-player');
          video.src({
            src: response.playlist_url,
            type: 'application/x-mpegURL'
          });
          video.play();
        } else {
          alert('Error starting stream.');
        }
      }).fail(function () {
        alert('Error starting stream.');
      });
    }
  };

  $(document).ready(function () {
    StreamPlayer.init();
  });

})(jQuery);
