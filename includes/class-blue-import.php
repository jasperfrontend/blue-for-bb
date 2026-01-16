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

        // Convert JSON data back to BB's expected format
        $layout_data = $this->convert_asset_data_to_bb_format($asset['data']);

        if (!$layout_data) {
            wp_die(esc_html__('Failed to convert asset data to Beaver Builder format.', 'blue-for-bb'));
        }

        // Get BB template type
        $bb_type = $this->get_bb_template_type($asset['type']);

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
     * Convert asset data to Beaver Builder format
     */
    private function convert_asset_data_to_bb_format($data) {
        if (!is_array($data)) {
            return false;
        }

        // Top level must be array, child nodes are stdClass objects
        $layout_data = [];

        foreach ($data as $node_id => $node_data) {
            // Convert each node to stdClass object
            $layout_data[$node_id] = json_decode(wp_json_encode($node_data));
        }

        return $layout_data;
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
