/**
 * Blue Library Page JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle sync button click
        $('#blue-sync-btn').on('click', function(e) {
            e.preventDefault();

            const btn = $(this);
            if (btn.hasClass('syncing')) {
                return;
            }

            btn.addClass('syncing');
            const originalText = btn.html();
            btn.html('<span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 3px;"></span> Syncing...');

            $.post(blueLibrary.ajaxUrl, {
                action: 'blue_refresh_assets',
                nonce: blueLibrary.refreshNonce
            })
            .done(function(response) {
                if (response.success) {
                    // Update cache time display
                    if (response.data.cached_at) {
                        $('#blue-cache-time').text(response.data.cached_at);
                    }
                    // Reload page to show fresh data
                    location.reload();
                } else {
                    alert('Sync failed: ' + (response.data.message || 'Unknown error'));
                    btn.removeClass('syncing').html(originalText);
                }
            })
            .fail(function(xhr, status, error) {
                alert('Sync request failed: ' + error);
                btn.removeClass('syncing').html(originalText);
            });
        });

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
