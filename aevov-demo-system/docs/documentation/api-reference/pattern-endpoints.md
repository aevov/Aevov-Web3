# Pattern Endpoints

**Base Path**: `/patterns`

These endpoints provide standard CRUD (Create, Read, Update, Delete) operations for patterns within the system.

---

## 1. Get Patterns

Retrieves a paginated list of patterns, with various filtering and sorting options.

*   **URL**: `/patterns`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter    | Type      | Required | Description                               |
| :----------- | :-------- | :------- | :---------------------------------------- |
| `page`       | `integer` | No       | Current page of the collection (default: 1). |
| `per_page`   | `integer` | No       | Maximum number of items to return (default: 10, max: 100). |
| `type`       | `string`  | No       | Filter patterns by type (e.g., `text`, `image`, `audio`). |
| `confidence` | `number`  | No       | Filter patterns by minimum confidence score (0.0 to 1.0). |
| `tensor_sku` | `string`  | No       | Filter patterns by associated tensor SKU. |
| `site_id`    | `integer` | No       | Filter patterns by site ID (for multisite). |
| `status`     | `string`  | No       | Filter patterns by status (`active`, `archived`, `pending`, `error`). |
| `start_date` | `string`  | No       | Filter patterns created after this date (YYYY-MM-DD). |
| `end_date`   | `string`  | No       | Filter patterns created before this date (YYYY-MM-DD). |
| `orderby`    | `string`  | No       | Order collection by attribute (`id`, `pattern_hash`, `pattern_type`, `confidence`, `created_at`, `updated_at`). Default: `created_at`. |
| `order`      | `string`  | No       | Order sort attribute (`asc`, `desc`). Default: `desc`. |
| `search`     | `string`  | No       | Search for patterns by keywords in features or metadata. |

### Response (Success 200 OK)

```json
[
    {
        "id": 1,
        "pattern_hash": "a1b2c3d4e5f6...",
        "pattern_type": "text",
        "pattern_data": {
            "content": "This is a sample pattern data."
        },
        "confidence": 0.95,
        "created_at": "2023-10-26 10:00:00",
        "updated_at": "2023-10-26 10:00:00",
        "sync_status": "synced",
        "distribution_count": 5,
        "last_accessed": "2023-10-26 10:30:00",
        "cubbit_key": "patterns/a1b2c3d4e5f6.json",
        "contributor_id": 123,
        "useful_inference_count": 10
    }
]
```

### Response Headers

*   `X-WP-Total`: Total number of patterns.
*   `X-WP-TotalPages`: Total number of pages.

---

## 2. Create Pattern

Creates a new pattern in the system.

*   **URL**: `/patterns`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter          | Type     | Required | Description                               |
| :----------------- | :------- | :------- | :---------------------------------------- |
| `hash`             | `string` | Yes      | Unique hash of the pattern.               |
| `type`             | `string` | Yes      | Type of the pattern (e.g., `text`, `image`). |
| `data`             | `object` | Yes      | The actual pattern data.                  |
| `confidence`       | `number` | Yes      | Confidence score of the pattern (0.0 to 1.0). |
| `cubbit_key`       | `string` | No       | Key for Cubbit storage if data is off-chain. |
| `contributor_id`   | `integer`| No       | ID of the contributor who identified the pattern. |
| `useful_inference_count` | `integer`| No       | Number of useful inferences made from this pattern. |

### Request Body Example

```json
{
    "hash": "new_pattern_hash_abc",
    "type": "text",
    "data": {
        "content": "This is a new pattern to be stored."
    },
    "confidence": 0.88,
    "contributor_id": 1
}
```

### Response (Success 200 OK)

