<?php
/**
 * Template for directory scanning interface with model selection
 */
?>
<div class="wrap">
    <h1><?php _e('Directory Scanner', 'aps-tools'); ?></h1>

    <div class="scanner-container">
        <!-- Configuration Section -->
        <div class="scanner-section">
            <h2><?php _e('Scanner Configuration', 'aps-tools'); ?></h2>
            
            <div class="scanner-config">
                <!-- Model Category Selection -->
                <div class="form-field">
                    <label for="model-category"><?php _e('Model Category', 'aps-tools'); ?></label>
                    <?php
                    $terms = get_terms([
                        'taxonomy' => 'model_category',
                        'hide_empty' => false,
                    ]);
                    ?>
                    <select id="model-category" class="widefat" required>
                        <option value=""><?php _e('Select Model Category', 'aps-tools'); ?></option>
                        <?php foreach ($terms as $term): ?>
                            <option value="<?php echo esc_attr($term->term_id); ?>">
                                <?php echo esc_html($term->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Select the model category for the processed files', 'aps-tools'); ?>
                    </p>
                </div>

                <!-- Parent Model Selection -->
                <div class="form-field">
                    <label for="parent-model"><?php _e('Parent Model', 'aps-tools'); ?></label>
                    <select id="parent-model" class="widefat" required>
                        <option value=""><?php _e('Select Parent Model', 'aps-tools'); ?></option>
                        <!-- Populated via JavaScript based on category selection -->
                    </select>
                    <p class="description">
                        <?php _e('Select the parent model for the processed files', 'aps-tools'); ?>
                    </p>
                </div>

                <!-- Directory Selection -->
                <div class="form-field">
                    <label for="directory-path"><?php _e('Directory Path', 'aps-tools'); ?></label>
                    <input type="text" 
                           id="directory-path" 
                           class="widefat"
                           placeholder="<?php esc_attr_e('Enter directory path', 'aps-tools'); ?>"
                           required>
                    <p class="description">
                        <?php _e('Enter the full server path to the directory containing JSON files', 'aps-tools'); ?>
                    </p>
                </div>
                
                <div class="scanner-section">
    <h2><?php _e('Data Table View', 'aps-tools'); ?></h2>
    
    <div class="table-view-container">
        <?php 
        // Initialize and render table
        if (class_exists('\APSTools\Handlers\TableHandler')) {
            $table_handler = \APSTools\Handlers\TableHandler::instance();
            $table_handler->render_table('directory-data-table');
        }
        ?>
    </div>
</div>

                <!-- Processing Options -->
                <div class="form-field">
                    <label for="batch-size"><?php _e('Batch Size', 'aps-tools'); ?></label>
                    <input type="number" 
                           id="batch-size" 
                           class="small-text"
                           value="50" 
                           min="1" 
                           max="100">
                    <p class="description">
                        <?php _e('Number of files to process in each batch', 'aps-tools'); ?>
                    </p>
                </div>

                <div class="form-field">
                    <label>
                        <input type="checkbox" id="recursive-scan" checked>
                        <?php _e('Scan Subdirectories', 'aps-tools'); ?>
                    </label>
                </div>

                <div class="form-actions">
                    <button id="start-scan" class="button button-primary">
                        <?php _e('Start Scanning', 'aps-tools'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Progress Section -->
        <div class="scanner-section" id="progress-section" style="display: none;">
            <h2><?php _e('Scanning Progress', 'aps-tools'); ?></h2>
            <div class="scan-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-stats">
                    <span class="stat">
                        <?php _e('Processed:', 'aps-tools'); ?> 
                        <span id="processed-count">0</span>
                    </span>
                    <span class="stat">
                        <?php _e('Failed:', 'aps-tools'); ?> 
                        <span id="failed-count">0</span>
                    </span>
                    <span class="stat">
                        <?php _e('Pending:', 'aps-tools'); ?> 
                        <span id="pending-count">0</span>
                    </span>
                </div>
                <button id="stop-scan" class="button button-secondary">
                    <?php _e('Stop Scanning', 'aps-tools'); ?>
                </button>
            </div>
        </div>
        
        
        

        <!-- Error Log -->
        <div class="scanner-section" id="error-section" style="display: none;">
            <h2><?php _e('Processing Errors', 'aps-tools'); ?></h2>
            <div class="error-log">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('File', 'aps-tools'); ?></th>
                            <th><?php _e('Error', 'aps-tools'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="error-log"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>