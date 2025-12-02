<?php
namespace AROS\API;
use Aevov\Security\SecurityHelper;
class AROSEndpoint {
    public function register_routes() {
        register_rest_route('aros/v1', '/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_status'],
            'permission_callback' => [SecurityHelper::class, 'can_read_aevov'],
        ]);
        register_rest_route('aros/v1', '/command', [
            'methods' => 'POST',
            'callback' => [$this, 'execute_command'],
            'permission_callback' => [SecurityHelper::class, 'can_edit_aevov'],
        ]);
    }
    public function get_status($request) {
        $aros = \AROS\AROS::get_instance();
        return new \WP_REST_Response([
            'status' => 'online',
            'systems' => array_keys($aros->get_all_systems()),
            'improvement' => $aros->get_system('improvement')->get_status(),
        ]);
    }
    public function execute_command($request) {
        $command = $request->get_param('command');
        return new \WP_REST_Response(['result' => 'executed']);
    }
}
