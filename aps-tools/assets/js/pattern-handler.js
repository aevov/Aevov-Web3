// File: aps-tools/assets/js/pattern-handler.js
jQuery(document).ready(function($) {
    const hot = window.hotInstance; // Reference to Handsontable instance
    
    $('#generate-patterns').on('click', function() {
        const selectedRows = hot.getSelected();
        if (!selectedRows || !selectedRows.length) {
            alert('Please select chunks to generate patterns');
            return;
        }

        const chunkIds = selectedRows.map(selection => {
            return hot.getDataAtRow(selection[0])[0]; // Get ID from first column
        });

        $(this).prop('disabled', true);
        $('#pattern-generation-status').html('Generating patterns...');

        $.ajax({
            url: apsPatterns.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aps_generate_patterns',
                nonce: apsPatterns.nonce,
                chunk_ids: chunkIds
            },
            success: function(response) {
                if (response.success) {
                    $('#pattern-generation-status').html(
                        `Successfully generated ${response.data.length} patterns`
                    );
                } else {
                    $('#pattern-generation-status').html(
                        `Error: ${response.data}`
                    );
                }
            },
            error: function() {
                $('#pattern-generation-status').html('Error generating patterns');
            },
            complete: function() {
                $('#generate-patterns').prop('disabled', false);
            }
        });
    });
});