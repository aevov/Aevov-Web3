<?php
/*
Plugin Name: Aevov Cubbit CDN
Plugin URI:
Description: Serves Aevov model chunks from Cubbit via a CDN with LiteSpeed caching.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-cubbit-cdn
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovCubbitCDN {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    private $view_pattern_page_hook_suffix;

    public function init() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    /**
     * Add the admin menu page.
     */
    public function add_admin_menu() {
        $this->view_pattern_page_hook_suffix = add_menu_page(
            __( 'View Pattern', 'aevov-cubbit-cdn' ),
            __( 'View Pattern', 'aevov-cubbit-cdn' ),
            'manage_options',
            'aevov-view-pattern',
            [ $this, 'render_view_pattern_page' ],
            'dashicons-visibility',
            80
        );
    }

    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->view_pattern_page_hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'aevov-cubbit-cdn-view-pattern',
            plugin_dir_url( __FILE__ ) . 'assets/js/view-pattern.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );
    }

    /**
     * Render the view pattern page.
     */
    public function render_view_pattern_page() {
        if ( ! isset( $_GET['id'] ) ) {
            wp_die( esc_html__( 'No pattern ID specified.', 'aevov-cubbit-cdn' ) );
        }

        $pattern_id = intval( $_GET['id'] );

        // I will need to enqueue a script to fetch the data.
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'View Pattern', 'aevov-cubbit-cdn' ); ?></h1>
            <div id="pattern-container" data-pattern-id="<?php echo esc_attr( $pattern_id ); ?>">
                <p><?php echo esc_html__( 'Loading pattern...', 'aevov-cubbit-cdn' ); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Register the REST API routes.
     */
    public function register_routes() {
        register_rest_route( 'aevov-cubbit-cdn/v1', '/get-chunk-url/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_chunk_url' ],
            'permission_callback' => '__return_true', // Publicly accessible, as it's for serving content.
            'args'                => [
                'id' => [
                    'validate_callback' => function( $param, $request, $key ) {
                        return is_numeric( $param );
                    }
                ],
            ],
        ] );
    }

    /**
     * Get the pre-signed URL for a chunk.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error The response object or a WP_Error on failure.
     */
    public function get_chunk_url( $request ) {
        $chunk_id = $request['id'];
        $object_key = $this->get_cubbit_key_for_chunk( $chunk_id );

        if ( is_wp_error( $object_key ) ) {
            return $object_key;
        }

        $presigned_url = $this->generate_cubbit_presigned_url( $object_key );

        if ( is_wp_error( $presigned_url ) ) {
            return $presigned_url;
        }

        $response = new WP_REST_Response( [ 'url' => $presigned_url ] );
        $response->set_headers( [
            'Cache-Control' => 'public, max-age=3600',
        ] );

        return $response;
    }

    /**
     * Get the Cubbit object key for a chunk.
     *
     * @param int $chunk_id The chunk ID.
     * @return string|WP_Error The object key or a WP_Error on failure.
     */
    private function get_cubbit_key_for_chunk( $chunk_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aps_patterns';
        $key = $wpdb->get_var( $wpdb->prepare( "SELECT cubbit_key FROM $table_name WHERE id = %d", $chunk_id ) );

        if ( empty( $key ) ) {
            return new WP_Error( 'not_found', 'The requested chunk was not found in Cubbit.', [ 'status' => 404 ] );
        }

        return $key;
    }

    /**
     * Generate a pre-signed URL for a Cubbit object.
     *
     * @param string $key     The object key in Cubbit.
     * @param int    $expires The lifetime of the URL in seconds.
     * @return string|WP_Error The pre-signed URL or a WP_Error on failure.
     */
    public function generate_cubbit_presigned_url( $key, $expires = 3600 ) {
        $access_key = get_option( 'cubbit_access_key' );
        $secret_key = get_option( 'cubbit_secret_key' );
        $bucket_name = get_option( 'cubbit_bucket_name' );
        $region = 'eu-central-1';
        $endpoint = 'https://s3.cubbit.eu';

        if ( ! $access_key || ! $secret_key || ! $bucket_name ) {
            return new WP_Error( 'config_error', 'Cubbit configuration is missing.' );
        }

        $amz_date = gmdate( 'Ymd\THis\Z' );
        $datestamp = gmdate( 'Ymd' );

        $credential_scope = "{$datestamp}/{$region}/s3/aws4_request";
        $signed_headers = 'host';

        $query_params = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $access_key . '/' . $credential_scope,
            'X-Amz-Date'          => $amz_date,
            'X-Amz-Expires'       => $expires,
            'X-Amz-SignedHeaders' => $signed_headers,
        ];

        ksort( $query_params );
        $canonical_query_string = http_build_query( $query_params, '', '&', PHP_QUERY_RFC3986 );

        // URL-encode every part of the key
        $encoded_key = implode( '/', array_map( 'rawurlencode', explode( '/', $key ) ) );
        $canonical_uri = '/' . $encoded_key;

        $canonical_headers = 'host:' . parse_url( $endpoint, PHP_URL_HOST ) . "\n";
        $payload_hash = 'UNSIGNED-PAYLOAD';

        $canonical_request = "GET\n"
            . $canonical_uri . "\n"
            . $canonical_query_string . "\n"
            . $canonical_headers . "\n"
            . $signed_headers . "\n"
            . $payload_hash;

        $string_to_sign = "AWS4-HMAC-SHA256\n"
            . $amz_date . "\n"
            . $credential_scope . "\n"
            . hash( 'sha256', $canonical_request );

        $signing_key = $this->derive_signature_key( $secret_key, $datestamp, $region, 's3' );
        $signature = hash_hmac( 'sha256', $string_to_sign, $signing_key );

        $query_params['X-Amz-Signature'] = $signature;

        $presigned_url = $endpoint . '/' . $bucket_name . $canonical_uri . '?' . http_build_query( $query_params, '', '&', PHP_QUERY_RFC3986 );

        return $presigned_url;
    }

    /**
     * Generate signing key for AWS Signature V4.
     *
     * @param string $key The secret key.
     * @param string $date The date in Ymd format.
     * @param string $region The AWS region.
     * @param string $service The AWS service.
     * @return string The signing key.
     */
    private function derive_signature_key( $key, $date, $region, $service ) {
        $kDate = hash_hmac( 'sha256', $date, "AWS4" . $key, true );
        $kRegion = hash_hmac( 'sha256', $region, $kDate, true );
        $kService = hash_hmac( 'sha256', $service, $kRegion, true );
        $kSigning = hash_hmac( 'sha256', 'aws4_request', $kService, true );
        return $kSigning;
    }
}

new AevovCubbitCDN();
