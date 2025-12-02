# Aevov Monitoring Setup

Comprehensive monitoring configuration for the Aevov ecosystem, including error tracking and performance metrics.

## Overview

The Aevov monitoring system provides two main components:

1. **Error Tracking** - Capture and report errors, exceptions, and warnings
2. **Performance Metrics** - Track execution time, memory usage, and custom metrics

## Supported Services

### Error Tracking
- **Sentry** - Modern error tracking and performance monitoring
- **Rollbar** - Real-time error monitoring
- **Bugsnag** - Stability monitoring
- **Custom** - Your own API endpoint

### Performance Metrics
- **New Relic** - Application performance monitoring
- **Datadog** - Infrastructure and application monitoring
- **Prometheus** - Open-source monitoring system
- **Custom** - Your own metrics collector

## Installation

### 1. Install Dependencies

```bash
# For Sentry
composer require sentry/sentry

# For Rollbar
composer require rollbar/rollbar

# For Bugsnag
composer require bugsnag/bugsnag

# For Datadog (PHP extension)
# Follow: https://docs.datadoghq.com/tracing/setup_overview/setup/php/
```

### 2. Include Monitoring Files

Add to your main plugin file or `wp-config.php`:

```php
// Define start time for performance tracking
define('AEVOV_START_TIME', microtime(true));

// Include monitoring classes
require_once __DIR__ . '/.monitoring/error-tracking.php';
require_once __DIR__ . '/.monitoring/performance-metrics.php';

use Aevov\Monitoring\Error_Tracking;
use Aevov\Monitoring\Performance_Metrics;
```

## Configuration

### Error Tracking with Sentry

```php
// Initialize Sentry
Error_Tracking::instance()->init('sentry', [
    'dsn' => 'https://your-key@o12345.ingest.sentry.io/67890',
    'environment' => defined('WP_ENV') ? WP_ENV : 'production',
    'release' => 'aevov@1.0.0',
    'traces_sample_rate' => 0.2, // Sample 20% of transactions
]);
```

**Get Your Sentry DSN:**
1. Sign up at https://sentry.io
2. Create a new PHP project
3. Copy the DSN from the project settings

### Error Tracking with Rollbar

```php
// Initialize Rollbar
Error_Tracking::instance()->init('rollbar', [
    'access_token' => 'your_rollbar_access_token',
    'environment' => 'production',
]);
```

**Get Your Rollbar Token:**
1. Sign up at https://rollbar.com
2. Create a new project
3. Copy the access token from Project Settings → Project Access Tokens

### Custom Error Tracking

```php
// Initialize custom error tracking
Error_Tracking::instance()->init('custom', [
    'endpoint' => 'https://your-api.com/errors',
    'api_key' => 'your-api-key',
]);

// Or use WordPress hooks
add_action('aevov_log_error', function($error_data) {
    // Send to your custom system
    wp_mail('admin@example.com', 'Aevov Error', print_r($error_data, true));
});
```

### Performance Metrics with New Relic

```php
// Initialize New Relic (requires New Relic PHP extension)
Performance_Metrics::instance()->init([
    'service' => 'new_relic',
    'enabled' => true,
]);
```

**Setup New Relic:**
1. Sign up at https://newrelic.com
2. Install the New Relic PHP agent on your server
3. No additional configuration needed - the monitoring code will auto-detect

### Performance Metrics with Datadog

```php
// Initialize Datadog
Performance_Metrics::instance()->init([
    'service' => 'datadog',
    'datadog_host' => 'localhost',
    'datadog_port' => 8125,
]);
```

**Setup Datadog:**
1. Sign up at https://www.datadoghq.com
2. Install the Datadog agent on your server
3. Install the DD Trace PHP extension

### Custom Performance Metrics

```php
// Initialize custom metrics
Performance_Metrics::instance()->init([
    'service' => 'custom',
    'endpoint' => 'https://your-metrics-api.com/metrics',
    'api_key' => 'your-api-key',
]);

// Or use WordPress hooks
add_action('aevov_flush_metrics', function($metrics) {
    // Process metrics your way
    error_log('Metrics: ' . json_encode($metrics));
});
```

## Usage

### Tracking Custom Metrics

```php
// Time a code block
aevov_start_timer('data_processing');

// Your code here
process_large_dataset();

$duration = aevov_stop_timer('data_processing');
echo "Processing took {$duration} seconds";

// Record custom metrics
aevov_record_metric('active_users', get_active_user_count());
Performance_Metrics::instance()->increment('api_calls');
Performance_Metrics::instance()->gauge('queue_size', get_queue_size());
```

