<?php
/**
 * Traffic Randomizer
 *
 * Randomizes traffic patterns to prevent timing analysis and correlation attacks.
 * Makes it impossible to identify AI requests based on:
 * - Request timing
 * - Request size
 * - Response patterns
 * - Burst characteristics
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Stealth;

/**
 * Traffic Randomizer Class
 */
class TrafficRandomizer
{
    /**
     * Request queue for batching
     *
     * @var array
     */
    private array $request_queue = [];

    /**
     * Last request timestamp
     *
     * @var int
     */
    private int $last_request_time = 0;

    /**
     * Minimum delay between requests (ms)
     *
     * @var int
     */
    private int $min_delay = 100;

    /**
     * Maximum delay between requests (ms)
     *
     * @var int
     */
    private int $max_delay = 2000;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->min_delay = (int) get_option('aevov_stealth_min_delay', 100);
        $this->max_delay = (int) get_option('aevov_stealth_max_delay', 2000);
    }

    /**
     * Add jitter to timing
     *
     * @param int $base_delay Base delay in ms
     * @return int Randomized delay
     */
    public function add_jitter(int $base_delay): int
    {
        // Gaussian distribution around base delay
        $stddev = $base_delay * 0.3; // 30% standard deviation
        $jitter = $this->gaussian_random($base_delay, $stddev);

        return max($this->min_delay, min($this->max_delay, (int) $jitter));
    }

    /**
     * Generate Gaussian random number
     *
     * @param float $mean Mean value
     * @param float $stddev Standard deviation
     * @return float Random value
     */
    private function gaussian_random(float $mean, float $stddev): float
    {
        // Box-Muller transform
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();

        $z = sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);

        return $mean + $stddev * $z;
    }

    /**
     * Apply timing randomization before request
     *
     * @return void
     */
    public function randomize_timing(): void
    {
        $now = microtime(true) * 1000; // Current time in ms

        if ($this->last_request_time > 0) {
            $elapsed = $now - $this->last_request_time;
            $target_delay = $this->add_jitter(500); // Target 500ms with jitter

            if ($elapsed < $target_delay) {
                $sleep_time = ($target_delay - $elapsed) * 1000; // Convert to microseconds
                usleep((int) $sleep_time);
            }
        }

        $this->last_request_time = microtime(true) * 1000;
    }

    /**
     * Queue request for batching
     *
     * @param callable $request Request callback
     * @param int $priority Priority (higher = sooner)
     * @return void
     */
    public function queue_request(callable $request, int $priority = 5): void
    {
        $this->request_queue[] = [
            'callback' => $request,
            'priority' => $priority,
            'queued_at' => microtime(true)
        ];

        // Sort by priority
        usort($this->request_queue, function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
    }

    /**
     * Process request queue with randomization
     *
     * @return void
     */
    public function process_queue(): void
    {
        if (empty($this->request_queue)) {
            return;
        }

        // Process in batches of random size
        $batch_size = rand(1, min(5, count($this->request_queue)));

        for ($i = 0; $i < $batch_size; $i++) {
            if (empty($this->request_queue)) {
                break;
            }

            $item = array_shift($this->request_queue);

            // Randomize timing
            $this->randomize_timing();

            // Execute request
            call_user_func($item['callback']);

            // Random delay between items in batch
            if ($i < $batch_size - 1) {
                usleep(rand(50000, 200000)); // 50-200ms
            }
        }
    }

    /**
     * Pad request size to hide actual size
     *
     * @param string $data Request data
     * @param int|null $target_size Target size (null for random)
     * @return string Padded data
     */
    public function pad_request_size(string $data, ?int $target_size = null): string
    {
        $current_size = strlen($data);

        if ($target_size === null) {
            // Random size between current and current * 1.5
            $target_size = rand($current_size, (int) ($current_size * 1.5));
        }

        if ($target_size <= $current_size) {
            return $data;
        }

        // Add random padding
        $padding_size = $target_size - $current_size;
        $padding = base64_encode(random_bytes((int) ceil($padding_size * 0.75)));
        $padding = substr($padding, 0, $padding_size);

        return $data . '|padding:' . $padding;
    }

    /**
     * Remove padding from response
     *
     * @param string $data Padded data
     * @return string Original data
     */
    public function remove_padding(string $data): string
    {
        $parts = explode('|padding:', $data);
        return $parts[0];
    }

    /**
     * Generate cover traffic (decoy requests)
     *
     * @param int $count Number of decoy requests
     * @return void
     */
    public function generate_cover_traffic(int $count = 3): void
    {
        $decoy_endpoints = [
            home_url('/wp-json/wp/v2/posts'),
            home_url('/wp-json/wp/v2/users'),
            home_url('/wp-json/wp/v2/media'),
            home_url('/wp-content/themes/'),
            home_url('/wp-includes/js/'),
        ];

        for ($i = 0; $i < $count; $i++) {
            $endpoint = $decoy_endpoints[array_rand($decoy_endpoints)];

            // Non-blocking request
            wp_remote_get($endpoint, [
                'timeout' => 1,
                'blocking' => false,
                'headers' => [
                    'User-Agent' => 'WordPress/' . get_bloginfo('version')
                ]
            ]);

            // Random delay between decoys
            usleep(rand(100000, 500000)); // 100-500ms
        }
    }

    /**
     * Analyze and randomize request pattern
     *
     * @param array $requests Array of requests
     * @return array Randomized order
     */
    public function randomize_pattern(array $requests): array
    {
        // Shuffle while maintaining some locality
        $chunks = array_chunk($requests, rand(2, 5));
        shuffle($chunks);

        $randomized = [];
        foreach ($chunks as $chunk) {
            shuffle($chunk);
            $randomized = array_merge($randomized, $chunk);
        }

        return $randomized;
    }

    /**
     * Get randomization statistics
     *
     * @return array
     */
    public function get_stats(): array
    {
        return [
            'queue_size' => count($this->request_queue),
            'min_delay' => $this->min_delay,
            'max_delay' => $this->max_delay,
            'last_request' => $this->last_request_time
        ];
    }
}
