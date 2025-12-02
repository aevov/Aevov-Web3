<?php
/**
 * Error Tracking Integration for Aevov Ecosystem
 *
 * Provides integration points for error tracking services like Sentry, Rollbar, etc.
 *
 * @package AevovMonitoring
 * @since 1.0.0
 */

namespace Aevov\Monitoring;

/**
 * Error Tracking Handler
 *
 * Centralizes error handling and reporting for all Aevov plugins
 */
class Error_Tracking {

    /**
     * Error tracking service (sentry, rollbar, bugsnag, etc.)
     */
    private $service = null;

    /**
     * Configuration
     */
    private $config = [];

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize error tracking
     *
     * @param string $service Service name (sentry, rollbar, bugsnag, custom)
     * @param array $config Service configuration
     */
    public function init($service, $config = []) {
        $this->service = $service;
        $this->config = $config;

        // Register error handlers
        $this->register_handlers();

        // Initialize service-specific SDK
        switch ($service) {
            case 'sentry':
                $this->init_sentry();
                break;
            case 'rollbar':
                $this->init_rollbar();
                break;
            case 'bugsnag':
                $this->init_bugsnag();
                break;
            case 'custom':
                $this->init_custom();
                break;
        }
    }

    /**
     * Register PHP error handlers
     */
    private function register_handlers() {
        // Exception handler
        set_exception_handler([$this, 'handle_exception']);

        // Error handler
        set_error_handler([$this, 'handle_error']);

        // Shutdown handler for fatal errors
        register_shutdown_function([$this, 'handle_shutdown']);

        // WordPress-specific hooks
        if (function_exists('add_action')) {
            add_action('wp_die_handler', [$this, 'handle_wp_die']);
            add_action('doing_it_wrong_trigger_error', [$this, 'handle_doing_it_wrong'], 10, 4);
        }
    }

