# API Reference for Aevov Pattern Recognition System

This section provides detailed documentation for the REST API endpoints exposed by the Aevov Pattern Recognition System plugins. These APIs allow for programmatic interaction with the system, enabling integration with external applications, custom dashboards, and automated workflows.

All API endpoints are accessible via the WordPress REST API, typically under the base URL:

`YOUR_WORDPRESS_SITE_URL/wp-json/aps/v1/`

## Authentication

All API endpoints require authentication. The recommended method for authenticated requests is to use a nonce (for logged-in WordPress users) or API keys (for external applications).

*   **Nonce-based Authentication**: For requests made from within the WordPress admin, a nonce can be generated using `wp_create_nonce('wp_rest')` and passed in the `X-WP-Nonce` header.
*   **API Key Authentication**: For external applications, API keys can be generated and managed via `APS Tools > Settings`. These keys should be passed in the `Authorization` header as a Bearer token (e.g., `Authorization: Bearer YOUR_API_KEY`).

## Error Handling

API responses will include standard HTTP status codes. In case of an error, the response body will typically contain a JSON object with the following structure:

```json
{
    "code": "error_code",
    "message": "A human-readable error message.",
    "data": {
        "status": 4xx_or_5xx_http_status_code
    }
}
```

## Endpoints Overview

The API is organized into several logical groups, each with its own base path:

*   **Admin Endpoints**: `/admin` - For managing plugin settings, database, logs, and system information.
*   **Consensus Mechanism Endpoints**: `/poc/consensus` - For interacting with the Proof of Contribution consensus mechanism, including proposals and voting.
*   **Distributed Ledger Endpoints**: `/ledger` - For querying blockchain blocks, transactions, and managing network nodes.
*   **Metrics Endpoints**: `/metrics` - For retrieving system, pattern, and performance metrics.
*   **Network Endpoints**: `/network` - For managing multisite network topology, sites, and pattern distribution.
*   **Pattern Analysis Endpoints**: `/analysis` - For triggering pattern analysis, feature extraction, and retrieving analysis reports.
*   **Pattern Comparison Endpoints**: `/patterns/comparison` - For comparing patterns and retrieving comparison history.
*   **Pattern Distribution Endpoints**: `/patterns/distribution` - For distributing patterns across the network.
*   **Pattern Endpoints**: `/patterns` - For CRUD operations on patterns.
*   **Proof of Contribution Endpoints**: `/poc` - For interacting with the Proof of Contribution mechanism, including mining and contributor scores.
*   **System Status Endpoints**: `/status` - For detailed system health and status information.

Click on the links below for detailed documentation on each endpoint group.

## Detailed Endpoint Documentation

*   [Admin Endpoints](admin-endpoints.md)
*   [Consensus Mechanism Endpoints](consensus-mechanism-endpoints.md)
*   [Distributed Ledger Endpoints](distributed-ledger-endpoints.md)
*   [Metrics Endpoints](metrics-endpoints.md)
*   [Network Endpoints](network-endpoints.md)
*   [Pattern Analysis Endpoints](pattern-analysis-endpoints.md)
*   [Pattern Comparison Endpoints](pattern-comparison-endpoints.md)
*   [Pattern Distribution Endpoints](pattern-distribution-endpoints.md)
*   [Pattern Endpoints](pattern-endpoints.md)
*   [Proof of Contribution Endpoints](proof-of-contribution-endpoints.md)
*   [System Status Endpoints](system-status-endpoints.md)