<?php
namespace AROS\Learning;
class ExperienceReplay {
    private $buffer = [];
    private $max_size = 10000;
    public function add($state, $action, $reward, $next_state, $done) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'aros_experiences', [
            'state' => json_encode($state),
            'action' => json_encode($action),
            'reward' => $reward,
            'next_state' => json_encode($next_state),
            'done' => $done,
        ]);
    }
    public function sample($batch_size = 32) {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aros_experiences ORDER BY RAND() LIMIT {$batch_size}", ARRAY_A);
    }
}
