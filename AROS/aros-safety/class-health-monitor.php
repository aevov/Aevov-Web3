<?php
/**
 * Health Monitoring System for AROS
 *
 * CRITICAL SAFETY SYSTEM - Monitors system health and triggers safety responses
 * Checks hardware, software, network, and operational status
 *
 * @package AROS
 * @subpackage Safety
 * @since 1.0.0
 */

namespace AROS\Safety;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HealthMonitor
 *
 * Comprehensive health monitoring including:
 * - CPU temperature and usage
 * - Memory usage
 * - Battery/power status
 * - Motor health and temperatures
 * - Sensor status
 * - Network connectivity
 * - Software errors and warnings
 */
class HealthMonitor {

    /**
     * Health status levels
     */
    const STATUS_HEALTHY = 'healthy';
    const STATUS_WARNING = 'warning';
    const STATUS_CRITICAL = 'critical';
    const STATUS_FAILURE = 'failure';

    /**
     * @var array Health check history
     */
    private $health_history = [];

    /**
     * @var array Threshold configuration
     */
    private $thresholds = [];

    /**
     * @var int Check frequency (seconds)
     */
    private $check_frequency = 1;

    /**
     * @var Emergency stop instance
     */
    private $emergency_stop = null;

    /**
     * @var int Check counter
     */
    private $check_count = 0;

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct($config = []) {
        $this->check_frequency = $config['check_frequency'] ?? 1;
        $this->init_thresholds($config['thresholds'] ?? []);
        $this->emergency_stop = apply_filters('aros_get_emergency_stop', null);

        // Register health check cron
        if (!wp_next_scheduled('aros_health_check')) {
            wp_schedule_event(time(), 'aros_health_interval', 'aros_health_check');
        }

        add_action('aros_health_check', [$this, 'perform_scheduled_check']);
    }

    /**
     * Initialize health thresholds
     *
     * @param array $custom_thresholds Custom threshold overrides
     */
    private function init_thresholds($custom_thresholds) {
        $defaults = [
            'cpu_temp_warning' => 70,      // °C
            'cpu_temp_critical' => 85,
            'cpu_usage_warning' => 80,      // %
            'cpu_usage_critical' => 95,
            'memory_usage_warning' => 85,   // %
            'memory_usage_critical' => 95,
            'battery_warning' => 20,        // %
            'battery_critical' => 10,
            'motor_temp_warning' => 60,     // °C
            'motor_temp_critical' => 80,
            'disk_usage_warning' => 85,     // %
            'disk_usage_critical' => 95,
            'network_latency_warning' => 1000,  // ms
            'network_latency_critical' => 5000,
        ];

        $this->thresholds = array_merge($defaults, $custom_thresholds);
    }

    /**
     * Check system health
     *
     * @return array Health status with details
     */
    public function check_health() {
        $this->check_count++;
        $health = [
            'status' => self::STATUS_HEALTHY,
            'timestamp' => current_time('mysql'),
            'checks' => [],
            'warnings' => [],
            'errors' => [],
        ];

        try {
            // Check CPU
            $cpu_health = $this->check_cpu();
            $health['checks']['cpu'] = $cpu_health;
            if ($cpu_health['status'] !== self::STATUS_HEALTHY) {
                $health['warnings'][] = $cpu_health;
                if ($cpu_health['status'] === self::STATUS_CRITICAL) {
                    $health['status'] = self::STATUS_CRITICAL;
                }
            }

            // Check memory
            $memory_health = $this->check_memory();
            $health['checks']['memory'] = $memory_health;
            if ($memory_health['status'] !== self::STATUS_HEALTHY) {
                $health['warnings'][] = $memory_health;
                if ($memory_health['status'] === self::STATUS_CRITICAL) {
                    $health['status'] = self::STATUS_CRITICAL;
                }
            }

            // Check battery/power
            $power_health = $this->check_power();
            $health['checks']['power'] = $power_health;
            if ($power_health['status'] !== self::STATUS_HEALTHY) {
                $health['warnings'][] = $power_health;
                if ($power_health['status'] === self::STATUS_CRITICAL) {
                    $health['status'] = self::STATUS_CRITICAL;
                }
            }

            // Check motors
            $motor_health = $this->check_motors();
            $health['checks']['motors'] = $motor_health;
            if ($motor_health['status'] !== self::STATUS_HEALTHY) {
                $health['warnings'][] = $motor_health;
                if ($motor_health['status'] === self::STATUS_CRITICAL) {
                    $health['status'] = self::STATUS_CRITICAL;
                }
            }

            // Check sensors
            $sensor_health = $this->check_sensors();
            $health['checks']['sensors'] = $sensor_health;
            if ($sensor_health['status'] !== self::STATUS_HEALTHY) {
                $health['warnings'][] = $sensor_health;
            }

            // Check network
            $network_health = $this->check_network();
            $health['checks']['network'] = $network_health;
            if ($network_health['status'] !== self::STATUS_HEALTHY) {
                $health['warnings'][] = $network_health;
            }

            // Check disk
            $disk_health = $this->check_disk();
            $health['checks']['disk'] = $disk_health;
            if ($disk_health['status'] !== self::STATUS_HEALTHY) {
                $health['warnings'][] = $disk_health;
            }

            // Store in history
            $this->add_to_history($health);

            // Handle critical status
            if ($health['status'] === self::STATUS_CRITICAL) {
                $this->handle_critical_health($health);
            }

            // Fire action
            do_action('aros_health_check_complete', $health);

            return $health;

        } catch (\Exception $e) {
            return [
                'status' => self::STATUS_FAILURE,
                'error' => $e->getMessage(),
                'timestamp' => current_time('mysql'),
            ];
        }
    }

