<?php
/**
 * Main Plugin Orchestrator
 *
 * Initializes and coordinates all plugin components
 */

if (!defined('ABSPATH')) {
    exit;
}

class Blue_Plugin {

    private static $instance = null;
    private $api_client;
    private $admin;
    private $library;
    private $export;
    private $import;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once BLUE_PLUGIN_DIR . 'includes/class-blue-validator.php';
        require_once BLUE_PLUGIN_DIR . 'includes/class-blue-api-client.php';
        require_once BLUE_PLUGIN_DIR . 'includes/class-blue-admin.php';
        require_once BLUE_PLUGIN_DIR . 'includes/class-blue-library.php';
        require_once BLUE_PLUGIN_DIR . 'includes/class-blue-export.php';
        require_once BLUE_PLUGIN_DIR . 'includes/class-blue-import.php';
    }

    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize API client
        $this->api_client = new Blue_API_Client();

        // Initialize admin components
        $this->admin = new Blue_Admin($this->api_client);
        $this->admin->init();

        $this->library = new Blue_Library($this->api_client);
        $this->library->init();

        $this->export = new Blue_Export($this->api_client);
        $this->export->init();

        $this->import = new Blue_Import($this->api_client);
        $this->import->init();
    }

    /**
     * Get API client instance
     */
    public function get_api_client() {
        return $this->api_client;
    }

    /**
     * Get admin instance
     */
    public function get_admin() {
        return $this->admin;
    }

    /**
     * Get library instance
     */
    public function get_library() {
        return $this->library;
    }

    /**
     * Get export instance
     */
    public function get_export() {
        return $this->export;
    }

    /**
     * Get import instance
     */
    public function get_import() {
        return $this->import;
    }
}
