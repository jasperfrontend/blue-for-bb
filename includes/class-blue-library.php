<?php
/**
 * Library Page Handler
 *
 * Displays and manages the asset library
 */

if (!defined('ABSPATH')) {
    exit;
}

class Blue_Library {

    private $api_client;

    public function __construct(Blue_API_Client $api_client) {
        $this->api_client = $api_client;
    }

    /**
     * Initialize library hooks
     */
    public function init() {
        add_action('admin_menu', [$this, 'register_library_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_blue_delete_asset', [$this, 'ajax_delete_asset']);
    }

    /**
     * Register library page callback
     */
    public function register_library_page() {
        // Update the callback for the blue-library page
        global $submenu;
        if (isset($submenu['blue-library'])) {
            foreach ($submenu['blue-library'] as $key => $item) {
                if ($item[2] === 'blue-library') {
                    $submenu['blue-library'][$key][2] = 'blue-library';
                }
            }
        }

        add_submenu_page(
            'blue-library',
            'Library',
            'Library',
            'edit_posts',
            'blue-library',
            [$this, 'render_library_page'],
            0 // Position at top
        );
    }

    /**
     * Enqueue library page scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_blue-library') {
            return;
        }

        wp_enqueue_style(
            'blue-admin',
            BLUE_PLUGIN_URL . 'assets/css/blue-admin.css',
            [],
            BLUE_VERSION
        );

        wp_enqueue_script(
            'blue-library',
            BLUE_PLUGIN_URL . 'assets/js/blue-library.js',
            ['jquery'],
            BLUE_VERSION,
            true
        );

        wp_localize_script('blue-library', 'blueLibrary', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'deleteNonce' => wp_create_nonce('blue_delete_asset')
        ]);
    }

    /**
     * Render library page
     */
    public function render_library_page() {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blue-for-bb'));
        }

        $api_key = get_option('blue_api_key', '');
        if (empty($api_key)) {
            ?>
            <div class="wrap">
                <h1>Blue Library</h1>
                <div class="notice notice-warning">
                    <p>
                        Please <a href="<?php echo esc_url(admin_url('admin.php?page=blue-settings')); ?>">configure your API key</a> first.
                    </p>
                </div>
            </div>
            <?php
            return;
        }

        // Get and sanitize filter params
        $type_filter = isset($_GET['type']) ? Blue_Validator::sanitize_asset_type($_GET['type']) : '';
        $search = isset($_GET['s']) ? Blue_Validator::sanitize_search_query($_GET['s']) : '';

        // Fetch assets from API
        $filters = [];
        if ($type_filter) {
            $filters['type'] = $type_filter;
        }

        $assets = $this->api_client->get_assets($filters);
        $error_message = null;

        if (is_wp_error($assets)) {
            $error_message = 'Failed to fetch assets: ' . $assets->get_error_message();
            $assets = [];
        }

        // Client-side search filter
        if ($search && !empty($assets)) {
            $assets = $this->filter_assets_by_search($assets, $search);
        }

        $this->render_library_html($assets, $error_message, $type_filter, $search);
    }

    /**
     * Filter assets by search query
     */
    private function filter_assets_by_search($assets, $search) {
        $search_lower = strtolower($search);

        return array_filter($assets, function($asset) use ($search_lower) {
            // Search in title
            if (stripos($asset['title'], $search_lower) !== false) {
                return true;
            }

            // Search in description
            if (isset($asset['description']) && stripos($asset['description'], $search_lower) !== false) {
                return true;
            }

            // Search in tags
            if (isset($asset['tags']) && is_array($asset['tags'])) {
                foreach ($asset['tags'] as $tag) {
                    if (stripos($tag, $search_lower) !== false) {
                        return true;
                    }
                }
            }

            return false;
        });
    }

