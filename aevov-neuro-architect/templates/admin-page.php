<div class="wrap">
    <h1><?php _e( 'Aevov Neuro-Architect', 'aevov-neuro-architect' ); ?></h1>

    <div id="blueprint-designer">
        <h2><?php _e( 'Design Architectural Blueprint', 'aevov-neuro-architect' ); ?></h2>
        <form id="blueprint-form">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="blueprint-name"><?php _e( 'Blueprint Name', 'aevov-neuro-architect' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="blueprint-name" name="blueprint-name" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="blueprint-layers"><?php _e( 'Layers', 'aevov-neuro-architect' ); ?></label>
                        </th>
                        <td>
                            <textarea id="blueprint-layers" name="blueprint-layers" rows="10" cols="50"></textarea>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e( 'Compose Model', 'aevov-neuro-architect' ); ?></button>
            </p>
        </form>
    </div>

    <div id="model-visualizer">
        <h2><?php _e( 'Composed Model Visualizer', 'aevov-neuro-architect' ); ?></h2>
        <div id="model-visualizer-container"></div>
    </div>
</div>