    /**
     * Check CPU health
     *
     * @return array CPU health status
     */
    private function check_cpu() {
        $health = [
            'component' => 'CPU',
            'status' => self::STATUS_HEALTHY,
            'metrics' => [],
        ];

        // Check CPU temperature
        $cpu_temp = $this->get_cpu_temperature();
        if ($cpu_temp !== null) {
            $health['metrics']['temperature'] = $cpu_temp;

            if ($cpu_temp >= $this->thresholds['cpu_temp_critical']) {
                $health['status'] = self::STATUS_CRITICAL;
                $health['message'] = "CPU temperature critical: {$cpu_temp}°C";
            } elseif ($cpu_temp >= $this->thresholds['cpu_temp_warning']) {
                $health['status'] = self::STATUS_WARNING;
                $health['message'] = "CPU temperature high: {$cpu_temp}°C";
            }
        }

        // Check CPU usage
        $cpu_usage = $this->get_cpu_usage();
        if ($cpu_usage !== null) {
            $health['metrics']['usage'] = $cpu_usage;

            if ($cpu_usage >= $this->thresholds['cpu_usage_critical']) {
                $health['status'] = self::STATUS_CRITICAL;
                $health['message'] = "CPU usage critical: {$cpu_usage}%";
            } elseif ($cpu_usage >= $this->thresholds['cpu_usage_warning']) {
                if ($health['status'] === self::STATUS_HEALTHY) {
                    $health['status'] = self::STATUS_WARNING;
                    $health['message'] = "CPU usage high: {$cpu_usage}%";
                }
            }
        }

        return $health;
    }

    /**
     * Get CPU temperature
     *
     * @return float|null Temperature in °C
     */
    private function get_cpu_temperature() {
        // Try thermal zone (Linux)
        $thermal_files = glob('/sys/class/thermal/thermal_zone*/temp');
        if (!empty($thermal_files)) {
            $temp_raw = @file_get_contents($thermal_files[0]);
            if ($temp_raw !== false) {
                return (float)$temp_raw / 1000; // Convert from millidegrees
            }
        }

        // Try Raspberry Pi vcgencmd
        if (function_exists('exec')) {
            exec('vcgencmd measure_temp 2>/dev/null', $output, $return_var);
            if ($return_var === 0 && !empty($output)) {
                if (preg_match('/temp=([\d.]+)/', $output[0], $matches)) {
                    return (float)$matches[1];
                }
            }
        }

        // Hook for custom temperature reading
        return apply_filters('aros_get_cpu_temperature', null);
    }

    /**
     * Get CPU usage percentage
     *
     * @return float|null CPU usage %
     */
    private function get_cpu_usage() {
        // Try reading /proc/stat
        $stat1 = @file_get_contents('/proc/stat');
        if ($stat1 !== false) {
            usleep(100000); // 100ms delay
            $stat2 = @file_get_contents('/proc/stat');

            if ($stat2 !== false) {
                $info1 = $this->parse_cpu_stat($stat1);
                $info2 = $this->parse_cpu_stat($stat2);

                $diff_total = $info2['total'] - $info1['total'];
                $diff_idle = $info2['idle'] - $info1['idle'];

                if ($diff_total > 0) {
                    return (1 - $diff_idle / $diff_total) * 100;
                }
            }
        }

        return apply_filters('aros_get_cpu_usage', null);
    }

