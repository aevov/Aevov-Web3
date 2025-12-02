(function ($) {
  'use strict';

  var SimulationViewer = {
    init: function () {
      $('#start-simulation').on('click', this.startSimulation);
      $('#stop-simulation').on('click', this.stopSimulation);
    },

    startSimulation: function () {
      $.ajax({
        url: '/wp-json/aevov-sim/v1/start',
        method: 'POST'
      }).done(function (response) {
        if (response.websocket_url) {
          SimulationViewer.connectWebSocket(response.websocket_url);
        } else {
          alert('Error starting simulation.');
        }
      }).fail(function () {
        alert('Error starting simulation.');
      });
    },

    stopSimulation: function () {
      // This is a placeholder.
      // In a real implementation, this would close the WebSocket connection
      // and stop the simulation on the backend.
    },

    connectWebSocket: function (url) {
      var socket = new WebSocket(url);

      socket.onopen = function () {
        console.log('WebSocket connection established.');
      };

      socket.onmessage = function (event) {
        var data = JSON.parse(event.data);
        SimulationViewer.render(data);
      };

      socket.onclose = function () {
        console.log('WebSocket connection closed.');
      };
    },

    render: function (data) {
      var canvas = document.getElementById('simulation-canvas');
      var ctx = canvas.getContext('2d');

      // Clear the canvas.
      ctx.clearRect(0, 0, canvas.width, canvas.height);

      // This is a placeholder for the 3D visualization.
      // In a real implementation, this would use a library like Three.js
      // to render the simulation.
      data.entities.forEach(function (entity) {
        if (entity.type === 'agent') {
          ctx.fillStyle = 'blue';
        } else {
          ctx.fillStyle = 'green';
        }
        ctx.fillRect(entity.x * 10, entity.y * 10, 10, 10);
      });
    }
  };

  $(document).ready(function () {
    SimulationViewer.init();
  });

})(jQuery);
