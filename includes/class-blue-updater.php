<?php
/**
 * GitHub Plugin Updater
 *
 * Enables automatic updates from GitHub releases using WordPress's native update system.
 *
 * @package Blue_For_BB
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Blue_Updater
 *
 * Checks GitHub releases for plugin updates and integrates with WordPress update system.
 */
class Blue_Updater {

    /**
     * GitHub username/organization
     *
     * @var string
     */
    private string $github_username;

    /**
     * GitHub repository name
     *
     * @var string
     */
    private string $github_repo;

    /**
     * Plugin slug (folder name)
     *
     * @var string
     */
    private string $plugin_slug;

    /**
     * Plugin basename (folder/file.php)
     *
     * @var string
     */
    private string $plugin_basename;

    /**
     * Current plugin version
     *
     * @var string
     */
    private string $current_version;

    /**
     * Cached GitHub release data
     *
     * @var object|null
     */
    private ?object $github_response = null;

    /**
     * Constructor
     *
     * @param string $github_username GitHub username or organization
     * @param string $github_repo     GitHub repository name
     */
    public function __construct(string $github_username, string $github_repo) {
        $this->github_username = $github_username;
        $this->github_repo     = $github_repo;
        $this->plugin_slug     = 'blue-for-bb';
        $this->plugin_basename = 'blue-for-bb/blue-for-bb.php';
        $this->current_version = BLUE_VERSION;

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        // Check for updates
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);

