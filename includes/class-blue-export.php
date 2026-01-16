<?php
/**
 * Export Handler
 *
 * Manages exporting Beaver Builder layouts to Blue cloud
 */

if (!defined('ABSPATH')) {
    exit;
}

class Blue_Export {

    private $api_client;

    public function __construct(Blue_API_Client $api_client) {
        $this->api_client = $api_client;
    }

    /**
     * Initialize export hooks
     */
    public function init() {
        add_action('add_meta_boxes', [$this, 'add_export_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_blue_save_asset', [$this, 'ajax_save_asset']);
    }

    /**
     * Add meta box for exporting to Blue
     */
    public function add_export_meta_box() {
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        $bb_enabled = get_post_meta($post_id, '_fl_builder_enabled', true);
        if (!$bb_enabled) {
            return;
        }

        add_meta_box(
            'blue_export_box',
            'Blue for Beaver Builder',
            [$this, 'render_export_meta_box'],
            null,
            'side',
            'high'
        );
    }

    /**
     * Render export meta box
     */
    public function render_export_meta_box($post) {
        $api_key = get_option('blue_api_key', '');

        if (empty($api_key)) {
            ?>
            <p>Please <a href="<?php echo esc_url(admin_url('admin.php?page=blue-settings')); ?>">configure your API key</a> first.</p>
            <?php
            return;
        }

        wp_nonce_field('blue_export', 'blue_export_nonce');
        ?>
        <div id="blue-export-container">
            <p><strong>Save this layout to your Blue library</strong></p>

            <div id="blue-export-form">
                <p>
                    <label for="blue_asset_title">Title</label>
                    <input
                        type="text"
                        id="blue_asset_title"
                        class="widefat"
                        value="<?php echo esc_attr(get_the_title($post)); ?>"
                        maxlength="200"
                    />
                </p>

                <p>
                    <label for="blue_asset_description">Description</label>
                    <textarea
                        id="blue_asset_description"
                        class="widefat"
                        rows="3"
                        placeholder="Optional description"
                        maxlength="1000"
                    ></textarea>
                </p>

                <p>
                    <label for="blue_asset_tags">Tags (comma-separated)</label>
                    <input
                        type="text"
                        id="blue_asset_tags"
                        class="widefat"
                        placeholder="e.g. woocommerce, header, hero"
                        maxlength="500"
                    />
                </p>

                <p>
                    <label for="blue_asset_type">Asset Type</label>
                    <select id="blue_asset_type" class="widefat">
                        <option value="template">Full Template</option>
                        <option value="row">Row</option>
                        <option value="column">Column</option>
                        <option value="module">Module</option>
                    </select>
                </p>

                <p>
                    <button type="button" id="blue-save-btn" class="button button-primary button-large" style="width: 100%;">
                        Save to Blue
                    </button>
                </p>

                <div id="blue-export-status" style="margin-top: 10px;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        $bb_enabled = get_post_meta($post_id, '_fl_builder_enabled', true);
        if (!$bb_enabled) {
            return;
        }

        wp_enqueue_script(
            'blue-export',
            BLUE_PLUGIN_URL . 'assets/js/blue-export.js',
            ['jquery'],
            BLUE_VERSION,
            true
        );

        wp_localize_script('blue-export', 'blueExport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('blue_export'),
            'postId' => absint($post_id)
        ]);
    }

    /**
     * AJAX handler for saving asset
     */
    public function ajax_save_asset() {
        check_ajax_referer('blue_export', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(['message' => 'Invalid post ID']);
            return;
        }

        // Get BB layout data
        $layout_data = get_post_meta($post_id, '_fl_builder_data', true);
        if (empty($layout_data)) {
            wp_send_json_error(['message' => 'No Beaver Builder layout data found. Please save your layout in Beaver Builder first.']);
            return;
        }

        // Validate layout data before conversion
        if (!Blue_Validator::validate_layout_data($layout_data)) {
            wp_send_json_error(['message' => 'Layout data is invalid or too large.']);
            return;
        }

        // Convert PHP objects to arrays for JSON encoding
        $layout_data_array = json_decode(wp_json_encode($layout_data), true);

        // Prepare asset data
        $asset_data = [
            'type' => isset($_POST['type']) ? $_POST['type'] : 'template',
            'title' => isset($_POST['title']) ? $_POST['title'] : '',
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'tags' => isset($_POST['tags']) ? $_POST['tags'] : '',
            'bb_version' => defined('FL_BUILDER_VERSION') ? FL_BUILDER_VERSION : 'unknown',
            'data' => $layout_data_array,
            'requires' => $this->extract_requirements($layout_data),
            'source_site' => get_site_url(),
            'version' => '1.0.0'
        ];

        // Create asset via API client
        $result = $this->api_client->create_asset($asset_data);

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => 'Failed to save asset: ' . $result->get_error_message()
            ]);
            return;
        }

        wp_send_json_success([
            'message' => 'Asset saved successfully!',
            'asset_id' => $result['id'] ?? null
        ]);
    }

    /**
     * Extract requirements from layout data
     */
    private function extract_requirements($layout_data) {
        $requirements = [
            'plugins' => ['beaver-builder'],
            'modules' => []
        ];

        if (!is_array($layout_data) && !is_object($layout_data)) {
            return $requirements;
        }

        // Walk through layout data to find module types
        foreach ($layout_data as $node_id => $node) {
            if (is_object($node) && isset($node->type) && $node->type === 'module') {
                $module_type = isset($node->settings->type) ? $node->settings->type : 'unknown';
                if (!in_array($module_type, $requirements['modules'], true)) {
                    $requirements['modules'][] = sanitize_text_field($module_type);
                }
            }
        }

        return $requirements;
    }
}
