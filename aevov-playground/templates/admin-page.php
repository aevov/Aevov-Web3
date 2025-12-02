<div class="wrap">
    <h1><?php _e( 'Aevov Playground', 'aevov-playground' ); ?></h1>

    <div id="playground-container">
        <div id="playground-toolbar">
            <div class="draggable-block" data-engine="language">Language Engine</div>
            <div class="draggable-block" data-engine="image">Image Engine</div>
            <div class="draggable-block" data-engine="music">Music Forge</div>
            <div class="draggable-block" data-engine="stream">Stream Engine</div>
            <div class="draggable-block" data-engine="application">Application Forge</div>
        </div>
        <div id="playground-canvas"></div>
        <div id="playground-controls">
            <h2><?php _e( 'Aevov-esque Parameters', 'aevov-playground' ); ?></h2>
            <label for="pattern-jitter"><?php _e( 'Pattern Jitter', 'aevov-playground' ); ?></label>
            <input type="range" id="pattern-jitter" name="pattern-jitter" min="0" max="1" step="0.1" value="0.5">
            <label for="cross-modal-synesthesia"><?php _e( 'Cross-modal Synesthesia', 'aevov-playground' ); ?></label>
            <input type="range" id="cross-modal-synesthesia" name="cross-modal-synesthesia" min="0" max="1" step="0.1" value="0.5">
            <button id="spawn-as-application"><?php _e( 'Spawn as Application', 'aevov-playground' ); ?></button>
            <button id="save-as-pattern"><?php _e( 'Save as Pattern', 'aevov-playground' ); ?></button>
        </div>
    </div>
</div>
