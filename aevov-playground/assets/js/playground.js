(function ($) {
  'use strict';

  var Playground = {
    selectedEndpoint: null,
    connections: [],
    blocks: [], // To store information about blocks on canvas

    init: function () {
      var self = this;
      $('.draggable-block').draggable({
        helper: 'clone',
        revert: 'invalid',
        stop: function(event, ui) {
          self.addBlockToCanvas($(this).data('type'), ui.position.left, ui.position.top);
        }
      });

      $('#playground-canvas').droppable({
        drop: function (event, ui) {
          // Handled by draggable stop event
        }
      });

      $('#playground-canvas').on('click', '.endpoint', function (e) {
        self.handleEndpointClick($(this));
      });

      $('#playground-canvas').on('dblclick', '.playground-block', function() {
        self.showBlockSettings($(this));
      });

      // Initialize existing blocks on page load if any (for persistence)
      // This part would need backend integration to load saved workflows
    },

    addBlockToCanvas: function (type, x, y) {
      var self = this;
      var blockId = 'block-' + Date.now();
      var blockHtml = `
        <div class="playground-block" id="${blockId}" data-type="${type}" style="left:${x}px;top:${y}px;">
          <div class="block-header">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
          <div class="block-content"></div>
          <div class="endpoint input"></div>
          <div class="endpoint output"></div>
        </div>`;
      $('#playground-canvas').append(blockHtml);
      var newBlock = $('#' + blockId);
      newBlock.draggable({ containment: "parent" });
      this.blocks.push({ id: blockId, type: type, x: x, y: y, inputs: {}, outputs: {} });
    },

    handleEndpointClick: function (endpoint) {
      if (this.selectedEndpoint) {
        if (this.selectedEndpoint[0] === endpoint[0]) { // Deselect if same endpoint clicked
          this.selectedEndpoint.removeClass('selected');
          this.selectedEndpoint = null;
          return;
        }
        this.connectEndpoints(this.selectedEndpoint, endpoint);
        this.selectedEndpoint.removeClass('selected');
        this.selectedEndpoint = null;
      } else {
        endpoint.addClass('selected');
        this.selectedEndpoint = endpoint;
      }
    },

    connectEndpoints: function (startEndpoint, endEndpoint) {
      var startBlock = startEndpoint.closest('.playground-block');
      var endBlock = endEndpoint.closest('.playground-block');

      if (!startBlock.length || !endBlock.length) return;

      var connection = {
        startBlock: startBlock.attr('id'),
        startEndpointType: startEndpoint.hasClass('input') ? 'input' : 'output',
        endBlock: endBlock.attr('id'),
        endEndpointType: endEndpoint.hasClass('input') ? 'input' : 'output'
      };
      this.connections.push(connection);
      this.drawLine(startEndpoint, endEndpoint);
      this.processConnection(connection); // Process the connection immediately
    },

    drawLine: function (start, end) {
      // Basic line drawing, can be enhanced with SVG for better curves
      var startPos = start.offset();
      var endPos = end.offset();
      var canvasPos = $('#playground-canvas').offset();

      var line = $('<div class="connection-line"></div>');
      line.css({
        top: startPos.top - canvasPos.top + start.height() / 2,
        left: startPos.left - canvasPos.left + start.width() / 2,
        width: Math.sqrt(Math.pow(endPos.left - startPos.left, 2) + Math.pow(endPos.top - startPos.top, 2)),
        transformOrigin: '0 0',
        transform: `rotate(${Math.atan2(endPos.top - startPos.top, endPos.left - startPos.left)}rad)`
      });
      $('#playground-canvas').append(line);
    },

    processConnection: function(connection) {
      var self = this;
      var startBlockData = this.blocks.find(b => b.id === connection.startBlock);
      var endBlockData = this.blocks.find(b => b.id === connection.endBlock);

      if (!startBlockData || !endBlockData) return;

      // Logic to trigger API calls based on block types and connection
      // This is a simplified example, real logic would be more complex
      if (connection.startEndpointType === 'output' && connection.endEndpointType === 'input') {
        var inputParams = {}; // Collect parameters from startBlockData's outputs or block settings
        var outputContainer = $('#' + endBlockData.id).find('.block-content');
        outputContainer.html('<span class="spinner is-active"></span> Processing...');

        // Example: Language -> Language connection (chaining)
        if (startBlockData.type === 'language' && endBlockData.type === 'language') {
          // Assuming startBlockData has a 'result' from a previous operation
          var prompt = startBlockData.outputs.text || "Continue the story.";
          var params = {}; // Get params from endBlockData settings
          this.callApi('language', prompt, params, outputContainer, (response) => {
            endBlockData.outputs.text = response.text; // Store result
          });
        }
        // Example: Language -> Image connection
        else if (startBlockData.type === 'language' && endBlockData.type === 'image') {
          var prompt = startBlockData.outputs.text || "Generate an image.";
          var params = { width: 512, height: 512 }; // Get params from endBlockData settings
          this.callApi('image', prompt, params, outputContainer, (response) => {
            endBlockData.outputs.job_id = response.job_id; // Store job ID
            outputContainer.html(`<p>Image job created: ${response.job_id}</p><img id="img-${response.job_id}" style="max-width:100%;" />`);
            self.pollImageStatus(response.job_id, $(`#img-${response.job_id}`));
          });
        }
        // Add more engine-specific logic here
      }
    },

    callApi: function(engine, data, params, outputContainer, successCallback) {
      var self = this;
      var endpoint = '';
      var requestData = {};

      switch(engine) {
        case 'language':
          endpoint = '/aevov-language/v1/generate';
          requestData = { prompt: data, params: params };
          break;
        case 'image':
          endpoint = '/aevov-image/v1/generate';
          requestData = { prompt: data, params: params };
          break;
        // Add cases for other engines
        default:
          outputContainer.html('<p class="error">Unknown engine: ' + engine + '</p>');
          return;
      }

      $.ajax({
        url: playgroundData.restUrl + endpoint,
        method: 'POST',
        beforeSend: function(xhr) {
          xhr.setRequestHeader('X-WP-Nonce', playgroundData.restNonce);
        },
        data: JSON.stringify(requestData),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
          if (response.text || response.job_id) { // Basic check for success
            successCallback(response);
          } else {
            outputContainer.html('<p class="error">API Error: ' + (response.message || 'Unknown error') + '</p>');
          }
        },
        error: function(xhr, status, error) {
          outputContainer.html('<p class="error">API Call Failed: ' + (xhr.responseJSON ? xhr.responseJSON.message : error) + '</p>');
          console.error('API Call Failed:', xhr.responseText);
        }
      });
    },

    pollImageStatus: function(jobId, imgElement) {
      var self = this;
      var pollInterval = setInterval(function() {
        $.ajax({
          url: playgroundData.restUrl + '/aevov-image/v1/status/' + jobId,
          method: 'GET',
          beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', playgroundData.restNonce);
          },
          success: function(response) {
            if (response.status === 'complete') {
              clearInterval(pollInterval);
              $.ajax({
                url: playgroundData.restUrl + '/aevov-image/v1/image/' + jobId,
                method: 'GET', // This will actually trigger a redirect for the browser
                beforeSend: function(xhr) {
                  xhr.setRequestHeader('X-WP-Nonce', playgroundData.restNonce);
                },
                success: function(imageResponse) {
                  // This part needs careful handling as it's a redirect.
                  // For now, we'll assume the browser handles the redirect and loads the image directly.
                  // A more robust solution would involve fetching the presigned URL and setting img src.
                  imgElement.attr('src', imageResponse.url || imageResponse); // Assuming imageResponse is the URL
                },
                error: function(xhr, status, error) {
                  imgElement.closest('.block-content').html('<p class="error">Failed to load image: ' + (xhr.responseJSON ? xhr.responseJSON.message : error) + '</p>');
                }
              });
            } else if (response.status === 'failed') {
              clearInterval(pollInterval);
              imgElement.closest('.block-content').html('<p class="error">Image generation failed.</p>');
            }
          },
          error: function(xhr, status, error) {
            clearInterval(pollInterval);
            imgElement.closest('.block-content').html('<p class="error">Failed to poll image status.</p>');
          }
        });
      }, 3000); // Poll every 3 seconds
    },

    showBlockSettings: function(block) {
      // Implement a modal or inline form to edit block-specific parameters
      // This is crucial for making the playground usable.
      alert('Double-clicked ' + block.data('type') + ' block. Implement settings here!');
    }
  };

  $(document).ready(function () {
    // Localize script data if not already done by PHP
    if (typeof playgroundData === 'undefined') {
      console.warn('playgroundData not localized. Using defaults.');
      window.playgroundData = {
        restUrl: '/wp-json',
        restNonce: 'YOUR_NONCE_HERE', // Replace with actual nonce in production
        ajaxUrl: '/wp-admin/admin-ajax.php'
      };
    }
    Playground.init();
  });

})(jQuery);
