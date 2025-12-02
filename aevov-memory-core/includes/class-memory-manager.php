<?php

namespace AevovMemoryCore;

use AevovCubbitCDN\AevovCubbitCDN;

class MemoryManager {

    private $cubbit_cdn;

    public function __construct() {
        $this->cubbit_cdn = new AevovCubbitCDN();
    }

    /**
     * Reads memory data associated with a given address (post_title of astrocyte CPT).
     *
     * @param string $address The unique identifier (post_title) of the memory.
     * @return array|null The memory data, or null if not found.
     */
    public function read_from_memory( $address ) {
        $args = [
            'post_type'      => 'astrocyte',
            'post_status'    => 'publish',
            'title'          => $address,
            'posts_per_page' => 1,
            'fields'         => 'ids', // Only get post IDs for efficiency
        ];
        $posts = get_posts( $args );

        if ( empty( $posts ) ) {
            return null;
        }

        $post_id = $posts[0];
        $memory_data = get_post_meta( $post_id, 'memory_data', true );
        $cubbit_key = get_post_meta( $post_id, 'cubbit_key', true );

        if ( ! empty( $cubbit_key ) && $this->cubbit_cdn->is_configured() ) {
            // If data is stored in Cubbit, download it
            $downloaded_data = $this->cubbit_cdn->download_data( $cubbit_key );
            if ( ! is_wp_error( $downloaded_data ) ) {
                return json_decode( $downloaded_data, true );
            } else {
                error_log( 'Failed to download memory data from Cubbit: ' . $downloaded_data->get_error_message() );
            }
        }

        return $memory_data; // Return data from post meta if not in Cubbit or download failed
    }

    /**
     * Writes memory data to an astrocyte CPT, optionally offloading to Cubbit.
     *
     * @param string $address The unique identifier (post_title) for the memory.
     * @param array  $data    The memory data to store.
     * @param bool   $offload_to_cubbit Whether to offload large data to Cubbit.
     * @return bool True on success, false on failure.
     */
    public function write_to_memory( $address, $data, $memory_system_id = null, $offload_to_cubbit = false ) {
        $post_id = null;
        $existing_posts = get_posts( [
            'post_type'      => 'astrocyte',
            'post_status'    => 'publish',
            'title'          => $address,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ] );

        if ( ! empty( $existing_posts ) ) {
            $post_id = $existing_posts[0];
        }

        $post_args = [
            'post_title'   => $address,
            'post_type'    => 'astrocyte',
            'post_status'  => 'publish',
        ];

        if ( $post_id ) {
            $post_args['ID'] = $post_id;
            $new_post_id = wp_update_post( $post_args, true );
        } else {
            $new_post_id = wp_insert_post( $post_args, true );
        }

        if ( is_wp_error( $new_post_id ) ) {
            error_log( 'Failed to create/update astrocyte post: ' . $new_post_id->get_error_message() );
            return false;
        }

        if($memory_system_id) {
            update_post_meta( $new_post_id, 'memory_system_id', $memory_system_id );
        }

        $cubbit_key = null;
        if ( $offload_to_cubbit && $this->cubbit_cdn->is_configured() ) {
            $cubbit_key = 'aevov-memory-core/' . sanitize_title( $address ) . '-' . time() . '.json';
            $upload_success = $this->cubbit_cdn->upload_data( $cubbit_key, json_encode( $data ), 'application/json' );

            if ( ! is_wp_error( $upload_success ) ) {
                update_post_meta( $new_post_id, 'cubbit_key', $cubbit_key );
                update_post_meta( $new_post_id, 'memory_data', null ); // Clear in-DB data if offloaded
            } else {
                error_log( 'Failed to offload memory data to Cubbit: ' . $upload_success->get_error_message() );
                // Fallback to storing in DB if Cubbit upload fails
                update_post_meta( $new_post_id, 'memory_data', $data );
                delete_post_meta( $new_post_id, 'cubbit_key' );
            }
        } else {
            update_post_meta( $new_post_id, 'memory_data', $data );
            delete_post_meta( $new_post_id, 'cubbit_key' ); // Ensure no old cubbit key remains
        }

        return true;
    }

    /**
     * Sends a "calcium-like" signal (WordPress action).
     *
     * @param string $target The target of the signal (e.g., a specific astrocyte ID or type).
     * @param array  $payload The data payload of the signal.
     * @return bool True on success.
     */
    public function send_calcium_signal( $target, $payload ) {
        do_action( 'aevov_calcium_signal', $target, $payload );
        return true;
    }

    /**
     * Sends a "gliotransmitter-like" signal (WordPress filter).
     *
     * @param string $target The target of the signal (e.g., a specific neuron ID or type).
     * @param array  $payload The data payload of the signal.
     * @return mixed The filtered response.
     */
    public function send_gliotransmitter_signal( $target, $payload ) {
        $response = apply_filters( 'aevov_gliotransmitter_signal', null, $target, $payload );
        return $response;
    }
}
