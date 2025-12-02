(function ($) {
  'use strict';

  var MemoryDesigner = {
    init: function () {
      $('#memory-design-form').on('submit', this.createMemorySystem);
    },

    createMemorySystem: function (e) {
      e.preventDefault();
      var memoryType = $('#memory-type').val();
      var capacity = $('#capacity').val();
      var decayRate = $('#decay-rate').val();

      $.ajax({
        url: '/wp-json/aevov-memory/v1/memory',
        method: 'POST',
        data: {
          type: memoryType,
          capacity: capacity,
          decay_rate: decayRate
        },
        beforeSend: function (xhr) {
          // You might need a nonce if the endpoint requires authentication.
        }
      }).done(function (response) {
        if (response.success) {
          MemoryDesigner.displayMemorySystem(response.data);
        } else {
          alert('Error creating memory system.');
        }
      }).fail(function () {
        alert('Error creating memory system.');
      });
    },

    displayMemorySystem: function (memorySystem) {
      var container = $('#memory-visualizer-container');
      container.html('<pre>' + JSON.stringify(memorySystem, null, 2) + '</pre>');
    }
  };

  $(document).ready(function () {
    MemoryDesigner.init();
  });

})(jQuery);
