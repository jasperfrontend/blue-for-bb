<?php
/**
 * Import Handler
 *
 * Manages importing assets from Blue cloud to Beaver Builder
 */

if (!defined('ABSPATH')) {
    exit;
}

class Blue_Import {

    private $api_client;

    public function __construct(Blue_API_Client $api_client) {
        $this->api_client = $api_client;
    }

    /**
     * Initialize import hooks
     */
    public function init() {
        add_action('admin_init', [$this, 'handle_import_action']);
    }

    /**
     * Handle import action
     */
    public function handle_import_action() {
        // Only process on library page
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        if ($current_page !== 'blue-library') {
            return;
        }

        // Only process import action
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        if ($action !== 'import') {
            return;
        }

        // Validate asset ID
        $asset_id = isset($_GET['asset_id']) ? sanitize_text_field($_GET['asset_id']) : '';
        if (empty($asset_id)) {
            wp_die(esc_html__('Missing asset ID.', 'blue-for-bb'));
        }

        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'blue_import_' . $asset_id)) {
            wp_die(esc_html__('Security check failed.', 'blue-for-bb'));
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Insufficient permissions.', 'blue-for-bb'));
        }

        // Perform import
        $this->import_asset($asset_id);
    }

    /**
     * Check if asset type is a Themer type
     */
    private function is_themer_asset($asset_type) {
        return Blue_Validator::is_themer_type($asset_type);
    }

    /**
     * Detect if an asset is a Themer layout based on available data
     * This helps identify legacy assets that were exported before proper type detection
     */
    private function detect_themer_asset($asset) {
        // Check if it has themer_settings
        if (isset($asset['themer_settings']) && !empty($asset['themer_settings'])) {
            return true;
        }

        // Check if requires bb-theme-builder
        if (isset($asset['requires']['plugins']) && is_array($asset['requires']['plugins'])) {
            if (in_array('bb-theme-builder', $asset['requires']['plugins'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Infer Themer type from asset data
     */
    private function infer_themer_type($asset) {
        $type_map = [
            'header' => 'themer-header',
            'footer' => 'themer-footer',
            'singular' => 'themer-singular',
            'archive' => 'themer-archive',
            '404' => 'themer-404',
            'part' => 'themer-part'
        ];

        // Get from themer_settings.layout_type
        if (isset($asset['themer_settings']['layout_type'])) {
            $layout_type = $asset['themer_settings']['layout_type'];
            return isset($type_map[$layout_type]) ? $type_map[$layout_type] : 'themer-singular';
        }

        // Default to singular if we can't determine
        return 'themer-singular';
    }

    /**
     * Import asset from API
     */
    private function import_asset($asset_id) {
        // Fetch asset from API
        $asset = $this->api_client->get_asset($asset_id);

        if (is_wp_error($asset)) {
            wp_die('Failed to fetch asset: ' . esc_html($asset->get_error_message()));
        }

        // Validate asset data
        if (!isset($asset['data']) || !isset($asset['title'])) {
            wp_die(esc_html__('Invalid asset data received from API.', 'blue-for-bb'));
        }

        $asset_type = isset($asset['type']) && !empty($asset['type']) ? $asset['type'] : 'template';

        // Check if this is a Themer asset - either by explicit type or by detection
        $is_themer = $this->is_themer_asset($asset_type);

        // For legacy assets without proper Themer type, detect if it's actually a Themer layout
        if (!$is_themer && $this->detect_themer_asset($asset)) {
            $is_themer = true;
            $asset_type = $this->infer_themer_type($asset);
        }

        // Check if this is a Themer asset and if Themer is available
        if ($is_themer) {
            if (!$this->is_themer_available()) {
                wp_die(esc_html__('This asset requires Beaver Themer to be installed and active.', 'blue-for-bb'));
            }
            return $this->import_themer_asset($asset, $asset_id, $asset_type);
        }

        // Convert JSON data back to BB's expected format
        $layout_data = $this->convert_asset_data_to_bb_format($asset['data']);

        if (!$layout_data) {
            wp_die(esc_html__('Failed to convert asset data to Beaver Builder format.', 'blue-for-bb'));
        }

        // Get BB template type
        $bb_type = $this->get_bb_template_type($asset_type);

        // Create BB saved template
        $post_id = wp_insert_post([
            'post_title' => Blue_Validator::sanitize_title($asset['title']),
            'post_status' => 'publish',
            'post_type' => 'fl-builder-template',
            'post_content' => isset($asset['description']) ? Blue_Validator::sanitize_description($asset['description']) : ''
        ]);

        if (is_wp_error($post_id)) {
            wp_die('Failed to create template: ' . esc_html($post_id->get_error_message()));
        }

        // Set template type taxonomy
        wp_set_object_terms($post_id, $bb_type, 'fl-builder-template-type');

        // Save layout data (must be PHP serialized)
        update_post_meta($post_id, '_fl_builder_data', $layout_data);

        // Set as BB enabled
        update_post_meta($post_id, '_fl_builder_enabled', true);

        // Add BB template meta
        update_post_meta($post_id, '_fl_builder_template_id', uniqid());
        update_post_meta($post_id, '_fl_builder_template_global', '0');
        update_post_meta($post_id, '_fl_builder_template_dynamic_editing', '0');

        // Add Blue metadata for reference
        update_post_meta($post_id, '_blue_asset_id', sanitize_text_field($asset_id));
        update_post_meta($post_id, '_blue_imported_at', current_time('mysql'));

        // Redirect to BB's saved templates list
        $redirect_url = admin_url('edit.php?post_type=fl-builder-template&fl-builder-template-type=' . $bb_type);

        // Add success message via transient
        set_transient('blue_import_success_' . get_current_user_id(), [
            'title' => $asset['title'],
            'type' => $bb_type
        ], 30);

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Check if Beaver Themer is available
     */
    private function is_themer_available() {
        return class_exists('FLThemeBuilderLoader') || defined('FL_THEME_BUILDER_VERSION');
    }

    /**
     * Import a Themer layout asset
     *
     * @param array  $asset       The asset data from the API
     * @param string $asset_id    The asset ID
     * @param string $asset_type  The resolved asset type (may be inferred for legacy assets)
     */
    private function import_themer_asset($asset, $asset_id, $asset_type = null) {
        // Convert JSON data back to BB's expected format
        $layout_data = $this->convert_asset_data_to_bb_format($asset['data']);

        if (!$layout_data) {
            wp_die(esc_html__('Failed to convert asset data to Beaver Builder format.', 'blue-for-bb'));
        }

        // Use passed asset_type if available, otherwise fall back to asset['type']
        $resolved_type = $asset_type ?? (isset($asset['type']) ? $asset['type'] : 'themer-singular');

        // Get the Themer layout type from asset type
        $themer_layout_type = $this->get_themer_layout_type($resolved_type);

        // Create Themer layout post
        $post_id = wp_insert_post([
            'post_title' => Blue_Validator::sanitize_title($asset['title']),
            'post_status' => 'publish',
            'post_type' => 'fl-theme-layout',
            'post_content' => isset($asset['description']) ? Blue_Validator::sanitize_description($asset['description']) : ''
        ]);

        if (is_wp_error($post_id)) {
            wp_die('Failed to create Themer layout: ' . esc_html($post_id->get_error_message()));
        }

        // Save layout data
        update_post_meta($post_id, '_fl_builder_data', $layout_data);
        update_post_meta($post_id, '_fl_builder_draft', $layout_data);

        // Set as BB enabled
        update_post_meta($post_id, '_fl_builder_enabled', true);

        // Set Themer layout type
        update_post_meta($post_id, '_fl_theme_layout_type', $themer_layout_type);

        // Import Themer-specific settings if available
        if (isset($asset['themer_settings']) && is_array($asset['themer_settings'])) {
            $this->import_themer_settings($post_id, $asset['themer_settings']);
        } else {
            // Set default empty Themer metadata
            $this->set_default_themer_metadata($post_id);
        }

        // Add Blue metadata for reference
        update_post_meta($post_id, '_blue_asset_id', sanitize_text_field($asset_id));
        update_post_meta($post_id, '_blue_imported_at', current_time('mysql'));

        // Redirect to Themer layouts list
        $redirect_url = admin_url('edit.php?post_type=fl-theme-layout');

        // Add success message via transient
        set_transient('blue_import_success_' . get_current_user_id(), [
            'title' => $asset['title'],
            'type' => 'themer-' . $themer_layout_type,
            'is_themer' => true
        ], 30);

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Map asset type to Themer layout type
     */
    private function get_themer_layout_type($asset_type) {
        $type_map = [
            'themer-header' => 'header',
            'themer-footer' => 'footer',
            'themer-singular' => 'singular',
            'themer-archive' => 'archive',
            'themer-404' => '404',
            'themer-part' => 'part'
        ];
        return isset($type_map[$asset_type]) ? $type_map[$asset_type] : 'singular';
    }

    /**
     * Import Themer-specific settings
     */
    private function import_themer_settings($post_id, $settings) {
        // Layout locations
        if (isset($settings['locations']) && is_array($settings['locations'])) {
            update_post_meta($post_id, '_fl_theme_builder_locations', $settings['locations']);
        } else {
            update_post_meta($post_id, '_fl_theme_builder_locations', []);
        }

        // Layout exclusions
        if (isset($settings['exclusions']) && is_array($settings['exclusions'])) {
            update_post_meta($post_id, '_fl_theme_builder_exclusions', $settings['exclusions']);
        } else {
            update_post_meta($post_id, '_fl_theme_builder_exclusions', []);
        }

        // Layout settings (sticky, shrink, overlay, etc.)
        if (isset($settings['layout_settings']) && is_array($settings['layout_settings'])) {
            update_post_meta($post_id, '_fl_theme_layout_settings', $settings['layout_settings']);
        } else {
            update_post_meta($post_id, '_fl_theme_layout_settings', [
                'sticky' => '0',
                'sticky-on' => '',
                'shrink' => '0',
                'overlay' => '0',
                'overlay_bg' => 'transparent'
            ]);
        }

        // User rules
        if (isset($settings['user_rules']) && is_array($settings['user_rules'])) {
            update_post_meta($post_id, '_fl_theme_builder_user_rules', $settings['user_rules']);
        } else {
            update_post_meta($post_id, '_fl_theme_builder_user_rules', []);
        }

        // Custom template rules
        if (isset($settings['custom_template_rules']) && is_array($settings['custom_template_rules'])) {
            update_post_meta($post_id, '_fl_theme_builder_custom_template_rules', $settings['custom_template_rules']);
        } else {
            update_post_meta($post_id, '_fl_theme_builder_custom_template_rules', []);
        }

        // Conditional logic
        if (isset($settings['logic']) && is_array($settings['logic'])) {
            update_post_meta($post_id, '_fl_theme_builder_logic', $settings['logic']);
        } else {
            update_post_meta($post_id, '_fl_theme_builder_logic', []);
        }
    }

    /**
     * Set default Themer metadata for imported layouts
     */
    private function set_default_themer_metadata($post_id) {
        update_post_meta($post_id, '_fl_theme_builder_locations', []);
        update_post_meta($post_id, '_fl_theme_builder_exclusions', []);
        update_post_meta($post_id, '_fl_theme_builder_user_rules', []);
        update_post_meta($post_id, '_fl_theme_builder_custom_template_rules', []);
        update_post_meta($post_id, '_fl_theme_builder_logic', []);
        update_post_meta($post_id, '_fl_theme_layout_settings', [
            'sticky' => '0',
            'sticky-on' => '',
            'shrink' => '0',
            'overlay' => '0',
            'overlay_bg' => 'transparent'
        ]);
    }

    /**
     * Convert asset data to Beaver Builder format
     */
    private function convert_asset_data_to_bb_format($data) {
        if (!is_array($data)) {
            return false;
        }

        // Top level must be array, child nodes are stdClass objects
        $layout_data = [];

        foreach ($data as $node_id => $node_data) {
            // Convert each node to stdClass object, but preserve arrays in settings
            $layout_data[$node_id] = $this->convert_node_to_object($node_data);
        }

        return $layout_data;
    }

    /**
     * Convert a node to stdClass while preserving array structures in settings
     *
     * Beaver Builder expects nodes to be stdClass objects, but certain nested
     * settings (like typography fields) must remain as arrays because BB accesses
     * them with array syntax: $module->settings->typography_field['font_family']
     */
    private function convert_node_to_object($node_data) {
        if (!is_array($node_data)) {
            return $node_data;
        }

        $node = new stdClass();

        foreach ($node_data as $key => $value) {
            if ($key === 'settings' && is_array($value)) {
                // Settings need special handling - convert to object but preserve nested arrays
                $node->$key = $this->convert_settings_to_object($value);
            } elseif (is_array($value) && $this->is_associative_array($value)) {
                // Recursively convert associative arrays to objects (for node structure)
                $node->$key = $this->convert_node_to_object($value);
            } else {
                $node->$key = $value;
            }
        }

        return $node;
    }

    /**
     * Convert settings array to stdClass while preserving nested arrays
     *
     * Typography, shadow, border, and other complex field types in BB are stored
     * as arrays and accessed with array syntax. We must preserve these.
     */
    private function convert_settings_to_object($settings) {
        if (!is_array($settings)) {
            return $settings;
        }

        $obj = new stdClass();

        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                // Keep arrays as arrays - BB accesses these with bracket notation
                // This includes typography, shadows, borders, margins, padding, etc.
                $obj->$key = $value;
            } else {
                $obj->$key = $value;
            }
        }

        return $obj;
    }

    /**
     * Check if an array is associative (has string keys)
     */
    private function is_associative_array($arr) {
        if (!is_array($arr) || empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Map Blue asset type to BB template type
     */
    private function get_bb_template_type($asset_type) {
        $type_map = [
            'template' => 'layout',
            'row' => 'row',
            'column' => 'column',
            'module' => 'module'
        ];

        return isset($type_map[$asset_type]) ? $type_map[$asset_type] : 'layout';
    }
}
