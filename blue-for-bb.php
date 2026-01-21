<?php
/**
 * Plugin Name: Blue for Beaver Builder
 * Description: Cloud library for Beaver Builder layouts
 * Version: 0.2.4.2
 * Author: Jasper
 * Author URI: https://github.com/jasperfrontend
 * Plugin URI: https://github.com/jasperfrontend/blue-for-bb
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Update URI: https://github.com/jasperfrontend/blue-for-bb
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BLUE_VERSION', '0.2.4.2');
define('BLUE_API_URL', 'https://assets.blueforbb.com/api');
define('BLUE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BLUE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Initialize the plugin
 */
function blue_for_bb_init() {
    // Load the main plugin class
    require_once BLUE_PLUGIN_DIR . 'includes/class-blue-plugin.php';

    // Initialize plugin
    Blue_Plugin::instance();

    // Initialize GitHub updater (update with your GitHub username and repo name)
    require_once BLUE_PLUGIN_DIR . 'includes/class-blue-updater.php';
    new Blue_Updater('jasperfrontend', 'blue-for-bb');
}
add_action('plugins_loaded', 'blue_for_bb_init');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    // Verify PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Blue for Beaver Builder requires PHP 8.0 or higher. Please upgrade your PHP version.', 'blue-for-bb'),
            esc_html__('Plugin Activation Error', 'blue-for-bb'),
            ['back_link' => true]
        );
    }

    // Check if Beaver Builder is installed
    if (!class_exists('FLBuilder')) {
        // Just show a notice, don't prevent activation
        set_transient('blue_bb_check_notice', true, 30);
    }
});

/**
 * Show notice if Beaver Builder is not active
 */
add_action('admin_notices', function() {
    if (get_transient('blue_bb_check_notice')) {
        delete_transient('blue_bb_check_notice');
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>Blue for Beaver Builder:</strong>
                Beaver Builder is not detected. Please install and activate Beaver Builder to use this plugin.
            </p>
        </div>
        <?php
    }
});