    /**
     * Parse /proc/stat CPU line
     *
     * @param string $stat_content Contents of /proc/stat
     * @return array Parsed CPU stats
     */
    private function parse_cpu_stat($stat_content) {
        $lines = explode("\n", $stat_content);
        $cpu_line = $lines[0];
        $values = preg_split('/\s+/', $cpu_line);

        $user = (int)($values[1] ?? 0);
        $nice = (int)($values[2] ?? 0);
        $system = (int)($values[3] ?? 0);
        $idle = (int)($values[4] ?? 0);
        $iowait = (int)($values[5] ?? 0);

        return [
            'total' => $user + $nice + $system + $idle + $iowait,
            'idle' => $idle + $iowait,
        ];
    }

    /**
     * Check memory health
     *
     * @return array Memory health status
     */
    private function check_memory() {
        $health = [
            'component' => 'Memory',
            'status' => self::STATUS_HEALTHY,
            'metrics' => [],
        ];

        $mem_info = $this->get_memory_info();

        if ($mem_info) {
            $usage_percent = (1 - $mem_info['available'] / $mem_info['total']) * 100;
            $health['metrics'] = $mem_info;
            $health['metrics']['usage_percent'] = $usage_percent;

            if ($usage_percent >= $this->thresholds['memory_usage_critical']) {
                $health['status'] = self::STATUS_CRITICAL;
                $health['message'] = sprintf("Memory usage critical: %.1f%%", $usage_percent);
            } elseif ($usage_percent >= $this->thresholds['memory_usage_warning']) {
                $health['status'] = self::STATUS_WARNING;
                $health['message'] = sprintf("Memory usage high: %.1f%%", $usage_percent);
            }
        }

        return $health;
    }

    /**
     * Get memory information
     *
     * @return array|null Memory info
     */
    private function get_memory_info() {
        $meminfo = @file_get_contents('/proc/meminfo');
        if ($meminfo === false) {
            return null;
        }

        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);

        if (empty($total) || empty($available)) {
            return null;
        }

