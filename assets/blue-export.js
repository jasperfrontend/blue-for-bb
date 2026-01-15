jQuery(document).ready(function($) {
    $('#blue-save-btn').on('click', function() {
        const btn = $(this);
        const statusDiv = $('#blue-export-status');
        
        // Disable button
        btn.prop('disabled', true).text('Saving...');
        statusDiv.removeClass('success error').text('');
        
        // Gather form data
        const data = {
            action: 'blue_save_asset',
            nonce: blueExport.nonce,
            post_id: blueExport.postId,
            title: $('#blue_asset_title').val(),
            description: $('#blue_asset_description').val(),
            tags: $('#blue_asset_tags').val(),
            type: $('#blue_asset_type').val()
        };
        
        // Validate
        if (!data.title.trim()) {
            statusDiv.addClass('error').text('Please enter a title');
            btn.prop('disabled', false).text('Save to Blue');
            return;
        }
        
        // Send AJAX request
        $.post(blueExport.ajaxUrl, data, function(response) {
            if (response.success) {
                statusDiv.addClass('success').text('✓ ' + response.data.message);
                
                // Clear form
                $('#blue_asset_description').val('');
                $('#blue_asset_tags').val('');
                
                // Re-enable button after 2 seconds
                setTimeout(function() {
                    btn.prop('disabled', false).text('Save to Blue');
                }, 2000);
            } else {
                statusDiv.addClass('error').text('✗ ' + response.data.message);
                btn.prop('disabled', false).text('Save to Blue');
            }
        }).fail(function(xhr, status, error) {
            statusDiv.addClass('error').text('✗ Request failed: ' + error);
            btn.prop('disabled', false).text('Save to Blue');
        });
    });
});