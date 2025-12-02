<?php
/**
 * Bandwidth Manager
 *
 * Manages bandwidth allocation and monitoring for mesh operations.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Relay;

/**
 * Bandwidth Manager Class
 */
class BandwidthManager
{
    /**
     * Allocated bandwidth for relay (bytes/sec)
     *
     * @var int
     */
    private int $relay_bandwidth;

    /**
     * Allocated bandwidth for download (bytes/sec)
     *
     * @var int
     */
    private int $download_bandwidth;

    /**
     * Current usage tracking
     *
     * @var array
     */
    private array $usage = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->relay_bandwidth = (int) get_option('aevov_meshcore_relay_bandwidth', 5 * 1024 * 1024);
        $this->download_bandwidth = (int) get_option('aevov_meshcore_download_bandwidth', 10 * 1024 * 1024);
    }

    /**
     * Allocate bandwidth for operation
     *
     * @param string $operation Operation type (relay, download, upload)
     * @param int $bytes Bytes needed
     * @return bool Success
     */
    public function allocate(string $operation, int $bytes): bool
    {
        $available = $this->get_available_bandwidth($operation);

        if ($bytes > $available) {
            return false;
        }

        if (!isset($this->usage[$operation])) {
            $this->usage[$operation] = 0;
        }

        $this->usage[$operation] += $bytes;

        return true;
    }

    /**
     * Get available bandwidth for operation
     *
     * @param string $operation Operation type
     * @return int Available bytes
     */
    public function get_available_bandwidth(string $operation): int
    {
        $limit = $this->get_limit($operation);
        $used = $this->usage[$operation] ?? 0;

        return max(0, $limit - $used);
    }

    /**
     * Get bandwidth limit for operation
     *
     * @param string $operation Operation type
     * @return int Limit in bytes/sec
     */
    private function get_limit(string $operation): int
    {
        return match ($operation) {
            'relay' => $this->relay_bandwidth,
            'download' => $this->download_bandwidth,
            default => 1024 * 1024 // 1 MB/s default
        };
    }

    /**
     * Reset usage counters
     *
     * @return void
     */
    public function reset_usage(): void
    {
        $this->usage = [];
    }

    /**
     * Get bandwidth statistics
     *
     * @return array
     */
    public function get_stats(): array
    {
        return [
            'relay_bandwidth' => $this->relay_bandwidth,
            'download_bandwidth' => $this->download_bandwidth,
            'current_usage' => $this->usage
        ];
    }
}
