<?php
/**
 * BLOOM Model Manager CPT Implementation
 */
namespace APSTools\Models;

class BloomModelManager {
    private static $instance = null;
    private $post_type = 'bloom_model';
    private $chunk_post_type = 'bloom_chunk';
    private $model_category = 'model_category';
    private $model_tag = 'model_tag';

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Only add WordPress hooks if WordPress is loaded
        if (function_exists('add_action')) {
            add_action('init', [$this, 'register_post_types']);
            add_action('init', [$this, 'register_taxonomies']);
            add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
            add_action('save_post', [$this, 'save_meta_boxes']);
            add_filter('manage_bloom_model_posts_columns', [$this, 'set_model_columns']);
            add_action('manage_bloom_model_posts_custom_column', [$this, 'render_model_columns'], 10, 2);
            add_filter('manage_bloom_chunk_posts_columns', [$this, 'set_chunk_columns']);
            add_action('manage_bloom_chunk_posts_custom_column', [$this, 'render_chunk_columns'], 10, 2);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        }
    }

    public function register_post_types() {
        // Register Model CPT
        register_post_type('bloom_model', [
            'labels' => [
                'name' => __('BLOOM Models', 'aps-tools'),
                'singular_name' => __('BLOOM Model', 'aps-tools'),
                'add_new' => __('Add New Model', 'aps-tools'),
                'add_new_item' => __('Add New BLOOM Model', 'aps-tools'),
                'edit_item' => __('Edit BLOOM Model', 'aps-tools'),
                'new_item' => __('New BLOOM Model', 'aps-tools'),
                'view_item' => __('View BLOOM Model', 'aps-tools'),
                'search_items' => __('Search BLOOM Models', 'aps-tools'),
                'not_found' => __('No BLOOM models found', 'aps-tools'),
                'not_found_in_trash' => __('No BLOOM models found in trash', 'aps-tools'),
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-networking',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_position' => 30,
            'taxonomies' => ['model_category', 'model_tag'],
            'show_in_rest' => true,
        ]);

        // Register Chunk CPT
        register_post_type('bloom_chunk', [
            'labels' => [
                'name' => __('Model Chunks', 'aps-tools'),
                'singular_name' => __('Model Chunk', 'aps-tools'),
                'add_new' => __('Add New Chunk', 'aps-tools'),
                'add_new_item' => __('Add New Model Chunk', 'aps-tools'),
                'edit_item' => __('Edit Model Chunk', 'aps-tools'),
                'new_item' => __('New Model Chunk', 'aps-tools'),
                'view_item' => __('View Model Chunk', 'aps-tools'),
                'search_items' => __('Search Model Chunks', 'aps-tools'),
                'not_found' => __('No model chunks found', 'aps-tools'),
                'not_found_in_trash' => __('No model chunks found in trash', 'aps-tools'),
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-archive',
            'supports' => ['title'],
            'menu_position' => 31,
            'show_in_rest' => true,
        ]);
    }

    public function register_taxonomies() {
        // Register Model Category
        register_taxonomy('model_category', ['bloom_model'], [
            'labels' => [
                'name' => __('Model Categories', 'aps-tools'),
                'singular_name' => __('Model Category', 'aps-tools'),
                'search_items' => __('Search Model Categories', 'aps-tools'),
                'all_items' => __('All Model Categories', 'aps-tools'),
                'parent_item' => __('Parent Model Category', 'aps-tools'),
                'parent_item_colon' => __('Parent Model Category:', 'aps-tools'),
                'edit_item' => __('Edit Model Category', 'aps-tools'),
                'update_item' => __('Update Model Category', 'aps-tools'),
                'add_new_item' => __('Add New Model Category', 'aps-tools'),
                'new_item_name' => __('New Model Category Name', 'aps-tools'),
            ],
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
        ]);

        // Register Model Tags
        register_taxonomy('model_tag', ['bloom_model'], [
            'labels' => [
                'name' => __('Model Tags', 'aps-tools'),
                'singular_name' => __('Model Tag', 'aps-tools'),
                'search_items' => __('Search Model Tags', 'aps-tools'),
                'all_items' => __('All Model Tags', 'aps-tools'),
                'edit_item' => __('Edit Model Tag', 'aps-tools'),
                'update_item' => __('Update Model Tag', 'aps-tools'),
                'add_new_item' => __('Add New Model Tag', 'aps-tools'),
                'new_item_name' => __('New Model Tag Name', 'aps-tools'),
            ],
            'hierarchical' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
        ]);
    }

    public function add_meta_boxes() {
        // Model Meta Box
        add_meta_box(
            'bloom_model_meta',
            __('Model Details', 'aps-tools'),
            [$this, 'render_model_meta_box'],
            'bloom_model',
            'normal',
            'high'
        );

        // Chunk Meta Box
        add_meta_box(
            'bloom_chunk_meta',
            __('Chunk Details', 'aps-tools'),
            [$this, 'render_chunk_meta_box'],
            'bloom_chunk',
            'normal',
            'high'
        );
    }

    public function render_model_meta_box($post) {
        wp_nonce_field('bloom_model_meta', 'bloom_model_meta_nonce');
        $model_version = get_post_meta($post->ID, '_model_version', true);
        $total_chunks = get_post_meta($post->ID, '_total_chunks', true);
        $model_size = get_post_meta($post->ID, '_model_size', true);
        ?>
        <div class="bloom-model-meta">
            <p>
                <label for="model_version"><?php _e('Model Version:', 'aps-tools'); ?></label>
                <input type="text" id="model_version" name="model_version" value="<?php echo esc_attr($model_version); ?>">
            </p>
            <p>
                <label for="total_chunks"><?php _e('Total Chunks:', 'aps-tools'); ?></label>
                <input type="number" id="total_chunks" name="total_chunks" value="<?php echo esc_attr($total_chunks); ?>">
            </p>
            <p>
                <label for="model_size"><?php _e('Model Size (MB):', 'aps-tools'); ?></label>
                <input type="number" step="0.01" id="model_size" name="model_size" value="<?php echo esc_attr($model_size); ?>">
            </p>
        </div>
        <?php
    }

    public function render_chunk_meta_box($post) {
    wp_nonce_field('bloom_chunk_meta', 'bloom_chunk_meta_nonce');
    
    // Get existing values
    $chunk_sku = get_post_meta($post->ID, '_chunk_sku', true);
    $chunk_size = get_post_meta($post->ID, '_chunk_size', true);
    $chunk_order = get_post_meta($post->ID, '_chunk_order', true);
    $parent_model = get_post_meta($post->ID, '_parent_model', true);
    $tensor_name = get_post_meta($post->ID, '_tensor_name', true);
    $tensor_dtype = get_post_meta($post->ID, '_tensor_dtype', true);
    $tensor_shape = get_post_meta($post->ID, '_tensor_shape', true);
    $is_partial = get_post_meta($post->ID, '_is_partial', true);
    $part_number = get_post_meta($post->ID, '_part_number', true);
    $total_parts = get_post_meta($post->ID, '_total_parts', true);
    
    ?>
    <div class="bloom-chunk-meta">
        <p>
            <label for="parent_model"><?php _e('Parent Model:', 'aps-tools'); ?></label>
            <?php
            $models = get_posts([
                'post_type' => 'bloom_model',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
            ?>
            <select id="parent_model" name="parent_model">
                <option value=""><?php _e('Select a model', 'aps-tools'); ?></option>
                <?php foreach ($models as $model): ?>
                    <option value="<?php echo esc_attr($model->ID); ?>" <?php selected($parent_model, $model->ID); ?>>
                        <?php echo esc_html($model->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="tensor_name"><?php _e('Tensor Name:', 'aps-tools'); ?></label>
            <input type="text" id="tensor_name" name="tensor_name" value="<?php echo esc_attr($tensor_name); ?>" class="widefat">
        </p>

        <p>
            <label for="chunk_sku"><?php _e('Chunk SKU:', 'aps-tools'); ?></label>
            <input type="text" id="chunk_sku" name="chunk_sku" value="<?php echo esc_attr($chunk_sku); ?>" required>
        </p>

        <p>
            <label for="tensor_dtype"><?php _e('Data Type:', 'aps-tools'); ?></label>
            <input type="text" id="tensor_dtype" name="tensor_dtype" value="<?php echo esc_attr($tensor_dtype); ?>" required>
        </p>

        <p>
            <label for="tensor_shape"><?php _e('Tensor Shape:', 'aps-tools'); ?></label>
            <input type="text" id="tensor_shape" name="tensor_shape" value="<?php echo esc_attr($tensor_shape); ?>" required>
            <span class="description"><?php _e('Enter shape as comma-separated values, e.g. "4096,16384"', 'aps-tools'); ?></span>
        </p>

        <div class="partition-details" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
            <h4><?php _e('Partition Details', 'aps-tools'); ?></h4>
            
            <p>
                <label>
                    <input type="checkbox" id="is_partial" name="is_partial" value="1" <?php checked($is_partial, true); ?>>
                    <?php _e('This is a partial chunk', 'aps-tools'); ?>
                </label>
            </p>

            <div class="partial-fields" style="<?php echo $is_partial ? '' : 'display: none;'; ?>">
                <p>
                    <label for="part_number"><?php _e('Part Number:', 'aps-tools'); ?></label>
                    <input type="number" id="part_number" name="part_number" value="<?php echo esc_attr($part_number); ?>" min="0">
                </p>

                <p>
                    <label for="total_parts"><?php _e('Total Parts:', 'aps-tools'); ?></label>
                    <input type="number" id="total_parts" name="total_parts" value="<?php echo esc_attr($total_parts); ?>" min="1">
                </p>
            </div>
        </div>

        <div class="chunk-upload">
            <label><?php _e('Upload Chunk JSON:', 'aps-tools'); ?></label>
            <input type="file" id="chunk_data" name="chunk_data" accept=".json">
            <div id="chunk_preview" class="chunk-preview"></div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#is_partial').on('change', function() {
            $('.partial-fields').toggle($(this).is(':checked'));
        });

        // Handle file upload and auto-fill
        $('#chunk_data').on('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const json = JSON.parse(e.target.result);
                    // Get the first key-value pair from the JSON
                    const tensorName = Object.keys(json)[0];
                    const tensorData = json[tensorName];

                    // Auto-fill the fields
                    $('#tensor_name').val(tensorName);
                    $('#chunk_sku').val(tensorData.sku);
                    $('#tensor_dtype').val(tensorData.dtype);
                    $('#tensor_shape').val(tensorData.shape.join(','));
                    
                    if (tensorData.is_partial) {
                        $('#is_partial').prop('checked', true).trigger('change');
                        $('#part_number').val(tensorData.part);
                        $('#total_parts').val(tensorData.total_parts);
                    }
                    
                    // Update preview
                    updatePreview(tensorName, tensorData);
                } catch (error) {
                    alert('Invalid JSON file');
                }
            };
            reader.readAsText(file);
        });

        function updatePreview(tensorName, data) {
            const preview = `
                <div class="chunk-preview-data">
                    <div class="preview-row">
                        <span class="preview-label">Tensor:</span>
                        <span class="preview-value">${tensorName}</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">SKU:</span>
                        <span class="preview-value">${data.sku}</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">Type:</span>
                        <span class="preview-value">${data.dtype}</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-label">Shape:</span>
                        <span class="preview-value">${data.shape.join(' × ')}</span>
                    </div>
                    ${data.is_partial ? `
                    <div class="preview-row">
                        <span class="preview-label">Part:</span>
                        <span class="preview-value">${data.part + 1} of ${data.total_parts}</span>
                    </div>
                    ` : ''}
                </div>
            `;
            $('#chunk_preview').html(preview);
        }
    });
    </script>
    <?php
}

   public function save_meta_boxes($post_id) {
    // Verify nonce and permissions
    if (!isset($_POST['bloom_chunk_meta_nonce']) || 
        !wp_verify_nonce($_POST['bloom_chunk_meta_nonce'], 'bloom_chunk_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save chunk metadata
    $fields = [
        '_parent_model' => 'intval',
        '_tensor_name' => 'sanitize_text_field',
        '_chunk_sku' => 'sanitize_text_field',
        '_tensor_dtype' => 'sanitize_text_field',
        '_tensor_shape' => 'sanitize_text_field',
        '_is_partial' => 'boolval',
        '_part_number' => 'intval',
        '_total_parts' => 'intval'
    ];

    foreach ($fields as $meta_key => $sanitize_callback) {
        $field_name = ltrim($meta_key, '_'); // Remove leading underscore for POST field
        if (isset($_POST[$field_name])) {
            $value = call_user_func($sanitize_callback, $_POST[$field_name]);
            update_post_meta($post_id, $meta_key, $value);
        }
    }

    // Handle file upload
    if (isset($_FILES['chunk_data']) && !empty($_FILES['chunk_data']['tmp_name'])) {
        $this->handle_chunk_upload($post_id);
    }
}


   private function handle_chunk_upload($post_id) {
    $upload_dir = wp_upload_dir();
    $chunks_dir = $upload_dir['basedir'] . '/bloom-chunks';

    // Create chunks directory if it doesn't exist
    if (!file_exists($chunks_dir)) {
        wp_mkdir_p($chunks_dir);
    }

    $file = $_FILES['chunk_data'];
    $chunk_sku = get_post_meta($post_id, '_chunk_sku', true);
    $filename = $chunks_dir . '/' . sanitize_file_name($chunk_sku . '.json');

    if (move_uploaded_file($file['tmp_name'], $filename)) {
        // Store the file location
        update_post_meta($post_id, '_chunk_file', $filename);
        update_post_meta($post_id, '_chunk_uploaded', current_time('mysql'));

        // Read and store the tensor data using the BloomTensorStorage class
        $tensor_data = json_decode(file_get_contents($filename), true);
        if ($tensor_data) {
            BloomTensorStorage::instance()->store_tensor_data($post_id, $tensor_data);
        }
    }
}

    public function set_model_columns($columns) {
        $columns = [
            'cb' => $columns['cb'],
            'title' => __('Model Name', 'aps-tools'),
            'version' => __('Version', 'aps-tools'),
            'chunks' => __('Chunks', 'aps-tools'),
            'size' => __('Size', 'aps-tools'),
            'category' => __('Category', 'aps-tools'),
            'date' => __('Date', 'aps-tools'),
        ];
        return $columns;
    }

    public function render_model_columns($column, $post_id) {
        switch ($column) {
            case 'version':
                echo esc_html(get_post_meta($post_id, '_model_version', true));
                break;
            case 'chunks':
                $total = get_post_meta($post_id, '_total_chunks', true);
                $uploaded = $this->count_model_chunks($post_id);
                echo sprintf(__('%d / %d', 'aps-tools'), $uploaded, $total);
                break;
            case 'size':
                $size = get_post_meta($post_id, '_model_size', true);
                echo esc_html($size . ' MB');
                break;
            case 'category':
                $terms = get_the_terms($post_id, 'model_category');
                if ($terms) {
                    echo esc_html(join(', ', wp_list_pluck($terms, 'name')));
                }
                break;
        }
    }

    public function set_chunk_columns($columns) {
        $columns = [
            'cb' => $columns['cb'],
            'title' => __('Chunk Name', 'aps-tools'),
            'sku' => __('SKU', 'aps-tools'),
            'size' => __('Size', 'aps-tools'),
            'order' => __('Order', 'aps-tools'),
            'model' => __('Parent Model', 'aps-tools'),
            'uploaded' => __('Uploaded', 'aps-tools'),
        ];
        return $columns;
    }

public function render_chunk_columns($column, $post_id) {
        switch ($column) {
            case 'sku':
                echo esc_html(get_post_meta($post_id, '_chunk_sku', true));
                break;
            case 'size':
                $size = get_post_meta($post_id, '_chunk_size', true);
                echo esc_html($size . ' MB');
                break;
            case 'order':
                echo esc_html(get_post_meta($post_id, '_chunk_order', true));
                break;
            case 'model':
                $parent_id = get_post_meta($post_id, '_parent_model', true);
                if ($parent_id) {
                    $parent = get_post($parent_id);
                    if ($parent) {
                        echo '<a href="' . get_edit_post_link($parent_id) . '">' . 
                             esc_html($parent->post_title) . '</a>';
                    }
                }
                break;
            case 'uploaded':
                $uploaded = get_post_meta($post_id, '_chunk_uploaded', true);
                if ($uploaded) {
                    echo esc_html(get_date_from_gmt($uploaded, get_option('date_format') . ' ' . get_option('time_format')));
                } else {
                    echo '—';
                }
                break;
        }
    }

    public function enqueue_assets($hook) {
        global $post_type;
        
        if (!in_array($hook, ['post.php', 'post-new.php']) || 
            !in_array($post_type, ['bloom_model', 'bloom_chunk'])) {
            return;
        }

        wp_enqueue_style(
            'bloom-model-admin',
            APSTOOLS_URL . 'assets/css/bloom-model-admin.css',
            [],
            APSTOOLS_VERSION
        );

        wp_enqueue_script(
            'bloom-model-admin',
            APSTOOLS_URL . 'assets/js/bloom-model-admin.js',
            ['jquery'],
            APSTOOLS_VERSION,
            true
        );

        wp_localize_script('bloom-model-admin', 'bloomModelAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bloom-model-admin'),
            'i18n' => [
                'uploadError' => __('Error uploading chunk file', 'aps-tools'),
                'invalidJson' => __('Invalid JSON file', 'aps-tools'),
                'chunkSaved' => __('Chunk saved successfully', 'aps-tools')
            ]
        ]);
    }

    private function count_model_chunks($model_id) {
        $args = [
            'post_type' => 'bloom_chunk',
            'meta_key' => '_parent_model',
            'meta_value' => $model_id,
            'post_status' => 'any',
            'posts_per_page' => -1,
        ];
        
        $chunks = get_posts($args);
        return count($chunks);
    }

    public function get_model_chunks($model_id) {
        $args = [
            'post_type' => 'bloom_chunk',
            'meta_key' => '_parent_model',
            'meta_value' => $model_id,
            'meta_key' => '_chunk_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'posts_per_page' => -1,
        ];
        
        return get_posts($args);
    }

    public function get_models_by_category($category_slug) {
        $args = [
            'post_type' => 'bloom_model',
            'tax_query' => [
                [
                    'taxonomy' => 'model_category',
                    'field' => 'slug',
                    'terms' => $category_slug,
                ]
            ],
            'posts_per_page' => -1,
        ];
        
        return get_posts($args);
    }

    public function validate_chunk_data($chunk_data) {
        if (!is_array($chunk_data)) {
            return false;
        }

        $required_fields = ['sku', 'dtype', 'shape', 'data'];
        foreach ($required_fields as $field) {
            if (!isset($chunk_data[$field])) {
                return false;
            }
        }

        return true;
    }

    public function get_chunk_file_path($chunk_id) {
        return get_post_meta($chunk_id, '_chunk_file', true);
    }

    public function get_chunk_data($chunk_id) {
    $tensor_data = BloomTensorStorage::instance()->get_tensor_data($chunk_id);
    if ($tensor_data) {
        return $tensor_data;
    }

    return false;
}

    public function model_is_complete($model_id) {
        $total_chunks = get_post_meta($model_id, '_total_chunks', true);
        $uploaded_chunks = $this->count_model_chunks($model_id);
        
        return $total_chunks > 0 && $total_chunks === $uploaded_chunks;
    }

    /**
     * Get all available models with their chunks
     */
    public function get_available_models() {
        $args = [
            'post_type' => 'bloom_model',
            'posts_per_page' => -1,
        ];

        $models = get_posts($args);
        $available_models = [];

        foreach ($models as $model) {
            if ($this->model_is_complete($model->ID)) {
                $chunks = $this->get_model_chunks($model->ID);
                $available_models[] = [
                    'id' => $model->ID,
                    'title' => $model->post_title,
                    'version' => get_post_meta($model->ID, '_model_version', true),
                    'size' => get_post_meta($model->ID, '_model_size', true),
                    'chunks' => array_map(function($chunk) {
                        return [
                            'id' => $chunk->ID,
                            'sku' => get_post_meta($chunk->ID, '_chunk_sku', true),
                            'order' => get_post_meta($chunk->ID, '_chunk_order', true),
                            'size' => get_post_meta($chunk->ID, '_chunk_size', true),
                        ];
                    }, $chunks),
                    'categories' => wp_get_post_terms($model->ID, 'model_category', ['fields' => 'names']),
                    'tags' => wp_get_post_terms($model->ID, 'model_tag', ['fields' => 'names'])
                ];
            }
        }

        return $available_models;
    }
}

// Initialize the class
// Only initialize if WordPress is loaded
if (function_exists('add_action')) {
    add_action('plugins_loaded', function() {
        BloomModelManager::instance();
    });
}