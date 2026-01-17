<?php
/**
 * Admin Settings Page Handler
 *
 * Manages plugin settings and admin menu
 */

if (!defined('ABSPATH')) {
    exit;
}

class Blue_Admin {

    private $api_client;

    public function __construct(Blue_API_Client $api_client) {
        $this->api_client = $api_client;
    }

    /**
     * Initialize admin hooks
     */
    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
    }

    /**
     * Add settings page to WordPress admin
     */
    public function add_admin_menu() {
        // Get library instance from plugin singleton
        $plugin = Blue_Plugin::instance();
        $library = $plugin->get_library();

        // Main library page
        add_menu_page(
            'Blue Library',
            'Blue Library',
            'edit_posts',
            'blue-library',
            [$library, 'render_library_page'],
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

    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('blue_settings', 'blue_api_key', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_api_key'],
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
     * Sanitize API key with validation
     */
    public function sanitize_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }

        $validated = Blue_Validator::validate_api_key($api_key);
        if (!$validated) {
            add_settings_error(
                'blue_api_key',
                'invalid_api_key',
                'Invalid API key format. API keys should be 20-100 alphanumeric characters.',
                'error'
            );
            return get_option('blue_api_key', ''); // Return old value
        }

        return $validated;
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
            autocomplete="off"
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
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'blue-for-bb'));
        }

        // Handle connection test
        $test_result = null;
        if (isset($_POST['test_connection']) && check_admin_referer('blue_test_connection')) {
            $test_result = $this->api_client->test_connection();
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors(); ?>

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
                            echo '<span style="color: #dc3232;">Not configured</span>';
                        } else {
                            echo '<span style="color: #46b450;">Configured</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Beaver Builder</th>
                    <td>
                        <?php
                        if (class_exists('FLBuilder')) {
                            echo '<span style="color: #46b450;">Active</span>';
                            if (defined('FL_BUILDER_VERSION')) {
                                echo ' <code>v' . esc_html(FL_BUILDER_VERSION) . '</code>';
                            }
                        } else {
                            echo '<span style="color: #dc3232;">Not detected</span>';
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        $current_screen = get_current_screen();

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
        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $api_key = get_option('blue_api_key', '');

        if (empty($api_key) && !in_array($current_page, ['blue-for-bb', 'blue-settings'], true)) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>Blue for Beaver Builder:</strong>
                    Please <a href="<?php echo esc_url(admin_url('admin.php?page=blue-settings')); ?>">configure your API key</a> to start using Blue.
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
                    <strong>Import successful!</strong>
                    "<?php echo esc_html($import_success['title']); ?>" has been added to your Saved <?php echo esc_html(ucfirst($import_success['type'])); ?>s.
                </p>
            </div>
            <?php
        }
    }
}
