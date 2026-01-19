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
     * Check if Beaver Themer is active
     */
    private function is_themer_active() {
        return class_exists('FLThemeBuilderLoader') || defined('FL_THEME_BUILDER_VERSION');
    }

    /**
     * Check if post is a Themer layout
     */
    private function is_themer_layout($post_id) {
        $post = get_post($post_id);
        return $post && $post->post_type === 'fl-theme-layout';
    }

    /**
     * Get Themer layout type for a post
     */
    private function get_themer_layout_type($post_id) {
        $layout_type = get_post_meta($post_id, '_fl_theme_layout_type', true);
        return $layout_type ? $layout_type : 'singular';
    }

    /**
     * Map Themer layout type to Blue asset type
     */
    private function map_themer_type_to_asset_type($layout_type) {
        $type_map = [
            'header' => 'themer-header',
            'footer' => 'themer-footer',
            'singular' => 'themer-singular',
            'archive' => 'themer-archive',
            '404' => 'themer-404',
            'part' => 'themer-part'
        ];
        return isset($type_map[$layout_type]) ? $type_map[$layout_type] : 'themer-singular';
    }

    /**
     * Add meta box for exporting to Blue
     */
    public function add_export_meta_box() {
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        // Check if it's a Themer layout
        if ($this->is_themer_layout($post_id)) {
            // Themer layouts always have BB enabled implicitly
            add_meta_box(
                'blue_export_box',
                'Blue for Beaver Builder',
                [$this, 'render_export_meta_box'],
                'fl-theme-layout',
                'side',
                'high'
            );
            return;
        }

        // Regular BB check for other post types
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

        $is_themer = $this->is_themer_layout($post->ID);
        $themer_layout_type = $is_themer ? $this->get_themer_layout_type($post->ID) : '';
        $default_asset_type = $is_themer ? $this->map_themer_type_to_asset_type($themer_layout_type) : 'template';

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
                        <?php if ($is_themer): ?>
                            <optgroup label="Beaver Themer">
                                <option value="themer-header" <?php selected($default_asset_type, 'themer-header'); ?>>Themer Header</option>
                                <option value="themer-footer" <?php selected($default_asset_type, 'themer-footer'); ?>>Themer Footer</option>
                                <option value="themer-singular" <?php selected($default_asset_type, 'themer-singular'); ?>>Themer Singular</option>
                                <option value="themer-archive" <?php selected($default_asset_type, 'themer-archive'); ?>>Themer Archive</option>
                                <option value="themer-404" <?php selected($default_asset_type, 'themer-404'); ?>>Themer 404</option>
                                <option value="themer-part" <?php selected($default_asset_type, 'themer-part'); ?>>Themer Part</option>
                            </optgroup>
                        <?php else: ?>
                            <optgroup label="Beaver Builder">
                                <option value="template">Full Template</option>
                                <option value="row">Row</option>
                                <option value="column">Column</option>
                                <option value="module">Module</option>
                            </optgroup>
                            <?php if ($this->is_themer_active()): ?>
                            <optgroup label="Beaver Themer">
                                <option value="themer-header">Themer Header</option>
                                <option value="themer-footer">Themer Footer</option>
                                <option value="themer-singular">Themer Singular</option>
                                <option value="themer-archive">Themer Archive</option>
                                <option value="themer-404">Themer 404</option>
                                <option value="themer-part">Themer Part</option>
                            </optgroup>
                            <?php endif; ?>
                        <?php endif; ?>
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

        // Check if this is a Themer layout or has BB enabled
        $is_themer = $this->is_themer_layout($post_id);
        $bb_enabled = get_post_meta($post_id, '_fl_builder_enabled', true);

        if (!$bb_enabled && !$is_themer) {
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

        // Determine asset type - auto-detect for Themer layouts based on post type
        $is_themer_post = $this->is_themer_layout($post_id);
        $user_selected_type = isset($_POST['type']) ? Blue_Validator::sanitize_asset_type($_POST['type']) : 'template';

        // If this is a Themer layout post, ensure we use a Themer asset type
        if ($is_themer_post) {
            // If user selected a non-Themer type, auto-correct to the proper Themer type
            if (!Blue_Validator::is_themer_type($user_selected_type)) {
                $themer_layout_type = $this->get_themer_layout_type($post_id);
                $asset_type = $this->map_themer_type_to_asset_type($themer_layout_type);
            } else {
                $asset_type = $user_selected_type;
            }
        } else {
            // For non-Themer posts, don't allow Themer types
            if (Blue_Validator::is_themer_type($user_selected_type)) {
                $asset_type = 'template';
            } else {
                $asset_type = $user_selected_type;
            }
        }

        // Prepare asset data
        $asset_data = [
            'type' => $asset_type,
            'title' => isset($_POST['title']) ? $_POST['title'] : '',
            'description' => isset($_POST['description']) ? $_POST['description'] : '',
            'tags' => isset($_POST['tags']) ? $_POST['tags'] : '',
            'bb_version' => defined('FL_BUILDER_VERSION') ? FL_BUILDER_VERSION : 'unknown',
            'data' => $layout_data_array,
            'requires' => $this->extract_requirements($layout_data, $asset_type),
            'source_site' => get_site_url(),
            'version' => '1.0.0'
        ];

        // Add Themer-specific metadata if this is a Themer layout
        if (Blue_Validator::is_themer_type($asset_type)) {
            $themer_metadata = $this->get_themer_metadata($post_id);
            if ($themer_metadata) {
                $asset_data['themer_settings'] = $themer_metadata;
            }

            // Add Themer version if available
            if (defined('FL_THEME_BUILDER_VERSION')) {
                $asset_data['themer_version'] = FL_THEME_BUILDER_VERSION;
            }
        }

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
    private function extract_requirements($layout_data, $asset_type = 'template') {
        $requirements = [
            'plugins' => ['beaver-builder'],
            'modules' => []
        ];

        // Add Themer as requirement if it's a Themer asset
        if (Blue_Validator::is_themer_type($asset_type)) {
            $requirements['plugins'][] = 'bb-theme-builder';
        }

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

    /**
     * Get Themer-specific metadata for export
     */
    private function get_themer_metadata($post_id) {
        $metadata = [];

        // Layout type (header, footer, singular, archive, etc.)
        $layout_type = get_post_meta($post_id, '_fl_theme_layout_type', true);
        if ($layout_type) {
            $metadata['layout_type'] = $layout_type;
        }

        // Layout locations (where the layout applies)
        $locations = get_post_meta($post_id, '_fl_theme_builder_locations', true);
        if ($locations && is_array($locations)) {
            $metadata['locations'] = $locations;
        }

        // Layout exclusions
        $exclusions = get_post_meta($post_id, '_fl_theme_builder_exclusions', true);
        if ($exclusions && is_array($exclusions)) {
            $metadata['exclusions'] = $exclusions;
        }

        // Layout settings (sticky, shrink, overlay, etc.)
        $layout_settings = get_post_meta($post_id, '_fl_theme_layout_settings', true);
        if ($layout_settings && is_array($layout_settings)) {
            $metadata['layout_settings'] = $layout_settings;
        }

        // User rules
        $user_rules = get_post_meta($post_id, '_fl_theme_builder_user_rules', true);
        if ($user_rules && is_array($user_rules)) {
            $metadata['user_rules'] = $user_rules;
        }

        // Custom template rules
        $custom_rules = get_post_meta($post_id, '_fl_theme_builder_custom_template_rules', true);
        if ($custom_rules && is_array($custom_rules)) {
            $metadata['custom_template_rules'] = $custom_rules;
        }

        // Conditional logic
        $logic = get_post_meta($post_id, '_fl_theme_builder_logic', true);
        if ($logic && is_array($logic)) {
            $metadata['logic'] = $logic;
        }

        return !empty($metadata) ? $metadata : null;
    }
}
