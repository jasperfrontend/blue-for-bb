/**
 * Blue Library Page JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle delete button clicks
        $('.blue-delete-asset').on('click', function(e) {
            e.preventDefault();

            const btn = $(this);
            const assetId = btn.data('asset-id');
            const assetTitle = btn.data('asset-title');

            // Confirm deletion
            if (!confirm('Are you sure you want to delete "' + assetTitle + '"? This cannot be undone.')) {
                return;
            }

            // Disable button and show loading state
            btn.prop('disabled', true).text('Deleting...');

            // Send AJAX request
            $.post(blueLibrary.ajaxUrl, {
                action: 'blue_delete_asset',
                nonce: blueLibrary.deleteNonce,
                asset_id: assetId
            })
            .done(function(response) {
                if (response.success) {
                    // Remove row with fade effect
                    btn.closest('tr').fadeOut(400, function() {
                        $(this).remove();
                        updateItemCount();
                    });
                } else {
                    alert('Failed to delete: ' + (response.data.message || 'Unknown error'));
                    btn.prop('disabled', false).text('Delete');
                }
            })
            .fail(function(xhr, status, error) {
                alert('Request failed: ' + error);
                btn.prop('disabled', false).text('Delete');
            });
        });

        /**
         * Update the item count display
         */
        function updateItemCount() {
            const count = $('table.wp-list-table tbody tr').length;
            $('.displaying-num').text(count + ' items');

            // Show empty state if no items left
            if (count === 0) {
                $('table.wp-list-table').replaceWith(
                    '<div class="blue-empty-state">' +
                    '<p>Your library is empty. Start by saving a Beaver Builder layout to Blue!</p>' +
                    '</div>'
                );
            }
        }
    });

})(jQuery);
