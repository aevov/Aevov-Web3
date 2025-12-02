(function ($) {
  'use strict';

  var ApplicationViewer = {
    init: function () {
      $('#spawn-application').on('click', this.spawnApplication);
      $('#evolve-application').on('click', this.evolveApplication);
    },

    spawnApplication: function () {
      $.ajax({
        url: '/wp-json/aevov-app/v1/spawn',
        method: 'POST'
      }).done(function (response) {
        if (response.websocket_url) {
          ApplicationViewer.connectWebSocket(response.websocket_url);
        } else {
          alert('Error spawning application.');
        }
      }).fail(function () {
        alert('Error spawning application.');
      });
    },

    evolveApplication: function () {
      // This is a placeholder.
      // In a real implementation, this would send an evolution request
      // to the backend.
    },

    connectWebSocket: function (url) {
      var socket = new WebSocket(url);

      socket.onopen = function () {
        console.log('WebSocket connection established.');
      };

      socket.onmessage = function (event) {
        var data = JSON.parse(event.data);
        ApplicationViewer.render(data);
      };

      socket.onclose = function () {
        console.log('WebSocket connection closed.');
      };
    },

    render: function (data) {
      var container = $('#application-container');
      container.empty();

      // Render the UI components.
      data.ui.components.forEach(function (component) {
        if (component.type === 'button') {
          container.append('<button>' + component.label + '</button>');
        } else if (component.type === 'text') {
          container.append('<p>' + component.content + '</p>');
        }
      });
    }
  };

  $(document).ready(function () {
    ApplicationViewer.init();
  });

})(jQuery);
