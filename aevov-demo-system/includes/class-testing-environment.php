<?php
/**
 * Testing Environment Class
 * Provides live testing interface for downloaded and chunked SLMs after Typebot configuration
 */

namespace AevovDemo;

class TestingEnvironment {
    private $model_management;
    private $api_configuration;
    
    public function __construct() {
        // Only instantiate dependencies when needed to avoid circular dependencies
        $this->model_management = null;
        $this->api_configuration = null;
    }
    
    private function get_model_management() {
        if ($this->model_management === null) {
            require_once ADS_PLUGIN_DIR . 'includes/class-model-management.php';
            $this->model_management = new ModelManagement();
        }
        return $this->model_management;
    }
    
    private function get_api_configuration() {
        if ($this->api_configuration === null) {
            require_once ADS_PLUGIN_DIR . 'includes/class-api-configuration.php';
            $this->api_configuration = new APIConfiguration();
        }
        return $this->api_configuration;
    }
    
    public function render_testing_page() {
        $available_models = $this->get_available_models();
        $active_apis = $this->get_active_api_configurations();
        
        ?>
        <div class="ads-demo-container">
            <div class="ads-header">
                <h1><?php _e('Live Testing Environment', 'aevov-demo-system'); ?></h1>
                <p><?php _e('Test your downloaded and chunked SLMs with real-time interaction through the Aevov deAI network', 'aevov-demo-system'); ?></p>
                <div class="ads-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=aevov-demo-system'); ?>" class="button button-secondary">
                        ← <?php _e('Back to Dashboard', 'aevov-demo-system'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=aevov-demo-api-config'); ?>" class="button button-primary">
                        ⚙️ <?php _e('API Configuration', 'aevov-demo-system'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Model Selection -->
            <div class="ads-section">
                <h2><?php _e('Select Model for Testing', 'aevov-demo-system'); ?></h2>
                <div class="ads-model-selector">
                    <?php if (!empty($available_models)): ?>
                        <div class="ads-model-grid">
                            <?php foreach ($available_models as $model): ?>
                                <div class="ads-model-card <?php echo $model['status'] === 'ready' ? 'ready' : 'processing'; ?>" 
                                     data-model-key="<?php echo esc_attr($model['model_key']); ?>">
                                    <div class="ads-model-header">
                                        <h4><?php echo esc_html($model['name']); ?></h4>
                                        <span class="ads-model-status <?php echo esc_attr($model['status']); ?>">
                                            <?php echo esc_html(ucfirst($model['status'])); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="ads-model-details">
                                        <div class="ads-model-info">
                                            <span class="ads-info-label"><?php _e('Size:', 'aevov-demo-system'); ?></span>
                                            <span class="ads-info-value"><?php echo esc_html($model['size']); ?></span>
                                        </div>
                                        <div class="ads-model-info">
                                            <span class="ads-info-label"><?php _e('Type:', 'aevov-demo-system'); ?></span>
                                            <span class="ads-info-value"><?php echo esc_html($model['type']); ?></span>
                                        </div>
                                        <div class="ads-model-info">
                                            <span class="ads-info-label"><?php _e('Chunks:', 'aevov-demo-system'); ?></span>
                                            <span class="ads-info-value"><?php echo esc_html($model['chunks_count']); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($model['status'] === 'ready'): ?>
                                        <button class="button button-primary ads-select-model" 
                                                data-model-key="<?php echo esc_attr($model['model_key']); ?>">
                                            🚀 <?php _e('Start Testing', 'aevov-demo-system'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="button button-secondary" disabled>
                                            ⏳ <?php _e('Processing...', 'aevov-demo-system'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="ads-empty-state">
                            <div class="ads-empty-icon">🤖</div>
                            <h3><?php _e('No Models Available', 'aevov-demo-system'); ?></h3>
                            <p><?php _e('Download and process some models first to start testing.', 'aevov-demo-system'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=aevov-demo-system'); ?>" class="button button-primary">
                                <?php _e('Go to Model Management', 'aevov-demo-system'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Interface -->
            <div class="ads-section" id="ads-chat-section" style="display: none;">
                <h2><?php _e('Interactive Chat Interface', 'aevov-demo-system'); ?></h2>
                <div class="ads-chat-container">
                    <div class="ads-chat-header">
                        <div class="ads-chat-model-info">
                            <span class="ads-chat-model-name" id="ads-active-model-name"></span>
                            <span class="ads-chat-status" id="ads-chat-status">
                                <span class="ads-status-indicator ready"></span>
                                <?php _e('Ready', 'aevov-demo-system'); ?>
                            </span>
                        </div>
                        <div class="ads-chat-controls">
                            <button class="button button-secondary" id="ads-clear-chat">
                                🗑️ <?php _e('Clear Chat', 'aevov-demo-system'); ?>
                            </button>
                            <button class="button button-secondary" id="ads-export-chat">
                                📄 <?php _e('Export Chat', 'aevov-demo-system'); ?>
                            </button>
                            <button class="button ads-danger" id="ads-stop-testing">
                                ⏹️ <?php _e('Stop Testing', 'aevov-demo-system'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="ads-chat-messages" id="ads-chat-messages">
                        <div class="ads-chat-welcome">
                            <div class="ads-welcome-icon">🤖</div>
                            <h4><?php _e('Welcome to Aevov deAI Testing', 'aevov-demo-system'); ?></h4>
                            <p><?php _e('Start chatting with your selected model. All responses are processed through the Aevov neurosymbolic architecture.', 'aevov-demo-system'); ?></p>
                        </div>
                    </div>
                    
                    <div class="ads-chat-input-container">
                        <div class="ads-chat-input-wrapper">
                            <textarea id="ads-chat-input" 
                                      placeholder="<?php _e('Type your message here...', 'aevov-demo-system'); ?>"
                                      rows="3"></textarea>
                            <div class="ads-chat-input-controls">
                                <div class="ads-input-settings">
                                    <label for="ads-chat-temperature"><?php _e('Temperature:', 'aevov-demo-system'); ?></label>
                                    <input type="range" id="ads-chat-temperature" min="0" max="2" step="0.1" value="0.7">
                                    <span id="ads-temperature-value">0.7</span>
                                    
                                    <label for="ads-chat-max-tokens"><?php _e('Max Tokens:', 'aevov-demo-system'); ?></label>
                                    <input type="number" id="ads-chat-max-tokens" min="10" max="500" value="150">
                                </div>
                                <button class="button button-primary" id="ads-send-message">
                                    🚀 <?php _e('Send', 'aevov-demo-system'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Real-time Monitoring -->
            <div class="ads-section" id="ads-monitoring-section" style="display: none;">
                <h2><?php _e('Real-time Performance Monitoring', 'aevov-demo-system'); ?></h2>
                <div class="ads-monitoring-dashboard">
                    <div class="ads-monitoring-stats">
                        <div class="ads-stat-card">
                            <div class="ads-stat-icon">💬</div>
                            <div class="ads-stat-content">
                                <h3 id="ads-total-messages">0</h3>
                                <p><?php _e('Messages Sent', 'aevov-demo-system'); ?></p>
                            </div>
                        </div>
                        
                        <div class="ads-stat-card">
                            <div class="ads-stat-icon">⚡</div>
                            <div class="ads-stat-content">
                                <h3 id="ads-avg-response-time">0ms</h3>
                                <p><?php _e('Avg Response Time', 'aevov-demo-system'); ?></p>
                            </div>
                        </div>
                        
                        <div class="ads-stat-card">
                            <div class="ads-stat-icon">🧠</div>
                            <div class="ads-stat-content">
                                <h3 id="ads-total-tokens">0</h3>
                                <p><?php _e('Tokens Processed', 'aevov-demo-system'); ?></p>
                            </div>
                        </div>
                        
                        <div class="ads-stat-card">
                            <div class="ads-stat-icon">📊</div>
                            <div class="ads-stat-content">
                                <h3 id="ads-success-rate">100%</h3>
                                <p><?php _e('Success Rate', 'aevov-demo-system'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ads-monitoring-charts">
                        <div class="ads-chart-container">
                            <h4><?php _e('Response Time Trend', 'aevov-demo-system'); ?></h4>
                            <canvas id="ads-response-time-chart" width="400" height="200"></canvas>
                        </div>
                        
                        <div class="ads-chart-container">
                            <h4><?php _e('Token Usage', 'aevov-demo-system'); ?></h4>
                            <canvas id="ads-token-usage-chart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Typebot Integration Status -->
            <?php if (!empty($active_apis)): ?>
                <div class="ads-section" id="ads-typebot-section" style="display: none;">
                    <h2><?php _e('Typebot Integration Status', 'aevov-demo-system'); ?></h2>
                    <div class="ads-typebot-status">
                        <?php foreach ($active_apis as $api): ?>
                            <div class="ads-typebot-card">
                                <div class="ads-typebot-header">
                                    <h4><?php echo esc_html($api['model_name']); ?></h4>
                                    <span class="ads-typebot-status-indicator active">
                                        ✅ <?php _e('Connected', 'aevov-demo-system'); ?>
                                    </span>
                                </div>
                                
                                <div class="ads-typebot-details">
                                    <div class="ads-typebot-info">
                                        <label><?php _e('Webhook URL:', 'aevov-demo-system'); ?></label>
                                        <code><?php echo esc_html($api['typebot_webhook']); ?></code>
                                    </div>
                                    
                                    <div class="ads-typebot-actions">
                                        <button class="button button-primary ads-test-typebot" 
                                                data-api-id="<?php echo esc_attr($api['id']); ?>">
                                            🧪 <?php _e('Test Webhook', 'aevov-demo-system'); ?>
                                        </button>
                                        <a href="https://typebot.io" target="_blank" class="button button-secondary">
                                            🔗 <?php _e('Open Typebot', 'aevov-demo-system'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .ads-model-selector {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 30px;
        }
        
        .ads-model-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .ads-model-card {
            background: #f8f9fa;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 25px;
            transition: all 0.3s ease;
        }
        
        .ads-model-card.ready {
            border-color: #28a745;
        }
        
        .ads-model-card.processing {
            border-color: #ffc107;
        }
        
        .ads-model-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .ads-model-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .ads-model-header h4 {
            margin: 0;
            color: #333;
        }
        
        .ads-model-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .ads-model-status.ready {
            background: #d4edda;
            color: #155724;
        }
        
        .ads-model-status.processing {
            background: #fff3cd;
            color: #856404;
        }
        
        .ads-model-details {
            margin-bottom: 20px;
        }
        
        .ads-model-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .ads-info-label {
            font-weight: 600;
            color: #666;
        }
        
        .ads-info-value {
            color: #333;
        }
        
        .ads-empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .ads-empty-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        .ads-empty-state h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .ads-empty-state p {
            margin: 0 0 25px 0;
            color: #666;
        }
        
        .ads-chat-container {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .ads-chat-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ads-chat-model-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .ads-chat-model-name {
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
        }
        
        .ads-chat-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            color: #666;
        }
        
        .ads-chat-controls {
            display: flex;
            gap: 10px;
        }
        
        .ads-chat-messages {
            height: 400px;
            overflow-y: auto;
            padding: 20px;
            background: #fafafa;
        }
        
        .ads-chat-welcome {
            text-align: center;
            padding: 40px 20px;
        }
        
        .ads-welcome-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        
        .ads-chat-welcome h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .ads-chat-welcome p {
            margin: 0;
            color: #666;
        }
        
        .ads-message {
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
        }
        
        .ads-message.user {
            flex-direction: row-reverse;
        }
        
        .ads-message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            flex-shrink: 0;
        }
        
        .ads-message.user .ads-message-avatar {
            background: #667eea;
            color: white;
        }
        
        .ads-message.bot .ads-message-avatar {
            background: #28a745;
            color: white;
        }
        
        .ads-message-content {
            flex: 1;
            max-width: 70%;
        }
        
        .ads-message-bubble {
            padding: 12px 16px;
            border-radius: 18px;
            margin-bottom: 5px;
        }
        
        .ads-message.user .ads-message-bubble {
            background: #667eea;
            color: white;
            margin-left: auto;
        }
        
        .ads-message.bot .ads-message-bubble {
            background: white;
            color: #333;
            border: 1px solid #e9ecef;
        }
        
        .ads-message-meta {
            font-size: 0.8em;
            color: #666;
            display: flex;
            gap: 10px;
        }
        
        .ads-message.user .ads-message-meta {
            justify-content: flex-end;
        }
        
        .ads-chat-input-container {
            background: #f8f9fa;
            padding: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .ads-chat-input-wrapper {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .ads-chat-input-wrapper textarea {
            width: 100%;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            padding: 12px;
            font-size: 14px;
            resize: vertical;
            min-height: 60px;
        }
        
        .ads-chat-input-wrapper textarea:focus {
            border-color: #667eea;
            outline: none;
        }
        
        .ads-chat-input-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ads-input-settings {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 0.9em;
        }
        
        .ads-input-settings label {
            font-weight: 600;
            color: #666;
        }
        
        .ads-input-settings input[type="range"] {
            width: 80px;
        }
        
        .ads-input-settings input[type="number"] {
            width: 80px;
            padding: 4px 8px;
            border: 1px solid #e1e5e9;
            border-radius: 4px;
        }
        
        .ads-monitoring-dashboard {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 30px;
        }
        
        .ads-monitoring-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .ads-stat-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
        }
        
        .ads-stat-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
        }
        
        .ads-stat-content h3 {
            margin: 0 0 5px 0;
            font-size: 2em;
            font-weight: 700;
            color: #333;
        }
        
        .ads-stat-content p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }
        
        .ads-monitoring-charts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .ads-chart-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        
        .ads-chart-container h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.1em;
        }
        
        .ads-typebot-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
        }
        
        .ads-typebot-card {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 25px;
        }
        
        .ads-typebot-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .ads-typebot-header h4 {
            margin: 0;
            color: #333;
        }
        
        .ads-typebot-status-indicator {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .ads-typebot-status-indicator.active {
            background: #d4edda;
            color: #155724;
        }
        
        .ads-typebot-info {
            margin-bottom: 20px;
        }
        
        .ads-typebot-info label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .ads-typebot-info code {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            border: 1px solid #e9ecef;
            display: block;
        }
        
        .ads-typebot-actions {
            display: flex;
            gap: 10px;
        }
        
        .ads-message-typing {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 12px 16px;
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 18px;
            color: #666;
        }
        
        .ads-typing-dots {
            display: flex;
            gap: 3px;
        }
        
        .ads-typing-dot {
            width: 6px;
            height: 6px;
            background: #666;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }
        
        .ads-typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .ads-typing-dot:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes typing {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let currentModel = null;
            let chatStats = {
                messages: 0,
                totalTokens: 0,
                responseTimes: [],
                successCount: 0
            };
            let responseTimeChart = null;
            let tokenUsageChart = null;
            
            // Initialize charts
            function initializeCharts() {
                if (typeof Chart === 'undefined') return;
                
                // Response time chart
                const rtCtx = document.getElementById('ads-response-time-chart');
                if (rtCtx) {
                    responseTimeChart = new Chart(rtCtx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: [],
                            datasets: [{
                                label: 'Response Time (ms)',
                                data: [],
                                borderColor: '#667eea',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                }
                
                // Token usage chart
                const tuCtx = document.getElementById('ads-token-usage-chart');
                if (tuCtx) {
                    tokenUsageChart = new Chart(tuCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: [],
                            datasets: [{
                                label: 'Tokens Used',
                                data: [],
                                backgroundColor: '#28a745',
                                borderColor: '#1e7e34',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                }
            }
            
            // Select model for testing
            $('.ads-select-model').on('click', function() {
                const modelKey = $(this).data('model-key');
                const modelName = $(this).closest('.ads-model-card').find('h4').text();
                
                currentModel = modelKey;
                $('#ads-active-model-name').text(modelName);
                $('#ads-chat-section, #ads-monitoring-section, #ads-typebot-section').show();
                
                // Scroll to chat section
                $('html, body').animate({
                    scrollTop: $('#ads-chat-section').offset().top - 100
                }, 500);
                
                adsDemo.showNotification('Model selected: ' + modelName, 'success');
            });
            
            // Send message
            $('#ads-send-message').on('click', function() {
                sendMessage();
            });
            
            // Send message on Enter (Shift+Enter for new line)
            $('#ads-chat-input').on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            
            // Temperature slider
            $('#ads-chat-temperature').on('input', function() {
                $('#ads-temperature-value').text($(this).val());
            });
            
            // Clear chat
            $('#ads-clear-chat').on('click', function() {
                if (confirm('Are you sure you want to clear the chat history?')) {
                    $('#ads-chat-messages').html(`
                        <div class="ads-chat-welcome">
                            <div class="ads-welcome-icon">🤖</div>
                            <h4>Welcome to Aevov deAI Testing</h4>
                            <p>Start chatting with your selected model. All responses are processed through the Aevov neurosymbolic architecture.</p>
                        </div>
                    `);
                    
                    // Reset stats
                    chatStats = {
                        messages: 0,
                        totalTokens: 0,
                        responseTimes: [],
                        successCount: 0
                    };
                    updateStats();
                }
            });
            
            // Export chat
            $('#ads-export-chat').on('click', function() {
                exportChatHistory();
            });
            
            // Stop testing
            $('#ads-stop-testing').on('click', function() {
                if (confirm('Are you sure you want to stop testing?')) {
                    currentModel = null;
                    $('#ads-chat-section, #ads-monitoring-section, #ads-typebot-section').hide();
                    adsDemo.showNotification('Testing stopped', 'info');
                }
            });
            
            // Test Typebot webhook
            $('.ads-test-typebot').on('click', function() {
                const apiId = $(this).data('api-id');
                testTypebotWebhook(apiId);
            });
            
            function sendMessage() {
                if (!currentModel) {
                    adsDemo.showNotification('Please select a model first', 'error');
                    return;
                }
                
                const message = $('#ads-chat-input').val().trim();
                if (!message) {
                    adsDemo.showNotification('Please enter a message', 'error');
                    return;
                }
                
                const temperature = parseFloat($('#ads-chat-temperature').val());
                const maxTokens = parseInt($('#ads-chat-max-tokens').val());
                
                // Add user message to chat
                addMessageToChat('user', message);
                $('#ads-chat-input').val('');
                
                // Show typing indicator
                showTypingIndicator();
                
                const startTime = Date.now();
                
                // Send to API
                $.ajax({
                    url: adsDemo.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ads_send_chat_message',
                        model_key: currentModel,
                        message: message,
                        temperature: temperature,
                        max_tokens: maxTokens,
                        nonce: adsDemo.nonce
                    },
                    success: function(response) {
                        hideTypingIndicator();
                        
                        if (response.success) {
                            const responseTime = Date.now() - startTime;
                            
                            // Add bot response to chat
                            addMessageToChat('bot', response.data.response, {
                                responseTime: responseTime,
                                tokensUsed: response.data.tokens_used,
                                modelUsed: response.data.model_used
                            });
                            
                            // Update stats
                            updateChatStats(responseTime, response.data.tokens_used, true);
                        } else {
                            addMessageToChat('bot', 'Sorry, I encountered an error: ' + (response.data || 'Unknown error'), {
                                error: true
                            });
                            updateChatStats(Date.now() - startTime, 0, false);
                        }
                    },
                    error: function(xhr, status, error) {
                        hideTypingIndicator();
                        addMessageToChat('bot', 'Network error: ' + error, {
                            error: true
                        });
                        updateChatStats(Date.now() - startTime, 0, false);
                    }
                });
            }
            
            function addMessageToChat(type, message, meta = {}) {
                const timestamp = new Date().toLocaleTimeString();
                const avatar = type === 'user' ? '👤' : '🤖';
                
                let metaHtml = '';
                if (meta.responseTime) {
                    metaHtml = `<div class="ads-message-meta">
                        <span>${timestamp}</span>
                        <span>${meta.responseTime}ms</span>
                        <span>${meta.tokensUsed} tokens</span>
                    </div>`;
                } else if (meta.error) {
                    metaHtml = `<div class="ads-message-meta">
                        <span>${timestamp}</span>
                        <span style="color: #dc3545;">Error</span>
                    </div>`;
                } else {
                    metaHtml = `<div class="ads-message-meta">
                        <span>${timestamp}</span>
                    </div>`;
                }
                
                const messageHtml = `
                    <div class="ads-message ${type}">
                        <div class="ads-message-avatar">${avatar}</div>
                        <div class="ads-message-content">
                            <div class="ads-message-bubble">${message}</div>
                            ${metaHtml}
                        </div>
                    </div>
                `;
                
                // Remove welcome message if it exists
                $('.ads-chat-welcome').remove();
                
                $('#ads-chat-messages').append(messageHtml);
                $('#ads-chat-messages').scrollTop($('#ads-chat-messages')[0].scrollHeight);
            }
            
            function showTypingIndicator() {
                const typingHtml = `
                    <div class="ads-message bot ads-typing-message">
                        <div class="ads-message-avatar">🤖</div>
                        <div class="ads-message-content">
                            <div class="ads-message-typing">
                                <span>Thinking</span>
                                <div class="ads-typing-dots">
                                    <div class="ads-typing-dot"></div>
                                    <div class="ads-typing-dot"></div>
                                    <div class="ads-typing-dot"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#ads-chat-messages').append(typingHtml);
                $('#ads-chat-messages').scrollTop($('#ads-chat-messages')[0].scrollHeight);
            }
            
            function hideTypingIndicator() {
                $('.ads-typing-message').remove();
            }
            
            function updateChatStats(responseTime, tokensUsed, success) {
                chatStats.messages++;
                chatStats.totalTokens += tokensUsed;
                chatStats.responseTimes.push(responseTime);
                if (success) chatStats.successCount++;
                
                updateStats();
                updateCharts(responseTime, tokensUsed);
            }
            
            function updateStats() {
                $('#ads-total-messages').text(chatStats.messages);
                $('#ads-total-tokens').text(chatStats.totalTokens);
                
                const avgResponseTime = chatStats.responseTimes.length > 0
                    ? Math.round(chatStats.responseTimes.reduce((a, b) => a + b, 0) / chatStats.responseTimes.length)
                    : 0;
                $('#ads-avg-response-time').text(avgResponseTime + 'ms');
                
                const successRate = chatStats.messages > 0
                    ? Math.round((chatStats.successCount / chatStats.messages) * 100)
                    : 100;
                $('#ads-success-rate').text(successRate + '%');
            }
            
            function updateCharts(responseTime, tokensUsed) {
                const timestamp = new Date().toLocaleTimeString();
                
                // Update response time chart
                if (responseTimeChart) {
                    responseTimeChart.data.labels.push(timestamp);
                    responseTimeChart.data.datasets[0].data.push(responseTime);
                    
                    // Keep only last 20 data points
                    if (responseTimeChart.data.labels.length > 20) {
                        responseTimeChart.data.labels.shift();
                        responseTimeChart.data.datasets[0].data.shift();
                    }
                    
                    responseTimeChart.update();
                }
                
                // Update token usage chart
                if (tokenUsageChart) {
                    tokenUsageChart.data.labels.push(timestamp);
                    tokenUsageChart.data.datasets[0].data.push(tokensUsed);
                    
                    // Keep only last 20 data points
                    if (tokenUsageChart.data.labels.length > 20) {
                        tokenUsageChart.data.labels.shift();
                        tokenUsageChart.data.datasets[0].data.shift();
                    }
                    
                    tokenUsageChart.update();
                }
            }
            
            function exportChatHistory() {
                const messages = [];
                $('#ads-chat-messages .ads-message').each(function() {
                    const type = $(this).hasClass('user') ? 'User' : 'Bot';
                    const message = $(this).find('.ads-message-bubble').text();
                    const timestamp = $(this).find('.ads-message-meta span:first').text();
                    
                    messages.push(`[${timestamp}] ${type}: ${message}`);
                });
                
                if (messages.length === 0) {
                    adsDemo.showNotification('No messages to export', 'info');
                    return;
                }
                
                const chatContent = messages.join('\n');
                const blob = new Blob([chatContent], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                
                const a = document.createElement('a');
                a.href = url;
                a.download = `aevov-chat-${new Date().toISOString().slice(0, 10)}.txt`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                adsDemo.showNotification('Chat history exported', 'success');
            }
            
            function testTypebotWebhook(apiId) {
                $.ajax({
                    url: adsDemo.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ads_test_typebot_webhook',
                        api_id: apiId,
                        nonce: adsDemo.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            adsDemo.showNotification('Typebot webhook test successful', 'success');
                        } else {
                            adsDemo.showNotification('Typebot webhook test failed: ' + (response.data || 'Unknown error'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        adsDemo.showNotification('Network error testing webhook: ' + error, 'error');
                    }
                });
            }
            
            // Initialize charts when page loads
            initializeCharts();
        });
        </script>
        <?php
    }
    
    private function get_available_models() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_model_downloads';
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE download_status = 'completed' OR processing_status = 'completed' ORDER BY created_at DESC"
        );
        
        $models = [];
        foreach ($results as $result) {
            $config = json_decode($result->configuration ?? '{}', true);
            $models[] = [
                'model_key' => $result->model_key,
                'name' => $config['name'] ?? $result->model_name,
                'size' => $config['size'] ?? 'Unknown',
                'type' => $config['type'] ?? 'SLM',
                'status' => $result->processing_status === 'completed' ? 'ready' : 'processing',
                'chunks_count' => $this->get_model_chunks_count($result->model_key)
            ];
        }
        
        return $models;
    }
    
    private function get_active_api_configurations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_api_monitoring';
        $results = $wpdb->get_results(
            "SELECT DISTINCT model_key, api_endpoint, api_key, configuration, status
             FROM $table_name
             WHERE status = 'active' AND model_key IS NOT NULL
             GROUP BY model_key
             ORDER BY created_at DESC"
        );
        
        $configs = [];
        foreach ($results as $result) {
            $config = json_decode($result->configuration ?? '{}', true);
            $configs[] = [
                'id' => $result->model_key,
                'model_name' => $config['model_name'] ?? 'Unknown Model',
                'api_endpoint' => $result->api_endpoint ?? $result->endpoint ?? '',
                'api_key' => $result->api_key ?? '',
                'typebot_webhook' => $config['typebot_webhook'] ?? '',
                'status' => $result->status
            ];
        }
        
        return $configs;
    }
    
    private function get_model_chunks_count($model_key) {
        // This would integrate with the chunking system to get actual chunk count
        // For now, return a simulated count
        return rand(50, 200);
    }
    
    public function handle_chat_message($model_key, $message, $temperature, $max_tokens) {
        // This would integrate with the actual model processing
        // For now, simulate a response
        $responses = [
            "Hello! I'm processing your message through the Aevov deAI network - 'The Web's Neural Network'.",
            "As part of the decentralized AI architecture, I can help you with various tasks using neurosymbolic processing.",
            "The Aevov network's distributed pattern recognition allows me to provide intelligent responses.",
            "I'm analyzing your request through the hybrid comparator engine for optimal results.",
            "Thank you for using the Aevov deAI testing environment. How else can I assist you?"
        ];
        
        $response_text = $responses[array_rand($responses)];
        $tokens_used = rand(20, 80);
        
        return [
            'success' => true,
            'response' => $response_text,
            'model_used' => $model_key,
            'tokens_used' => $tokens_used
        ];
    }
    
    public function test_typebot_webhook($api_id) {
        // Simulate webhook test
        $test_payload = [
            'sessionId' => 'test_session_' . time(),
            'message' => 'This is a test message from the Aevov deAI network',
            'variables' => [
                'userName' => 'Test User',
                'context' => 'webhook_test'
            ]
        ];
        
        // In real implementation, this would make an actual HTTP request to the webhook
        return [
            'success' => true,
            'message' => 'Webhook test completed successfully',
            'payload' => $test_payload
        ];
    }
}