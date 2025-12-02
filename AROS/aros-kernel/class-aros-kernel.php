<?php
/**
 * AROS Kernel
 * Core kernel managing all AROS subsystems and runtime environment
 */

namespace AROS\Kernel;

class AROSKernel {

    private $running = false;
    private $subsystems = [];
    private $event_loop;
    private $update_rate = 100; // Hz
    private $last_update = 0;

    public function __construct() {
        $this->update_rate = get_option('aros_update_frequency', 100);
        $this->init_event_loop();
    }

    /**
     * Initialize event loop
     */
    private function init_event_loop() {
        // Register shutdown handler
        register_shutdown_function([$this, 'shutdown']);

        // Set up signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handle_signal']);
            pcntl_signal(SIGINT, [$this, 'handle_signal']);
        }
    }

    /**
     * Start kernel
     */
    public function start() {
        $this->running = true;
        $this->last_update = microtime(true);

        do_action('aros_kernel_started');

        return true;
    }

    /**
     * Stop kernel
     */
    public function stop() {
        $this->running = false;

        do_action('aros_kernel_stopped');

        return true;
    }

    /**
     * Update kernel (called at update_rate frequency)
     */
    public function update() {
        if (!$this->running) {
            return false;
        }

        $current_time = microtime(true);
        $dt = $current_time - $this->last_update;

        // Update all subsystems
        foreach ($this->subsystems as $name => $subsystem) {
            if (method_exists($subsystem, 'update')) {
                $subsystem->update($dt);
            }
        }

        // Trigger kernel update event
        do_action('aros_kernel_update', $dt);

        $this->last_update = $current_time;

        return true;
    }

    /**
     * Register subsystem
     */
    public function register_subsystem($name, $subsystem) {
        $this->subsystems[$name] = $subsystem;

        return true;
    }

    /**
     * Get subsystem
     */
    public function get_subsystem($name) {
        return $this->subsystems[$name] ?? null;
    }

    /**
     * Handle system signals
     */
    public function handle_signal($signal) {
        switch ($signal) {
            case SIGTERM:
            case SIGINT:
                $this->shutdown();
                break;
        }
    }

    /**
     * Graceful shutdown
     */
    public function shutdown() {
        if ($this->running) {
            do_action('aros_kernel_shutdown');

            // Stop all subsystems safely
            foreach ($this->subsystems as $subsystem) {
                if (method_exists($subsystem, 'shutdown')) {
                    $subsystem->shutdown();
                }
            }

            $this->stop();
        }
    }

    /**
     * Get kernel status
     */
    public function get_status() {
        return [
            'running' => $this->running,
            'update_rate' => $this->update_rate,
            'subsystems' => array_keys($this->subsystems),
            'uptime' => time() - get_option('aros_start_time', time()),
        ];
    }

    /**
     * Check if kernel is running
     */
    public function is_running() {
        return $this->running;
    }
}
