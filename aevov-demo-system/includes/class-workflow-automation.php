<?php
/**
 * Workflow Automation Class
 * Handles automated workflow execution for the Aevov deAI network
 */

namespace AevovDemo;

class WorkflowAutomation {
    private $model_management;
    private $current_workflow;
    
    public function __construct() {
        add_action('ads_process_workflow_step', [$this, 'process_workflow_step'], 10, 2);
    }
    
    private function get_model_management() {
        if (!$this->model_management) {
            require_once plugin_dir_path(__FILE__) . 'class-model-management.php';
            $this->model_management = new ModelManagement();
        }
        return $this->model_management;
    }
    
    public function render_workflow_page() {
        $active_workflows = $this->get_active_workflows();
        $completed_workflows = $this->get_completed_workflows();
        
        ?>
        <div class="ads-demo-container">
            <div class="ads-header">
                <h1><?php _e('Workflow Automation', 'aevov-demo-system'); ?></h1>
                <p><?php _e('Automated processing pipeline for the Aevov deAI network - "The Web\'s Neural Network"', 'aevov-demo-system'); ?></p>
                <div class="ads-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=aevov-demo-system'); ?>" class="button button-secondary">
                        ← <?php _e('Back to Dashboard', 'aevov-demo-system'); ?>
                    </a>
                    <button class="button button-primary" onclick="adsDemo.startNewWorkflow()">
                        <?php _e('🚀 Start New Workflow', 'aevov-demo-system'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Workflow Pipeline Visualization -->
            <div class="ads-section">
                <h2><?php _e('Aevov deAI Processing Pipeline', 'aevov-demo-system'); ?></h2>
                <div class="ads-pipeline-container">
                    <div id="ads-workflow-pipeline" class="ads-workflow-pipeline">
                        <!-- D3.js pipeline visualization will be rendered here -->
                    </div>
                </div>
            </div>
            
            <!-- Active Workflows -->
            <?php if (!empty($active_workflows)): ?>
                <div class="ads-section">
                    <h2><?php _e('Active Workflows', 'aevov-demo-system'); ?></h2>
                    <div class="ads-workflow-grid">
                        <?php foreach ($active_workflows as $workflow): ?>
                            <div class="ads-workflow-card ads-active-workflow" data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                <div class="ads-workflow-header">
                                    <h4><?php echo esc_html($workflow['model_name']); ?></h4>
                                    <span class="ads-workflow-status <?php echo esc_attr($workflow['status']); ?>">
                                        <?php echo esc_html(ucfirst($workflow['status'])); ?>
                                    </span>
                                </div>
                                
                                <div class="ads-workflow-progress">
                                    <div class="ads-progress-steps">
                                        <?php foreach ($this->get_workflow_steps() as $step_key => $step_name): ?>
                                            <?php
                                            $step_status = $this->get_step_status($workflow['id'], $step_key);
                                            $step_class = '';
                                            if ($step_status === 'completed') $step_class = 'completed';
                                            elseif ($step_status === 'active') $step_class = 'active';
                                            elseif ($step_status === 'failed') $step_class = 'failed';
                                            ?>
                                            <div class="ads-progress-step <?php echo $step_class; ?>" data-step="<?php echo esc_attr($step_key); ?>">
                                                <div class="ads-step-icon">
                                                    <?php echo $this->get_step_icon($step_key, $step_status); ?>
                                                </div>
                                                <div class="ads-step-label"><?php echo esc_html($step_name); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="ads-workflow-details">
                                    <div class="ads-workflow-stat">
                                        <span class="ads-stat-label"><?php _e('Started:', 'aevov-demo-system'); ?></span>
                                        <span class="ads-stat-value"><?php echo esc_html($workflow['started_at']); ?></span>
                                    </div>
                                    <div class="ads-workflow-stat">
                                        <span class="ads-stat-label"><?php _e('Current Step:', 'aevov-demo-system'); ?></span>
                                        <span class="ads-stat-value"><?php echo esc_html($workflow['current_step']); ?></span>
                                    </div>
                                    <div class="ads-workflow-stat">
                                        <span class="ads-stat-label"><?php _e('Progress:', 'aevov-demo-system'); ?></span>
                                        <span class="ads-stat-value"><?php echo esc_html($workflow['progress']); ?>%</span>
                                    </div>
                                </div>
                                
                                <div class="ads-workflow-actions">
                                    <?php if ($workflow['status'] === 'running'): ?>
                                        <button class="button button-secondary ads-pause-workflow" data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                            ⏸️ <?php _e('Pause', 'aevov-demo-system'); ?>
                                        </button>
                                        <button class="button ads-danger ads-stop-workflow" data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                            ⏹️ <?php _e('Stop', 'aevov-demo-system'); ?>
                                        </button>
                                    <?php elseif ($workflow['status'] === 'paused'): ?>
                                        <button class="button button-primary ads-resume-workflow" data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                            ▶️ <?php _e('Resume', 'aevov-demo-system'); ?>
                                        </button>
                                        <button class="button ads-danger ads-stop-workflow" data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                            ⏹️ <?php _e('Stop', 'aevov-demo-system'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <button class="button button-secondary ads-view-logs" data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                        📋 <?php _e('View Logs', 'aevov-demo-system'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Start New Workflow Section -->
            <div class="ads-section">
                <h2><?php _e('Start New Workflow', 'aevov-demo-system'); ?></h2>
                <div class="ads-new-workflow-container">
                    <div class="ads-model-selection">
                        <h3><?php _e('Select Model', 'aevov-demo-system'); ?></h3>
                        <div class="ads-model-selector">
                            <?php
                            $downloaded_models = $this->get_available_models_for_workflow();
                            if (empty($downloaded_models)):
                            ?>
                                <div class="ads-no-models">
                                    <p><?php _e('No models available for workflow. Please download a model first.', 'aevov-demo-system'); ?></p>
                                    <a href="<?php echo admin_url('admin.php?page=aevov-demo-model-management'); ?>" class="button button-primary">
                                        <?php _e('Download Models', 'aevov-demo-system'); ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <select id="ads-workflow-model-select" class="ads-select">
                                    <option value=""><?php _e('Choose a model...', 'aevov-demo-system'); ?></option>
                                    <?php foreach ($downloaded_models as $model): ?>
                                        <option value="<?php echo esc_attr($model['model_key']); ?>" 
                                                data-name="<?php echo esc_attr($model['model_name']); ?>"
                                                data-size="<?php echo esc_attr($model['size']); ?>">
                                            <?php echo esc_html($model['model_name']); ?> (<?php echo esc_html($model['size']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="ads-workflow-configuration">
                        <h3><?php _e('Workflow Configuration', 'aevov-demo-system'); ?></h3>
                        <div class="ads-config-grid">
                            <div class="ads-config-item">
                                <label for="ads-chunk-size"><?php _e('Chunk Size:', 'aevov-demo-system'); ?></label>
                                <select id="ads-chunk-size" class="ads-select">
                                    <option value="512">512 tokens</option>
                                    <option value="1024" selected>1024 tokens</option>
                                    <option value="2048">2048 tokens</option>
                                    <option value="4096">4096 tokens</option>
                                </select>
                            </div>
                            
                            <div class="ads-config-item">
                                <label for="ads-overlap-size"><?php _e('Overlap Size:', 'aevov-demo-system'); ?></label>
                                <select id="ads-overlap-size" class="ads-select">
                                    <option value="50">50 tokens</option>
                                    <option value="100" selected>100 tokens</option>
                                    <option value="200">200 tokens</option>
                                </select>
                            </div>
                            
                            <div class="ads-config-item">
                                <label for="ads-processing-mode"><?php _e('Processing Mode:', 'aevov-demo-system'); ?></label>
                                <select id="ads-processing-mode" class="ads-select">
                                    <option value="standard" selected>Standard Processing</option>
                                    <option value="fast">Fast Processing</option>
                                    <option value="thorough">Thorough Analysis</option>
                                </select>
                            </div>
                            
                            <div class="ads-config-item">
                                <label for="ads-auto-api-config">
                                    <input type="checkbox" id="ads-auto-api-config" checked>
                                    <?php _e('Auto-generate API configuration', 'aevov-demo-system'); ?>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ads-workflow-actions">
                        <button class="button button-primary ads-start-workflow" disabled>
                            🚀 <?php _e('Start Automated Workflow', 'aevov-demo-system'); ?>
                        </button>
                        <button class="button button-secondary ads-preview-workflow">
                            👁️ <?php _e('Preview Workflow Steps', 'aevov-demo-system'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Completed Workflows -->
            <?php if (!empty($completed_workflows)): ?>
                <div class="ads-section">
                    <h2><?php _e('Completed Workflows', 'aevov-demo-system'); ?></h2>
                    <div class="ads-completed-workflows">
                        <div class="ads-workflow-table">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Model', 'aevov-demo-system'); ?></th>
                                        <th><?php _e('Status', 'aevov-demo-system'); ?></th>
                                        <th><?php _e('Duration', 'aevov-demo-system'); ?></th>
                                        <th><?php _e('Completed', 'aevov-demo-system'); ?></th>
                                        <th><?php _e('Actions', 'aevov-demo-system'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completed_workflows as $workflow): ?>
                                        <tr>
                                            <td><?php echo esc_html($workflow['model_name']); ?></td>
                                            <td>
                                                <span class="ads-status-badge <?php echo esc_attr($workflow['status']); ?>">
                                                    <?php echo esc_html(ucfirst($workflow['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html($workflow['duration']); ?></td>
                                            <td><?php echo esc_html($workflow['completed_at']); ?></td>
                                            <td>
                                                <button class="button button-small ads-view-results" data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                                    <?php _e('View Results', 'aevov-demo-system'); ?>
                                                </button>
                                                <?php if ($workflow['status'] === 'completed'): ?>
                                                    <button class="button button-small button-primary ads-test-api" data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                                        <?php _e('Test API', 'aevov-demo-system'); ?>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="button button-small ads-delete-workflow" data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
                                                    <?php _e('Delete', 'aevov-demo-system'); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Workflow Preview Modal -->
        <div id="ads-workflow-preview-modal" class="ads-modal" style="display: none;">
            <div class="ads-modal-content">
                <div class="ads-modal-header">
                    <h3><?php _e('Workflow Preview', 'aevov-demo-system'); ?></h3>
                    <button class="ads-modal-close">&times;</button>
                </div>
                <div class="ads-modal-body">
                    <div id="ads-workflow-preview-content"></div>
                </div>
                <div class="ads-modal-footer">
                    <button class="button button-secondary ads-modal-close">
                        <?php _e('Close', 'aevov-demo-system'); ?>
                    </button>
                    <button class="button button-primary" id="ads-confirm-start-workflow">
                        <?php _e('Start This Workflow', 'aevov-demo-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Workflow Logs Modal -->
        <div id="ads-workflow-logs-modal" class="ads-modal" style="display: none;">
            <div class="ads-modal-content ads-logs-modal">
                <div class="ads-modal-header">
                    <h3><?php _e('Workflow Logs', 'aevov-demo-system'); ?></h3>
                    <button class="ads-modal-close">&times;</button>
                </div>
                <div class="ads-modal-body">
                    <div id="ads-workflow-logs-content" class="ads-logs-container"></div>
                </div>
                <div class="ads-modal-footer">
                    <button class="button button-secondary ads-modal-close">
                        <?php _e('Close', 'aevov-demo-system'); ?>
                    </button>
                    <button class="button button-primary ads-download-logs">
                        <?php _e('Download Logs', 'aevov-demo-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <style>
        .ads-pipeline-container {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .ads-workflow-pipeline {
            min-height: 300px;
            width: 100%;
        }
        
        .ads-workflow-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
        }
        
        .ads-workflow-card {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 25px;
            transition: all 0.3s ease;
        }
        
        .ads-active-workflow {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }
        
        .ads-workflow-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .ads-workflow-header h4 {
            margin: 0;
            color: #333;
            font-size: 1.2em;
        }
        
        .ads-workflow-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .ads-workflow-status.running {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .ads-workflow-status.paused {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .ads-workflow-status.completed {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .ads-workflow-status.failed {
            background: #ffebee;
            color: #c62828;
        }
        
        .ads-progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            position: relative;
        }
        
        .ads-progress-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e1e5e9;
            z-index: 1;
        }
        
        .ads-progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .ads-step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            border: 2px solid #e1e5e9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            font-size: 1.2em;
        }
        
        .ads-progress-step.completed .ads-step-icon {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .ads-progress-step.active .ads-step-icon {
            background: #667eea;
            border-color: #667eea;
            color: white;
            animation: pulse 2s infinite;
        }
        
        .ads-progress-step.failed .ads-step-icon {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        
        .ads-step-label {
            font-size: 0.8em;
            color: #666;
            text-align: center;
            max-width: 80px;
        }
        
        .ads-workflow-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .ads-workflow-stat {
            text-align: center;
        }
        
        .ads-stat-label {
            display: block;
            font-size: 0.8em;
            color: #666;
            margin-bottom: 5px;
        }
        
        .ads-stat-value {
            display: block;
            font-weight: 600;
            color: #333;
        }
        
        .ads-new-workflow-container {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 30px;
        }
        
        .ads-model-selection,
        .ads-workflow-configuration {
            margin-bottom: 30px;
        }
        
        .ads-model-selection h3,
        .ads-workflow-configuration h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .ads-config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .ads-config-item label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .ads-select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .ads-select:focus {
            border-color: #667eea;
            outline: none;
        }
        
        .ads-no-models {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .ads-workflow-table {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .ads-status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .ads-status-badge.completed {
            background: #d4edda;
            color: #155724;
        }
        
        .ads-status-badge.failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .ads-logs-modal .ads-modal-content {
            max-width: 800px;
            max-height: 90%;
        }
        
        .ads-logs-container {
            background: #1e1e1e;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Model selection change handler
            $('#ads-workflow-model-select').on('change', function() {
                const selectedModel = $(this).val();
                $('.ads-start-workflow').prop('disabled', !selectedModel);
            });
            
            // Preview workflow
            $('.ads-preview-workflow').on('click', function() {
                const selectedModel = $('#ads-workflow-model-select').val();
                if (!selectedModel) {
                    adsDemo.showNotification('Please select a model first', 'warning');
                    return;
                }
                adsDemo.showWorkflowPreview(selectedModel);
            });
            
            // Start workflow
            $('.ads-start-workflow').on('click', function() {
                const selectedModel = $('#ads-workflow-model-select').val();
                if (!selectedModel) {
                    adsDemo.showNotification('Please select a model first', 'warning');
                    return;
                }
                adsDemo.startWorkflow(selectedModel);
            });
            
            // Workflow control buttons
            $('.ads-pause-workflow').on('click', function() {
                const workflowId = $(this).data('workflow-id');
                adsDemo.pauseWorkflow(workflowId);
            });
            
            $('.ads-resume-workflow').on('click', function() {
                const workflowId = $(this).data('workflow-id');
                adsDemo.resumeWorkflow(workflowId);
            });
            
            $('.ads-stop-workflow').on('click', function() {
                const workflowId = $(this).data('workflow-id');
                if (confirm('Are you sure you want to stop this workflow?')) {
                    adsDemo.stopWorkflow(workflowId);
                }
            });
            
            // View logs
            $('.ads-view-logs').on('click', function() {
                const workflowId = $(this).data('workflow-id');
                adsDemo.viewWorkflowLogs(workflowId);
            });
            
            // Modal handlers
            $('.ads-modal-close').on('click', function() {
                $(this).closest('.ads-modal').hide();
            });
            
            // Initialize pipeline visualization
            if (typeof adsDemo !== 'undefined' && adsDemo.initializePipelineVisualization) {
                adsDemo.initializePipelineVisualization();
            }
            
            // Auto-refresh active workflows
            setInterval(function() {
                if ($('.ads-active-workflow').length > 0) {
                    adsDemo.refreshActiveWorkflows();
                }
            }, 5000);
        });
        </script>
        <?php
    }
    
    private function get_workflow_steps() {
        return [
            'model_validation' => 'Model Validation',
            'chunking' => 'Chunking Process',
            'pattern_analysis' => 'Pattern Analysis',
            'comparator_processing' => 'Comparator Processing',
            'neurosymbolic_integration' => 'Neurosymbolic Integration',
            'api_configuration' => 'API Configuration',
            'testing' => 'System Testing'
        ];
    }
    
    private function get_step_icon($step_key, $status) {
        $icons = [
            'model_validation' => '🔍',
            'chunking' => '📦',
            'pattern_analysis' => '🧠',
            'comparator_processing' => '⚖️',
            'neurosymbolic_integration' => '🔗',
            'api_configuration' => '🔧',
            'testing' => '✅'
        ];
        
        if ($status === 'completed') return '✅';
        if ($status === 'failed') return '❌';
        if ($status === 'active') return '⏳';
        
        return $icons[$step_key] ?? '⭕';
    }
    
    private function get_step_status($workflow_id, $step_key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT step_status FROM $table_name WHERE id = %d",
            $workflow_id
        ));
        
        if (!$result) return 'pending';
        
        $step_data = json_decode($result, true);
        return $step_data[$step_key] ?? 'pending';
    }
    
    private function get_active_workflows() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status IN ('running', 'paused') ORDER BY created_at DESC"
        );
        
        $workflows = [];
        foreach ($results as $result) {
            $workflows[] = [
                'id' => $result->id,
                'model_name' => $result->model_name,
                'status' => $result->status,
                'current_step' => $result->current_step,
                'progress' => $result->progress,
                'started_at' => date('M j, Y H:i', strtotime($result->created_at))
            ];
        }
        
        return $workflows;
    }
    
    private function get_completed_workflows() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status IN ('completed', 'failed') ORDER BY updated_at DESC LIMIT 10"
        );
        
        $workflows = [];
        foreach ($results as $result) {
            $duration = $this->calculate_duration($result->created_at, $result->updated_at);
            $workflows[] = [
                'id' => $result->id,
                'model_name' => $result->model_name,
                'status' => $result->status,
                'duration' => $duration,
                'completed_at' => date('M j, Y H:i', strtotime($result->updated_at))
            ];
        }
        
        return $workflows;
    }
    
    private function get_available_models_for_workflow() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE download_status = 'completed' ORDER BY model_name"
        );
        
        $models = [];
        foreach ($results as $result) {
            $config = json_decode($result->model_config, true);
            $models[] = [
                'model_key' => $result->model_key,
                'model_name' => $result->model_name,
                'size' => $config['size'] ?? 'Unknown'
            ];
        }
        
        return $models;
    }
    
    private function calculate_duration($start_time, $end_time) {
        $start = strtotime($start_time);
        $end = strtotime($end_time);
        $duration = $end - $start;
        
        if ($duration < 60) {
            return $duration . 's';
        } elseif ($duration < 3600) {
            return floor($duration / 60) . 'm ' . ($duration % 60) . 's';
        } else {
            $hours = floor($duration / 3600);
            $minutes = floor(($duration % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        }
    }
    
    public function start_workflow($model_key, $config = []) {
        global $wpdb;
        
        // Validate model exists and is ready
        $model_status = $this->get_model_management()->get_model_status($model_key);
        if (!$model_status || $model_status['status'] !== 'completed') {
            return new \WP_Error('invalid_model', 'Model not ready for workflow');
        }
        
        // Create workflow record
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        $workflow_id = $wpdb->insert($table_name, [
            'model_key' => $model_key,
            'model_name' => $model_status['config']['model_name'] ?? 'Unknown Model',
            'status' => 'running',
            'current_step' => 'model_validation',
            'progress' => 0,
            'workflow_config' => json_encode($config),
            'step_status' => json_encode([]),
            'created_at' => current_time('mysql')
        ]);
        
        if (!$workflow_id) {
            return new \WP_Error('workflow_creation_failed', 'Failed to create workflow');
        }
        
        // Schedule first step
        wp_schedule_single_event(time(), 'ads_process_workflow_step', [$workflow_id, 'model_validation']);
        
        return $workflow_id;
    }
    
    public function process_workflow_step($workflow_id, $step) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        $workflow = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $workflow_id
        ));
        
        if (!$workflow || $workflow->status !== 'running') {
            return;
        }
        
        $step_status = json_decode($workflow->step_status, true) ?: [];
        $workflow_config = json_decode($workflow->workflow_config, true) ?: [];
        
        // Mark current step as active
        $step_status[$step] = 'active';
        $this->update_workflow_status($workflow_id, $workflow->status, $step, $step_status);
        
        // Process the step
        $result = $this->execute_workflow_step($workflow, $step, $workflow_config);
        
        if (is_wp_error($result)) {
            // Step failed
            $step_status[$step] = 'failed';
            $this->update_workflow_status($workflow_id, 'failed', $step, $step_status);
            $this->log_workflow_error($workflow_id, $step, $result->get_error_message());
        } else {
            // Step completed successfully
            $step_status[$step] = 'completed';
            
            // Get next step
            $next_step = $this->get_next_step($step);
            if ($next_step) {
                // Schedule next step
                $this->update_workflow_status($workflow_id, 'running', $next_step, $step_status);
                wp_schedule_single_event(time() + 5, 'ads_process_workflow_step', [$workflow_id, $next_step]);
            } else {
                // Workflow completed
                $this->update_workflow_status($workflow_id, 'completed', 'completed', $step_status, 100);
                $this->complete_workflow($workflow_id);
            }
        }
    }
    
    private function execute_workflow_step($workflow, $step, $config) {
        switch ($step) {
            case 'model_validation':
                return $this->validate_model($workflow->model_key);
                
            case 'chunking':
                return $this->process_chunking($workflow->model_key, $config);
                
            case 'pattern_analysis':
                return $this->analyze_patterns($workflow->model_key, $config);
                
            case 'comparator_processing':
                return $this->process_comparator($workflow->model_key, $config);
                
            case 'neurosymbolic_integration':
                return $this->integrate_neurosymbolic($workflow->model_key, $config);
                
            case 'api_configuration':
                return $this->configure_api($workflow->model_key, $config);
                
            case 'testing':
                return $this->test_system($workflow->model_key, $config);
                
            default:
                return new \WP_Error('unknown_step', 'Unknown workflow step: ' . $step);
        }
    }
    
    private function validate_model($model_key) {
        $model_status = $this->get_model_management()->get_model_status($model_key);
        
        if (!$model_status || $model_status['status'] !== 'completed') {
            return new \WP_Error('model_validation_failed', 'Model is not ready');
        }
        
        $config = $model_status['config'];
        $required_files = ['config_file', 'tokenizer_file', 'model_file'];
        
        foreach ($required_files as $file_key) {
            if (!isset($config[$file_key]) || !file_exists($config[$file_key])) {
                return new \WP_Error('missing_file', 'Required model file missing: ' . $file_key);
            }
        }
        
        return true;
    }
    
    private function process_chunking($model_key, $config) {
        // Integration with APS Tools chunking system
        if (!class_exists('APS_Tools')) {
            return new \WP_Error('aps_tools_missing', 'APS Tools plugin required for chunking');
        }
        
        $chunk_size = $config['chunk_size'] ?? 1024;
        $overlap_size = $config['overlap_size'] ?? 100;
        
        // Simulate chunking process
        sleep(2);
        
        return true;
    }
    
    private function analyze_patterns($model_key, $config) {
        // Integration with AevovBit Pattern Recognition
        if (!class_exists('BLOOM\\Core')) {
            return new \WP_Error('bloom_missing', 'AevovBit Pattern Recognition plugin required');
        }
        
        // Simulate pattern analysis
        sleep(3);
        
        return true;
    }
    
    private function process_comparator($model_key, $config) {
        // Hybrid Comparator Engine processing
        $processing_mode = $config['processing_mode'] ?? 'standard';
        
        // Simulate comparator processing based on mode
        switch ($processing_mode) {
            case 'fast':
                sleep(1);
                break;
            case 'thorough':
                sleep(5);
                break;
            default:
                sleep(3);
        }
        
        return true;
    }
    
    private function integrate_neurosymbolic($model_key, $config) {
        // Neurosymbolic integration for deAI network
        sleep(2);
        
        return true;
    }
    
    private function configure_api($model_key, $config) {
        if (!isset($config['auto_api_config']) || !$config['auto_api_config']) {
            return true; // Skip if auto-config is disabled
        }
        
        // Generate API configuration for Typebot integration
        $api_config = $this->generate_api_configuration($model_key);
        
        // Store API configuration
        global $wpdb;
        $table_name = $wpdb->prefix . 'ads_api_monitoring';
        $wpdb->insert($table_name, [
            'model_key' => $model_key,
            'api_endpoint' => $api_config['endpoint'],
            'api_key' => $api_config['key'],
            'configuration' => json_encode($api_config),
            'status' => 'active',
            'created_at' => current_time('mysql')
        ]);
        
        return true;
    }
    
    private function test_system($model_key, $config) {
        // System testing
        sleep(2);
        
        return true;
    }
    
    private function generate_api_configuration($model_key) {
        return [
            'endpoint' => home_url('/wp-json/aevov-demo/v1/chat'),
            'key' => wp_generate_password(32, false),
            'model_key' => $model_key,
            'typebot_webhook' => home_url('/wp-json/aevov-demo/v1/typebot-webhook'),
            'settings' => [
                'max_tokens' => 150,
                'temperature' => 0.7,
                'top_p' => 0.9
            ]
        ];
    }
    
    private function get_next_step($current_step) {
        $steps = array_keys($this->get_workflow_steps());
        $current_index = array_search($current_step, $steps);
        
        if ($current_index === false || $current_index >= count($steps) - 1) {
            return null;
        }
        
        return $steps[$current_index + 1];
    }
    
    private function update_workflow_status($workflow_id, $status, $current_step, $step_status, $progress = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        
        $update_data = [
            'status' => $status,
            'current_step' => $current_step,
            'step_status' => json_encode($step_status),
            'updated_at' => current_time('mysql')
        ];
        
        if ($progress !== null) {
            $update_data['progress'] = $progress;
        } else {
            // Calculate progress based on completed steps
            $total_steps = count($this->get_workflow_steps());
            $completed_steps = count(array_filter($step_status, function($status) {
                return $status === 'completed';
            }));
            $update_data['progress'] = ($completed_steps / $total_steps) * 100;
        }
        
        $wpdb->update($table_name, $update_data, ['id' => $workflow_id]);
    }
    
    private function complete_workflow($workflow_id) {
        // Workflow completion actions
        do_action('ads_workflow_completed', $workflow_id);
        
        // Send notification
        $this->send_workflow_notification($workflow_id, 'completed');
    }
    
    private function log_workflow_error($workflow_id, $step, $error_message) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        $wpdb->update($table_name, [
            'error_log' => $error_message,
            'updated_at' => current_time('mysql')
        ], ['id' => $workflow_id]);
        
        // Send error notification
        $this->send_workflow_notification($workflow_id, 'failed', $error_message);
    }
    
    private function send_workflow_notification($workflow_id, $status, $error = null) {
        // Implementation for workflow notifications
        // Could integrate with email, Slack, etc.
    }
    
    public function pause_workflow($workflow_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        $wpdb->update($table_name, [
            'status' => 'paused',
            'updated_at' => current_time('mysql')
        ], ['id' => $workflow_id]);
        
        return true;
    }
    
    public function resume_workflow($workflow_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        $workflow = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $workflow_id
        ));
        
        if (!$workflow || $workflow->status !== 'paused') {
            return false;
        }
        
        $wpdb->update($table_name, [
            'status' => 'running',
            'updated_at' => current_time('mysql')
        ], ['id' => $workflow_id]);
        
        // Resume from current step
        wp_schedule_single_event(time() + 1, 'ads_process_workflow_step', [$workflow_id, $workflow->current_step]);
        
        return true;
    }
    
    public function stop_workflow($workflow_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        $wpdb->update($table_name, [
            'status' => 'stopped',
            'updated_at' => current_time('mysql')
        ], ['id' => $workflow_id]);
        
        return true;
    }
    
    public function get_workflow_logs($workflow_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_workflow_executions';
        $workflow = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $workflow_id
        ));
        
        if (!$workflow) {
            return null;
        }
        
        $logs = [];
        $step_status = json_decode($workflow->step_status, true) ?: [];
        
        foreach ($this->get_workflow_steps() as $step_key => $step_name) {
            $status = $step_status[$step_key] ?? 'pending';
            $logs[] = [
                'step' => $step_name,
                'status' => $status,
                'timestamp' => $workflow->updated_at
            ];
        }
        
        return $logs;
    }
}