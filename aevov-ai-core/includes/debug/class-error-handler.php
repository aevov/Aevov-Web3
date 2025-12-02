<?php
/**
 * Error Handler
 *
 * Handles PHP errors and exceptions with stack traces
 *
 * @package AevovAICore
 */

namespace Aevov\AICore\Debug;

/**
 * Error Handler Class
 */
class ErrorHandler
{
    /**
     * Debug engine
     *
     * @var DebugEngine
     */
    private DebugEngine $debug_engine;

    /**
     * Registered
     *
     * @var bool
     */
    private bool $registered = false;

    /**
     * Constructor
     *
     * @param DebugEngine $debug_engine Debug engine
     */
    public function __construct(DebugEngine $debug_engine)
    {
        $this->debug_engine = $debug_engine;
    }

    /**
     * Register error handlers
     *
     * @return void
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        set_error_handler([$this, 'handle_error']);
        set_exception_handler([$this, 'handle_exception']);
        register_shutdown_function([$this, 'handle_shutdown']);

        $this->registered = true;
    }

    /**
     * Handle PHP error
     *
     * @param int $errno Error number
     * @param string $errstr Error string
     * @param string $errfile Error file
     * @param int $errline Error line
     * @return bool
     */
    public function handle_error(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        $level = $this->get_error_level($errno);

        $this->debug_engine->log($level, 'PHP', $errstr, [
            'file' => $errfile,
            'line' => $errline,
            'errno' => $errno,
            'stack_trace' => $this->get_stack_trace()
        ]);

        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle exception
     *
     * @param \Throwable $exception Exception
     * @return void
     */
    public function handle_exception(\Throwable $exception): void
    {
        $this->debug_engine->log('critical', 'Exception', $exception->getMessage(), [
            'class' => get_class($exception),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Handle shutdown
     *
     * @return void
     */
    public function handle_shutdown(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->debug_engine->log('critical', 'PHP Fatal', $error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ]);
        }
    }

    /**
     * Get error level
     *
     * @param int $errno Error number
     * @return string
     */
    private function get_error_level(int $errno): string
    {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'error';

            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'warning';

            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'info';

            default:
                return 'debug';
        }
    }

    /**
     * Get stack trace
     *
     * @return string
     */
    private function get_stack_trace(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        // Remove error handler frames
        $trace = array_slice($trace, 2);

        $output = [];

        foreach ($trace as $i => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? 0;
            $function = $frame['function'] ?? '';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';

            if ($class) {
                $output[] = "#{$i} {$file}({$line}): {$class}{$type}{$function}()";
            } else {
                $output[] = "#{$i} {$file}({$line}): {$function}()";
            }
        }

        return implode("\n", $output);
    }

    /**
     * Log custom error
     *
     * @param string $message Error message
     * @param array $context Context
     * @return void
     */
    public function log_error(string $message, array $context = []): void
    {
        $context['stack_trace'] = $this->get_stack_trace();

        $this->debug_engine->log('error', 'Custom', $message, $context);
    }

    /**
     * Is registered
     *
     * @return bool
     */
    public function is_registered(): bool
    {
        return $this->registered;
    }
}
