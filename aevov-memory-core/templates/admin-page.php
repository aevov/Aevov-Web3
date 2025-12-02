<div class="wrap">
    <h1><?php _e( 'Aevov Memory Core', 'aevov-memory-core' ); ?></h1>

    <div id="memory-designer">
        <h2><?php _e( 'Design Memory System', 'aevov-memory-core' ); ?></h2>
        <form id="memory-design-form">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="memory-type"><?php _e( 'Memory Type', 'aevov-memory-core' ); ?></label>
                        </th>
                        <td>
                            <select id="memory-type" name="memory-type">
                                <option value="short-term"><?php _e( 'Short-term', 'aevov-memory-core' ); ?></option>
                                <option value="long-term"><?php _e( 'Long-term', 'aevov-memory-core' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="capacity"><?php _e( 'Capacity', 'aevov-memory-core' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="capacity" name="capacity" value="1024">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="decay-rate"><?php _e( 'Decay Rate', 'aevov-memory-core' ); ?></label>
                        </th>
                        <td>
                            <input type="number" id="decay-rate" name="decay-rate" value="0.1" step="0.01">
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e( 'Create Memory System', 'aevov-memory-core' ); ?></button>
            </p>
        </form>
    </div>

    <div id="memory-visualizer">
        <h2><?php _e( 'Memory Visualizer', 'aevov-memory-core' ); ?></h2>
        <div id="memory-visualizer-container"></div>
    </div>
</div>