        // Provide plugin information for the update details popup
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);

        // Ensure proper source directory after update
        add_filter('upgrader_source_selection', [$this, 'fix_source_dir'], 10, 4);

        // Clear cache after update
        add_action('upgrader_process_complete', [$this, 'clear_cache'], 10, 2);
    }

    /**
     * Get the latest release data from GitHub
     *
     * @return object|null Release data or null on failure
     */
    private function get_github_release(): ?object {
        // Return cached response if available
        if ($this->github_response !== null) {
            return $this->github_response;
        }

        // Check transient cache first
        $cache_key = 'blue_github_release_' . md5($this->github_username . $this->github_repo);
        $cached    = get_transient($cache_key);

        if ($cached !== false) {
            $this->github_response = $cached;
            return $this->github_response;
        }

        // Fetch from GitHub API
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );

        $response = wp_remote_get($url, [
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data) || !isset($data->tag_name)) {
            return null;
        }

        // Cache for 6 hours
        set_transient($cache_key, $data, 6 * HOUR_IN_SECONDS);

        $this->github_response = $data;
        return $this->github_response;
    }

    /**
     * Parse version from GitHub tag name
     *
     * Removes 'v' prefix if present (e.g., 'v1.0.0' becomes '1.0.0')
     *
     * @param string $tag_name GitHub tag name
     * @return string Cleaned version string
     */
    private function parse_version(string $tag_name): string {
        return ltrim($tag_name, 'vV');
    }

    /**
     * Check for plugin updates
     *
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_update(object $transient): object {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();

        if ($release === null) {
            return $transient;
        }

        $remote_version = $this->parse_version($release->tag_name);

        // Compare versions
        if (version_compare($this->current_version, $remote_version, '<')) {
            $download_url = $this->get_download_url($release);

            if ($download_url) {
                $transient->response[$this->plugin_basename] = (object) [
                    'slug'        => $this->plugin_slug,
                    'plugin'      => $this->plugin_basename,
                    'new_version' => $remote_version,
                    'url'         => $release->html_url,
                    'package'     => $download_url,
                    'icons'       => [],
                    'banners'     => [],
                    'tested'      => '',
                    'requires'    => '',
                    'requires_php' => '8.0',
                ];
            }
        }

        return $transient;
    }

    /**
     * Get the download URL for a release
     *
     * Prefers a .zip asset if uploaded, otherwise uses the source zipball
     *
     * @param object $release GitHub release data
     * @return string|null Download URL or null
     */
    private function get_download_url(object $release): ?string {
        // First, look for a .zip asset (recommended for cleaner installs)
        if (!empty($release->assets)) {
            foreach ($release->assets as $asset) {
                if (
                    isset($asset->browser_download_url) &&
                    str_ends_with($asset->name, '.zip')
                ) {
                    return $asset->browser_download_url;
                }
            }
        }

        // Fall back to GitHub's source zipball
        return $release->zipball_url ?? null;
    }

    /**
     * Provide plugin information for the update details popup
     *
     * @param false|object|array $result The result object or array
     * @param string             $action The API action being performed
     * @param object             $args   Plugin API arguments
     * @return false|object Plugin info or false
     */
    public function plugin_info($result, string $action, object $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $release = $this->get_github_release();

        if ($release === null) {
            return $result;
        }

        $remote_version = $this->parse_version($release->tag_name);

        return (object) [
            'name'          => 'Blue for Beaver Builder',
            'slug'          => $this->plugin_slug,
            'version'       => $remote_version,
            'author'        => '<a href="https://github.com/' . esc_attr($this->github_username) . '">Jasper</a>',
            'homepage'      => 'https://github.com/' . $this->github_username . '/' . $this->github_repo,
            'requires'      => '6.0',
            'tested'        => get_bloginfo('version'),
            'requires_php'  => '8.0',
            'downloaded'    => 0,
            'last_updated'  => $release->published_at ?? '',
            'sections'      => [
                'description'  => 'Cloud library for Beaver Builder layouts.',
                'changelog'    => $this->format_changelog($release->body ?? ''),
            ],
            'download_link' => $this->get_download_url($release),
            'banners'       => [],
        ];
    }

    /**
     * Format GitHub release notes as HTML changelog
     *
     * @param string $body Release notes markdown
     * @return string HTML formatted changelog
     */
    private function format_changelog(string $body): string {
        if (empty($body)) {
            return '<p>No changelog available.</p>';
        }

        // Basic markdown to HTML conversion
        $html = esc_html($body);
        $html = nl2br($html);

        // Convert markdown links [text](url) to HTML
        $html = preg_replace(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            '<a href="$2" target="_blank">$1</a>',
            $html
        );

        // Convert **bold** to <strong>
        $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html);

        // Convert bullet points
        $html = preg_replace('/^[\-\*]\s+/m', '• ', $html);

        return '<div class="changelog">' . $html . '</div>';
    }

    /**
     * Fix the source directory name after extracting the update
     *
     * GitHub zipballs have directory names like 'username-repo-hash',
     * but WordPress expects the plugin folder name.
     *
     * @param string       $source        Extracted source path
     * @param string       $remote_source Remote source path
     * @param \WP_Upgrader $upgrader      Upgrader instance
     * @param array        $hook_extra    Extra hook arguments
     * @return string|WP_Error Corrected source path or error
     */
    public function fix_source_dir(string $source, string $remote_source, \WP_Upgrader $upgrader, array $hook_extra) {
        global $wp_filesystem;

        // Only process our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $source;
        }

        // Expected directory name
        $expected_dir = trailingslashit($remote_source) . $this->plugin_slug . '/';

        // If source already has correct name, do nothing
        if ($source === $expected_dir) {
            return $source;
        }

        // Rename the extracted directory
        if ($wp_filesystem->move($source, $expected_dir, true)) {
            return $expected_dir;
        }

        return new WP_Error(
            'rename_failed',
            __('Unable to rename the update to match the plugin directory.', 'blue-for-bb')
        );
    }

    /**
     * Clear cached release data after plugin update
     *
     * @param \WP_Upgrader $upgrader Upgrader instance
     * @param array        $options  Upgrade options
     */
    public function clear_cache(\WP_Upgrader $upgrader, array $options): void {
        if (
            isset($options['action'], $options['type'], $options['plugins']) &&
            $options['action'] === 'update' &&
            $options['type'] === 'plugin' &&
            in_array($this->plugin_basename, $options['plugins'], true)
        ) {
            $cache_key = 'blue_github_release_' . md5($this->github_username . $this->github_repo);
            delete_transient($cache_key);
        }
    }
}
