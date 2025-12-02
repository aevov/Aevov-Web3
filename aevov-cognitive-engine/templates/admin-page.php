<div class="wrap">
    <h1><?php _e( 'Aevov Cognitive Engine', 'aevov-cognitive-engine' ); ?></h1>

    <div id="problem-solver">
        <h2><?php _e( 'Solve a Problem', 'aevov-cognitive-engine' ); ?></h2>
        <form id="problem-solver-form">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="problem"><?php _e( 'Problem', 'aevov-cognitive-engine' ); ?></label>
                        </th>
                        <td>
                            <textarea id="problem" name="problem" rows="5" cols="50"></textarea>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e( 'Solve', 'aevov-cognitive-engine' ); ?></button>
            </p>
        </form>
    </div>

    <div id="solution">
        <h2><?php _e( 'Solution', 'aevov-cognitive-engine' ); ?></h2>
        <div id="solution-container"></div>
    </div>
</div>
