<div class="wrap">
    <h1><?php _e( 'Aevov Reasoning Engine', 'aevov-reasoning-engine' ); ?></h1>

    <div id="reasoning-query">
        <h2><?php _e( 'Make a Query', 'aevov-reasoning-engine' ); ?></h2>
        <form id="reasoning-query-form">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="query"><?php _e( 'Query', 'aevov-reasoning-engine' ); ?></label>
                        </th>
                        <td>
                            <textarea id="query" name="query" rows="5" cols="50"></textarea>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e( 'Submit Query', 'aevov-reasoning-engine' ); ?></button>
            </p>
        </form>
    </div>

    <div id="reasoning-result">
        <h2><?php _e( 'Result', 'aevov-reasoning-engine' ); ?></h2>
        <div id="reasoning-result-container"></div>
    </div>
</div>
