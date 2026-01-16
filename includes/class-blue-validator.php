<?php
/**
 * Input Validation and Sanitization
 *
 * Centralized validation to prevent XSS, SQL injection, and other attacks
 */

if (!defined('ABSPATH')) {
    exit;
}

class Blue_Validator {

    /**
     * Sanitize and validate asset type
     */
    public static function sanitize_asset_type($type) {
        $allowed_types = ['template', 'row', 'column', 'module'];
        $sanitized = sanitize_text_field($type);
        return in_array($sanitized, $allowed_types, true) ? $sanitized : 'template';
    }

    /**
     * Sanitize tags array
     */
    public static function sanitize_tags($tags_string) {
        if (empty($tags_string)) {
            return [];
        }

        $tags = array_map('trim', explode(',', $tags_string));
        $tags = array_map('sanitize_text_field', $tags);
        $tags = array_filter($tags); // Remove empty values

        // Limit to 20 tags max, 50 chars each
        $tags = array_slice($tags, 0, 20);
        $tags = array_map(function($tag) {
            return substr($tag, 0, 50);
        }, $tags);

        return array_values($tags);
    }

    /**
     * Validate and sanitize title
     */
    public static function sanitize_title($title) {
        $sanitized = sanitize_text_field($title);

        // Limit to 200 characters
        if (strlen($sanitized) > 200) {
            $sanitized = substr($sanitized, 0, 200);
        }

        return $sanitized;
    }

    /**
     * Validate and sanitize description
     */
    public static function sanitize_description($description) {
        $sanitized = sanitize_textarea_field($description);

        // Limit to 1000 characters
        if (strlen($sanitized) > 1000) {
            $sanitized = substr($sanitized, 0, 1000);
        }

        return $sanitized;
    }

    /**
     * Validate API key format
     */
    public static function validate_api_key($api_key) {
        $sanitized = sanitize_text_field($api_key);

        // API key should be alphanumeric with dashes/underscores, 20-100 chars
        if (!preg_match('/^[a-zA-Z0-9_-]{20,100}$/', $sanitized)) {
            return false;
        }

        return $sanitized;
    }

    /**
     * Validate search query
     */
    public static function sanitize_search_query($query) {
        $sanitized = sanitize_text_field($query);

        // Limit to 100 characters
        if (strlen($sanitized) > 100) {
            $sanitized = substr($sanitized, 0, 100);
        }

        return $sanitized;
    }

    /**
     * Validate asset ID format
     */
    public static function validate_asset_id($asset_id) {
        $sanitized = sanitize_text_field($asset_id);

        // Asset IDs should be alphanumeric with dashes, max 50 chars
        if (!preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $sanitized)) {
            return false;
        }

        return $sanitized;
    }

    /**
     * Validate Beaver Builder layout data structure
     */
    public static function validate_layout_data($layout_data) {
        if (!is_array($layout_data) && !is_object($layout_data)) {
            return false;
        }

        // Check size limit (5MB serialized max)
        $serialized_size = strlen(serialize($layout_data));
        if ($serialized_size > 5 * 1024 * 1024) {
            return false;
        }

        return true;
    }

    /**
     * Validate API response structure
     */
    public static function validate_api_response($response, $expected_fields = []) {
        if (!is_array($response)) {
            return false;
        }

        foreach ($expected_fields as $field) {
            if (!isset($response[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize URL parameter
     */
    public static function sanitize_url_param($param) {
        return urlencode(sanitize_text_field($param));
    }
}