```json
{
    "id": 2,
    "pattern_hash": "new_pattern_hash_abc",
    "pattern_type": "text",
    "pattern_data": {
        "content": "This is a new pattern to be stored."
    },
    "confidence": 0.88,
    "created_at": "2023-10-26 10:05:00",
    "updated_at": "2023-10-26 10:05:00",
    "sync_status": "pending",
    "distribution_count": 0,
    "last_accessed": null,
    "cubbit_key": null,
    "contributor_id": 1,
    "useful_inference_count": 0
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "pattern_creation_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 3. Get Pattern by Hash

Retrieves details for a single pattern by its hash.

*   **URL**: `/patterns/<hash>`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Path Parameters

| Parameter | Type     | Required | Description          |
| :-------- | :------- | :------- | :------------------- |
| `hash`    | `string` | Yes      | The hash of the pattern. |

### Response (Success 200 OK)

```json
{
    "id": 1,
    "pattern_hash": "a1b2c3d4e5f6...",
    "pattern_type": "text",
    "pattern_data": {
        "content": "This is a sample pattern data."
    },
    "confidence": 0.95,
    "created_at": "2023-10-26 10:00:00",
    "updated_at": "2023-10-26 10:00:00",
    "sync_status": "synced",
    "distribution_count": 5,
    "last_accessed": "2023-10-26 10:30:00",
    "cubbit_key": "patterns/a1b2c3d4e5f6.json",
    "contributor_id": 123,
    "useful_inference_count": 10
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

## 4. Update Pattern

Updates an existing pattern identified by its hash.

*   **URL**: `/patterns/<hash>`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Path Parameters

| Parameter | Type     | Required | Description          |
| :-------- | :------- | :------- | :------------------- |
| `hash`    | `string` | Yes      | The hash of the pattern to update. |

### Arguments

| Parameter          | Type     | Required | Description                               |
| :----------------- | :------- | :------- | :---------------------------------------- |
| `data`             | `object` | No       | Updated pattern data.                     |
| `confidence`       | `number` | No       | Updated confidence score (0.0 to 1.0).    |
| `sync_status`      | `string` | No       | Updated sync status (`pending`, `synced`, `failed`, `processing`). |
| `distribution_count` | `integer`| No       | Updated distribution count.               |
| `last_accessed`    | `string` | No       | Updated last accessed timestamp (YYYY-MM-DD HH:MM:SS). |
| `cubbit_key`       | `string` | No       | Updated Cubbit key.                       |
| `contributor_id`   | `integer`| No       | Updated contributor ID.                   |
| `useful_inference_count` | `integer`| No       | Updated useful inference count.           |

### Request Body Example

```json
{
    "confidence": 0.99,
    "sync_status": "synced"
}
```

### Response (Success 200 OK)

```json
{
    "id": 1,
    "pattern_hash": "a1b2c3d4e5f6...",
    "pattern_type": "text",
    "pattern_data": {
        "content": "This is a sample pattern data."
    },
    "confidence": 0.99,
    "created_at": "2023-10-26 10:00:00",
    "updated_at": "2023-10-26 10:10:00",
    "sync_status": "synced",
    "distribution_count": 5,
    "last_accessed": "2023-10-26 10:30:00",
    "cubbit_key": "patterns/a1b2c3d4e5f6.json",
    "contributor_id": 123,
    "useful_inference_count": 10
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "pattern_update_failed",
    "message": "Failed to update pattern or pattern not found.",
    "data": {
        "status": 500
    }
}
```

---

## 5. Delete Pattern

Deletes a pattern from the system by its hash.

*   **URL**: `/patterns/<hash>`
*   **Method**: `DELETE`
*   **Permission**: `check_admin_permission`

### Path Parameters

| Parameter | Type     | Required | Description          |
| :-------- | :------- | :------- | :------------------- |
| `hash`    | `string` | Yes      | The hash of the pattern to delete. |

### Response (Success 204 No Content)

(No content is returned for a successful deletion)

### Response (Error 500 Internal Server Error)

```json
{
    "code": "pattern_delete_failed",
    "message": "Failed to delete pattern or pattern not found.",
    "data": {
        "status": 500
    }
}