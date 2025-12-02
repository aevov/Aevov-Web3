<?php

namespace AevovStream;

class SessionManager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aevov_stream_sessions';
    }

    public function create_session( $params ) {
        global $wpdb;
        $session_id = wp_generate_uuid4();
        $wpdb->insert(
            $this->table_name,
            [
                'session_id' => $session_id,
                'user_id' => get_current_user_id(),
                'params' => json_encode( $params ),
                'playlist' => '',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ]
        );
        return $session_id;
    }

    public function get_session( $session_id ) {
        global $wpdb;
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE session_id = %s", $session_id ) );
        if ( $session ) {
            $session->params = json_decode( $session->params, true );
            $session->playlist = json_decode( $session->playlist, true );
        }
        return $session;
    }

    public function update_session( $session_id, $data ) {
        global $wpdb;
        $wpdb->update(
            $this->table_name,
            [
                'params' => isset( $data['params'] ) ? json_encode( $data['params'] ) : null,
                'playlist' => isset( $data['playlist'] ) ? json_encode( $data['playlist'] ) : null,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'session_id' => $session_id ]
        );
    }

    public function delete_session( $session_id ) {
        global $wpdb;
        $wpdb->delete( $this->table_name, [ 'session_id' => $session_id ] );
    }
}
