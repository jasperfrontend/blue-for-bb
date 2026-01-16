<?php
/**
 * Plugin Name: Blue for Beaver Builder
 * Description: Cloud library for Beaver Builder layouts
 * Version: 0.1.2
 * Author: Jasper
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BLUE_VERSION', '0.1.0');
define('BLUE_API_URL', 'https://assets.blueforbb.com/api');
define('BLUE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BLUE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class Blue_For_BB {
    
    private static $instance = null;
    
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
        
        // Export hooks
        add_action('add_meta_boxes', [$this, 'add_export_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_blue_save_asset', [$this, 'ajax_save_asset']);
        
        // Import hooks
        add_action('admin_init', [$this, 'handle_import_action']);
        add_action('wp_ajax_blue_delete_asset', [$this, 'ajax_delete_asset']);
    }
    
    /**
     * Add settings page to WordPress admin
     */
    public function add_admin_menu() {
        // Main library page
        add_menu_page(
            'Blue Library',
            'Blue Library',
            'edit_posts',
            'blue-library',
            [$this, 'render_library_page'],
            'dashicons-cloud',
            30
        );
        
        // Settings as submenu
        add_submenu_page(
            'blue-library',
            'Blue Settings',
            'Settings',
            'manage_options',
            'blue-settings',
            [$this, 'render_settings_page']
        );
        
        // Also keep it under Settings for discoverability
        add_options_page(
            'Blue for BB Settings',
            'Blue for BB',
            'manage_options',
            'blue-for-bb',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('blue_settings', 'blue_api_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        add_settings_section(
            'blue_main_section',
            'API Configuration',
            [$this, 'render_section_info'],
            'blue-for-bb'
        );
        
        add_settings_field(
            'blue_api_key',
            'API Key',
            [$this, 'render_api_key_field'],
            'blue-for-bb',
            'blue_main_section'
        );
    }
    
    /**
     * Render settings section description
     */
    public function render_section_info() {
        echo '<p>Enter your Blue API key to connect this site to your cloud library.</p>';
    }
    
    /**
     * Render API key input field
     */
    public function render_api_key_field() {
        $api_key = get_option('blue_api_key', '');
        ?>
        <input 
            type="text" 
            name="blue_api_key" 
            value="<?php echo esc_attr($api_key); ?>" 
            class="regular-text"
            placeholder="Enter your API key"
        />
        <p class="description">
            Get your API key from your Blue dashboard at 
            <code><?php echo esc_html(BLUE_API_URL); ?></code>
        </p>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle connection test
        $test_result = null;
        if (isset($_POST['test_connection']) && check_admin_referer('blue_test_connection')) {
            $test_result = $this->test_api_connection();
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if ($test_result !== null): ?>
                <div class="notice notice-<?php echo $test_result['success'] ? 'success' : 'error'; ?> is-dismissible">
                    <p><?php echo esc_html($test_result['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('blue_settings');
                do_settings_sections('blue-for-bb');
                submit_button('Save Settings');
                ?>
            </form>
            
            <hr>
            
            <h2>Connection Test</h2>
            <p>Test your API connection to verify everything is working.</p>
            <form method="post">
                <?php wp_nonce_field('blue_test_connection'); ?>
                <input type="submit" name="test_connection" class="button button-secondary" value="Test Connection">
            </form>
            
            <hr>
            
            <h2>Debug Information</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Plugin Version</th>
                    <td><code><?php echo esc_html(BLUE_VERSION); ?></code></td>
                </tr>
                <tr>
                    <th scope="row">API Endpoint</th>
                    <td><code><?php echo esc_html(BLUE_API_URL); ?></code></td>
                </tr>
                <tr>
                    <th scope="row">API Key Status</th>
                    <td>
                        <?php 
                        $api_key = get_option('blue_api_key', '');
                        if (empty($api_key)) {
                            echo '<span style="color: #dc3232;">❌ Not configured</span>';
                        } else {
                            echo '<span style="color: #46b450;">✓ Configured</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Beaver Builder</th>
                    <td>
                        <?php 
                        if (class_exists('FLBuilder')) {
                            echo '<span style="color: #46b450;">✓ Active</span>';
                            if (defined('FL_BUILDER_VERSION')) {
                                echo ' <code>v' . esc_html(FL_BUILDER_VERSION) . '</code>';
                            }
                        } else {
                            echo '<span style="color: #dc3232;">❌ Not detected</span>';
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Test API connection
     */
    private function test_api_connection() {
        $api_key = get_option('blue_api_key', '');
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => 'Please enter an API key first.'
            ];
        }
        
        $response = wp_remote_get(BLUE_API_URL . '/assets', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            $data = json_decode($body, true);
            $asset_count = isset($data['assets']) ? count($data['assets']) : 0;
            
            return [
                'success' => true,
                'message' => "✓ Connection successful! Found {$asset_count} assets in your library."
            ];
        } elseif ($status_code === 401) {
            return [
                'success' => false,
                'message' => 'Authentication failed. Please check your API key.'
            ];
        } else {
            return [
                'success' => false,
                'message' => "API returned status code {$status_code}. Response: " . substr($body, 0, 100)
            ];
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if Beaver Builder is active
        if (!class_exists('FLBuilder')) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong>Blue for Beaver Builder:</strong> 
                    Beaver Builder is not detected. This plugin requires Beaver Builder to be installed and active.
                </p>
            </div>
            <?php
        }
        
        // Check if API key is configured
        $api_key = get_option('blue_api_key', '');
        if (empty($api_key) && isset($_GET['page']) && $_GET['page'] !== 'blue-for-bb') {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>Blue for Beaver Builder:</strong> 
                    Please <a href="<?php echo admin_url('options-general.php?page=blue-for-bb'); ?>">configure your API key</a> to start using Blue.
                </p>
            </div>
            <?php
        }
        
        // Show import success message
        $import_success = get_transient('blue_import_success_' . get_current_user_id());
        if ($import_success) {
            delete_transient('blue_import_success_' . get_current_user_id());
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>✓ Import successful!</strong> 
                    "<?php echo esc_html($import_success['title']); ?>" has been added to your Saved <?php echo esc_html(ucfirst($import_success['type'])); ?>s.
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Add meta box for exporting to Blue
     */
    public function add_export_meta_box() {
        // Only add if Beaver Builder is active on this post
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
            <p>Please <a href="<?php echo admin_url('options-general.php?page=blue-for-bb'); ?>">configure your API key</a> first.</p>
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
                    />
                </p>
                
                <p>
                    <label for="blue_asset_description">Description</label>
                    <textarea 
                        id="blue_asset_description" 
                        class="widefat" 
                        rows="3"
                        placeholder="Optional description"
                    ></textarea>
                </p>
                
                <p>
                    <label for="blue_asset_tags">Tags (comma-separated)</label>
                    <input 
                        type="text" 
                        id="blue_asset_tags" 
                        class="widefat" 
                        placeholder="e.g. woocommerce, header, hero"
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
        
        <style>
            #blue-export-status.success {
                padding: 8px;
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
                border-radius: 4px;
            }
            #blue-export-status.error {
                padding: 8px;
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
                border-radius: 4px;
            }
        </style>
        <?php
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on post edit screens with BB enabled
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
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
            BLUE_PLUGIN_URL . 'assets/blue-export.js',
            ['jquery'],
            BLUE_VERSION,
            true
        );
        
        wp_localize_script('blue-export', 'blueExport', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('blue_export'),
            'postId' => $post_id
        ]);
    }
    
    /**
     * AJAX handler for saving asset
     */
    public function ajax_save_asset() {
        check_ajax_referer('blue_export', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(['message' => 'Invalid post ID']);
        }
        
        // Get BB layout data (this is a PHP serialized array/object)
        $layout_data = get_post_meta($post_id, '_fl_builder_data', true);
        if (empty($layout_data)) {
            wp_send_json_error(['message' => 'No Beaver Builder layout data found. Please save your layout in Beaver Builder first.']);
        }
        
        // Convert PHP objects to arrays for JSON encoding
        $layout_data_array = json_decode(json_encode($layout_data), true);
        
        // Prepare asset data
        $asset_data = [
            'type' => sanitize_text_field($_POST['type'] ?? 'template'),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'tags' => array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))),
            'bb_version' => defined('FL_BUILDER_VERSION') ? FL_BUILDER_VERSION : 'unknown',
            'data' => $layout_data_array, // Send as array, will be JSON encoded
            'requires' => $this->extract_requirements($layout_data),
            'source_site' => get_site_url(),
            'version' => '1.0.0'
        ];
        
        // Send to Blue API
        $api_key = get_option('blue_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API key not configured']);
        }
        
        $response = wp_remote_post(BLUE_API_URL . '/assets', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($asset_data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'API request failed: ' . $response->get_error_message()]);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 201) {
            $result = json_decode($body, true);
            wp_send_json_success([
                'message' => 'Asset saved successfully!',
                'asset_id' => $result['id'] ?? null
            ]);
        } else {
            wp_send_json_error([
                'message' => "API error (status {$status_code}): " . substr($body, 0, 200)
            ]);
        }
    }
    
    /**
     * Extract requirements from layout data
     */
    private function extract_requirements($layout_data) {
        $requirements = [
            'plugins' => ['beaver-builder'],
            'modules' => []
        ];
        
        if (!is_array($layout_data)) {
            return $requirements;
        }
        
        // Walk through layout data to find module types
        foreach ($layout_data as $node_id => $node) {
            if (isset($node->type) && $node->type === 'module') {
                $module_type = $node->settings->type ?? 'unknown';
                if (!in_array($module_type, $requirements['modules'])) {
                    $requirements['modules'][] = $module_type;
                }
            }
        }
        
        return $requirements;
    }
    
    /**
     * Render library page
     */
    public function render_library_page() {
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $api_key = get_option('blue_api_key', '');
        if (empty($api_key)) {
            ?>
            <div class="wrap">
                <h1>Blue Library</h1>
                <div class="notice notice-warning">
                    <p>
                        Please <a href="<?php echo admin_url('admin.php?page=blue-settings'); ?>">configure your API key</a> first.
                    </p>
                </div>
            </div>
            <?php
            return;
        }
        
        // Get filter params
        $type_filter = $_GET['type'] ?? '';
        $search = $_GET['s'] ?? '';
        
        // Fetch assets from API
        $query_params = [];
        if ($type_filter) {
            $query_params['type'] = $type_filter;
        }
        
        $url = BLUE_API_URL . '/assets';
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key
            ],
            'timeout' => 15
        ]);
        
        $assets = [];
        $error_message = null;
        
        if (is_wp_error($response)) {
            $error_message = 'Failed to fetch assets: ' . $response->get_error_message();
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $assets = $data['assets'] ?? [];
                
                // Client-side search filter
                if ($search) {
                    $assets = array_filter($assets, function($asset) use ($search) {
                        $search_lower = strtolower($search);
                        return stripos($asset['title'], $search) !== false ||
                               stripos($asset['description'] ?? '', $search) !== false ||
                               (isset($asset['tags']) && array_filter($asset['tags'], function($tag) use ($search_lower) {
                                   return stripos($tag, $search_lower) !== false;
                               }));
                    });
                }
            } else {
                $error_message = "API returned status code {$status_code}";
            }
        }
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Blue Library</h1>
            <a href="<?php echo admin_url('admin.php?page=blue-settings'); ?>" class="page-title-action">Settings</a>
            <hr class="wp-header-end">
            
            <?php if ($error_message): ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($error_message); ?></p>
                </div>
            <?php endif; ?>
            
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
                            <a href="<?php echo admin_url('admin.php?page=blue-library'); ?>" class="button">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="blue-library">
                        <?php if ($type_filter): ?>
                            <input type="hidden" name="type" value="<?php echo esc_attr($type_filter); ?>">
                        <?php endif; ?>
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search assets...">
                        <input type="submit" class="button" value="Search">
                    </form>
                </div>
                
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo count($assets); ?> items</span>
                </div>
            </div>
            
            <?php if (empty($assets)): ?>
                <div style="padding: 40px; text-align: center; background: #fff; border: 1px solid #ccc; border-radius: 4px;">
                    <p style="font-size: 16px; color: #666;">
                        <?php if ($search || $type_filter): ?>
                            No assets found matching your filters.
                        <?php else: ?>
                            Your library is empty. Start by saving a Beaver Builder layout to Blue!
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
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
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($asset['title']); ?></strong>
                                    <?php if (!empty($asset['description'])): ?>
                                        <br><small style="color: #666;"><?php echo esc_html($asset['description']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($asset['source_site'])): ?>
                                        <br><small style="color: #999;">Source: <?php echo esc_html($asset['source_site']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="blue-type-badge blue-type-<?php echo esc_attr($asset['type']); ?>">
                                        <?php echo esc_html(ucfirst($asset['type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($asset['tags'])): ?>
                                        <?php foreach ($asset['tags'] as $tag): ?>
                                            <span class="blue-tag"><?php echo esc_html($tag); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code><?php echo esc_html($asset['bb_version'] ?? 'unknown'); ?></code>
                                </td>
                                <td>
                                    <a href="<?php echo wp_nonce_url(
                                        admin_url('admin.php?page=blue-library&action=import&asset_id=' . urlencode($asset['id'])),
                                        'blue_import_' . $asset['id']
                                    ); ?>" class="button button-primary button-small">
                                        Import
                                    </a>
                                    <button 
                                        class="button button-small blue-delete-asset" 
                                        data-asset-id="<?php echo esc_attr($asset['id']); ?>"
                                        data-asset-title="<?php echo esc_attr($asset['title']); ?>"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <style>
            .blue-type-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .blue-type-template { background: #d4edda; color: #155724; }
            .blue-type-row { background: #d1ecf1; color: #0c5460; }
            .blue-type-column { background: #fff3cd; color: #856404; }
            .blue-type-module { background: #f8d7da; color: #721c24; }
            
            .blue-tag {
                display: inline-block;
                padding: 2px 6px;
                margin: 2px;
                background: #f0f0f1;
                border-radius: 3px;
                font-size: 12px;
                color: #2c3338;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.blue-delete-asset').on('click', function(e) {
                e.preventDefault();
                const btn = $(this);
                const assetId = btn.data('asset-id');
                const assetTitle = btn.data('asset-title');
                
                if (!confirm('Are you sure you want to delete "' + assetTitle + '"? This cannot be undone.')) {
                    return;
                }
                
                btn.prop('disabled', true).text('Deleting...');
                
                $.post(ajaxurl, {
                    action: 'blue_delete_asset',
                    nonce: '<?php echo wp_create_nonce('blue_delete_asset'); ?>',
                    asset_id: assetId
                }, function(response) {
                    if (response.success) {
                        btn.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Failed to delete: ' + response.data.message);
                        btn.prop('disabled', false).text('Delete');
                    }
                }).fail(function() {
                    alert('Request failed');
                    btn.prop('disabled', false).text('Delete');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Handle import action
     */
    public function handle_import_action() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'blue-library') {
            return;
        }
        
        if (!isset($_GET['action']) || $_GET['action'] !== 'import') {
            return;
        }
        
        if (!isset($_GET['asset_id'])) {
            return;
        }
        
        $asset_id = sanitize_text_field($_GET['asset_id']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'blue_import_' . $asset_id)) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        // Fetch asset from API
        $api_key = get_option('blue_api_key', '');
        if (empty($api_key)) {
            wp_die('API key not configured');
        }
        
        $response = wp_remote_get(BLUE_API_URL . '/assets/' . $asset_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            wp_die('Failed to fetch asset: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            wp_die('API error: status ' . $status_code);
        }
        
        $asset = json_decode(wp_remote_retrieve_body($response), true);
        if (!$asset || !isset($asset['data'])) {
            wp_die('Invalid asset data');
        }
        
        // Convert JSON data back to BB's expected format:
        // Top level must be an ARRAY, child nodes are OBJECTS
        $layout_data = [];
        foreach ($asset['data'] as $node_id => $node_data) {
            // Convert each node to stdClass object
            $layout_data[$node_id] = json_decode(json_encode($node_data));
        }
        
        // Map Blue asset type to BB template type
        $type_map = [
            'template' => 'layout',
            'row' => 'row',
            'column' => 'column',
            'module' => 'module'
        ];
        
        $bb_type = $type_map[$asset['type']] ?? 'layout';
        
        // Create BB saved template
        $post_id = wp_insert_post([
            'post_title' => $asset['title'],
            'post_status' => 'publish',
            'post_type' => 'fl-builder-template'
        ]);
        
        if (is_wp_error($post_id)) {
            wp_die('Failed to create template: ' . $post_id->get_error_message());
        }

        wp_set_object_terms($post_id, $bb_type, 'fl-builder-template-type');
        
        // Inject layout data (must be PHP serialized, not JSON)
        update_post_meta($post_id, '_fl_builder_data', $layout_data);
        
        // Set as enabled
        update_post_meta($post_id, '_fl_builder_enabled', true);
        
        // Add BB template meta (from verified native BB saved rows)
        update_post_meta($post_id, '_fl_builder_template_id', uniqid());
        update_post_meta($post_id, '_fl_builder_template_global', '0');
        update_post_meta($post_id, '_fl_builder_template_dynamic_editing', '0');
        
        // Add Blue metadata for reference
        update_post_meta($post_id, '_blue_asset_id', $asset_id);
        update_post_meta($post_id, '_blue_imported_at', current_time('mysql'));
        
        if (!empty($asset['description'])) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $asset['description']
            ]);
        }
        
        // Redirect to BB's saved templates list for this type
        $redirect_url = admin_url('edit.php?post_type=fl-builder-template&fl-builder-template-type=' . $bb_type);
        
        // Add success message via transient
        set_transient('blue_import_success_' . get_current_user_id(), [
            'title' => $asset['title'],
            'type' => $bb_type
        ], 30);
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * AJAX handler for deleting asset
     */
    public function ajax_delete_asset() {
        check_ajax_referer('blue_delete_asset', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $asset_id = sanitize_text_field($_POST['asset_id'] ?? '');
        if (empty($asset_id)) {
            wp_send_json_error(['message' => 'Invalid asset ID']);
        }
        
        $api_key = get_option('blue_api_key', '');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API key not configured']);
        }
        
        // Delete from API
        $response = wp_remote_request(BLUE_API_URL . '/assets/' . $asset_id, [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'API request failed: ' . $response->get_error_message()]);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code === 200) {
            wp_send_json_success(['message' => 'Asset deleted']);
        } else {
            $body = wp_remote_retrieve_body($response);
            wp_send_json_error(['message' => "API error (status {$status_code}): " . substr($body, 0, 100)]);
        }
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    Blue_For_BB::instance();
});

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    // Nothing to do on activation yet
    // Future: Maybe check for BB presence, set default options, etc.
});