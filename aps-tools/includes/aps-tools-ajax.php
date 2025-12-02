<?php
/**
 * Handles AJAX requests for the APS Tools plugin
 */

add_action('wp_ajax_aps_tools_action', 'aps_tools_ajax_handler');

function aps_tools_ajax_handler() {
    check_ajax_referer('aps-tools-nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Insufficient permissions', 'aps-tools')]);
    }

    $action = $_POST['tool_action'] ?? '';
    $aps = aps_tools_get_aps_instance();

    if (!$aps) {
        wp_send_json_error(['message' => __('APS Plugin is not available', 'aps-tools')]);
    }

    switch ($action) {
        case 'analyze_pattern':
            $result = $aps->get_pattern_analyzer()->analyze_pattern(json_decode($_POST['pattern_data'], true));
            break;
        case 'run_comparison':
            $result = $aps->get_comparator()->compare_patterns(
                json_decode($_POST['comparison_data']['patterns'], true),
                json_decode($_POST['comparison_data']['options'], true)
            );
            break;
        case 'get_system_metrics':
            $result = $aps->get_system_monitor()->get_system_status();
            break;
        default:
            wp_send_json_error(['message' => __('Invalid action', 'aps-tools')]);
    }

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success($result);
}