<div class="wrap">
    <h1><?php _e( 'Aevov Simulation Engine', 'aevov-simulation-engine' ); ?></h1>

    <div id="simulation-viewer">
        <h2><?php _e( 'Simulation Viewer', 'aevov-simulation-engine' ); ?></h2>
        <canvas id="simulation-canvas"></canvas>
    </div>

    <div id="simulation-controls">
        <h2><?php _e( 'Controls', 'aevov-simulation-engine' ); ?></h2>
        <button id="start-simulation"><?php _e( 'Start Simulation', 'aevov-simulation-engine' ); ?></button>
        <button id="stop-simulation"><?php _e( 'Stop Simulation', 'aevov-simulation-engine' ); ?></button>
    </div>

    <h2><?php _e( 'Active Jobs', 'aevov-simulation-engine' ); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e( 'Job ID', 'aevov-simulation-engine' ); ?></th>
                <th><?php _e( 'Status', 'aevov-simulation-engine' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Include JobManager to retrieve jobs
            require_once plugin_dir_path( __FILE__ ) . '../includes/class-job-manager.php';
            $job_manager = new \AevovSimulationEngine\JobManager();

            // Retrieve all options that start with our job ID prefix
            global $wpdb;
            $jobs = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'aevov_simulation_job_%'" );

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
                    <td colspan="2"><?php _e( 'No active jobs found.', 'aevov-simulation-engine' ); ?></td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>

    <h2><?php _e( 'Configuration', 'aevov-simulation-engine' ); ?></h2>
    <form method="post" action="options.php">
        <?php
        settings_fields( 'aevov_simulation_engine_options' );
        do_settings_sections( 'aevov_simulation_engine' );
        submit_button();
        ?>
    </form>
</div>
