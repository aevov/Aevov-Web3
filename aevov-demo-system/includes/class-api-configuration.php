<?php
/**
 * API Configuration Class
 * Handles Typebot integration and API monitoring for the Aevov deAI network
 */

namespace AevovDemo;

class APIConfiguration {
    private $workflow_automation;
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_api_endpoints']);
    }
    
    private function get_workflow_automation() {
        if (!$this->workflow_automation) {
            require_once plugin_dir_path(__FILE__) . 'class-workflow-automation.php';
            $this->workflow_automation = new WorkflowAutomation();
        }
        return $this->workflow_automation;
    }
    
    public function register_api_endpoints() {
        // Chat API endpoint for Typebot integration
        register_rest_route('aevov-demo/v1', '/chat', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chat_request'],
            'permission_callback' => [$this, 'verify_api_key']
        ]);
        
        // Typebot webhook endpoint
        register_rest_route('aevov-demo/v1', '/typebot-webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_typebot_webhook'],
            'permission_callback' => '__return_true'
        ]);
        
        // API status endpoint
        register_rest_route('aevov-demo/v1', '/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_api_status'],
            'permission_callback' => [$this, 'verify_api_key']
        ]);
    }
    
    public function render_api_configuration_page() {
        $active_apis = $this->get_active_api_configurations();
        $api_activity = $this->get_recent_api_activity();
        
        ?>
        <div class="ads-demo-container">
            <div class="ads-header">
                <h1><?php _e('API Configuration', 'aevov-demo-system'); ?></h1>
                <p><?php _e('Configure and monitor API endpoints for Typebot integration with the Aevov deAI network', 'aevov-demo-system'); ?></p>
                <div class="ads-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=aevov-demo-system'); ?>" class="button button-secondary">
                        ← <?php _e('Back to Dashboard', 'aevov-demo-system'); ?>
                    </a>
                    <button class="button button-primary" onclick="adsDemo.generateNewApiKey()">
                        <?php _e('🔑 Generate New API Key', 'aevov-demo-system'); ?>
                    </button>
                </div>
            </div>
            
            <!-- API Overview Cards -->
            <div class="ads-api-overview">
                <div class="ads-api-card">
                    <div class="ads-api-card-icon">🔗</div>
                    <div class="ads-api-card-content">
                        <h3><?php echo count($active_apis); ?></h3>
                        <p><?php _e('Active APIs', 'aevov-demo-system'); ?></p>
                    </div>
                </div>
                
                <div class="ads-api-card">
                    <div class="ads-api-card-icon">📊</div>
                    <div class="ads-api-card-content">
                        <h3 id="ads-total-requests">0</h3>
                        <p><?php _e('Total Requests Today', 'aevov-demo-system'); ?></p>
                    </div>
                </div>
                
                <div class="ads-api-card">
                    <div class="ads-api-card-icon">⚡</div>
                    <div class="ads-api-card-content">
                        <h3 id="ads-avg-response-time">0ms</h3>
                        <p><?php _e('Avg Response Time', 'aevov-demo-system'); ?></p>
                    </div>
                </div>
                
                <div class="ads-api-card">
                    <div class="ads-api-card-icon">✅</div>
                    <div class="ads-api-card-content">
                        <h3 id="ads-success-rate">100%</h3>
                        <p><?php _e('Success Rate', 'aevov-demo-system'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Active API Configurations -->
            <?php if (!empty($active_apis)): ?>
                <div class="ads-section">
                    <h2><?php _e('Active API Configurations', 'aevov-demo-system'); ?></h2>
                    <div class="ads-api-configurations">
                        <?php foreach ($active_apis as $api): ?>
                            <div class="ads-api-config-card" data-api-id="<?php echo esc_attr($api['id']); ?>">
                                <div class="ads-api-config-header">
                                    <h4><?php echo esc_html($api['model_name']); ?></h4>
                                    <span class="ads-api-status <?php echo esc_attr($api['status']); ?>">
                                        <?php echo esc_html(ucfirst($api['status'])); ?>
                                    </span>
                                </div>
                                
                                <div class="ads-api-config-details">
                                    <div class="ads-api-detail">
                                        <label><?php _e('Endpoint:', 'aevov-demo-system'); ?></label>
                                        <div class="ads-api-endpoint">
                                            <code><?php echo esc_html($api['api_endpoint']); ?></code>
                                            <button class="ads-copy-btn" data-copy="<?php echo esc_attr($api['api_endpoint']); ?>">
                                                📋
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="ads-api-detail">
                                        <label><?php _e('API Key:', 'aevov-demo-system'); ?></label>
                                        <div class="ads-api-key">
                                            <code class="ads-api-key-masked"><?php echo str_repeat('*', 20) . substr($api['api_key'], -8); ?></code>
                                            <button class="ads-toggle-key" data-key="<?php echo esc_attr($api['api_key']); ?>">
                                                👁️
                                            </button>
                                            <button class="ads-copy-btn" data-copy="<?php echo esc_attr($api['api_key']); ?>">
                                                📋
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="ads-api-detail">
                                        <label><?php _e('Typebot Webhook:', 'aevov-demo-system'); ?></label>
                                        <div class="ads-api-endpoint">
                                            <code><?php echo esc_html($api['typebot_webhook']); ?></code>
                                            <button class="ads-copy-btn" data-copy="<?php echo esc_attr($api['typebot_webhook']); ?>">
                                                📋
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ads-api-config-actions">
                                    <button class="button button-primary ads-test-api" data-api-id="<?php echo esc_attr($api['id']); ?>">
                                        🧪 <?php _e('Test API', 'aevov-demo-system'); ?>
                                    </button>
                                    <button class="button button-secondary ads-configure-typebot" data-api-id="<?php echo esc_attr($api['id']); ?>">
                                        ⚙️ <?php _e('Configure Typebot', 'aevov-demo-system'); ?>
                                    </button>
                                    <button class="button ads-danger ads-revoke-api" data-api-id="<?php echo esc_attr($api['id']); ?>">
                                        🗑️ <?php _e('Revoke', 'aevov-demo-system'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Real-time API Activity Monitor -->
            <div class="ads-section">
                <h2><?php _e('Real-time API Activity', 'aevov-demo-system'); ?></h2>
                <div class="ads-api-monitoring">
                    <div class="ads-monitoring-controls">
                        <button class="button button-primary" id="ads-start-monitoring">
                            ▶️ <?php _e('Start Monitoring', 'aevov-demo-system'); ?>
                        </button>
                        <button class="button button-secondary" id="ads-pause-monitoring" disabled>
                            ⏸️ <?php _e('Pause', 'aevov-demo-system'); ?>
                        </button>
                        <button class="button" id="ads-clear-logs">
                            🗑️ <?php _e('Clear Logs', 'aevov-demo-system'); ?>
                        </button>
                        <div class="ads-monitoring-status">
                            <span id="ads-monitoring-indicator" class="ads-status-indicator stopped"></span>
                            <span id="ads-monitoring-text"><?php _e('Monitoring Stopped', 'aevov-demo-system'); ?></span>
                        </div>
                    </div>
                    
                    <div class="ads-monitoring-dashboard">
                        <div class="ads-monitoring-chart">
                            <h3><?php _e('API Request Volume', 'aevov-demo-system'); ?></h3>
                            <canvas id="ads-api-chart" width="400" height="200"></canvas>
                        </div>
                        
                        <div class="ads-monitoring-logs">
                            <h3><?php _e('Live API Calls', 'aevov-demo-system'); ?></h3>
                            <div id="ads-api-logs" class="ads-logs-container">
                                <div class="ads-log-placeholder">
                                    <?php _e('Start monitoring to see live API calls...', 'aevov-demo-system'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Typebot Integration Guide -->
            <div class="ads-section">
                <h2><?php _e('Typebot Integration Guide', 'aevov-demo-system'); ?></h2>
                <div class="ads-integration-guide">
                    <div class="ads-guide-step">
                        <div class="ads-step-number">1</div>
                        <div class="ads-step-content">
                            <h4><?php _e('Copy API Endpoint', 'aevov-demo-system'); ?></h4>
                            <p><?php _e('Copy the API endpoint URL from your active configuration above.', 'aevov-demo-system'); ?></p>
                        </div>
                    </div>
                    
                    <div class="ads-guide-step">
                        <div class="ads-step-number">2</div>
                        <div class="ads-step-content">
                            <h4><?php _e('Configure Typebot Webhook', 'aevov-demo-system'); ?></h4>
                            <p><?php _e('In your Typebot flow, add a "Webhook" block and paste the API endpoint.', 'aevov-demo-system'); ?></p>
                        </div>
                    </div>
                    
                    <div class="ads-guide-step">
                        <div class="ads-step-number">3</div>
                        <div class="ads-step-content">
                            <h4><?php _e('Add Authentication', 'aevov-demo-system'); ?></h4>
                            <p><?php _e('Add the API key as a header: "Authorization: Bearer YOUR_API_KEY"', 'aevov-demo-system'); ?></p>
                        </div>
                    </div>
                    
                    <div class="ads-guide-step">
                        <div class="ads-step-number">4</div>
                        <div class="ads-step-content">
                            <h4><?php _e('Test Integration', 'aevov-demo-system'); ?></h4>
                            <p><?php _e('Use the "Test API" button above to verify your integration is working.', 'aevov-demo-system'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- API Documentation -->
            <div class="ads-section">
                <h2><?php _e('API Documentation', 'aevov-demo-system'); ?></h2>
                <div class="ads-api-docs">
                    <div class="ads-api-endpoint-doc">
                        <h4><?php _e('POST /wp-json/aevov-demo/v1/chat', 'aevov-demo-system'); ?></h4>
                        <p><?php _e('Send a chat message to the Aevov deAI network for processing.', 'aevov-demo-system'); ?></p>
                        
                        <div class="ads-api-example">
                            <h5><?php _e('Request Example:', 'aevov-demo-system'); ?></h5>
                            <pre><code>{
  "message": "Hello, how can you help me?",
  "model_key": "phi-3-mini",
  "max_tokens": 150,
  "temperature": 0.7
}</code></pre>
                        </div>
                        
                        <div class="ads-api-example">
                            <h5><?php _e('Response Example:', 'aevov-demo-system'); ?></h5>
                            <pre><code>{
  "success": true,
  "response": "Hello! I'm part of the Aevov deAI network...",
  "model_used": "phi-3-mini",
  "processing_time": 1.23,
  "tokens_used": 45
}</code></pre>
                        </div>
                    </div>
                    
                    <div class="ads-api-endpoint-doc">
                        <h4><?php _e('POST /wp-json/aevov-demo/v1/typebot-webhook', 'aevov-demo-system'); ?></h4>
                        <p><?php _e('Webhook endpoint specifically designed for Typebot integration.', 'aevov-demo-system'); ?></p>
                        
                        <div class="ads-api-example">
                            <h5><?php _e('Typebot Payload:', 'aevov-demo-system'); ?></h5>
                            <pre><code>{
  "sessionId": "session_123",
  "message": "User message from Typebot",
  "variables": {
    "userName": "John",
    "context": "support"
  }
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- API Test Modal -->
        <div id="ads-api-test-modal" class="ads-modal" style="display: none;">
            <div class="ads-modal-content">
                <div class="ads-modal-header">
                    <h3><?php _e('API Test Console', 'aevov-demo-system'); ?></h3>
                    <button class="ads-modal-close">&times;</button>
                </div>
                <div class="ads-modal-body">
                    <div class="ads-api-test-form">
                        <div class="ads-form-group">
                            <label for="ads-test-message"><?php _e('Test Message:', 'aevov-demo-system'); ?></label>
                            <textarea id="ads-test-message" rows="3" placeholder="Enter your test message here..."><?php _e('Hello, this is a test message for the Aevov deAI network.', 'aevov-demo-system'); ?></textarea>
                        </div>
                        
                        <div class="ads-form-group">
                            <label for="ads-test-max-tokens"><?php _e('Max Tokens:', 'aevov-demo-system'); ?></label>
                            <input type="number" id="ads-test-max-tokens" value="150" min="1" max="1000">
                        </div>
                        
                        <div class="ads-form-group">
                            <label for="ads-test-temperature"><?php _e('Temperature:', 'aevov-demo-system'); ?></label>
                            <input type="number" id="ads-test-temperature" value="0.7" min="0" max="2" step="0.1">
                        </div>
                        
                        <button class="button button-primary" id="ads-send-test-request">
                            🚀 <?php _e('Send Test Request', 'aevov-demo-system'); ?>
                        </button>
                    </div>
                    
                    <div class="ads-api-test-results">
                        <h4><?php _e('Response:', 'aevov-demo-system'); ?></h4>
                        <div id="ads-test-response" class="ads-response-container">
                            <div class="ads-response-placeholder">
                                <?php _e('Send a test request to see the response...', 'aevov-demo-system'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="ads-modal-footer">
                    <button class="button button-secondary ads-modal-close">
                        <?php _e('Close', 'aevov-demo-system'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Typebot Configuration Modal -->
        <div id="ads-typebot-config-modal" class="ads-modal" style="display: none;">
            <div class="ads-modal-content">
                <div class="ads-modal-header">
                    <h3><?php _e('Typebot Configuration', 'aevov-demo-system'); ?></h3>
                    <button class="ads-modal-close">&times;</button>
                </div>
                <div class="ads-modal-body">
                    <div class="ads-typebot-config">
                        <div class="ads-config-section">
                            <h4><?php _e('Webhook Configuration', 'aevov-demo-system'); ?></h4>
                            <div class="ads-config-item">
                                <label><?php _e('Webhook URL:', 'aevov-demo-system'); ?></label>
                                <div class="ads-config-value">
                                    <input type="text" id="ads-typebot-webhook-url" readonly>
                                    <button class="ads-copy-btn" data-copy-target="#ads-typebot-webhook-url">📋</button>
                                </div>
                            </div>
                            
                            <div class="ads-config-item">
                                <label><?php _e('HTTP Method:', 'aevov-demo-system'); ?></label>
                                <div class="ads-config-value">
                                    <code>POST</code>
                                </div>
                            </div>
                            
                            <div class="ads-config-item">
                                <label><?php _e('Content-Type:', 'aevov-demo-system'); ?></label>
                                <div class="ads-config-value">
                                    <code>application/json</code>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ads-config-section">
                            <h4><?php _e('Authentication', 'aevov-demo-system'); ?></h4>
                            <div class="ads-config-item">
                                <label><?php _e('Header Name:', 'aevov-demo-system'); ?></label>
                                <div class="ads-config-value">
                                    <code>Authorization</code>
                                </div>
                            </div>
                            
                            <div class="ads-config-item">
                                <label><?php _e('Header Value:', 'aevov-demo-system'); ?></label>
                                <div class="ads-config-value">
                                    <input type="text" id="ads-typebot-auth-header" readonly>
                                    <button class="ads-copy-btn" data-copy-target="#ads-typebot-auth-header">📋</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="ads-config-section">
                            <h4><?php _e('Payload Template', 'aevov-demo-system'); ?></h4>
                            <textarea id="ads-typebot-payload-template" rows="8" readonly>{
  "message": "{{message}}",
  "sessionId": "{{sessionId}}",
  "variables": {
    "userName": "{{userName}}",
    "context": "typebot"
  }
}</textarea>
                            <button class="ads-copy-btn" data-copy-target="#ads-typebot-payload-template">📋</button>
                        </div>
                    </div>
                </div>
                <div class="ads-modal-footer">
                    <button class="button button-secondary ads-modal-close">
                        <?php _e('Close', 'aevov-demo-system'); ?>
                    </button>
                    <a href="https://typebot.io" target="_blank" class="button button-primary">
                        <?php _e('Open Typebot', 'aevov-demo-system'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <style>
        .ads-api-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .ads-api-card {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .ads-api-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .ads-api-card-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
        }
        
        .ads-api-card-content h3 {
            margin: 0 0 5px 0;
            font-size: 2em;
            font-weight: 700;
            color: #333;
        }
        
        .ads-api-card-content p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }
        
        .ads-api-configurations {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
        }
        
        .ads-api-config-card {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 25px;
        }
        
        .ads-api-config-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .ads-api-config-header h4 {
            margin: 0;
            color: #333;
        }
        
        .ads-api-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .ads-api-status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .ads-api-status.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .ads-api-config-details {
            margin-bottom: 20px;
        }
        
        .ads-api-detail {
            margin-bottom: 15px;
        }
        
        .ads-api-detail label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .ads-api-endpoint,
        .ads-api-key {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ads-api-endpoint code,
        .ads-api-key code {
            flex: 1;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            border: 1px solid #e9ecef;
        }
        
        .ads-copy-btn,
        .ads-toggle-key {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .ads-copy-btn:hover,
        .ads-toggle-key:hover {
            background: #5a67d8;
        }
        
        .ads-api-monitoring {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 30px;
        }
        
        .ads-monitoring-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .ads-monitoring-status {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .ads-status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .ads-status-indicator.running {
            background: #28a745;
            animation: pulse 2s infinite;
        }
        
        .ads-status-indicator.stopped {
            background: #dc3545;
        }
        
        .ads-monitoring-dashboard {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .ads-monitoring-chart,
        .ads-monitoring-logs {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        
        .ads-monitoring-chart h3,
        .ads-monitoring-logs h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.1em;
        }
        
        .ads-logs-container {
            background: #1e1e1e;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 6px;
            height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
        }
        
        .ads-log-placeholder {
            color: #666;
            text-align: center;
            padding: 50px 20px;
        }
        
        .ads-integration-guide {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 30px;
        }
        
        .ads-guide-step {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .ads-guide-step:last-child {
            margin-bottom: 0;
        }
        
        .ads-step-number {
            width: 40px;
            height: 40px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .ads-step-content h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .ads-step-content p {
            margin: 0;
            color: #666;
            line-height: 1.5;
        }
        
        .ads-api-docs {
            background: #fff;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            padding: 30px;
        }
        
        .ads-api-endpoint-doc {
            margin-bottom: 40px;
        }
        
        .ads-api-endpoint-doc:last-child {
            margin-bottom: 0;
        }
        
        .ads-api-endpoint-doc h4 {
            background: #667eea;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            margin: 0 0 15px 0;
            font-family: 'Courier New', monospace;
        }
        
        .ads-api-example {
            margin: 20px 0;
        }
        
        .ads-api-example h5 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .ads-api-example pre {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            overflow-x: auto;
        }
        
        .ads-api-example code {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #333;
        }
        
        .ads-api-test-form {
            margin-bottom: 30px;
        }
        
        .ads-form-group {
            margin-bottom: 20px;
        }
        
        .ads-form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .ads-form-group input,
        .ads-form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .ads-form-group input:focus,
        .ads-form-group textarea:focus {
            border-color: #667eea;
            outline: none;
        }
        
        .ads-response-container {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 20px;
            min-height: 200px;
        }
        
        .ads-response-placeholder {
            color: #666;
            text-align: center;
            padding: 50px 20px;
        }
        
        .ads-typebot-config {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .ads-config-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .ads-config-section:last-child {
            border-bottom: none;
        }
        
        .ads-config-section h4 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .ads-config-item {
            margin-bottom: 15px;
        }
        
        .ads-config-item label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .ads-config-value {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ads-config-value input,
        .ads-config-value textarea {
            flex: 1;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
        }
        
        .ads-config-value code {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            border: 1px solid #e9ecef;
        }
        
        .ads-api-response {
            background: #e8f5e8;
            border: 1px solid #c3e6c3;
            border-radius: 6px;
            padding: 15px;
        }
        
        .ads-api-response h5 {
            margin: 0 0 10px 0;
            color: #2e7d32;
        }
        
        .ads-response-text {
            background: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-family: system-ui, -apple-system, sans-serif;
            line-height: 1.5;
        }
        
        .ads-response-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85em;
            color: #666;
        }
        
        .ads-api-error {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            padding: 15px;
        }
        
        .ads-api-error h5 {
            margin: 0 0 10px 0;
            color: #c62828;
        }
        
        .ads-error-text {
            background: white;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #d32f2f;
        }
        
        .ads-loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .ads-loading::after {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #667eea;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        
        .ads-log-entry {
            padding: 5px 0;
            border-bottom: 1px solid #333;
            font-size: 0.8em;
        }
        
        .ads-log-entry.success {
            color: #4caf50;
        }
        
        .ads-log-entry.error {
            color: #f44336;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let monitoringActive = false;
            let apiChart = null;
            
            // Initialize API monitoring
            function initializeApiMonitoring() {
                const ctx = document.getElementById('ads-api-chart').getContext('2d');
                apiChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [{
                            label: 'Requests per minute',
                            data: [],
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Start monitoring
            $('#ads-start-monitoring').on('click', function() {
                monitoringActive = true;
                $(this).prop('disabled', true);
                $('#ads-pause-monitoring').prop('disabled', false);
                $('#ads-monitoring-indicator').removeClass('stopped').addClass('running');
                $('#ads-monitoring-text').text('Monitoring Active');
                
                adsDemo.startApiMonitoring();
            });
            
            // Pause monitoring
            $('#ads-pause-monitoring').on('click', function() {
                monitoringActive = false;
                $(this).prop('disabled', true);
                $('#ads-start-monitoring').prop('disabled', false);
                $('#ads-monitoring-indicator').removeClass('running').addClass('stopped');
                $('#ads-monitoring-text').text('Monitoring Paused');
                
                adsDemo.pauseApiMonitoring();
            });
            
            // Clear logs
            $('#ads-clear-logs').on('click', function() {
                $('#ads-api-logs').html('<div class="ads-log-placeholder">Logs cleared...</div>');
            });
            
            // Copy functionality
            $('.ads-copy-btn').on('click', function() {
                const copyText = $(this).data('copy') || $($(this).data('copy-target')).val();
                navigator.clipboard.writeText(copyText).then(function() {
                    adsDemo.showNotification('Copied to clipboard!', 'success');
                });
            });
            
            // Toggle API key visibility
            $('.ads-toggle-key').on('click', function() {
                const $this = $(this);
                const $keyElement = $this.siblings('.ads-api-key-masked');
                const fullKey = $this.data('key');
                
                if ($keyElement.text().includes('*')) {
                    $keyElement.text(fullKey);
                    $this.text('🙈');
                } else {
                    $keyElement.text('*'.repeat(20) + fullKey.slice(-8));
                    $this.text('👁️');
                }
            });
            
            // Test API
            $('.ads-test-api').on('click', function() {
                const apiId = $(this).data('api-id');
                adsDemo.openApiTestModal(apiId);
            });
            
            // Configure Typebot
            $('.ads-configure-typebot').on('click', function() {
                const apiId = $(this).data('api-id');
                adsDemo.openTypebotConfigModal(apiId);
            });
            
            // Send test request
            $('#ads-send-test-request').on('click', function() {
                adsDemo.sendTestApiRequest();
            });
            
            // Modal handlers
            $('.ads-modal-close').on('click', function() {
                $(this).closest('.ads-modal').hide();
            });
            
            // Initialize chart
            if (typeof Chart !== 'undefined') {
                initializeApiMonitoring();
            }
            
            // Extend adsDemo object
            if (typeof adsDemo !== 'undefined') {
                adsDemo.openApiTestModal = function(apiId) {
                    $('#ads-api-test-modal').show();
                    $('#ads-api-test-modal').data('api-id', apiId);
                };
                
                adsDemo.openTypebotConfigModal = function(apiId) {
                    // Get API configuration
                    $.ajax({
                        url: adsDemo.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ads_get_api_config',
                            api_id: apiId,
                            nonce: adsDemo.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                const config = response.data;
                                $('#ads-typebot-webhook-url').val(config.typebot_webhook);
                                $('#ads-typebot-auth-header').val('Bearer ' + config.api_key);
                                $('#ads-typebot-config-modal').show();
                            }
                        }
                    });
                };
                
                adsDemo.sendTestApiRequest = function() {
                    const apiId = $('#ads-api-test-modal').data('api-id');
                    const message = $('#ads-test-message').val();
                    const maxTokens = $('#ads-test-max-tokens').val();
                    const temperature = $('#ads-test-temperature').val();
                    
                    $('#ads-test-response').html('<div class="ads-loading">Sending request...</div>');
                    
                    $.ajax({
                        url: adsDemo.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ads_test_api_request',
                            api_id: apiId,
                            message: message,
                            max_tokens: maxTokens,
                            temperature: temperature,
                            nonce: adsDemo.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#ads-test-response').html(`
                                    <div class="ads-api-response">
                                        <h5>Response:</h5>
                                        <div class="ads-response-text">${response.data.response}</div>
                                        <div class="ads-response-meta">
                                            <span>Model: ${response.data.model_used}</span>
                                            <span>Processing Time: ${response.data.processing_time}s</span>
                                            <span>Tokens Used: ${response.data.tokens_used}</span>
                                        </div>
                                    </div>
                                `);
                            } else {
                                $('#ads-test-response').html(`
                                    <div class="ads-api-error">
                                        <h5>Error:</h5>
                                        <div class="ads-error-text">${response.data || 'Unknown error'}</div>
                                    </div>
                                `);
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#ads-test-response').html(`
                                <div class="ads-api-error">
                                    <h5>Network Error:</h5>
                                    <div class="ads-error-text">${error}</div>
                                </div>
                            `);
                        }
                    });
                };
                
                adsDemo.startApiMonitoring = function() {
                    // Start real-time monitoring
                    adsDemo.monitoringInterval = setInterval(function() {
                        if (!monitoringActive) return;
                        
                        // Fetch latest API activity
                        $.ajax({
                            url: adsDemo.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'ads_get_api_activity',
                                nonce: adsDemo.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    adsDemo.updateApiMonitoring(response.data);
                                }
                            }
                        });
                    }, 2000);
                };
                
                adsDemo.pauseApiMonitoring = function() {
                    if (adsDemo.monitoringInterval) {
                        clearInterval(adsDemo.monitoringInterval);
                    }
                };
                
                adsDemo.updateApiMonitoring = function(data) {
                    // Update chart
                    if (apiChart) {
                        const now = new Date().toLocaleTimeString();
                        apiChart.data.labels.push(now);
                        apiChart.data.datasets[0].data.push(data.requests_per_minute || 0);
                        
                        // Keep only last 20 data points
                        if (apiChart.data.labels.length > 20) {
                            apiChart.data.labels.shift();
                            apiChart.data.datasets[0].data.shift();
                        }
                        
                        apiChart.update();
                    }
                    
                    // Update overview cards
                    $('#ads-total-requests').text(data.total_requests || 0);
                    $('#ads-avg-response-time').text((data.avg_response_time || 0) + 'ms');
                    $('#ads-success-rate').text((data.success_rate || 100) + '%');
                    
                    // Update logs
                    if (data.recent_calls && data.recent_calls.length > 0) {
                        let logsHtml = '';
                        data.recent_calls.forEach(function(call) {
                            const timestamp = new Date(call.timestamp).toLocaleTimeString();
                            const status = call.success ? 'SUCCESS' : 'ERROR';
                            const statusClass = call.success ? 'success' : 'error';
                            
                            logsHtml += `<div class="ads-log-entry ${statusClass}">
                                [${timestamp}] ${status} - ${call.endpoint} (${call.response_time}ms)
                            </div>`;
                        });
                        
                        $('#ads-api-logs').html(logsHtml);
                        $('#ads-api-logs').scrollTop($('#ads-api-logs')[0].scrollHeight);
                    }
                };
                
                adsDemo.generateNewApiKey = function() {
                    if (confirm('Are you sure you want to generate a new API key? This will invalidate the current key.')) {
                        $.ajax({
                            url: adsDemo.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'ads_generate_api_key',
                                nonce: adsDemo.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    adsDemo.showNotification('New API key generated successfully', 'success');
                                    setTimeout(function() {
                                        location.reload();
                                    }, 2000);
                                } else {
                                    adsDemo.showNotification('Failed to generate API key: ' + (response.data || 'Unknown error'), 'error');
                                }
                            }
                        });
                    }
                };
            }
        });
        </script>
        <?php
    }
    
    public function handle_chat_request($request) {
        $params = $request->get_json_params();
        
        if (!isset($params['message'])) {
            return new \WP_Error('missing_message', 'Message parameter is required', ['status' => 400]);
        }
        
        $model_key = $params['model_key'] ?? 'default';
        $max_tokens = intval($params['max_tokens'] ?? 150);
        $temperature = floatval($params['temperature'] ?? 0.7);
        
        // Log API request
        $this->log_api_request($request, $model_key);
        
        // Process the chat request
        $start_time = microtime(true);
        $response = $this->process_chat_message($params['message'], $model_key, $max_tokens, $temperature);
        $processing_time = microtime(true) - $start_time;
        
        // Log API response
        $this->log_api_response($request, $response, $processing_time);
        
        return [
            'success' => true,
            'response' => $response['message'],
            'model_used' => $model_key,
            'processing_time' => round($processing_time, 3),
            'tokens_used' => $response['tokens_used'] ?? 0
        ];
    }
    
    public function handle_typebot_webhook($request) {
        $params = $request->get_json_params();
        
        if (!isset($params['message'])) {
            return new \WP_Error('missing_message', 'Message parameter is required', ['status' => 400]);
        }
        
        $session_id = $params['sessionId'] ?? 'unknown';
        $variables = $params['variables'] ?? [];
        
        // Process message with Typebot context
        $response = $this->process_chat_message($params['message'], 'default', 150, 0.7, [
            'session_id' => $session_id,
            'variables' => $variables,
            'source' => 'typebot'
        ]);
        
        return [
            'success' => true,
            'response' => $response['message'],
            'sessionId' => $session_id
        ];
    }
    
    public function get_api_status($request) {
        $stats = $this->get_api_statistics();
        
        return [
            'success' => true,
            'status' => 'active',
            'statistics' => $stats,
            'timestamp' => current_time('mysql')
        ];
    }
    
    public function verify_api_key($request) {
        $auth_header = $request->get_header('authorization');
        
        if (!$auth_header) {
            return new \WP_Error('missing_auth', 'Authorization header required', ['status' => 401]);
        }
        
        if (!preg_match('/Bearer\s+(.+)/', $auth_header, $matches)) {
            return new \WP_Error('invalid_auth_format', 'Invalid authorization format', ['status' => 401]);
        }
        
        $api_key = $matches[1];
        
        // Verify API key exists and is active
        global $wpdb;
        $table_name = $wpdb->prefix . 'ads_api_monitoring';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE api_key = %s AND status = 'active'",
            $api_key
        ));
        
        if (!$result) {
            return new \WP_Error('invalid_api_key', 'Invalid or inactive API key', ['status' => 401]);
        }
        
        return true;
    }
    
    private function process_chat_message($message, $model_key, $max_tokens, $temperature, $context = []) {
        // Simulate AI processing - in real implementation, this would integrate with the actual model
        $responses = [
            "Hello! I'm part of the Aevov deAI network - 'The Web's Neural Network'. How can I assist you today?",
            "As a decentralized AI node in the Aevov network, I can help you with various tasks using neurosymbolic processing.",
            "The Aevov deAI architecture allows me to provide intelligent responses through distributed pattern recognition.",
            "I'm processing your request through the Aevov neurosymbolic framework for optimal results.",
            "Thank you for connecting to the Aevov network. I'm ready to help with your inquiry."
        ];
        
        $response_text = $responses[array_rand($responses)];
        
        // Add context-aware responses
        if (isset($context['source']) && $context['source'] === 'typebot') {
            $response_text = "Via Typebot integration: " . $response_text;
        }
        
        return [
            'message' => $response_text,
            'tokens_used' => rand(20, 80)
        ];
    }
    
    private function log_api_request($request, $model_key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_api_monitoring';
        $wpdb->insert($table_name, [
            'model_key' => $model_key,
            'request_data' => json_encode($request->get_json_params()),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'created_at' => current_time('mysql')
        ]);
    }
    
    private function log_api_response($request, $response, $processing_time) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_api_monitoring';
        $wpdb->update($table_name, [
            'response_data' => json_encode($response),
            'processing_time' => $processing_time,
            'updated_at' => current_time('mysql')
        ], [
            'id' => $wpdb->insert_id
        ]);
    }
    
    private function get_active_api_configurations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_api_monitoring';
        $results = $wpdb->get_results(
            "SELECT DISTINCT model_key, api_endpoint, api_key, configuration, status
             FROM $table_name
             WHERE status = 'active'
             GROUP BY model_key
             ORDER BY created_at DESC"
        );
        
        $configs = [];
        foreach ($results as $result) {
            $config = json_decode($result->configuration, true);
            $configs[] = [
                'id' => $result->model_key,
                'model_name' => $config['model_name'] ?? 'Unknown Model',
                'api_endpoint' => $result->api_endpoint,
                'api_key' => $result->api_key,
                'typebot_webhook' => $config['typebot_webhook'] ?? '',
                'status' => $result->status
            ];
        }
        
        return $configs;
    }
    
    private function get_recent_api_activity() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_api_monitoring';
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY created_at DESC
             LIMIT 100"
        );
        
        return $results;
    }
    
    private function get_api_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ads_api_monitoring';
        
        // Total requests today
        $total_requests = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name
             WHERE DATE(created_at) = CURDATE()"
        );
        
        // Average response time
        $avg_response_time = $wpdb->get_var(
            "SELECT AVG(processing_time) FROM $table_name
             WHERE processing_time IS NOT NULL
             AND DATE(created_at) = CURDATE()"
        );
        
        // Success rate
        $success_rate = $wpdb->get_var(
            "SELECT (COUNT(CASE WHEN response_data IS NOT NULL THEN 1 END) * 100.0 / COUNT(*))
             FROM $table_name
             WHERE DATE(created_at) = CURDATE()"
        );
        
        return [
            'total_requests' => intval($total_requests),
            'avg_response_time' => round(floatval($avg_response_time) * 1000), // Convert to ms
            'success_rate' => round(floatval($success_rate), 1),
            'requests_per_minute' => rand(0, 10) // Simulated for demo
        ];
    }
}