### Manual Error Reporting

```php
// The error tracking is automatic, but you can also manually report
try {
    risky_operation();
} catch (Exception $e) {
    // Errors are automatically caught, but you can add context
    do_action('aevov_log_error', [
        'type' => 'custom_error',
        'message' => $e->getMessage(),
        'context' => 'User attempted to delete protected data',
    ]);
}
```

## Environment Variables

You can configure monitoring via environment variables:

```bash
# Error Tracking
AEVOV_ERROR_TRACKING_SERVICE=sentry
AEVOV_SENTRY_DSN=https://your-key@sentry.io/project
AEVOV_ROLLBAR_TOKEN=your-rollbar-token

# Performance Metrics
AEVOV_METRICS_SERVICE=datadog
AEVOV_DATADOG_HOST=localhost
AEVOV_DATADOG_PORT=8125

# Sampling
AEVOV_METRICS_SAMPLE_RATE=0.1  # Sample 10% of requests
```

Then in your code:

```php
$config = [
    'service' => getenv('AEVOV_ERROR_TRACKING_SERVICE') ?: 'sentry',
    'dsn' => getenv('AEVOV_SENTRY_DSN'),
];
Error_Tracking::instance()->init($config['service'], $config);
```

## WordPress Integration

### wp-config.php Setup

Add to `wp-config.php` before `require_once(ABSPATH . 'wp-settings.php');`:

```php
// Define start time
define('AEVOV_START_TIME', microtime(true));

// Load monitoring
if (file_exists(__DIR__ . '/.monitoring/error-tracking.php')) {
    require_once __DIR__ . '/.monitoring/error-tracking.php';
    require_once __DIR__ . '/.monitoring/performance-metrics.php';

    // Initialize error tracking
    \Aevov\Monitoring\Error_Tracking::instance()->init('sentry', [
        'dsn' => getenv('SENTRY_DSN'),
        'environment' => defined('WP_ENV') ? WP_ENV : 'production',
    ]);

    // Initialize performance metrics
    \Aevov\Monitoring\Performance_Metrics::instance()->init([
        'service' => 'datadog',
        'datadog_host' => 'localhost',
    ]);
}
```

## Docker Integration

If using the Docker development environment, add to `docker/wordpress.env`:

```bash
# Monitoring
AEVOV_ERROR_TRACKING_SERVICE=sentry
AEVOV_SENTRY_DSN=https://your-key@sentry.io/project
AEVOV_METRICS_SERVICE=datadog
AEVOV_DATADOG_HOST=datadog
```

And add the Datadog agent to `docker-compose.yml`:

```yaml
datadog:
  image: datadog/agent:latest
  environment:
    - DD_API_KEY=your_datadog_api_key
    - DD_SITE=datadoghq.com
  volumes:
    - /var/run/docker.sock:/var/run/docker.sock:ro
    - /proc/:/host/proc/:ro
    - /sys/fs/cgroup/:/host/sys/fs/cgroup:ro
```

## Best Practices

1. **Development vs Production**
   - Use different projects/tokens for dev and production
   - Sample fewer requests in production (10-20%)
   - Enable debug logging in development only

2. **Performance**
   - Keep metric collection lightweight
   - Use sampling for high-traffic sites
   - Send metrics asynchronously when possible

3. **Privacy**
   - Don't log sensitive user data (passwords, API keys, etc.)
   - Anonymize personal information
   - Follow GDPR requirements

4. **Alerting**
   - Set up alerts in your monitoring service
   - Monitor error rates, response times, and memory usage
   - Create runbooks for common issues

## Troubleshooting

### Errors Not Being Reported

1. Check that error tracking is initialized
2. Verify your DSN/token is correct
3. Check that errors are from Aevov plugins (the handler filters by path)
4. Look for errors in PHP error log

### Metrics Not Appearing

1. Verify the metrics service is running (Datadog agent, New Relic daemon, etc.)
2. Check network connectivity to the service
3. Verify sample rate isn't filtering all requests
4. Look for errors in service logs

### High Overhead

1. Reduce sample rate
2. Disable in development environments
3. Use lightweight metrics (counters instead of timings)
4. Batch metrics before sending

## Support

- Sentry Documentation: https://docs.sentry.io/platforms/php/
- Rollbar Documentation: https://docs.rollbar.com/docs/php
- New Relic Documentation: https://docs.newrelic.com/docs/apm/agents/php-agent/
- Datadog Documentation: https://docs.datadoghq.com/tracing/setup_overview/setup/php/

## License

This monitoring setup is part of the Aevov ecosystem and follows the same license.