    /**
     * Render library HTML
     */
    private function render_library_html($assets, $error_message, $type_filter, $search) {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Blue Library</h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=blue-settings')); ?>" class="page-title-action">Settings</a>
            <hr class="wp-header-end">

            <?php if ($error_message): ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($error_message); ?></p>
                </div>
            <?php endif; ?>

            <?php $this->render_filters($type_filter, $search); ?>

            <?php if (empty($assets)): ?>
                <div class="blue-empty-state">
                    <p>
                        <?php if ($search || $type_filter): ?>
                            No assets found matching your filters.
                        <?php else: ?>
                            Your library is empty. Start by saving a Beaver Builder layout to Blue!
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php $this->render_assets_table($assets); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render filter controls
     */
    private function render_filters($type_filter, $search) {
        ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" style="display: inline-block;">
                    <input type="hidden" name="page" value="blue-library">

                    <select name="type" id="filter-by-type">
                        <option value="">All Types</option>
                        <option value="template" <?php selected($type_filter, 'template'); ?>>Template</option>
                        <option value="row" <?php selected($type_filter, 'row'); ?>>Row</option>
                        <option value="column" <?php selected($type_filter, 'column'); ?>>Column</option>
                        <option value="module" <?php selected($type_filter, 'module'); ?>>Module</option>
                    </select>

                    <input type="submit" class="button" value="Filter">

                    <?php if ($type_filter): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=blue-library')); ?>" class="button">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="alignleft actions">
                <form method="get">
                    <input type="hidden" name="page" value="blue-library">
                    <?php if ($type_filter): ?>
                        <input type="hidden" name="type" value="<?php echo esc_attr($type_filter); ?>">
                    <?php endif; ?>
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search assets..." maxlength="100">
                    <input type="submit" class="button" value="Search">
                </form>
            </div>

            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo absint(count($assets)); ?> items</span>
            </div>
        </div>
        <?php
    }

    /**
     * Render assets table
     */
    private function render_assets_table($assets) {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 40%;">Title</th>
                    <th style="width: 10%;">Type</th>
                    <th style="width: 20%;">Tags</th>
                    <th style="width: 15%;">BB Version</th>
                    <th style="width: 15%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assets as $asset): ?>
                    <?php $this->render_asset_row($asset); ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render single asset row
     */
    private function render_asset_row($asset) {
        $asset_id = isset($asset['id']) ? $asset['id'] : '';
        $title = isset($asset['title']) ? $asset['title'] : 'Untitled';
        $description = isset($asset['description']) ? $asset['description'] : '';
        $source_site = isset($asset['source_site']) ? $asset['source_site'] : '';
        $type = isset($asset['type']) ? $asset['type'] : 'template';
        $tags = isset($asset['tags']) && is_array($asset['tags']) ? $asset['tags'] : [];
        $bb_version = isset($asset['bb_version']) ? $asset['bb_version'] : 'unknown';
        ?>
        <tr>
            <td>
                <strong><?php echo esc_html($title); ?></strong>
                <?php if ($description): ?>
                    <br><small class="description"><?php echo esc_html($description); ?></small>
                <?php endif; ?>
                <?php if ($source_site): ?>
                    <br><small class="source">Source: <?php echo esc_html($source_site); ?></small>
                <?php endif; ?>
            </td>
            <td>
                <span class="blue-type-badge blue-type-<?php echo esc_attr($type); ?>">
                    <?php echo esc_html(ucfirst($type)); ?>
                </span>
            </td>
            <td>
                <?php if (!empty($tags)): ?>
                    <?php foreach ($tags as $tag): ?>
                        <span class="blue-tag"><?php echo esc_html($tag); ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="no-tags">&mdash;</span>
                <?php endif; ?>
            </td>
            <td>
                <code><?php echo esc_html($bb_version); ?></code>
            </td>
            <td>
                <a href="<?php echo esc_url(wp_nonce_url(
                    admin_url('admin.php?page=blue-library&action=import&asset_id=' . urlencode($asset_id)),
                    'blue_import_' . $asset_id
                )); ?>" class="button button-primary button-small">
                    Import
                </a>
                <button
                    class="button button-small blue-delete-asset"
                    data-asset-id="<?php echo esc_attr($asset_id); ?>"
                    data-asset-title="<?php echo esc_attr($title); ?>"
                >
                    Delete
                </button>
            </td>
        </tr>
        <?php
    }

    /**
     * AJAX handler for deleting asset
     */
    public function ajax_delete_asset() {
        check_ajax_referer('blue_delete_asset', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        $asset_id = isset($_POST['asset_id']) ? sanitize_text_field($_POST['asset_id']) : '';
        if (empty($asset_id)) {
            wp_send_json_error(['message' => 'Invalid asset ID']);
            return;
        }

        // Validate asset ID format
        if (!Blue_Validator::validate_asset_id($asset_id)) {
            wp_send_json_error(['message' => 'Invalid asset ID format']);
            return;
        }

        // Delete via API client
        $result = $this->api_client->delete_asset($asset_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => 'Failed to delete: ' . $result->get_error_message()]);
            return;
        }

        wp_send_json_success(['message' => 'Asset deleted']);
    }
}
