(function ($) {
  'use strict';

  var NeuroArchitect = {
    init: function () {
      $('#blueprint-form').on('submit', this.composeModel);
    },

    composeModel: function (e) {
      e.preventDefault();
      var blueprintName = $('#blueprint-name').val();
      var blueprintLayersJson = $('#blueprint-layers').val(); // Get JSON string from textarea

      var blueprintData;
      try {
        blueprintData = JSON.parse(blueprintLayersJson); // Parse JSON string
      } catch (e) {
        alert('Error: Invalid JSON in Layers textarea. Please ensure it\'s valid JSON.');
        console.error('JSON parsing error:', e);
        return;
      }

      // Get nonce from localized script (assuming it's passed from PHP)
      var nonce = neuroArchitectVars.nonce;
      if (!nonce) {
        alert('Security nonce not found. Please refresh the page.');
        return;
      }
 
       $.ajax({
         url: neuroArchitectVars.restUrl + 'compose', // Use localized REST URL
         method: 'POST',
         data: {
           blueprint: {
             name: blueprintName,
             layers: blueprintData.layers || [], // Ensure layers is an array
             memory: blueprintData.memory || {} // Include memory section if present
           }
         },
         beforeSend: function (xhr) {
           xhr.setRequestHeader('X-WP-Nonce', nonce); // Set nonce header
         }
       }).done(function (response) {
         if (response.model_id) {
           NeuroArchitect.displayModel(response);
         } else {
           alert('Error composing model: ' + (response.message || 'Unknown error.'));
         }
       }).fail(function (jqXHR, textStatus, errorThrown) {
         alert('Error composing model. Check console for details.');
         console.error('AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
       });
    },

    displayModel: function (model) {
      var container = $('#model-visualizer-container');
      container.html('<pre>' + JSON.stringify(model, null, 2) + '</pre>');
      // TODO: Enhance visualization using a library like D3.js or Three.js
    }
  };

  $(document).ready(function () {
    NeuroArchitect.init();
  });

})(jQuery);
