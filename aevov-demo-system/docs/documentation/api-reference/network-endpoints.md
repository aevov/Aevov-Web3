# Network Endpoints

**Base Path**: `/network`

These endpoints provide functionalities for managing the multisite network, including topology, site management, pattern distribution, and network synchronization. These endpoints are only available in a WordPress Multisite environment.

---

## 1. Get Network Topology

Retrieves the current topology of the multisite network, including active sites and their relationships.

*   **URL**: `/network/topology`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Response (Success 200 OK)

```json
{
    "success": true,
    "topology": {
        "main_site": {
            "id": 1,
            "url": "http://main.example.com",
            "status": "active"
        },
        "sub_sites": [
            {
                "id": 2,
                "url": "http://site1.example.com",
                "status": "active"
            },
            {
                "id": 3,
                "url": "http://site2.example.com",
                "status": "inactive"
            }
        ],
        "connections": [
            {"from": 1, "to": 2, "type": "parent-child"},
            {"from": 1, "to": 3, "type": "parent-child"}
        ]
    },
    "timestamp": 1678886400
}
```

### Response (Error 400 Bad Request)

```json
{
    "code": "multisite_required",
    "message": "Network features require multisite installation",
    "data": {
        "status": 400
    }
}
```

---

## 2. Get Network Sites

Retrieves a list of all active sites in the network and their current status.

*   **URL**: `/network/sites`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Response (Success 200 OK)

```json
{
    "success": true,
    "sites": [
        {
            "id": 1,
            "blog_id": 1,
            "domain": "main.example.com",
            "path": "/",
            "site_name": "Main Site",
            "status": "active"
        },
        {
            "id": 2,
            "blog_id": 2,
            "domain": "site1.example.com",
            "path": "/site1/",
            "site_name": "Sub Site 1",
            "status": "active"
        }
    ],
    "total": 2,
    "status": {
        "active_sites": 2,
        "inactive_sites": 0,
        "maintenance_sites": 0
    }
}
```

---

## 3. Update Site Status

Updates the operational status of a specific site in the network.

*   **URL**: `/network/sites`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_admin_permission`

### Arguments

| Parameter | Type     | Required | Description                               |
| :-------- | :------- | :------- | :---------------------------------------- |
| `site_id` | `integer`| Yes      | The ID of the site to update.             |
| `status`  | `string` | Yes      | The new status (`active`, `inactive`, `maintenance`). |

### Request Body Example

```json
{
    "site_id": 3,
    "status": "maintenance"
}
```

### Response (Success 200 OK)

```json
{
    "success": true,
    "site_id": 3,
    "status": "maintenance",
    "updated": true
}
```

---

## 4. Distribute Patterns

Initiates the distribution of specified patterns to target sites in the network.

*   **URL**: `/network/distribute`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter      | Type    | Required | Description                               |
| :------------- | :------ | :------- | :---------------------------------------- |
| `pattern_ids`  | `array` | Yes      | An array of pattern IDs (hashes) to distribute. |
| `target_sites` | `array` | No       | An array of site IDs to distribute to (defaults to all active sites if empty). |

### Request Body Example

```json
{
    "pattern_ids": ["pattern_hash_1", "pattern_hash_2"],
    "target_sites": [2, 3]
}
```

### Response (Success 200 OK)

```json
{
    "success": true,
    "distribution_id": "dist_job_123",
    "patterns": ["pattern_hash_1", "pattern_hash_2"],
    "target_sites": [2, 3]
}
```

---

## 5. Sync Network

Triggers a full network synchronization process, ensuring consistency of Distributed Ledger and pattern data across all participating sites.

*   **URL**: `/network/sync`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Response (Success 200 OK)

```json
{
    "success": true,
    "sync_id": "sync_job_456",
    "sites_synced": 3,
    "timestamp": 1678886400
}
```

---

## 6. Get Distribution Status

Retrieves the overall status of pattern distribution across the network.

*   **URL**: `/network/distribution-status`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Response (Success 200 OK)

```json
{
    "success": true,
    "status": {
        "total_patterns_distributed": 100,
        "pending_distributions": 5,
        "failed_distributions": 1,
        "last_distribution_time": "2023-10-26 10:00:00"
    }
}
```

---

## 7. Get Network Metrics

Retrieves network-specific metrics (e.g., distribution rates, sync success rates) over a specified duration.

*   **URL**: `/network/metrics`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter  | Type     | Required | Description                               |
| :--------- | :------- | :------- | :---------------------------------------- |
| `duration` | `string` | No       | Time period for metrics (`1hour`, `24hours`, `7days`, `30days`). Default: `24hours`. |
| `type`     | `string` | No       | Type of network metrics (`all`, `distribution`, `sync`, `performance`). |

### Response (Success 200 OK)

```json
{
    "success": true,
    "metrics": [
        {
            "timestamp": "2023-10-26 10:00:00",
            "patterns_distributed_rate": 10,
            "sync_success_rate": 0.95,
            "network_latency": 50
        }
    ]
}
```

---

## 8. Get Site Health

Retrieves the health status of a specific site in the network.

*   **URL**: `/network/sites/<site_id>/health`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Path Parameters

| Parameter | Type     | Required | Description          |
| :-------- | :------- | :------- | :------------------- |
| `site_id` | `integer`| Yes      | The ID of the site.  |

### Response (Success 200 OK)

```json
{
    "success": true,
    "site_id": 2,
    "health": {
        "status": "healthy",
        "cpu_load": 0.1,
        "memory_usage": 0.3,
        "last_check": "2023-10-26 10:00:00"
    }
}
```

---

## 9. Rebalance Network

Initiates a network rebalancing operation, optimizing pattern distribution across sites.

*   **URL**: `/network/rebalance`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_admin_permission`

### Response (Success 200 OK)

```json
{
    "success": true,
    "rebalance_id": "rebalance_job_789",
    "patterns_moved": 50,
    "sites_affected": 2
}