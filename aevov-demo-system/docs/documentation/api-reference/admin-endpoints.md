# Admin Endpoints

**Base Path**: `/admin`

These endpoints provide administrative functionalities for managing plugin settings, database operations, logs, API keys, and system information.

---

## 1. Get Plugin Settings

Retrieves the current settings of the APS plugin.

*   **URL**: `/admin/settings`
*   **Method**: `GET`
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Response (Success 200 OK)

```json
{
    "success": true,
    "settings": {
        "sync_interval": 300,
        "batch_size": 50,
        "log_level": "info",
        "cache_lifetime": 3600,
        "api_rate_limit": 100
    }
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "settings_fetch_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 2. Update Plugin Settings

Updates the settings of the APS plugin.

*   **URL**: `/admin/settings`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Arguments

| Parameter      | Type    | Required | Description                               |
| :------------- | :------ | :------- | :---------------------------------------- |
| `sync_interval`| `integer` | No       | Frequency of pattern synchronization (seconds). |
| `batch_size`   | `integer` | No       | Number of patterns processed in a batch.  |
| `log_level`    | `string`  | No       | Verbosity of system logs (`debug`, `info`, `warning`, `error`). |
| `cache_lifetime`| `integer` | No       | Duration for which system data is cached (seconds). |
| `api_rate_limit`| `integer` | No       | Maximum number of API requests per period. |

### Request Body Example

```json
{
    "sync_interval": 600,
    "log_level": "debug"
}
```

### Response (Success 200 OK)

```json
{
    "success": true,
    "settings": {
        "sync_interval": 600,
        "batch_size": 50,
        "log_level": "debug",
        "cache_lifetime": 3600,
        "api_rate_limit": 100
    }
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "settings_update_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 3. Get Database Status

Retrieves the current status and information about the plugin's database tables.

*   **URL**: `/admin/db`
*   **Method**: `GET`
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Response (Success 200 OK)

```json
{
    "success": true,
    "status": {
        "table_prefix": "wp_",
        "tables_exist": {
            "aps_patterns": true,
            "aps_blocks": true,
            "aps_contributor_balances": true,
            "aps_comparison_results": true
        },
        "total_tables": 4,
        "last_optimization": "2023-10-26 10:00:00"
    }
}
```

---

## 4. Optimize Database

Triggers an optimization process for the plugin's database tables.

*   **URL**: `/admin/db/optimize`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Response (Success 200 OK)

```json
{
    "success": true,
    "result": {
        "aps_patterns": "Table optimized successfully",
        "aps_blocks": "Table optimized successfully"
    }
}
```

---

## 5. Perform Maintenance Tasks

Executes specified maintenance tasks.

*   **URL**: `/admin/maintenance`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Arguments

| Parameter | Type    | Required | Description                                                              |
| :-------- | :------ | :------- | :----------------------------------------------------------------------- |
| `tasks`   | `array` | Yes      | An array of maintenance tasks to perform. Valid values: `cleanup_old_data`, `optimize_tables`, `clear_cache`, `rebuild_indices`. |

### Request Body Example

```json
{
    "tasks": ["cleanup_old_data", "clear_cache"]
}
```

### Response (Success 200 OK)

```json
{
    "success": true,
    "results": {
        "cleanup_old_data": true,
        "clear_cache": true
    }
}
```

---

## 6. Get System Logs

Retrieves system logs based on specified filters.

*   **URL**: `/admin/logs`
*   **Method**: `GET`
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Arguments

| Parameter    | Type      | Required | Description                               |
| :----------- | :-------- | :------- | :---------------------------------------- |
| `type`       | `string`  | No       | Filter logs by type (e.g., `info`, `error`, `warning`). |
| `start_date` | `string`  | No       | Logs created after this date (YYYY-MM-DD). |
| `end_date`   | `string`  | No       | Logs created before this date (YYYY-MM-DD). |
| `limit`      | `integer` | No       | Maximum number of logs to return (default: 100). |
| `offset`     | `integer` | No       | Offset for pagination (default: 0).       |

### Response (Success 200 OK)

```json
{
    "success": true,
    "logs": [
        {
            "id": 1,
            "timestamp": "2023-10-26 10:00:00",
            "type": "info",
            "message": "Plugin activated successfully."
        },
        {
            "id": 2,
            "timestamp": "2023-10-26 10:05:00",
            "type": "error",
            "message": "Failed to connect to Cubbit."
        }
    ]
}
```

---

## 7. Clear System Logs

Clears all system logs.

*   **URL**: `/admin/logs`
*   **Method**: `DELETE`
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Response (Success 200 OK)

```json
{
    "success": true
}
```

---

## 8. Get API Keys

Retrieves a list of generated API keys.

*   **URL**: `/admin/api-keys`
*   **Method**: `GET`
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Response (Success 200 OK)

```json
{
    "success": true,
    "keys": [
        "your_api_key_1",
        "your_api_key_2"
    ]
}
```

---

## 9. Create API Key

Generates a new API key.

*   **URL**: `/admin/api-keys`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Response (Success 200 OK)

```json
{
    "success": true,
    "key": "newly_generated_api_key"
}
```

---

## 10. Revoke API Key

Revokes an existing API key.

*   **URL**: `/admin/api-keys`
*   **Method**: `DELETE`
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Arguments

| Parameter | Type    | Required | Description          |
| :-------- | :------ | :------- | :------------------- |
| `key_id`  | `string`| Yes      | The API key to revoke. |

### Request Body Example

```json
{
    "key_id": "api_key_to_revoke"
}
```

### Response (Success 200 OK)

```json
{
    "success": true
}
```

---

## 11. Get System Information

Retrieves detailed information about the WordPress and server environment.

*   **URL**: `/admin/system-info`
*   **Method**: `GET`
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Response (Success 200 OK)

```json
{
    "success": true,
    "info": {
        "wordpress_version": "6.3.1",
        "php_version": "8.1.2",
        "plugin_version": "1.0.0",
        "is_multisite": true,
        "active_plugins": [
            "aevov-pattern-sync-protocol/aevov-pattern-sync-protocol.php",
            "bloom-pattern-recognition/bloom-pattern-system.php"
        ],
        "memory_limit": "256M",
        "max_execution_time": "300",
        "upload_max_filesize": "64M",
        "max_input_vars": "1000"
    }
}
```

---

## 12. Get Queue Status

Retrieves the status of the background processing queue.

*   **URL**: `/admin/queue`
*   **Method**: `GET`
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Response (Success 200 OK)

```json
{
    "success": true,
    "status": {
        "pending": 5,
        "processing": 1,
        "completed": 150,
        "failed": 2
    }
}
```

---

## 13. Clear Queue

Clears all jobs from the background processing queue.

*   **URL**: `/admin/queue`
*   **Method**: `DELETE`
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Response (Success 200 OK)

```json
{
    "success": true
}
```

---

## 14. Get Dashboard Data

Retrieves aggregated data for the monitoring dashboard.

*   **URL**: `/admin/dashboard-data`
*   **Method**: `GET`
*   **Permission**: `check_admin_permission` (requires administrator privileges)

### Response (Success 200 OK)

```json
{
    "success": true,
    "data": {
        "pattern_stats": {
            "total": 1234,
            "by_type": [
                {"pattern_type": "text", "count": 800},
                {"pattern_type": "image", "count": 400}
            ],
            "avg_confidence": 0.85
        },
        "block_count": 500,
        "transaction_count": 2500,
        "consensus_status": {
            "last_proof": 12345,
            "active_proposals_count": 3,
            "total_votes_cast": 15,
            "health": "operational"
        },
        "queue_info": {
            "pending": 5,
            "processing": 1,
            "completed": 150,
            "failed": 2
        },
        "top_contributors": [
            {"id": 1, "name": "Contributor A", "balance": 100.50},
            {"id": 2, "name": "Contributor B", "balance": 75.20}
        ],
        "system_info": {
            "wordpress_version": "6.3.1",
            "php_version": "8.1.2",
            "plugin_version": "1.0.0",
            "is_multisite": true,
            "active_plugins": [],
            "memory_limit": "256M",
            "max_execution_time": "300",
            "upload_max_filesize": "64M",
            "max_input_vars": "1000"
        }
    }
}