<?php
/**
 * Base API endpoint class
 * Provides common functionality for all API endpoints
 * 
 * @package APS
 * @subpackage API\Endpoints
 */

namespace APS\API\Endpoints;

class BaseEndpoint {
    protected $namespace;
    protected $base;
    
    public function __construct($namespace) {
        $this->namespace = $namespace;
    }
    
    /**
     * Check if the current user has read permission
     *
     * @return bool
     */
    public function check_read_permission() {
        return true; // Public read access by default
    }
    
    /**
     * Check if the current user has write permission
     *
     * @return bool
     */
    public function check_write_permission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Check if the current user has admin permission
     *
     * @return bool
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get endpoint information for API discovery
     *
     * @return array
     */
    public function get_endpoint_info() {
        return [
            'base' => $this->base,
            'namespace' => $this->namespace,
            'class' => get_class($this)
        ];
    }
    
    /**
     * Prepare a response with common metadata
     *
     * @param mixed $data
     * @param int $status
     * @return \WP_REST_Response
     */
    protected function prepare_response($data, $status = 200) {
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => time()
        ];
        
        return rest_ensure_response($response, $status);
    }
    
    /**
     * Prepare an error response
     *
     * @param string $code
     * @param string $message
     * @param int $status
     * @return \WP_Error
     */
    protected function prepare_error($code, $message, $status = 400) {
        return new \WP_Error($code, $message, ['status' => $status]);
    }
}