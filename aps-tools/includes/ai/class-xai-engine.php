<?php
/**
 * XAI (Explainable AI) Engine
 * 
 * Provides transparency, traceability, and explainability for AI components
 * in the Aevov neurosymbolic architecture.
 * 
 * @package APS_Tools
 * @subpackage AI
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class XAI_Engine {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Decision log file
     */
    private $decision_log_file;
    
    /**
     * Explanation cache
     */
    private $explanation_cache = array();
    
    /**
     * Traceability database table
     */
    private $trace_table = 'aps_ai_traces';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->decision_log_file = WP_CONTENT_DIR . '/uploads/aps-tools/xai-decisions.log';
        $this->init_hooks();
        $this->ensure_log_directory();
        $this->create_trace_table();
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_aps_xai_get_explanation', array($this, 'ajax_get_explanation'));
        add_action('wp_ajax_aps_xai_get_trace', array($this, 'ajax_get_trace'));
        add_action('wp_ajax_aps_xai_export_decisions', array($this, 'ajax_export_decisions'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'aps-tools',
            'XAI Engine',
            'XAI Engine',
            'manage_options',
            'aps-xai-engine',
            array($this, 'render_xai_dashboard')
        );
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensure_log_directory() {
        $log_dir = dirname($this->decision_log_file);
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
    }
    
    /**
     * Create traceability database table
     */
    private function create_trace_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $this->trace_table;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            trace_id varchar(255) NOT NULL,
            component varchar(100) NOT NULL,
            action varchar(100) NOT NULL,
            input_data longtext,
            output_data longtext,
            decision_factors longtext,
            confidence_score decimal(5,4),
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            site_id bigint(20),
            user_id bigint(20),
            PRIMARY KEY (id),
            KEY trace_id (trace_id),
            KEY component (component),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log AI decision for traceability
     * 
     * @param string $component Component making the decision
     * @param string $action Action being performed
     * @param array $input_data Input data for the decision
     * @param array $output_data Output/result of the decision
     * @param array $decision_factors Factors that influenced the decision
     * @param float $confidence_score Confidence score (0-1)
     * @return string Trace ID
     */
    public function log_decision($component, $action, $input_data, $output_data, $decision_factors, $confidence_score = null) {
        global $wpdb;
        
        $trace_id = $this->generate_trace_id();
        
        // Log to database
        $wpdb->insert(
            $wpdb->prefix . $this->trace_table,
            array(
                'trace_id' => $trace_id,
                'component' => $component,
                'action' => $action,
                'input_data' => json_encode($input_data),
                'output_data' => json_encode($output_data),
                'decision_factors' => json_encode($decision_factors),
                'confidence_score' => $confidence_score,
                'site_id' => get_current_blog_id(),
                'user_id' => get_current_user_id()
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d')
        );
        
        // Log to file for backup
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'trace_id' => $trace_id,
            'component' => $component,
            'action' => $action,
            'confidence' => $confidence_score,
            'site_id' => get_current_blog_id()
        );
        
        $this->write_log_entry($log_entry);
        
        return $trace_id;
    }
    
    /**
     * Generate unique trace ID
     */
    private function generate_trace_id() {
        return 'xai_' . uniqid() . '_' . time();
    }
    
    /**
     * Write log entry to file
     */
    private function write_log_entry($entry) {
        $log_line = '[' . $entry['timestamp'] . '] ' . 
                   $entry['trace_id'] . ' | ' . 
                   $entry['component'] . ' | ' . 
                   $entry['action'] . ' | ' . 
                   'Confidence: ' . ($entry['confidence'] ?? 'N/A') . ' | ' .
                   'Site: ' . $entry['site_id'] . "\n";
        
        file_put_contents($this->decision_log_file, $log_line, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get explanation for a decision
     * 
     * @param string $trace_id Trace ID of the decision
     * @return array Explanation data
     */
    public function get_explanation($trace_id) {
        // Check cache first
        if (isset($this->explanation_cache[$trace_id])) {
            return $this->explanation_cache[$trace_id];
        }
        
        global $wpdb;
        
        $trace = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}{$this->trace_table} WHERE trace_id = %s",
                $trace_id
            ),
            ARRAY_A
        );
        
        if (!$trace) {
            return array('error' => 'Trace not found');
        }
        
        $explanation = $this->generate_explanation($trace);
        
        // Cache the explanation
        $this->explanation_cache[$trace_id] = $explanation;
        
        return $explanation;
    }
    
    /**
     * Generate human-readable explanation
     */
    private function generate_explanation($trace) {
        $input_data = json_decode($trace['input_data'], true);
        $output_data = json_decode($trace['output_data'], true);
        $decision_factors = json_decode($trace['decision_factors'], true);
        
        $explanation = array(
            'summary' => $this->generate_summary($trace, $decision_factors),
            'reasoning' => $this->generate_reasoning($decision_factors),
            'confidence' => array(
                'score' => $trace['confidence_score'],
                'interpretation' => $this->interpret_confidence($trace['confidence_score'])
            ),
            'input_analysis' => $this->analyze_input($input_data),
            'output_analysis' => $this->analyze_output($output_data),
            'alternative_outcomes' => $this->suggest_alternatives($decision_factors),
            'transparency_score' => $this->calculate_transparency_score($trace)
        );
        
        return $explanation;
    }
    
    /**
     * Generate summary explanation
     */
    private function generate_summary($trace, $decision_factors) {
        $component = $trace['component'];
        $action = $trace['action'];
        $confidence = $trace['confidence_score'];
        
        $summary = "The {$component} component performed a {$action} action";
        
        if ($confidence !== null) {
            $confidence_text = $confidence > 0.8 ? 'high confidence' : 
                              ($confidence > 0.6 ? 'moderate confidence' : 'low confidence');
            $summary .= " with {$confidence_text} (score: " . round($confidence, 3) . ")";
        }
        
        if (!empty($decision_factors['primary_factor'])) {
            $summary .= ". The primary factor was: " . $decision_factors['primary_factor'];
        }
        
        return $summary;
    }
    
    /**
     * Generate reasoning explanation
     */
    private function generate_reasoning($decision_factors) {
        $reasoning = array();
        
        if (isset($decision_factors['weights'])) {
            $reasoning['factor_weights'] = $decision_factors['weights'];
        }
        
        if (isset($decision_factors['rules_applied'])) {
            $reasoning['rules_applied'] = $decision_factors['rules_applied'];
        }
        
        if (isset($decision_factors['data_quality'])) {
            $reasoning['data_quality_assessment'] = $decision_factors['data_quality'];
        }
        
        if (isset($decision_factors['algorithm'])) {
            $reasoning['algorithm_used'] = $decision_factors['algorithm'];
        }
        
        return $reasoning;
    }
    
    /**
     * Interpret confidence score
     */
    private function interpret_confidence($score) {
        if ($score === null) {
            return 'No confidence score available';
        }
        
        if ($score >= 0.9) {
            return 'Very high confidence - Decision is highly reliable';
        } elseif ($score >= 0.8) {
            return 'High confidence - Decision is reliable';
        } elseif ($score >= 0.6) {
            return 'Moderate confidence - Decision should be reviewed';
        } elseif ($score >= 0.4) {
            return 'Low confidence - Decision requires human review';
        } else {
            return 'Very low confidence - Decision should not be trusted';
        }
    }
    
    /**
     * Analyze input data
     */
    private function analyze_input($input_data) {
        if (empty($input_data)) {
            return array('status' => 'No input data available');
        }
        
        $analysis = array(
            'data_points' => count($input_data),
            'data_types' => array(),
            'completeness' => 0,
            'quality_indicators' => array()
        );
        
        $complete_fields = 0;
        $total_fields = 0;
        
        foreach ($input_data as $key => $value) {
            $total_fields++;
            
            if (!empty($value)) {
                $complete_fields++;
            }
            
            $analysis['data_types'][$key] = gettype($value);
            
            // Quality indicators
            if (is_numeric($value) && $value < 0) {
                $analysis['quality_indicators'][] = "Negative value in {$key}";
            }
            
            if (is_string($value) && strlen($value) > 1000) {
                $analysis['quality_indicators'][] = "Large text field in {$key}";
            }
        }
        
        $analysis['completeness'] = $total_fields > 0 ? ($complete_fields / $total_fields) * 100 : 0;
        
        return $analysis;
    }
    
    /**
     * Analyze output data
     */
    private function analyze_output($output_data) {
        if (empty($output_data)) {
            return array('status' => 'No output data available');
        }
        
        $analysis = array(
            'result_type' => gettype($output_data),
            'complexity' => $this->calculate_complexity($output_data),
            'validation_status' => $this->validate_output($output_data)
        );
        
        if (is_array($output_data)) {
            $analysis['array_size'] = count($output_data);
            $analysis['nested_levels'] = $this->count_nested_levels($output_data);
        }
        
        return $analysis;
    }
    
    /**
     * Calculate data complexity
     */
    private function calculate_complexity($data) {
        if (is_scalar($data)) {
            return 'simple';
        }
        
        if (is_array($data)) {
            $size = count($data);
            $nested = $this->count_nested_levels($data);
            
            if ($size < 10 && $nested <= 2) {
                return 'moderate';
            } elseif ($size < 100 && $nested <= 4) {
                return 'complex';
            } else {
                return 'very_complex';
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Count nested levels in array
     */
    private function count_nested_levels($array, $level = 0) {
        if (!is_array($array)) {
            return $level;
        }
        
        $max_level = $level;
        foreach ($array as $value) {
            if (is_array($value)) {
                $nested_level = $this->count_nested_levels($value, $level + 1);
                $max_level = max($max_level, $nested_level);
            }
        }
        
        return $max_level;
    }
    
    /**
     * Validate output data
     */
    private function validate_output($output_data) {
        $validation = array(
            'is_valid' => true,
            'issues' => array()
        );
        
        // Check for common issues
        if (is_array($output_data) && empty($output_data)) {
            $validation['issues'][] = 'Empty result array';
        }
        
        if (is_string($output_data) && trim($output_data) === '') {
            $validation['issues'][] = 'Empty string result';
        }
        
        if (is_numeric($output_data) && !is_finite($output_data)) {
            $validation['issues'][] = 'Non-finite numeric result';
            $validation['is_valid'] = false;
        }
        
        return $validation;
    }
    
    /**
     * Suggest alternative outcomes
     */
    private function suggest_alternatives($decision_factors) {
        $alternatives = array();
        
        if (isset($decision_factors['alternative_algorithms'])) {
            $alternatives['algorithms'] = $decision_factors['alternative_algorithms'];
        }
        
        if (isset($decision_factors['threshold_sensitivity'])) {
            $alternatives['threshold_adjustments'] = $decision_factors['threshold_sensitivity'];
        }
        
        // Generic suggestions based on confidence
        if (isset($decision_factors['confidence']) && $decision_factors['confidence'] < 0.7) {
            $alternatives['recommendations'] = array(
                'Consider collecting more data',
                'Review input data quality',
                'Try alternative algorithms',
                'Adjust decision thresholds'
            );
        }
        
        return $alternatives;
    }
    
    /**
     * Calculate transparency score
     */
    private function calculate_transparency_score($trace) {
        $score = 0;
        $max_score = 100;
        
        // Has input data
        if (!empty($trace['input_data'])) {
            $score += 20;
        }
        
        // Has output data
        if (!empty($trace['output_data'])) {
            $score += 20;
        }
        
        // Has decision factors
        if (!empty($trace['decision_factors'])) {
            $score += 30;
        }
        
        // Has confidence score
        if ($trace['confidence_score'] !== null) {
            $score += 15;
        }
        
        // Has component and action info
        if (!empty($trace['component']) && !empty($trace['action'])) {
            $score += 15;
        }
        
        return array(
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => round(($score / $max_score) * 100, 1),
            'level' => $this->get_transparency_level($score / $max_score)
        );
    }
    
    /**
     * Get transparency level
     */
    private function get_transparency_level($ratio) {
        if ($ratio >= 0.9) {
            return 'Excellent';
        } elseif ($ratio >= 0.7) {
            return 'Good';
        } elseif ($ratio >= 0.5) {
            return 'Fair';
        } else {
            return 'Poor';
        }
    }
    
    /**
     * Get decision trace
     * 
     * @param string $trace_id Trace ID
     * @return array Trace data
     */
    public function get_trace($trace_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}{$this->trace_table} WHERE trace_id = %s",
                $trace_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Get traces by component
     * 
     * @param string $component Component name
     * @param int $limit Number of traces to return
     * @return array Traces
     */
    public function get_traces_by_component($component, $limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}{$this->trace_table} 
                 WHERE component = %s 
                 ORDER BY timestamp DESC 
                 LIMIT %d",
                $component,
                $limit
            ),
            ARRAY_A
        );
    }
    
    /**
     * Get recent decisions
     * 
     * @param int $limit Number of decisions to return
     * @return array Recent decisions
     */
    public function get_recent_decisions($limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}{$this->trace_table} 
                 ORDER BY timestamp DESC 
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }
    
    /**
     * Export decisions to CSV
     * 
     * @param array $filters Filters to apply
     * @return string CSV content
     */
    public function export_decisions($filters = array()) {
        global $wpdb;
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($filters['component'])) {
            $where_clauses[] = 'component = %s';
            $where_values[] = $filters['component'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'timestamp >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'timestamp <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $sql = "SELECT * FROM {$wpdb->prefix}{$this->trace_table} {$where_sql} ORDER BY timestamp DESC";
        
        if (!empty($where_values)) {
            $traces = $wpdb->get_results($wpdb->prepare($sql, $where_values), ARRAY_A);
        } else {
            $traces = $wpdb->get_results($sql, ARRAY_A);
        }
        
        // Generate CSV
        $csv = "Trace ID,Component,Action,Timestamp,Confidence Score,Site ID,User ID\n";
        
        foreach ($traces as $trace) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                $trace['trace_id'],
                $trace['component'],
                $trace['action'],
                $trace['timestamp'],
                $trace['confidence_score'] ?? 'N/A',
                $trace['site_id'],
                $trace['user_id']
            );
        }
        
        return $csv;
    }
    
    /**
     * AJAX handler for getting explanation
     */
    public function ajax_get_explanation() {
        check_ajax_referer('aps_tools_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $trace_id = sanitize_text_field($_POST['trace_id']);
        $explanation = $this->get_explanation($trace_id);
        
        wp_send_json_success($explanation);
    }
    
    /**
     * AJAX handler for getting trace
     */
    public function ajax_get_trace() {
        check_ajax_referer('aps_tools_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $trace_id = sanitize_text_field($_POST['trace_id']);
        $trace = $this->get_trace($trace_id);
        
        wp_send_json_success($trace);
    }
    
    /**
     * AJAX handler for exporting decisions
     */
    public function ajax_export_decisions() {
        check_ajax_referer('aps_tools_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $filters = array();
        if (!empty($_POST['component'])) {
            $filters['component'] = sanitize_text_field($_POST['component']);
        }
        if (!empty($_POST['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_POST['date_from']);
        }
        if (!empty($_POST['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_POST['date_to']);
        }
        
        $csv = $this->export_decisions($filters);
        
        wp_send_json_success(array('csv' => $csv));
    }
    
    /**
     * Render XAI dashboard
     */
    public function render_xai_dashboard() {
        $recent_decisions = $this->get_recent_decisions(10);
        ?>
        <div class="wrap">
            <h1>XAI (Explainable AI) Engine</h1>
            
            <div class="xai-dashboard">
                <div class="dashboard-overview">
                    <h2>AI Transparency Dashboard</h2>
                    <p>Monitor and explain AI decisions across the Aevov neurosymbolic architecture.</p>
                </div>
                
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <h3>Recent Decisions</h3>
                        <div id="recent-decisions">
                            <?php if (!empty($recent_decisions)): ?>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Trace ID</th>
                                            <th>Component</th>
                                            <th>Action</th>
                                            <th>Confidence</th>
                                            <th>Time</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_decisions as $decision): ?>
                                            <tr>
                                                <td><code><?php echo esc_html(substr($decision['trace_id'], 0, 20)); ?>...</code></td>
                                                <td><?php echo esc_html($decision['component']); ?></td>
                                                <td><?php echo esc_html($decision['action']); ?></td>
                                                <td>
                                                    <?php if ($decision['confidence_score'] !== null): ?>
                                                        <span class="confidence-score confidence-<?php echo $decision['confidence_score'] > 0.7 ? 'high' : ($decision['confidence_score'] > 0.5 ? 'medium' : 'low'); ?>">
                                                            <?php echo round($decision['confidence_score'], 3); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="confidence-score confidence-none">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo esc_html(human_time_diff(strtotime($decision['timestamp']))); ?> ago</td>
                                                <td>
                                                    <button class="button button-small explain-decision" data-trace-id="<?php echo esc_attr($decision['trace_id']); ?>">
                                                        Explain
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p>No AI decisions recorded yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <h3>Explanation Viewer</h3>
                        <div id="explanation-viewer">
                            <p>Select a decision to view its explanation.</p>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <h3>Transparency Metrics</h3>
                        <div id="transparency-metrics">
                            <div class="metric">
                                <span class="metric-label">Total Decisions:</span>
                                <span class="metric-value" id="total-decisions">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Avg Confidence:</span>
                                <span class="metric-value" id="avg-confidence">--</span>
                            </div>
                            <div class="metric">
                                <span class="metric-label">Transparency Score:</span>
                                <span class="metric-value" id="transparency-score">--</span>
                            </div>
                        </div>
                        
                        <button id="refresh-metrics" class="button">Refresh Metrics</button>
                    </div>
                    
                    <div class="dashboard-card">
                        <h3>Export & Analysis</h3>
                        <form id="export-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Component Filter</th>
                                    <td>
                                        <select name="component" id="export-component">
                                            <option value="">All Components</option>
                                            <option value="tensor_processor">Tensor Processor</option>
                                            <option value="pattern_recognition">Pattern Recognition</option>
                                            <option value="bloom_filter">Bloom Filter</option>
                                            <option value="cubbit_integration">Cubbit Integration</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Date Range</th>
                                    <td>
                                        <input type="date" name="date_from" id="export-date-from">
                                        to
                                        <input type="date" name="date_to" id="export-date-to">
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="button" id="export-decisions" class="button button-primary">Export to CSV</button>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Explain decision
            $('.explain-decision').on('click', function() {
                const traceId = $(this).data('trace-id');
                const button = $(this);
                
                button.prop('disabled', true).text('Loading...');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'aps_xai_get_explanation',
                        trace_id: traceId,
                        nonce: '<?php echo wp_create_nonce('aps_tools_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayExplanation(response.data);
                        } else {
                            alert('Failed to get explanation: ' + response.data);
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Explain');
                    }
                });
            });
            
            // Display explanation
            function displayExplanation(explanation) {
                let html = '<div class="explanation-content">';
                
                if (explanation.error) {
                    html += '<p class="error">' + explanation.error + '</p>';
                } else {
                    html += '<h4>Decision Summary</h4>';
                    html += '<p>' + explanation.summary + '</p>';
                    
                    html += '<h4>Confidence Analysis</h4>';
                    html += '<p><strong>Score:</strong> ' + (explanation.confidence.score || 'N/A') + '</p>';
                    html += '<p><strong>Interpretation:</strong> ' + explanation.confidence.interpretation + '</p>';
                    
                    if (explanation.reasoning) {
                        html += '<h4>Decision Reasoning</h4>';
                        if (explanation.reasoning.factor_weights) {
                            html += '<p><strong>Factor Weights:</strong></p>';
                            html += '<ul>';
                            for (let factor in explanation.reasoning.factor_weights) {
                                html += '<li>' + factor + ': ' + explanation.reasoning.factor_weights[factor] + '</li>';
                            }
                            html += '</ul>';
                        }
                        
                        if (explanation.reasoning.algorithm_used) {
                            html += '<p><strong>Algorithm:</strong> ' + explanation.reasoning.algorithm_used + '</p>';
                        }
                    }
                    
                    if (explanation.transparency_score) {
                        html += '<h4>Transparency Score</h4>';
                        html += '<p><strong>Score:</strong> ' + explanation.transparency_score.percentage + '% (' + explanation.transparency_score.level + ')</p>';
                    }
                    
                    if (explanation.alternative_outcomes && explanation.alternative_outcomes.recommendations) {
                        html += '<h4>Recommendations</h4>';
                        html += '<ul>';
                        explanation.alternative_outcomes.recommendations.forEach(function(rec) {
                            html += '<li>' + rec + '</li>';
                        });
                        html += '</ul>';
                    }
                }
                
                html += '</div>';
                $('#explanation-viewer').html(html);
            }
            
            // Refresh metrics
            $('#refresh-metrics').on('click', function() {
                const button = $(this);
                button.prop('disabled', true).text('Loading...');
                
                // Calculate metrics from recent decisions
                let totalDecisions = $('.explain-decision').length;
                let confidenceScores = [];
                
                $('.confidence-score').each(function() {
                    let score = parseFloat($(this).text());
                    if (!isNaN(score)) {
                        confidenceScores.push(score);
                    }
                });
                
                let avgConfidence = confidenceScores.length > 0 ?
                    (confidenceScores.reduce((a, b) => a + b, 0) / confidenceScores.length).toFixed(3) : 'N/A';
                
                let transparencyScore = confidenceScores.length > 0 ?
                    Math.round((confidenceScores.filter(s => s > 0.7).length / confidenceScores.length) * 100) + '%' : 'N/A';
                
                $('#total-decisions').text(totalDecisions);
                $('#avg-confidence').text(avgConfidence);
                $('#transparency-score').text(transparencyScore);
                
                setTimeout(function() {
                    button.prop('disabled', false).text('Refresh Metrics');
                }, 1000);
            });
            
            // Export decisions
            $('#export-decisions').on('click', function() {
                const button = $(this);
                button.prop('disabled', true).text('Exporting...');
                
                const formData = {
                    action: 'aps_xai_export_decisions',
                    nonce: '<?php echo wp_create_nonce('aps_tools_nonce'); ?>',
                    component: $('#export-component').val(),
                    date_from: $('#export-date-from').val(),
                    date_to: $('#export-date-to').val()
                };
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            // Create download link
                            const blob = new Blob([response.data.csv], { type: 'text/csv' });
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'xai-decisions-' + new Date().toISOString().split('T')[0] + '.csv';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            window.URL.revokeObjectURL(url);
                            
                            alert('Export completed successfully!');
                        } else {
                            alert('Export failed: ' + response.data);
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Export to CSV');
                    }
                });
            });
            
            // Load initial metrics
            $('#refresh-metrics').click();
        });
        </script>
        
        <style>
        .xai-dashboard {
            max-width: 1400px;
        }
        
        .dashboard-overview {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .dashboard-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .dashboard-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #23282d;
        }
        
        .confidence-score {
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .confidence-high {
            background: #d4edda;
            color: #155724;
        }
        
        .confidence-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .confidence-low {
            background: #f8d7da;
            color: #721c24;
        }
        
        .confidence-none {
            background: #e2e3e5;
            color: #6c757d;
        }
        
        .explanation-content {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .explanation-content h4 {
            margin-top: 15px;
            margin-bottom: 10px;
            color: #0073aa;
        }
        
        .explanation-content h4:first-child {
            margin-top: 0;
        }
        
        .explanation-content ul {
            margin-left: 20px;
        }
        
        .metric {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .metric:last-child {
            border-bottom: none;
        }
        
        .metric-label {
            font-weight: bold;
        }
        
        .metric-value {
            color: #0073aa;
            font-weight: bold;
        }
        
        .error {
            color: #dc3232;
            font-weight: bold;
        }
        
        #export-form .form-table th {
            width: 150px;
        }
        
        #export-form input[type="date"] {
            margin: 0 5px;
        }
        </style>
        <?php
    }
}

// Initialize the XAI Engine
XAI_Engine::get_instance();