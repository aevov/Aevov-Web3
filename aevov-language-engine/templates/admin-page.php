<div class="wrap">
    <h1><?php _e( 'Aevov Language Engine', 'aevov-language-engine' ); ?></h1>
    <p><?php _e( 'Generate text using the Aevov Language Engine.', 'aevov-language-engine' ); ?></p>

    <hr>

    <h2><?php _e( 'Generate Text', 'aevov-language-engine' ); ?></h2>
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="aevov-prompt"><?php _e( 'Prompt', 'aevov-language-engine' ); ?></label>
                </th>
                <td>
                    <textarea id="aevov-prompt" name="aevov-prompt" rows="5" class="large-text"></textarea>
                    <p class="description">
                        <?php _e( 'Enter the prompt to generate text from.', 'aevov-language-engine' ); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <p class="submit">
        <button id="aevov-generate-text" class="button button-primary"><?php _e( 'Generate Text', 'aevov-language-engine' ); ?></button>
        <span class="spinner"></span>
    </p>

    <hr>

    <h2><?php _e( 'Generated Text', 'aevov-language-engine' ); ?></h2>
    <div id="aevov-generated-text-container" style="border: 1px solid #ccd0d4; padding: 10px; min-height: 100px; background-color: #f6f7f7;">
        <p><?php _e( 'The generated text will appear here.', 'aevov-language-engine' ); ?></p>
    </div>
</div>
