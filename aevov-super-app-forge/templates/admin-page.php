<div class="wrap">
    <h1><?php _e( 'Aevov Super-App Forge', 'aevov-super-app-forge' ); ?></h1>

    <div id="app-ingestion">
        <h2><?php _e( 'Ingest App', 'aevov-super-app-forge' ); ?></h2>
        <form id="app-ingestion-form">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="app-url"><?php _e( 'App URL', 'aevov-super-app-forge' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="app-url" name="app-url" class="regular-text">
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e( 'Ingest', 'aevov-super-app-forge' ); ?></button>
            </p>
        </form>
    </div>

    <div id="app-generation">
        <h2><?php _e( 'Generate App', 'aevov-super-app-forge' ); ?></h2>
        <button id="generate-app"><?php _e( 'Generate App', 'aevov-super-app-forge' ); ?></button>
    </div>

    <div id="simulation-viewer">
        <h2><?php _e( 'Simulation Viewer', 'aevov-super-app-forge' ); ?></h2>
        <canvas id="simulation-canvas"></canvas>
    </div>
</div>
