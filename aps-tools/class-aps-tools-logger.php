<?php
/**
 * Centralized logger for APS Tools
 *
 * @package APS_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APS_Tools_Logger {

    private $debug_mode;

    public function __construct() {
        $this->debug_mode = get_option( 'aps_debug_mode', false );
    }

    /**
     * Log a debug message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context.
     */
    public function debug( $message, $context = [] ) {
        if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || $this->debug_mode ) {
            $this->log( 'debug', $message, $context );
        }
    }

    /**
     * Log an info message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context.
     */
    public function info( $message, $context = [] ) {
        $this->log( 'info', $message, $context );
    }

    /**
     * Log a warning message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context.
     */
    public function warning( $message, $context = [] ) {
        $this->log( 'warning', $message, $context );
    }

    /**
     * Log an error message.
     *
     * @param string $message The message to log.
     * @param array  $context Optional context.
     */
    public function error( $message, $context = [] ) {
        $this->log( 'error', $message, $context );
    }

    /**
     * The main logging method.
     *
     * @param string $level The log level.
     * @param string $message The message to log.
     * @param array  $context Optional context.
     */
    private function log( $level, $message, $context = [] ) {
        $log_entry = sprintf(
            "[%s] [%s] %s",
            gmdate( 'Y-m-d H:i:s' ),
            strtoupper( $level ),
            $message
        );

        if ( ! empty( $context ) ) {
            $log_entry .= ' | Context: ' . wp_json_encode( $context, JSON_PRETTY_PRINT );
        }

        error_log( $log_entry );
    }
}
