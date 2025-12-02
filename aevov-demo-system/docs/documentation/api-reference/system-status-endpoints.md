# System Status Endpoints

**Base Path**: `/status`

These endpoints provide detailed information about the system's health, performance, resource usage, and integration status.

---

## 1. Get Health Status

Retrieves the overall health status of the system and its components.

*   **URL**: `/status/health`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Response (Success 200 OK)

```json
{
    "success": true,
    "status": "healthy",
    "components": {
        "database": "healthy",
        "cubbit_integration": "healthy",
        "queue": "healthy",
        "api": "healthy"
    },
    "last_check": "2023-10-26 10:00:00",
    "alerts": []
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "health_check_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 2. Get Performance Metrics

Retrieves performance metrics for the system.

*   **URL**: `/status/performance`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter     | Type     | Required | Description                               |
| :------------ | :------- | :------- | :---------------------------------------- |
| `duration`    | `string` | No       | Time period for metrics (`1hour`, `24hours`, `7days`, `30days`). Default: `1hour`. |
| `aggregation` | `string` | No       | Aggregation level (`none`, `minute`, `hour`, `day`). Default: `none`. |
| `type`        | `string` | No       | Type of performance metrics (`all`, `system`, `patterns`, `network`). |

### Response (Success 200 OK)

```json
{
    "success": true,
    "metrics": [
        {
            "timestamp": "2023-10-26 10:00:00",
            "processing_rate": 1.5,
            "error_rate": 0.01,
            "api_response_time": 0.05
        }
    ],
    "period": {
        "start": "2023-10-26 09:00:00",
        "end": "2023-10-26 10:00:00"
    }
}
```

---

## 3. Get Resource Usage

Retrieves current resource usage statistics (CPU, memory, disk).

*   **URL**: `/status/resources`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Response (Success 200 OK)

```json
{
    "success": true,
    "resources": {
        "cpu_usage": 25.5,
        "memory_usage": 60.2,
        "disk_usage": 75.0
    },
    "thresholds": {
        "cpu_warning": 70,
        "memory_warning": 80,
        "disk_warning": 90
    },
    "timestamp": 1678886400
}
```

---

## 4. Get Queue Status

Retrieves the status of the background processing queue.

*   **URL**: `/status/queue`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Response (Success 200 OK)

```json
{
    "success": true,
    "queue": {
        "pending_jobs": 5,
        "processing_jobs": 1,
        "completed_jobs_today": 150,
        "failed_jobs_today": 2
    },
    "processing_rate": 1.5,
    "error_rate": 0.01
}
```

---

## 5. Get Network Status

Retrieves the status of the multisite network, including site health, pattern distribution, and synchronization status.

*   **URL**: `/status/network`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Response (Success 200 OK)

```json
{
    "success": true,
    "network": {
        "total_sites": 3,
        "active_sites": 2,
        "unhealthy_sites": 1
    },
    "distribution": {
        "total_patterns_distributed": 100,
        "pending_distributions": 5
    },
    "sync_status": {
        "last_sync": "2023-10-26 10:00:00",
        "sync_success_rate": 0.98
    }
}
```

### Response (Error 400 Bad Request)

```json
{
    "code": "network_not_supported",
    "message": "Network features require multisite",
    "data": {
        "status": 400
    }
}
```

---

## 6. Get Integration Status

Retrieves the status of various third-party integrations (e.g., Cubbit, external AI services).

*   **URL**: `/status/integrations`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Response (Success 200 OK)

```json
{
    "success": true,
    "integrations": {
        "cubbit": {
            "status": "connected",
            "last_connection": "2023-10-26 10:00:00",
            "storage_used": "10 GB"
        },
        "external_ai_service": {
            "status": "disconnected",
            "error_message": "API key invalid"
        }
    },
    "sync_status": {
        "cubbit_sync_rate": 0.99
    }
}