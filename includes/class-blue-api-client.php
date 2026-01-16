<?php
/**
 * API Client for Blue Cloud Service
 *
 * Handles all communication with the Blue API securely
 */

if (!defined('ABSPATH')) {
    exit;
}

class Blue_API_Client {

    private $api_key;
    private $api_url;
    private $timeout = 15;

    public function __construct() {
        $this->api_url = BLUE_API_URL;
        $this->api_key = get_option('blue_api_key', '');
    }

    /**
     * Test API connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'message' => 'Please enter an API key first.'
            ];
        }

        $response = $this->get('/assets');

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            ];
        }

        if ($response['status'] === 200 && isset($response['data']['assets'])) {
            $asset_count = count($response['data']['assets']);
            return [
                'success' => true,
                'message' => "Connection successful! Found {$asset_count} assets in your library."
            ];
        } elseif ($response['status'] === 401) {
            return [
                'success' => false,
                'message' => 'Authentication failed. Please check your API key.'
            ];
        } else {
            return [
                'success' => false,
                'message' => "API returned status code {$response['status']}."
            ];
        }
    }

    /**
     * Get all assets with optional filtering
     */
    public function get_assets($filters = []) {
        $query_params = [];

        if (!empty($filters['type'])) {
            $query_params['type'] = Blue_Validator::sanitize_asset_type($filters['type']);
        }

        $endpoint = '/assets';
        if (!empty($query_params)) {
            $endpoint .= '?' . http_build_query($query_params);
        }

        $response = $this->get($endpoint);

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['status'] === 200 && isset($response['data']['assets'])) {
            return $response['data']['assets'];
        }

        return new WP_Error('api_error', 'Failed to fetch assets', ['status' => $response['status']]);
    }

    /**
     * Get single asset by ID
     */
    public function get_asset($asset_id) {
        $asset_id = Blue_Validator::validate_asset_id($asset_id);
        if (!$asset_id) {
            return new WP_Error('invalid_id', 'Invalid asset ID');
        }

        $response = $this->get('/assets/' . $asset_id);

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['status'] === 200 && isset($response['data'])) {
            // Validate response structure
            if (!Blue_Validator::validate_api_response($response['data'], ['id', 'title', 'type', 'data'])) {
                return new WP_Error('invalid_response', 'Invalid asset data structure');
            }
            return $response['data'];
        }

        return new WP_Error('api_error', 'Asset not found', ['status' => $response['status']]);
    }

    /**
     * Create new asset
     */
    public function create_asset($asset_data) {
        // Validate required fields
        if (!isset($asset_data['title']) || !isset($asset_data['data'])) {
            return new WP_Error('missing_fields', 'Missing required fields');
        }

        // Sanitize data
        $sanitized_data = [
            'type' => Blue_Validator::sanitize_asset_type($asset_data['type'] ?? 'template'),
            'title' => Blue_Validator::sanitize_title($asset_data['title']),
            'description' => Blue_Validator::sanitize_description($asset_data['description'] ?? ''),
            'tags' => Blue_Validator::sanitize_tags($asset_data['tags'] ?? ''),
            'bb_version' => sanitize_text_field($asset_data['bb_version'] ?? 'unknown'),
            'data' => $asset_data['data'], // Validated separately
            'requires' => $asset_data['requires'] ?? [],
            'source_site' => esc_url_raw($asset_data['source_site'] ?? ''),
            'version' => sanitize_text_field($asset_data['version'] ?? '1.0.0')
        ];

        // Validate layout data
        if (!Blue_Validator::validate_layout_data($sanitized_data['data'])) {
            return new WP_Error('invalid_layout', 'Invalid or oversized layout data');
        }

        $response = $this->post('/assets', $sanitized_data);

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['status'] === 201) {
            return $response['data'];
        }

        return new WP_Error('api_error', 'Failed to create asset', [
            'status' => $response['status'],
            'body' => $response['body']
        ]);
    }

    /**
     * Delete asset
     */
    public function delete_asset($asset_id) {
        $asset_id = Blue_Validator::validate_asset_id($asset_id);
        if (!$asset_id) {
            return new WP_Error('invalid_id', 'Invalid asset ID');
        }

        $response = $this->delete('/assets/' . $asset_id);

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response['status'] === 200) {
            return true;
        }

        return new WP_Error('api_error', 'Failed to delete asset', ['status' => $response['status']]);
    }

    /**
     * Perform GET request
     */
    private function get($endpoint) {
        return $this->request('GET', $endpoint);
    }

    /**
     * Perform POST request
     */
    private function post($endpoint, $data = []) {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Perform DELETE request
     */
    private function delete($endpoint) {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Perform HTTP request with error handling
     */
    private function request($method, $endpoint, $data = null) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'API key not configured');
        }

        $url = $this->api_url . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => $this->timeout
        ];

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Try to decode JSON
        $decoded_body = json_decode($body, true);

        return [
            'status' => $status_code,
            'body' => $body,
            'data' => $decoded_body
        ];
    }
}
