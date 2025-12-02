<?php
/**
 * Model Management Class
 * Handles model download, management, and integration
 */

namespace AevovDemo;

class ModelManagement {
    private $available_models;
    private $upload_dir;
    
    public function __construct() {
        $this->setup_upload_directory();
        $this->available_models = $this->get_available_models();
    }
    
    private function setup_upload_directory() {
        $upload_dir = wp_upload_dir();
        $this->upload_dir = $upload_dir['basedir'] . '/aevov-models';
        
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }
    }
    
    private function get_available_models() {
        return [
            'phi-3-mini' => [
                'name' => 'Phi-3 Mini (3.8B)',
                'description' => 'Microsoft\'s lightweight language model optimized for mobile and edge devices',
                'size' => '2.4GB',
                'files' => [
                    'https://huggingface.co/microsoft/Phi-3-mini-4k-instruct/resolve/main/config.json',
                    'https://huggingface.co/microsoft/Phi-3-mini-4k-instruct/resolve/main/tokenizer.json',
                    'https://huggingface.co/microsoft/Phi-3-mini-4k-instruct/resolve/main/pytorch_model.bin'
                ],
                'chunk_compatible' => true,
                'context_length' => 4096,
                'recommended' => true
            ],
            'tinyllama' => [
                'name' => 'TinyLlama 1.1B',
                'description' => 'Compact Llama-based model for efficient inference',
                'size' => '637MB',
                'files' => [
                    'https://huggingface.co/TinyLlama/TinyLlama-1.1B-Chat-v1.0/resolve/main/config.json',
                    'https://huggingface.co/TinyLlama/TinyLlama-1.1B-Chat-v1.0/resolve/main/tokenizer.json',
                    'https://huggingface.co/TinyLlama/TinyLlama-1.1B-Chat-v1.0/resolve/main/pytorch_model.bin'
                ],
                'chunk_compatible' => true,
                'context_length' => 2048,
                'recommended' => false
            ],
            'gemma-2b' => [
                'name' => 'Gemma 2B',
                'description' => 'Google\'s efficient small language model',
                'size' => '1.4GB',
                'files' => [
                    'https://huggingface.co/google/gemma-2b/resolve/main/config.json',
                    'https://huggingface.co/google/gemma-2b/resolve/main/tokenizer.json',
                    'https://huggingface.co/google/gemma-2b/resolve/main/pytorch_model.bin'
                ],
                'chunk_compatible' => true,
                'context_length' => 8192,
                'recommended' => true
            ],
            'qwen-1.8b' => [
                'name' => 'Qwen 1.8B Chat',
                'description' => 'Alibaba\'s multilingual chat model',
                'size' => '1.1GB',
                'files' => [
                    'https://huggingface.co/Qwen/Qwen-1_8B-Chat/resolve/main/config.json',
                    'https://huggingface.co/Qwen/Qwen-1_8B-Chat/resolve/main/tokenizer.json',
                    'https://huggingface.co/Qwen/Qwen-1_8B-Chat/resolve/main/pytorch_model.bin'
                ],
                'chunk_compatible' => true,
                'context_length' => 32768,
                'recommended' => false
            ],
            'stablelm-2-1.6b' => [
                'name' => 'StableLM 2 1.6B',
                'description' => 'Stability AI\'s efficient language model',
                'size' => '950MB',
                'files' => [
                    'https://huggingface.co/stabilityai/stablelm-2-1_6b/resolve/main/config.json',
                    'https://huggingface.co/stabilityai/stablelm-2-1_6b/resolve/main/tokenizer.json',
                    'https://huggingface.co/stabilityai/stablelm-2-1_6b/resolve/main/pytorch_model.bin'
                ],
                'chunk_compatible' => true,
                'context_length' => 4096,
                'recommended' => false
            ]
        ];
    }
    
    public function render_model_management_page() {
        $downloaded_models = $this->get_downloaded_models();
        $quick_demo = isset($_GET['quick_demo']) && $_GET['quick_demo'] === '1';
        
        ?>
        <div class="ads-demo-container">
            <div class="ads-header">
                <h1><?php _e('Model Management', 'aevov-demo-system'); ?></h1>
                <p><?php _e('Download and manage Small Language Models for the Aevov deAI network', 'aevov-demo-system'); ?></p>
                <div class="ads-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=aevov-demo-system'); ?>" class="button button-secondary">
                        ← <?php _e('Back to Dashboard', 'aevov-demo-system'); ?>
                    </a>
                    <?php if ($quick_demo): ?>
                        <button class="button button-primary" onclick="adsDemo.startQuickWorkflow()">
                            <?php _e('🚀 Continue Quick Demo', 'aevov-demo-system'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($quick_demo): ?>
                <div class="ads-card" style="background: #e7f3ff; border-color: #0073aa; margin-bottom: 30px;">
                    <h3 style="color: #0073aa;"><?php _e('🚀 Quick Demo Mode', 'aevov-demo-system'); ?></h3>
                    <p><?php _e('You\'re in quick demo mode! Select a recommended model below to automatically download and process it through the entire Aevov workflow.', 'aevov-demo-system'); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Downloaded Models Section -->
            <?php if (!empty($downloaded_models)): ?>
                <div class="ads-section">
                    <h2><?php _e('Downloaded Models', 'aevov-demo-system'); ?></h2>
                    <div class="ads-model-grid">
                        <?php foreach ($downloaded_models as $model): ?>
                            <div class="ads-model-card ads-downloaded-model" data-model-key="<?php echo esc_attr($model['model_key']); ?>">
                                <div class="ads-model-header">
                                    <h4><?php echo esc_html($model['model_name']); ?></h4>
                                    <span class="ads-model-status <?php echo esc_attr($model['status']); ?>">
                                        <?php echo esc_html(ucfirst($model['status'])); ?>
                                    </span>
                                </div>
                                
                                <div class="ads-model-info">
                                    <div class="ads-model-spec">
                                        <span class="ads-model-spec-label"><?php _e('Downloaded:', 'aevov-demo-system'); ?></span>
                                        <span class="ads-model-spec-value"><?php echo esc_html($model['download_date']); ?></span>
                                    </div>
                                    <div class="ads-model-spec">
                                        <span class="ads-model-spec-label"><?php _e('Processing:', 'aevov-demo-system'); ?></span>
                                        <span class="ads-model-spec-value"><?php echo esc_html(ucfirst($model['processing_status'])); ?></span>
                                    </div>
                                </div>
                                
                                <?php if ($model['status'] === 'completed'): ?>
                                    <div class="ads-model-actions">
                                        <button class="button button-primary ads-start-workflow" data-model-key="<?php echo esc_attr($model['model_key']); ?>">
                                            <?php _e('Start Workflow', 'aevov-demo-system'); ?>
                                        </button>
                                        <button class="button button-secondary ads-configure-model" data-model-key="<?php echo esc_attr($model['model_key']); ?>">
                                            <?php _e('Configure', 'aevov-demo-system'); ?>
                                        </button>
                                        <button class="button ads-delete-model" data-model-key="<?php echo esc_attr($model['model_key']); ?>">
                                            <?php _e('Delete', 'aevov-demo-system'); ?>
                                        </button>
                                    </div>
                                <?php elseif ($model['status'] === 'downloading'): ?>
                                    <div class="ads-progress-container">
                                        <div class="ads-progress-bar" data-model="<?php echo esc_attr($model['model_key']); ?>">
                                            <div class="ads-progress-fill" style="width: <?php echo esc_attr($model['progress']); ?>%"></div>
                                        </div>
                                        <div class="ads-progress-text" data-model="<?php echo esc_attr($model['model_key']); ?>">
                                            <?php echo esc_html($model['progress']); ?>% complete
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Available Models Section -->
            <div class="ads-section">
                <h2><?php _e('Available Models', 'aevov-demo-system'); ?></h2>
                <p><?php _e('Select from our curated collection of Small Language Models optimized for the Aevov neurosymbolic architecture.', 'aevov-demo-system'); ?></p>
                
                <div class="ads-model-grid">
                    <?php foreach ($this->available_models as $model_key => $model): ?>
                        <?php
                        $is_downloaded = $this->is_model_downloaded($model_key);
                        $is_recommended = $model['recommended'] ?? false;
                        ?>
                        <div class="ads-model-card <?php echo $is_recommended ? 'ads-recommended' : ''; ?> <?php echo $is_downloaded ? 'ads-already-downloaded' : ''; ?>" 
                             data-model-key="<?php echo esc_attr($model_key); ?>">
                            
                            <?php if ($is_recommended): ?>
                                <div class="ads-recommended-badge">
                                    ⭐ <?php _e('Recommended', 'aevov-demo-system'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="ads-model-header">
                                <h4><?php echo esc_html($model['name']); ?></h4>
                                <span class="ads-model-size"><?php echo esc_html($model['size']); ?></span>
                            </div>
                            
                            <div class="ads-model-description">
                                <?php echo esc_html($model['description']); ?>
                            </div>
                            
                            <div class="ads-model-specs">
                                <div class="ads-model-spec">
                                    <span class="ads-model-spec-label"><?php _e('Context Length:', 'aevov-demo-system'); ?></span>
                                    <span class="ads-model-spec-value"><?php echo number_format($model['context_length']); ?></span>
                                </div>
                                <div class="ads-model-spec">
                                    <span class="ads-model-spec-label"><?php _e('Chunk Compatible:', 'aevov-demo-system'); ?></span>
                                    <span class="ads-model-spec-value"><?php echo $model['chunk_compatible'] ? '✅' : '❌'; ?></span>
                                </div>
                                <div class="ads-model-spec">
                                    <span class="ads-model-spec-label"><?php _e('Files:', 'aevov-demo-system'); ?></span>
                                    <span class="ads-model-spec-value"><?php echo count($model['files']); ?></span>
                                </div>
                                <div class="ads-model-spec">
                                    <span class="ads-model-spec-label"><?php _e('Size:', 'aevov-demo-system'); ?></span>
                                    <span class="ads-model-spec-value"><?php echo esc_html($model['size']); ?></span>
                                </div>
                            </div>
                            
                            <div class="ads-model-files">
                                <h5><?php _e('Model Files:', 'aevov-demo-system'); ?></h5>
                                <ul class="ads-file-list">
                                    <?php foreach ($model['files'] as $file_url): ?>
                                        <li>
                                            <span class="ads-file-name"><?php echo esc_html(basename($file_url)); ?></span>
                                            <a href="<?php echo esc_url($file_url); ?>" target="_blank" class="ads-file-link">
                                                <?php _e('View', 'aevov-demo-system'); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <div class="ads-model-actions">
                                <?php if ($is_downloaded): ?>
                                    <button class="button button-secondary" disabled>
                                        ✅ <?php _e('Already Downloaded', 'aevov-demo-system'); ?>
                                    </button>
                                <?php else: ?>
                                    <button class="button button-primary ads-download-model" data-model-key="<?php echo esc_attr($model_key); ?>">
                                        <?php _e('Download Model', 'aevov-demo-system'); ?>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="button button-secondary ads-preview-model" data-model-key="<?php echo esc_attr($model_key); ?>">
                                    <?php _e('Preview', 'aevov-demo-system'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Bulk Operations Section -->
            <div class="ads-section">
                <h2><?php _e('Bulk Operations', 'aevov-demo-system'); ?></h2>
                <div class="ads-bulk-operations">
                    <div class="ads-bulk-action">
                        <h4><?php _e('Download Recommended Models', 'aevov-demo-system'); ?></h4>
                        <p><?php _e('Download all recommended models for optimal Aevov performance.', 'aevov-demo-system'); ?></p>
                        <button class="button button-primary ads-download-recommended">
                            <?php _e('Download All Recommended', 'aevov-demo-system'); ?>
                        </button>
                    </div>
                    
                    <div class="ads-bulk-action">
                        <h4><?php _e('Clear All Models', 'aevov-demo-system'); ?></h4>
                        <p><?php _e('Remove all downloaded models and reset the system.', 'aevov-demo-system'); ?></p>
                        <button class="button ads-danger ads-clear-all-models">
                            <?php _e('Clear All Models', 'aevov-demo-system'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Model Preview Modal -->
        <div id="ads-model-preview-modal" class="ads-modal" style="display: none;">
            <div class="ads-modal-content">
                <div class="ads-modal-header">
                    <h3 id="ads-preview-model-name"></h3>
                    <button class="ads-modal-close">&times;</button>
                </div>
                <div class="ads-modal-body">
                    <div id="ads-preview-content"></div>
                </div>
                <div class="ads-modal-footer">
                    <button class="button button-secondary ads-modal-close">
                        <?php _e('Close', 'aevov-demo-system'); ?>
                    </button>
                    <button class="button button-primary" id="ads-preview-download">
                        <?php _e('Download This Model', 'aevov-demo-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <style>
        .ads-recommended {
            border-color: #ffc107 !important;
            position: relative;
        }
        
        .ads-recommended-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #ffc107;
            color: #333;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
            z-index: 1;
        }
        
        .ads-already-downloaded {
            opacity: 0.7;
            border-color: #28a745 !important;
        }
        
        .ads-model-files {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .ads-model-files h5 {
            margin: 0 0 10px 0;
            font-size: 0.9em;
            color: #666;
        }
        
        .ads-file-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .ads-file-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .ads-file-list li:last-child {
            border-bottom: none;
        }
        
        .ads-file-name {
            font-family: monospace;
            font-size: 0.85em;
            color: #333;
        }
        
        .ads-file-link {
            font-size: 0.8em;
            color: #667eea;
            text-decoration: none;
        }
        
        .ads-file-link:hover {
            text-decoration: underline;
        }
        
        .ads-bulk-operations {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .ads-bulk-action {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 25px;
        }
        
        .ads-bulk-action h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .ads-bulk-action p {
            color: #666;
            margin-bottom: 15px;
        }
        
        .ads-danger {
            background: #dc3545 !important;
            border-color: #dc3545 !important;
            color: white !important;
        }
        
        .ads-danger:hover {
            background: #c82333 !important;
            border-color: #c82333 !important;
        }
        
        .ads-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ads-modal-content {
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 80%;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .ads-modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ads-modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .ads-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .ads-modal-body {
            padding: 25px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .ads-modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .ads-section {
            margin-bottom: 50px;
        }
        
        .ads-section h2 {
            margin-bottom: 20px;
            color: #333;
            font-size: 1.8em;
            font-weight: 300;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Model preview functionality
            $('.ads-preview-model').on('click', function() {
                const modelKey = $(this).data('model-key');
                adsDemo.showModelPreview(modelKey);
            });
            
            // Modal close functionality
            $('.ads-modal-close').on('click', function() {
                $('#ads-model-preview-modal').hide();
            });
            
            // Download recommended models
            $('.ads-download-recommended').on('click', function() {
                adsDemo.downloadRecommendedModels();
            });
            
            // Clear all models
            $('.ads-clear-all-models').on('click', function() {
                if (confirm('Are you sure you want to delete all downloaded models? This cannot be undone.')) {
                    adsDemo.clearAllModels();
                }
            });
            
            // Quick demo workflow
            if (typeof adsDemo !== 'undefined') {
                adsDemo.startQuickWorkflow = function() {
                    const recommendedModel = $('.ads-model-card.ads-recommended:not(.ads-already-downloaded)').first();
                    if (recommendedModel.length) {
                        const modelKey = recommendedModel.data('model-key');
                        adsDemo.downloadModel(modelKey);
                        
                        // Auto-start workflow after download
                        setTimeout(() => {
                            adsDemo.startWorkflow(modelKey);
                        }, 5000);
                    } else {
                        adsDemo.showNotification('No recommended models available for quick demo', 'warning');
                    }
                };
                
                adsDemo.showModelPreview = function(modelKey) {
                    const model = adsDemo.models[modelKey];
                    if (!model) return;
                    
                    $('#ads-preview-model-name').text(model.name);
                    $('#ads-preview-content').html(`
                        <div class="ads-preview-details">
                            <p><strong>Description:</strong> ${model.description}</p>
                            <p><strong>Size:</strong> ${model.size}</p>
                            <p><strong>Context Length:</strong> ${model.context_length.toLocaleString()}</p>
                            <p><strong>Chunk Compatible:</strong> ${model.chunk_compatible ? 'Yes' : 'No'}</p>
                            <h4>Files to be downloaded:</h4>
                            <ul>
                                ${model.files.map(file => `<li>${file.split('/').pop()}</li>`).join('')}
                            </ul>
                        </div>
                    `);
                    
                    $('#ads-preview-download').off('click').on('click', function() {
                        $('#ads-model-preview-modal').hide();
                        adsDemo.downloadModel(modelKey);
                    });
                    
                    $('#ads-model-preview-modal').show();
                };
                
                adsDemo.downloadRecommendedModels = function() {
                    const recommendedModels = $('.ads-model-card.ads-recommended:not(.ads-already-downloaded)');
                    if (recommendedModels.length === 0) {
                        adsDemo.showNotification('All recommended models are already downloaded', 'info');
                        return;
                    }
                    
                    adsDemo.showNotification(`Starting download of ${recommendedModels.length} recommended models`, 'info');
                    
                    recommendedModels.each(function() {
                        const modelKey = $(this).data('model-key');
                        setTimeout(() => {
                            adsDemo.downloadModel(modelKey);
                        }, Math.random() * 2000); // Stagger downloads
                    });
                };
                
                adsDemo.clearAllModels = function() {
                    adsDemo.showLoadingOverlay('Clearing all models...');
                    
                    $.ajax({
                        url: adsDemo.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ads_clear_all_models',
                            nonce: adsDemo.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                adsDemo.showNotification('All models cleared successfully', 'success');
                                setTimeout(() => {
                                    location.reload();
                                }, 2000);
                            } else {
                                adsDemo.showNotification('Failed to clear models: ' + (response.data || 'Unknown error'), 'error');
                            }
                        },
                        error: function(xhr, status, error) {
                            adsDemo.showNotification('Network error: ' + error, 'error');
                        },
                        complete: function() {
                            adsDemo.hideLoadingOverlay();
                        }
                    });
                };
            }
        });
        </script>
        <?php
    }
    
    private function get_downloaded_models() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY updated_at DESC"
        );
        
        $models = [];
        foreach ($results as $result) {
            $models[] = [
                'model_key' => $result->model_key,
                'model_name' => $result->model_name,
                'status' => $result->download_status,
                'progress' => $result->download_progress,
                'processing_status' => $result->processing_status,
                'download_date' => date('M j, Y', strtotime($result->created_at))
            ];
        }
        
        return $models;
    }
    
    private function is_model_downloaded($model_key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE model_key = %s AND download_status = 'completed'",
            $model_key
        ));
        
        return $count > 0;
    }
    
    public function download_model($model_key) {
        if (!isset($this->available_models[$model_key])) {
            return new \WP_Error('invalid_model', 'Invalid model selected');
        }
        
        $model = $this->available_models[$model_key];
        
        // Create model directory
        $model_dir = $this->upload_dir . '/' . $model_key;
        if (!file_exists($model_dir)) {
            wp_mkdir_p($model_dir);
        }
        
        // Start download process
        $this->start_background_download($model_key, $model);
        
        return true;
    }
    
    private function start_background_download($model_key, $model) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        
        // Insert or update download record
        $wpdb->replace($table_name, [
            'model_key' => $model_key,
            'model_name' => $model['name'],
            'download_status' => 'downloading',
            'download_progress' => 0,
            'processing_status' => 'pending'
        ]);
        
        // Schedule background download
        wp_schedule_single_event(time(), 'ads_process_model_download', [$model_key]);
    }
    
    public function process_model_download($model_key) {
        if (!isset($this->available_models[$model_key])) {
            return;
        }
        
        $model = $this->available_models[$model_key];
        $model_dir = $this->upload_dir . '/' . $model_key;
        
        $total_files = count($model['files']);
        $downloaded_files = 0;
        
        foreach ($model['files'] as $file_url) {
            $filename = basename($file_url);
            $local_path = $model_dir . '/' . $filename;
            
            // Download file
            $response = wp_remote_get($file_url, [
                'timeout' => 300,
                'stream' => true,
                'filename' => $local_path
            ]);
            
            if (!is_wp_error($response)) {
                $downloaded_files++;
                $progress = ($downloaded_files / $total_files) * 100;
                
                // Update progress
                $this->update_download_progress($model_key, $progress);
            }
        }
        
        if ($downloaded_files === $total_files) {
            $this->complete_model_download($model_key);
        } else {
            $this->fail_model_download($model_key, 'Failed to download all files');
        }
    }
    
    private function update_download_progress($model_key, $progress) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $wpdb->update(
            $table_name,
            ['download_progress' => $progress],
            ['model_key' => $model_key]
        );
    }
    
    private function complete_model_download($model_key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $wpdb->update(
            $table_name,
            [
                'download_status' => 'completed',
                'download_progress' => 100,
                'processing_status' => 'ready'
            ],
            ['model_key' => $model_key]
        );
        
        // Trigger auto-population of model data
        $this->populate_model_data($model_key);
    }
    
    private function fail_model_download($model_key, $error_message) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $wpdb->update(
            $table_name,
            [
                'download_status' => 'failed',
                'error_message' => $error_message
            ],
            ['model_key' => $model_key]
        );
    }
    
    private function populate_model_data($model_key) {
        if (!isset($this->available_models[$model_key])) {
            return;
        }
        
        $model = $this->available_models[$model_key];
        $model_dir = $this->upload_dir . '/' . $model_key;
        
        // Auto-populate model configuration
        $config_data = [
            'model_key' => $model_key,
            'model_name' => $model['name'],
            'model_path' => $model_dir,
            'context_length' => $model['context_length'],
            'chunk_compatible' => $model['chunk_compatible'],
            'config_file' => $model_dir . '/config.json',
            'tokenizer_file' => $model_dir . '/tokenizer.json',
            'model_file' => $model_dir . '/pytorch_model.bin'
        ];
        
        // Update model configuration in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $wpdb->update(
            $table_name,
            ['model_config' => json_encode($config_data)],
            ['model_key' => $model_key]
        );
        
        // Trigger integration with existing plugins
        $this->integrate_with_plugins($model_key, $config_data);
    }
    
    private function integrate_with_plugins($model_key, $config_data) {
        // Integration with AevovBit Pattern Recognition
        if (class_exists('BLOOM\\Core')) {
            do_action('ads_model_ready', $model_key, $config_data);
        }
        
        // Integration with APS Tools
        if (class_exists('APS_Tools')) {
            do_action('ads_model_available', $model_key, $config_data);
        }
        
        // Integration with Onboarding System
        if (function_exists('aevov_onboarding_update_model_status')) {
            aevov_onboarding_update_model_status($model_key, 'ready');
        }
    }
    
    public function delete_model($model_key) {
        global $wpdb;
        
        // Remove model files
        $model_dir = $this->upload_dir . '/' . $model_key;
        if (file_exists($model_dir)) {
            $this->recursive_delete($model_dir);
        }
        
        // Remove from database
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $wpdb->delete($table_name, ['model_key' => $model_key]);
        
        return true;
    }
    
    private function recursive_delete($dir) {
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $this->recursive_delete($dir . '/' . $file);
        }
        
        return rmdir($dir);
    }
    
    public function clear_all_models() {
        global $wpdb;
        
        // Get all downloaded models
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $models = $wpdb->get_results("SELECT model_key FROM $table_name");
        
        // Delete each model
        foreach ($models as $model) {
            $this->delete_model($model->model_key);
        }
        
        // Clear the entire models directory
        if (file_exists($this->upload_dir)) {
            $this->recursive_delete($this->upload_dir);
            wp_mkdir_p($this->upload_dir);
        }
        
        return true;
    }
    
    public function get_model_status($model_key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE model_key = %s",
            $model_key
        ));
        
        if (!$result) {
            return null;
        }
        
        return [
            'status' => $result->download_status,
            'progress' => $result->download_progress,
            'processing_status' => $result->processing_status,
            'config' => json_decode($result->model_config, true),
            'error' => $result->error_message
        ];
    }
}