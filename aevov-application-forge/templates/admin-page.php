<div class="wrap">
    <h1><?php _e( 'Aevov Application Forge', 'aevov-application-forge' ); ?></h1>

    <div id="application-viewer">
        <h2><?php _e( 'Application Viewer', 'aevov-application-forge' ); ?></h2>
        <div id="application-container"></div>
    </div>

    <div id="application-controls">
        <h2><?php _e( 'Controls', 'aevov-application-forge' ); ?></h2>
        <button id="spawn-application"><?php _e( 'Spawn Application', 'aevov-application-forge' ); ?></button>
        <button id="evolve-application"><?php _e( 'Evolve Application', 'aevov-application-forge' ); ?></button>
    </div>

    <h2><?php _e( 'Active Jobs', 'aevov-application-forge' ); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e( 'Job ID', 'aevov-application-forge' ); ?></th>
                <th><?php _e( 'Status', 'aevov-application-forge' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Include JobManager to retrieve jobs
            require_once plugin_dir_path( __FILE__ ) . '../includes/class-job-manager.php';
            $job_manager = new \AevovApplicationForge\JobManager();

            // Retrieve all options that start with our job ID prefix
            global $wpdb;
            $jobs = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'aevov_forge_job_%'" );

            if ( $jobs ) {
                foreach ( $jobs as $job_option ) {
                    $job_data = unserialize( $job_option->option_value );
                    if ( $job_data ) {
                        ?>
                        <tr>
                            <td><?php echo esc_html( $job_data['job_id'] ); ?></td>
                            <td><?php echo esc_html( $job_data['status'] ); ?></td>
                        </tr>
                        <?php
                    }
                }
            } else {
                ?>
                <tr>
                    <td colspan="2"><?php _e( 'No active jobs found.', 'aevov-application-forge' ); ?></td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>

    <h2><?php _e( 'Configuration', 'aevov-application-forge' ); ?></h2>
    <form method="post" action="options.php">
        <?php
        settings_fields( 'aevov_application_forge_options' );
        do_settings_sections( 'aevov_application_forge' );
        submit_button();
        ?>
    </form>
</div>
