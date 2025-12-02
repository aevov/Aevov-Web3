# Pattern Distribution Endpoints

**Base Path**: `/patterns/distribution`

These endpoints provide functionalities for distributing patterns across the multisite network. These endpoints are only available in a WordPress Multisite environment.

---

## 1. Distribute Pattern

Distributes a single pattern to specified sites or all active sites in the network.

*   **URL**: `/patterns/distribution/<hash>`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_admin_permission`

### Path Parameters

| Parameter | Type     | Required | Description          |
| :-------- | :------- | :------- | :------------------- |
| `hash`    | `string` | Yes      | The hash of the pattern to distribute. |

### Arguments

| Parameter | Type    | Required | Description                               |
| :-------- | :------ | :------- | :---------------------------------------- |
| `sites`   | `array` | No       | Specific site IDs to distribute to (defaults to all active sites if empty). |
| `options` | `object` | No       | Distribution options (e.g., overwrite existing). |

### Request Body Example

```json
{
    "sites": [2, 3],
    "options": {
        "overwrite": true
    }
}
```

### Response (Success 200 OK)

```json
{
    "pattern_hash": "pattern_hash_123",
    "distributed_to": [2, 3],
    "failed_sites": [],
    "total_sites": 3
}
```

### Response (Error 400 Bad Request)

```json
{
    "code": "multisite_required",
    "message": "Pattern distribution requires multisite installation",
    "data": {
        "status": 400
    }
}
```

### Response (Error 404 Not Found)

```json
{
    "code": "pattern_not_found",
    "message": "Pattern not found",
    "data": {
        "status": 404
    }
}
```

---

## 2. Get Distribution Status

Retrieves the distribution status for a specific pattern across the network.

*   **URL**: `/patterns/distribution/status/<hash>`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Path Parameters

| Parameter | Type     | Required | Description          |
| :-------- | :------- | :------- | :------------------- |
| `hash`    | `string` | Yes      | The hash of the pattern. |

### Response (Success 200 OK)

```json
{
    "pattern_hash": "pattern_hash_123",
    "status": {
        "site_1": "distributed",
        "site_2": "pending",
        "site_3": "failed"
    }
}
```

---

## 3. Get Network Distribution Info

Retrieves general information about pattern distribution across the entire network.

*   **URL**: `/patterns/distribution/network`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Response (Success 200 OK)

```json
{
    "total_sites": 3,
    "active_sites": 2,
    "sync_enabled": true,
    "last_sync": "2023-10-26 10:00:00",
    "distribution_stats": {
        "total_patterns_distributed": 100,
        "pending_distributions": 5,
        "failed_distributions": 1
    }
}
```

---

## 4. Bulk Distribute Patterns

Distributes multiple patterns to specified sites or all active sites in the network.

*   **URL**: `/patterns/distribution/bulk`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_admin_permission`

### Arguments

| Parameter  | Type    | Required | Description                               |
| :--------- | :------- | :------- | :---------------------------------------- |
| `patterns` | `array` | Yes      | An array of pattern hashes to distribute. |
| `sites`    | `array` | No       | Specific site IDs to distribute to (defaults to all active sites if empty). |
| `options`  | `object` | No       | Distribution options.                     |

### Request Body Example

```json
{
    "patterns": ["pattern_hash_1", "pattern_hash_2", "pattern_hash_3"],
    "sites": [2],
    "options": {
        "force_sync": true
    }
}
```

### Response (Success 200 OK)

```json
{
    "results": [
        {
            "pattern_hash": "pattern_hash_1",
            "distributed_to": [2],
            "failed_sites": []
        },
        {
            "pattern_hash": "pattern_hash_2",
            "distributed_to": [2],
            "failed_sites": []
        }
    ],
    "errors": [
        {
            "pattern_hash": "pattern_hash_3",
            "error": "Pattern not found"
        }
    ],
    "total_processed": 2,
    "total_errors": 1
}