        return [
            'total' => (int)$total[1] * 1024, // Convert to bytes
            'available' => (int)$available[1] * 1024,
        ];
    }

    /**
     * Check power/battery health
     *
     * @return array Power health status
     */
    private function check_power() {
        $health = [
            'component' => 'Power',
            'status' => self::STATUS_HEALTHY,
            'metrics' => [],
        ];

        $battery_level = apply_filters('aros_get_battery_level', null);

        if ($battery_level !== null) {
            $health['metrics']['battery_percent'] = $battery_level;

            if ($battery_level <= $this->thresholds['battery_critical']) {
                $health['status'] = self::STATUS_CRITICAL;
                $health['message'] = "Battery critical: {$battery_level}%";
            } elseif ($battery_level <= $this->thresholds['battery_warning']) {
                $health['status'] = self::STATUS_WARNING;
                $health['message'] = "Battery low: {$battery_level}%";
            }
        }

        return $health;
    }

    /**
     * Check motor health
     *
     * @return array Motor health status
     */
    private function check_motors() {
        $health = [
            'component' => 'Motors',
            'status' => self::STATUS_HEALTHY,
            'metrics' => [],
        ];

        $motor_controller = apply_filters('aros_get_motor_controller', null);

        if ($motor_controller && method_exists($motor_controller, 'get_all_motor_status')) {
            $motor_status = $motor_controller->get_all_motor_status();

            foreach ($motor_status as $motor_id => $status) {
                $health['metrics'][$motor_id] = $status;

                // Check motor temperature
                if (isset($status['temperature'])) {
                    $temp = $status['temperature'];

                    if ($temp >= $this->thresholds['motor_temp_critical']) {
                        $health['status'] = self::STATUS_CRITICAL;
                        $health['message'] = "Motor {$motor_id} temperature critical: {$temp}°C";
                    } elseif ($temp >= $this->thresholds['motor_temp_warning']) {
                        if ($health['status'] === self::STATUS_HEALTHY) {
                            $health['status'] = self::STATUS_WARNING;
                            $health['message'] = "Motor {$motor_id} temperature high: {$temp}°C";
                        }
                    }
                }

                // Check for errors
                if (isset($status['error']) && $status['error']) {
                    $health['status'] = self::STATUS_CRITICAL;
                    $health['message'] = "Motor {$motor_id} error: " . ($status['error_message'] ?? 'Unknown');
                }
            }
        }

        return $health;
    }

    /**
     * Check sensor health
     *
     * @return array Sensor health status
     */
    private function check_sensors() {
        $health = [
            'component' => 'Sensors',
            'status' => self::STATUS_HEALTHY,
            'metrics' => [],
        ];

        $sensor_status = apply_filters('aros_get_sensor_status', []);

        foreach ($sensor_status as $sensor_id => $status) {
            $health['metrics'][$sensor_id] = $status;

            if (isset($status['online']) && !$status['online']) {
                $health['status'] = self::STATUS_WARNING;
                $health['message'] = "Sensor {$sensor_id} offline";
            }
        }

        return $health;
    }

    /**
     * Check network health
     *
     * @return array Network health status
     */
    private function check_network() {
        $health = [
            'component' => 'Network',
            'status' => self::STATUS_HEALTHY,
            'metrics' => [],
        ];

        // Check internet connectivity
        $ping_time = $this->ping_host('8.8.8.8');

        if ($ping_time === false) {
            $health['status'] = self::STATUS_WARNING;
            $health['message'] = 'Network unreachable';
        } else {
            $health['metrics']['ping_ms'] = $ping_time;

            if ($ping_time >= $this->thresholds['network_latency_critical']) {
                $health['status'] = self::STATUS_WARNING;
                $health['message'] = "Network latency high: {$ping_time}ms";
            }
        }

        return $health;
    }

    /**
     * Ping a host
     *
     * @param string $host Host to ping
     * @return float|bool Ping time in ms or false
     */
    private function ping_host($host) {
        if (!function_exists('exec')) {
            return null;
        }

        $start = microtime(true);
        exec("ping -c 1 -W 1 {$host} 2>&1", $output, $return_var);
        $time = (microtime(true) - $start) * 1000;

        return $return_var === 0 ? $time : false;
    }

    /**
     * Check disk health
     *
     * @return array Disk health status
     */
    private function check_disk() {
        $health = [
            'component' => 'Disk',
            'status' => self::STATUS_HEALTHY,
            'metrics' => [],
        ];

        $disk_total = disk_total_space('/');
        $disk_free = disk_free_space('/');

        if ($disk_total && $disk_free) {
            $usage_percent = (1 - $disk_free / $disk_total) * 100;

            $health['metrics'] = [
                'total_gb' => $disk_total / 1073741824,
                'free_gb' => $disk_free / 1073741824,
                'usage_percent' => $usage_percent,
            ];

            if ($usage_percent >= $this->thresholds['disk_usage_critical']) {
                $health['status'] = self::STATUS_CRITICAL;
                $health['message'] = sprintf("Disk usage critical: %.1f%%", $usage_percent);
            } elseif ($usage_percent >= $this->thresholds['disk_usage_warning']) {
                $health['status'] = self::STATUS_WARNING;
                $health['message'] = sprintf("Disk usage high: %.1f%%", $usage_percent);
            }
        }

        return $health;
    }

    /**
     * Handle critical health status
     *
     * @param array $health Health status
     */
    private function handle_critical_health($health) {
        error_log('[AROS Health Monitor] CRITICAL health status: ' . json_encode($health));

        // Trigger emergency stop if configured
        $auto_estop = apply_filters('aros_health_auto_estop', true);

        if ($auto_estop && $this->emergency_stop) {
            $this->emergency_stop->trigger(
                'Critical system health detected',
                'health',
                ['health' => $health]
            );
        }

        // Fire action
        do_action('aros_critical_health', $health);
    }

    /**
     * Add health check to history
     *
     * @param array $health Health status
     */
    private function add_to_history($health) {
        array_unshift($this->health_history, $health);
        $this->health_history = array_slice($this->health_history, 0, 100);
    }

    /**
     * Get health history
     *
     * @param int $limit Number of records
     * @return array Health history
     */
    public function get_history($limit = 10) {
        return array_slice($this->health_history, 0, $limit);
    }

    /**
     * Perform scheduled health check
     */
    public function perform_scheduled_check() {
        $health = $this->check_health();

        // Store in database
        update_option('aros_last_health_check', $health, false);
    }

    /**
     * Get last health check
     *
     * @return array Last health status
     */
    public function get_last_health() {
        return get_option('aros_last_health_check', ['status' => 'unknown']);
    }
}
