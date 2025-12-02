# Metrics Endpoints

**Base Path**: `/metrics`

These endpoints provide access to various system, pattern, and performance metrics collected by the plugin.

---

## 1. Get System Metrics

Retrieves system-level metrics (e.g., CPU, memory, disk, network usage) over a specified duration.

*   **URL**: `/metrics/system`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter  | Type     | Required | Description                               |
| :--------- | :------- | :------- | :---------------------------------------- |
| `duration` | `string` | No       | Time period for metrics (`1hour`, `24hours`, `7days`, `30days`). Default: `1hour`. |
| `type`     | `string` | No       | Type of metrics to retrieve (`all`, `cpu`, `memory`, `disk`, `network`). Default: `all`. |

### Response (Success 200 OK)

```json
{
    "metrics": [
        {
            "timestamp": "2023-10-26 10:00:00",
            "cpu_usage": 25.5,
            "memory_usage": 600,
            "disk_usage": 75.2,
            "network_in": 1024,
            "network_out": 512
        }
    ],
    "duration": "1hour",
    "type": "all"
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "system_metrics_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 2. Get Pattern Metrics

Retrieves metrics related to pattern processing and storage over a specified duration, with optional aggregation.

*   **URL**: `/metrics/patterns`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter     | Type     | Required | Description                               |
| :------------ | :------- | :------- | :---------------------------------------- |
| `duration`    | `string` | No       | Time period for metrics (`1hour`, `24hours`, `7days`, `30days`). Default: `1hour`. |
| `aggregation` | `string` | No       | Aggregation level (`none`, `minute`, `hour`, `day`). Default: `none`. |

### Response (Success 200 OK)

```json
{
    "metrics": [
        {
            "timestamp": "2023-10-26 10:00:00",
            "patterns_processed": 10,
            "new_patterns": 5,
            "updated_patterns": 3,
            "deleted_patterns": 2
        }
    ],
    "duration": "1hour",
    "aggregation": "none"
}
```

---

## 3. Get Performance Metrics

Retrieves performance-related metrics (e.g., processing rate, error rate, API response time) over a specified duration.

*   **URL**: `/metrics/performance`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter  | Type     | Required | Description                               |
| :--------- | :------- | :------- | :---------------------------------------- |
| `duration` | `string` | No       | Time period for metrics (`1hour`, `24hours`, `7days`, `30days`). Default: `1hour`. |
| `type`     | `string` | No       | Type of performance metrics (`all`, `processing`, `sync`, `api`). Default: `all`. |

### Response (Success 200 OK)

```json
{
    "metrics": [
        {
            "timestamp": "2023-10-26 10:00:00",
            "processing_rate": 1.5,
            "error_rate": 0.01,
            "api_response_time": 0.05
        }
    ],
    "duration": "1hour",
    "type": "all"
}
```

---

## 4. Get Metrics Summary

Retrieves a summary of various system metrics, including system health, pattern statistics, performance, and storage usage.

*   **URL**: `/metrics/summary`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Response (Success 200 OK)

```json
{
    "system": {
        "status": "healthy",
        "cpu_load": 0.2,
        "memory_usage": 0.4,
        "disk_free": 0.8
    },
    "patterns": {
        "total_patterns": 1234,
        "patterns_today": 50,
        "average_confidence": 0.85,
        "pattern_types": [
            {"pattern_type": "text", "count": 800},
            {"pattern_type": "image", "count": 400}
        ]
    },
    "performance": {
        "processing_rate": 1.5,
        "error_rate": 0.01,
        "sync_success_rate": 0.98,
        "api_response_time": 0.05
    },
    "storage": {
        "table_sizes": {
            "aps_patterns": "10.5 MB",
            "aps_blocks": "2.1 MB"
        },
        "total_size": "12.6 MB"
    }
}
```

---

## 5. Get Specific Metric by ID

Retrieves details for a specific metric entry by its ID.

*   **URL**: `/metrics/<id>`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Path Parameters

| Parameter | Type     | Required | Description          |
| :-------- | :------- | :------- | :------------------- |
| `id`      | `integer`| Yes      | The ID of the metric entry. |

### Response (Success 200 OK)

```json
{
    "id": 123,
    "type": "cpu_usage",
    "value": 35.7,
    "timestamp": "2023-10-26 10:15:00",
    "metadata": {}
}
```

### Response (Error 404 Not Found)

```json
{
    "code": "metric_not_found",
    "message": "Metric not found",
    "data": {
        "status": 404
    }
}
```

---

## 6. Trigger Metrics Collection

Manually triggers the collection of system and plugin-specific metrics.

*   **URL**: `/metrics/collect`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Response (Success 200 OK)

```json
{
    "message": "Metrics collection triggered successfully."
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "metrics_collection_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}