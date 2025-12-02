<?php

namespace AevovImageEngine\API;

use Aevov\Security\SecurityHelper;

use AevovImageEngine\ImageWeaver;
use AevovImageEngine\JobManager;
use AevovCubbitCDN\AevovCubbitCDN;

require_once dirname(__FILE__) . '/../../../aevov-cubbit-cdn/aevov-cubbit-cdn.php';

class ImageEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aevov-image/v1', '/generate', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'generate_image' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );

        register_rest_route( 'aevov-image/v1', '/status/(?P<job_id>[a-zA-Z0-9-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_status' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );

        register_rest_route( 'aevov-image/v1', '/image/(?P<job_id>[a-zA-Z0-9-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_image' ],
            'permission_callback' => [\Aevov\Security\SecurityHelper::class, 'can_edit_aevov'],
        ] );
    }

    public function generate_image( $request ) {
        $params = $request->get_params();
        $job_manager = new JobManager();
        $job_id = $job_manager->create_job( $params );

        // This is where we would trigger the backend worker process.
        // For now, I'll just return the job ID.
        return new \WP_REST_Response( [ 'job_id' => $job_id ] );
    }

    public function get_status( $request ) {
        $job_id = $request['job_id'];
        $job_manager = new JobManager();
        $job = $job_manager->get_job( $job_id );
        $response = new \WP_REST_Response( [ 'status' => $job['status'] ] );
        $response->set_headers( [
            'Cache-Control' => 'no-cache',
        ] );
        return $response;
    }

    public function get_image( $request ) {
        $job_id = $request['job_id'];
        $job_manager = new JobManager();
        $job = $job_manager->get_job( $job_id );

        if ( $job['status'] !== 'complete' ) {
            return new \WP_Error( 'image_not_ready', 'Image is not ready yet.', [ 'status' => 404 ] );
        }

        // Use Aevov Cubbit CDN to get a pre-signed URL for the image.
        $cubbit_cdn = new AevovCubbitCDN();
        $presigned_url = $cubbit_cdn->get_presigned_url( $job['image_url'] );

        if ( is_wp_error( $presigned_url ) ) {
            return new \WP_Error( 'cdn_error', $presigned_url->get_error_message(), [ 'status' => 500 ] );
        }

        wp_redirect( $presigned_url );
        exit;
    }
}
