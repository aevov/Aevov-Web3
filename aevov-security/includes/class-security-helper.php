<?php
/**
 * Aevov Security Helper
 *
 * Centralized security functions for authentication, sanitization, and CSRF protection
 * Used across all Aevov plugins to ensure consistent security practices
 */

namespace Aevov\Security;

class SecurityHelper {

    /**
     * Permission callback for REST API endpoints
     * Checks if user has required capability
     *
     * @param string $capability Required capability (default: 'edit_posts')
     * @return bool True if user has capability
     */
    public static function check_permission( $capability = 'edit_posts' ) {
        return current_user_can( $capability );
    }

    /**
     * Check if user can manage Aevov settings
     * Higher permission level for administrative actions
     *
     * @return bool
     */
    public static function can_manage_aevov() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Check if user can edit Aevov content
     * Standard permission for content operations
     *
     * @return bool
     */
    public static function can_edit_aevov() {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Check if user can read Aevov content
     * Lowest permission level for read-only operations
     *
     * @return bool
     */
    public static function can_read_aevov() {
        return current_user_can( 'read' );
    }

    /**
     * Sanitize text input
     *
     * @param mixed $input Raw input
     * @return string Sanitized text
     */
    public static function sanitize_text( $input ) {
        if ( is_array( $input ) ) {
            return array_map( [ __CLASS__, 'sanitize_text' ], $input );
        }

        if ( is_string( $input ) ) {
            return sanitize_text_field( $input );
        }

        return $input;
    }

    /**
     * Sanitize textarea input (preserves line breaks)
     *
     * @param string $input Raw input
     * @return string Sanitized textarea
     */
    public static function sanitize_textarea( $input ) {
        return sanitize_textarea_field( $input );
    }

    /**
     * Sanitize email
     *
     * @param string $email Raw email
     * @return string Sanitized email
     */
    public static function sanitize_email( $email ) {
        return sanitize_email( $email );
    }

    /**
     * Sanitize URL
     *
     * @param string $url Raw URL
     * @return string Sanitized URL
     */
    public static function sanitize_url( $url ) {
        return esc_url_raw( $url );
    }

    /**
     * Sanitize integer
     *
     * @param mixed $input Raw input
     * @return int Sanitized integer
     */
    public static function sanitize_int( $input ) {
        return absint( $input );
    }

    /**
     * Sanitize float
     *
     * @param mixed $input Raw input
     * @return float Sanitized float
     */
    public static function sanitize_float( $input ) {
        return floatval( $input );
    }

    /**
     * Sanitize boolean
     *
     * @param mixed $input Raw input
     * @return bool Sanitized boolean
     */
    public static function sanitize_bool( $input ) {
        return (bool) $input;
    }

    /**
     * Sanitize JSON data
     * Ensures valid JSON and sanitizes string values
     *
     * @param mixed $input Raw input (string or array)
     * @return array|false Sanitized array or false on error
     */
    public static function sanitize_json( $input ) {
        if ( is_string( $input ) ) {
            $decoded = json_decode( $input, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return false;
            }
            $input = $decoded;
        }

        if ( ! is_array( $input ) ) {
            return false;
        }

        return self::sanitize_array_recursive( $input );
    }

    /**
     * Recursively sanitize array values
     *
     * @param array $array Raw array
     * @return array Sanitized array
     */
    private static function sanitize_array_recursive( $array ) {
        foreach ( $array as $key => $value ) {
            if ( is_array( $value ) ) {
                $array[ $key ] = self::sanitize_array_recursive( $value );
            } elseif ( is_string( $value ) ) {
                // Don't sanitize if it looks like JSON
                if ( self::is_json_string( $value ) ) {
                    $array[ $key ] = $value;
                } else {
                    $array[ $key ] = sanitize_text_field( $value );
                }
            }
        }
        return $array;
    }

    /**
     * Check if string is JSON
     *
     * @param string $string Input string
     * @return bool True if valid JSON
     */
    private static function is_json_string( $string ) {
        if ( ! is_string( $string ) ) {
            return false;
        }

        json_decode( $string );
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Verify WordPress nonce
     *
     * @param string $nonce Nonce value
     * @param string $action Nonce action
     * @return bool True if nonce is valid
     */
    public static function verify_nonce( $nonce, $action ) {
        return wp_verify_nonce( $nonce, $action );
    }

    /**
     * Create WordPress nonce
     *
     * @param string $action Nonce action
     * @return string Nonce value
     */
    public static function create_nonce( $action ) {
        return wp_create_nonce( $action );
    }

    /**
     * Verify request nonce from header or parameter
     * Checks X-WP-Nonce header first, then falls back to parameter
     *
     * @param \WP_REST_Request $request REST request object
     * @param string $action Nonce action
     * @return bool True if nonce is valid
     */
    public static function verify_request_nonce( $request, $action = -1 ) {
        // Check header first (WordPress default for REST API)
        $nonce = $request->get_header( 'X-WP-Nonce' );

        if ( ! $nonce ) {
            // Fallback to parameter
            $nonce = $request->get_param( '_wpnonce' );
        }

        if ( ! $nonce ) {
            return false;
        }

        return wp_verify_nonce( $nonce, $action );
    }

    /**
     * Sanitize REST request parameters
     * Automatically sanitizes based on parameter type
     *
     * @param \WP_REST_Request $request REST request object
     * @param array $schema Parameter schema with types
     * @return array Sanitized parameters
     */
    public static function sanitize_request_params( $request, $schema = [] ) {
        $params = $request->get_params();
        $sanitized = [];

        foreach ( $params as $key => $value ) {
            $type = $schema[ $key ]['type'] ?? 'string';

            switch ( $type ) {
                case 'integer':
                    $sanitized[ $key ] = self::sanitize_int( $value );
                    break;

                case 'number':
                case 'float':
                    $sanitized[ $key ] = self::sanitize_float( $value );
                    break;

                case 'boolean':
                    $sanitized[ $key ] = self::sanitize_bool( $value );
                    break;

                case 'email':
                    $sanitized[ $key ] = self::sanitize_email( $value );
                    break;

                case 'url':
                    $sanitized[ $key ] = self::sanitize_url( $value );
                    break;

                case 'textarea':
                    $sanitized[ $key ] = self::sanitize_textarea( $value );
                    break;

                case 'array':
                case 'object':
                case 'json':
                    $sanitized[ $key ] = self::sanitize_json( $value );
                    break;

                case 'string':
                default:
                    $sanitized[ $key ] = self::sanitize_text( $value );
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Escape output for HTML
     *
     * @param mixed $output Raw output
     * @return string Escaped output
     */
    public static function esc_html( $output ) {
        if ( is_array( $output ) ) {
            return array_map( [ __CLASS__, 'esc_html' ], $output );
        }
        return esc_html( $output );
    }

    /**
     * Escape output for attributes
     *
     * @param mixed $output Raw output
     * @return string Escaped output
     */
    public static function esc_attr( $output ) {
        if ( is_array( $output ) ) {
            return array_map( [ __CLASS__, 'esc_attr' ], $output );
        }
        return esc_attr( $output );
    }

    /**
     * Prepare SQL for safe execution
     * Wrapper for $wpdb->prepare()
     *
     * @param string $query SQL query with placeholders
     * @param mixed ...$args Values for placeholders
     * @return string Prepared SQL
     */
    public static function prepare_sql( $query, ...$args ) {
        global $wpdb;
        return $wpdb->prepare( $query, ...$args );
    }

    /**
     * Validate and sanitize file upload
     *
     * @param array $file $_FILES array element
     * @param array $allowed_types Allowed MIME types
     * @param int $max_size Maximum file size in bytes
     * @return array|\WP_Error Sanitized file info or error
     */
    public static function validate_file_upload( $file, $allowed_types = [], $max_size = 10485760 ) {
        // Check if file was uploaded
        if ( empty( $file ) || ! isset( $file['tmp_name'] ) ) {
            return new \WP_Error( 'no_file', 'No file uploaded' );
        }

        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new \WP_Error( 'upload_error', 'File upload failed', [ 'error_code' => $file['error'] ] );
        }

        // Check file size
        if ( $file['size'] > $max_size ) {
            return new \WP_Error( 'file_too_large', sprintf( 'File size exceeds %s bytes', $max_size ) );
        }

        // Check MIME type if specified
        if ( ! empty( $allowed_types ) ) {
            $file_type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_types );

            if ( ! $file_type['type'] || ! in_array( $file_type['type'], $allowed_types, true ) ) {
                return new \WP_Error( 'invalid_file_type', 'File type not allowed' );
            }
        }

        // Sanitize filename
        $filename = sanitize_file_name( $file['name'] );

        return [
            'name' => $filename,
            'type' => $file['type'],
            'tmp_name' => $file['tmp_name'],
            'size' => $file['size']
        ];
    }

    /**
     * Rate limiting check
     * Simple rate limiting based on transients
     *
     * @param string $key Rate limit key (e.g., user_id, IP)
     * @param int $max_attempts Maximum attempts
     * @param int $time_window Time window in seconds
     * @return bool True if rate limit exceeded
     */
    public static function is_rate_limited( $key, $max_attempts = 60, $time_window = 60 ) {
        $transient_key = 'aevov_rate_limit_' . md5( $key );
        $attempts = get_transient( $transient_key );

        if ( $attempts === false ) {
            // First attempt
            set_transient( $transient_key, 1, $time_window );
            return false;
        }

        if ( $attempts >= $max_attempts ) {
            return true;
        }

        // Increment attempts
        set_transient( $transient_key, $attempts + 1, $time_window );
        return false;
    }

    /**
     * Log security event
     *
     * @param string $event Event type
     * @param array $context Event context
     */
    public static function log_security_event( $event, $context = [] ) {
        $log_entry = [
            'timestamp' => current_time( 'mysql' ),
            'event' => $event,
            'user_id' => get_current_user_id(),
            'ip' => self::get_client_ip(),
            'context' => $context
        ];

        // Log to WordPress error log
        error_log( 'AEVOV_SECURITY: ' . wp_json_encode( $log_entry ) );

        // Optionally store in database or send to monitoring service
        do_action( 'aevov_security_event', $log_entry );
    }

    /**
     * Get client IP address
     * Handles proxies and load balancers
     *
     * @return string Client IP
     */
    private static function get_client_ip() {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ( $ip_keys as $key ) {
            if ( isset( $_SERVER[ $key ] ) && filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP ) ) {
                return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
            }
        }

        return 'Unknown';
    }
}
