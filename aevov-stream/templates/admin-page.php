<div class="wrap">
    <h1><?php _e( 'Aevov Stream', 'aevov-stream' ); ?></h1>

    <h2><?php _e( 'Active Sessions', 'aevov-stream' ); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e( 'Session ID', 'aevov-stream' ); ?></th>
                <th><?php _e( 'User ID', 'aevov-stream' ); ?></th>
                <th><?php _e( 'Created At', 'aevov-stream' ); ?></th>
                <th><?php _e( 'Updated At', 'aevov-stream' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'aevov_stream_sessions';
            $sessions = $wpdb->get_results( "SELECT * FROM $table_name" );
            foreach ( $sessions as $session ) {
                ?>
                <tr>
                    <td><?php echo esc_html( $session->session_id ); ?></td>
                    <td><?php echo esc_html( $session->user_id ); ?></td>
                    <td><?php echo esc_html( $session->created_at ); ?></td>
                    <td><?php echo esc_html( $session->updated_at ); ?></td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>

    <h2><?php _e( 'Configuration', 'aevov-stream' ); ?></h2>
    <form method="post" action="options.php">
        <?php
        settings_fields( 'aevov_stream_options' );
        do_settings_sections( 'aevov_stream' );
        submit_button();
        ?>
    </form>
</div>