    /**
     * Handle exceptions
     */
    public function handle_exception($exception) {
        $this->log_error([
            'type' => 'exception',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'severity' => 'error',
        ]);

        // Re-throw in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            throw $exception;
        }
    }

    /**
     * Handle PHP errors
     */
    public function handle_error($errno, $errstr, $errfile, $errline) {
        // Map error number to severity
        $severity_map = [
            E_ERROR => 'error',
            E_WARNING => 'warning',
            E_PARSE => 'error',
            E_NOTICE => 'info',
            E_CORE_ERROR => 'error',
            E_CORE_WARNING => 'warning',
            E_COMPILE_ERROR => 'error',
            E_COMPILE_WARNING => 'warning',
            E_USER_ERROR => 'error',
            E_USER_WARNING => 'warning',
            E_USER_NOTICE => 'info',
            E_STRICT => 'info',
            E_RECOVERABLE_ERROR => 'error',
            E_DEPRECATED => 'warning',
            E_USER_DEPRECATED => 'warning',
        ];

        $severity = $severity_map[$errno] ?? 'info';

        // Only log errors from Aevov plugins
        if (strpos($errfile, 'aevov') === false &&
            strpos($errfile, 'bloom') === false &&
            strpos($errfile, 'aps') === false) {
            return false;
        }

        $this->log_error([
            'type' => 'error',
            'errno' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'severity' => $severity,
        ]);

        // Don't prevent default error handler
        return false;
    }

    /**
     * Handle fatal errors on shutdown
     */
    public function handle_shutdown() {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->log_error([
                'type' => 'fatal',
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'severity' => 'fatal',
            ]);
        }
    }

    /**
     * Handle WordPress wp_die()
     */
    public function handle_wp_die($message) {
        if (is_wp_error($message)) {
            $this->log_error([
                'type' => 'wp_error',
                'message' => $message->get_error_message(),
                'code' => $message->get_error_code(),
                'data' => $message->get_error_data(),
                'severity' => 'error',
            ]);
        }
    }

    /**
     * Handle _doing_it_wrong() calls
     */
    public function handle_doing_it_wrong($function, $message, $version) {
        $this->log_error([
            'type' => 'doing_it_wrong',
            'function' => $function,
            'message' => $message,
            'version' => $version,
            'severity' => 'warning',
        ]);
    }

    /**
     * Log error to tracking service
     */
    private function log_error($error_data) {
        // Add context
        $error_data['timestamp'] = time();
        $error_data['url'] = $_SERVER['REQUEST_URI'] ?? '';
        $error_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $error_data['php_version'] = PHP_VERSION;
        $error_data['wp_version'] = get_bloginfo('version');

        // Add user context if available
        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user->ID) {
                $error_data['user'] = [
                    'id' => $user->ID,
                    'email' => $user->user_email,
                    'role' => implode(',', $user->roles),
                ];
            }
        }

        // Send to service
        switch ($this->service) {
            case 'sentry':
                $this->send_to_sentry($error_data);
                break;
            case 'rollbar':
                $this->send_to_rollbar($error_data);
                break;
            case 'custom':
                $this->send_to_custom($error_data);
                break;
        }

        // Log locally in development
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[Aevov] ' . json_encode($error_data));
        }
    }

    /**
     * Initialize Sentry SDK
     *
     * Requires: composer require sentry/sentry
     */
    private function init_sentry() {
        if (!class_exists('\Sentry\init')) {
            return;
        }

        \Sentry\init([
            'dsn' => $this->config['dsn'] ?? '',
            'environment' => $this->config['environment'] ?? 'production',
            'release' => $this->config['release'] ?? 'aevov@1.0.0',
            'traces_sample_rate' => $this->config['traces_sample_rate'] ?? 0.2,
        ]);
    }

    /**
     * Send error to Sentry
     */
    private function send_to_sentry($error_data) {
        if (!function_exists('\Sentry\captureMessage')) {
            return;
        }

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($error_data) {
            $scope->setContext('error', $error_data);
        });

        \Sentry\captureMessage($error_data['message'], \Sentry\Severity::error());
    }

    /**
     * Initialize Rollbar SDK
     *
     * Requires: composer require rollbar/rollbar
     */
    private function init_rollbar() {
        if (!class_exists('\Rollbar\Rollbar')) {
            return;
        }

        \Rollbar\Rollbar::init([
            'access_token' => $this->config['access_token'] ?? '',
            'environment' => $this->config['environment'] ?? 'production',
        ]);
    }

    /**
     * Send error to Rollbar
     */
    private function send_to_rollbar($error_data) {
        if (!class_exists('\Rollbar\Rollbar')) {
            return;
        }

        \Rollbar\Rollbar::log($error_data['severity'], $error_data['message'], $error_data);
    }

    /**
     * Initialize custom error tracking
     */
    private function init_custom() {
        // Hook for custom implementation
        do_action('aevov_error_tracking_init', $this->config);
    }

    /**
     * Send to custom endpoint
     */
    private function send_to_custom($error_data) {
        // Hook for custom implementation
        do_action('aevov_log_error', $error_data);

        // Example: Send to custom API endpoint
        if (isset($this->config['endpoint'])) {
            wp_remote_post($this->config['endpoint'], [
                'body' => json_encode($error_data),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $this->config['api_key'] ?? '',
                ],
            ]);
        }
    }
}

// ============================================================================
// Usage Examples
// ============================================================================

/*
// Initialize with Sentry
Error_Tracking::instance()->init('sentry', [
    'dsn' => 'https://your-dsn@sentry.io/project-id',
    'environment' => 'production',
    'release' => 'aevov@1.0.0',
]);

// Initialize with Rollbar
Error_Tracking::instance()->init('rollbar', [
    'access_token' => 'your-rollbar-token',
    'environment' => 'production',
]);

// Initialize with custom endpoint
Error_Tracking::instance()->init('custom', [
    'endpoint' => 'https://your-api.com/errors',
    'api_key' => 'your-api-key',
]);
*/
