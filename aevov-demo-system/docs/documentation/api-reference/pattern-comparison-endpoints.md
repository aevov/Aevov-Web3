# Pattern Comparison Endpoints

**Base Path**: `/patterns/comparison`

These endpoints provide functionalities for comparing patterns, retrieving comparison history, and accessing specific comparison results.

---

## 1. Compare Patterns

Compares a set of patterns and returns the comparison result.

*   **URL**: `/patterns/comparison`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter | Type    | Required | Description                               |
| :-------- | :------ | :------- | :---------------------------------------- |
| `items`   | `array` | Yes      | An array of patterns to compare. Each item can be a pattern hash or a full pattern object. |
| `options` | `object` | No       | Comparison options (e.g., algorithm, similarity threshold). |

### Request Body Example

```json
{
    "items": [
        "pattern_hash_1",
        "pattern_hash_2"
    ],
    "options": {
        "algorithm": "jaccard_index",
        "threshold": 0.7
    }
}
```

### Response (Success 200 OK)

```json
{
    "comparison_id": 123,
    "result": {
        "type": "pattern_comparison",
        "items": ["pattern_hash_1", "pattern_hash_2"],
        "settings": {"algorithm": "jaccard_index"},
        "similarity_score": 0.85,
        "differences": ["feature_X_diff", "feature_Y_diff"]
    }
}
```

### Response (Error 400 Bad Request)

```json
{
    "code": "invalid_items",
    "message": "Items parameter is required and must be an array",
    "data": {
        "status": 400
    }
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "comparison_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 2. Get Comparison History

Retrieves a paginated list of past pattern comparison results.

*   **URL**: `/patterns/comparison/history`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter  | Type      | Required | Description                               |
| :--------- | :-------- | :------- | :---------------------------------------- |
| `page`     | `integer` | No       | Current page of the collection (default: 1). |
| `per_page` | `integer` | No       | Maximum number of items to return (default: 10, max: 100). |

### Response (Success 200 OK)

```json
[
    {
        "id": 123,
        "comparison_uuid": "uuid_123abc",
        "comparison_type": "generic",
        "created_at": "2023-10-26 10:00:00",
        "status": "completed"
    }
]
```

### Response Headers

*   `X-WP-Total`: Total number of comparison records.
*   `X-WP-TotalPages`: Total number of pages.

---

## 3. Get Specific Comparison Result

Retrieves the detailed result of a specific pattern comparison by its ID.

*   **URL**: `/patterns/comparison/<id>`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Path Parameters

| Parameter | Type     | Required | Description          |
| :-------- | :------- | :------- | :------------------- |
| `id`      | `integer`| Yes      | The ID of the comparison result. |

### Response (Success 200 OK)

```json
{
    "comparison": {
        "id": 123,
        "comparison_uuid": "uuid_123abc",
        "comparison_type": "generic",
        "items_data": ["pattern_hash_1", "pattern_hash_2"],
        "settings": {"algorithm": "jaccard_index"},
        "status": "completed",
        "created_at": "2023-10-26 10:00:00"
    },
    "result": {
        "type": "pattern_comparison",
        "items": ["pattern_hash_1", "pattern_hash_2"],
        "settings": {"algorithm": "jaccard_index"},
        "similarity_score": 0.85,
        "differences": ["feature_X_diff", "feature_Y_diff"]
    }
}
```

### Response (Error 404 Not Found)

```json
{
    "code": "comparison_not_found",
    "message": "Comparison result not found",
    "data": {
        "status": 404
    }
}