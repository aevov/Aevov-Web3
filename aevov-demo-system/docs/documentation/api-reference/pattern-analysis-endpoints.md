# Pattern Analysis Endpoints

**Base Path**: `/analysis`

These endpoints handle in-depth pattern analysis requests, including single pattern analysis, batch processing, feature extraction, and retrieving analysis reports.

---

## 1. Analyze Pattern

Initiates analysis for a single pattern.

*   **URL**: `/analysis/analyze`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter | Type     | Required | Description                               |
| :-------- | :------- | :------- | :---------------------------------------- |
| `data`    | `object` | Yes      | The pattern data to analyze.              |
| `options` | `object` | No       | Analysis options (e.g., algorithm, depth). |

### Request Body Example

```json
{
    "data": {
        "pattern_hash": "some_hash",
        "pattern_type": "text",
        "content": "This is a sample text pattern."
    },
    "options": {
        "algorithm": "nlp_sentiment",
        "depth": 2
    }
}
```

### Response (Success 200 OK)

```json
{
    "success": true,
    "data": {
        "pattern_hash": "some_hash",
        "analysis_result": {
            "sentiment": "positive",
            "keywords": ["sample", "text", "pattern"]
        },
        "confidence": 0.92,
        "type": "text_analysis"
    },
    "processing_time": 0.125
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "analysis_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 2. Batch Analyze Patterns

Initiates batch analysis for multiple patterns by queuing them for background processing.

*   **URL**: `/analysis/batch`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter  | Type    | Required | Description                               |
| :--------- | :------ | :------- | :---------------------------------------- |
| `patterns` | `array` | Yes      | An array of pattern data objects to analyze. |
| `options`  | `object` | No       | Analysis options for the batch.           |

### Request Body Example

```json
{
    "patterns": [
        {"pattern_hash": "hash1", "content": "Pattern 1 data"},
        {"pattern_hash": "hash2", "content": "Pattern 2 data"}
    ],
    "options": {
        "algorithm": "image_recognition"
    }
}
```

### Response (Success 200 OK)

```json
{
    "success": true,
    "job_id": "batch_job_uuid_123",
    "total_patterns": 2,
    "status_endpoint": "http://your-site.com/wp-json/aps/v1/analysis/status/batch_job_uuid_123"
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "batch_creation_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 3. Get Analysis Status

Retrieves the status of a previously initiated batch analysis job.

*   **URL**: `/analysis/status/<job_id>`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Path Parameters

| Parameter | Type     | Required | Description          |
| :-------- | :------- | :------- | :------------------- |
| `job_id`  | `string` | Yes      | The ID of the batch analysis job. |

### Response (Success 200 OK)

```json
{
    "success": true,
    "status": "processing",
    "progress": 50,
    "completed": 1,
    "failed": 0,
    "results": [
        {
            "pattern_hash": "hash1",
            "analysis_result": {
                "sentiment": "positive"
            }
        }
    ]
}
```

### Possible Status Values

*   `processing`: The batch job is still running.
*   `completed`: All patterns in the batch have been successfully analyzed.
*   `failed`: All patterns in the batch failed analysis.
*   `partial`: Some patterns succeeded, others failed.

---

## 4. Extract Features

Extracts specific features from given pattern data.

*   **URL**: `/analysis/features`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter | Type     | Required | Description                               |
| :-------- | :------- | :------- | :---------------------------------------- |
| `data`    | `object` | Yes      | The pattern data from which to extract features. |
| `options` | `object` | No       | Feature extraction options.               |

### Request Body Example

```json
{
    "data": {
        "pattern_hash": "image_hash",
        "image_url": "http://example.com/image.jpg"
    },
    "options": {
        "features": ["color_histogram", "edge_detection"]
    }
}
```

### Response (Success 200 OK)

```json
{
    "success": true,
    "features": {
        "color_histogram": [0.1, 0.2, 0.7],
        "edge_count": 150
    }
}
```

---

## 5. Compare Patterns

Compares two or more patterns and returns their similarity or differences.

*   **URL**: `/analysis/compare`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter  | Type    | Required | Description                               |
| :--------- | :------ | :------- | :---------------------------------------- |
| `patterns` | `array` | Yes      | An array of pattern data objects to compare. |
| `options`  | `object` | No       | Comparison options (e.g., algorithm).     |

### Request Body Example

```json
{
    "patterns": [
        {"pattern_hash": "hash_A", "content": "Pattern A data"},
        {"pattern_hash": "hash_B", "content": "Pattern B data"}
    ],
    "options": {
        "algorithm": "cosine_similarity"
    }
}
```

### Response (Success 200 OK)

```json
{
    "success": true,
    "comparison": {
        "pattern_A": "hash_A",
        "pattern_B": "hash_B",
        "similarity_score": 0.85,
        "differences": ["feature_x_diff"]
    }
}
```

---

## 6. Get Analysis Reports

Retrieves reports on pattern analysis results, filtered by date and type.

*   **URL**: `/analysis/reports`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter    | Type     | Required | Description                               |
| :----------- | :------- | :------- | :---------------------------------------- |
| `start_date` | `string` | No       | Reports generated after this date (YYYY-MM-DD). |
| `end_date`   | `string` | No       | Reports generated before this date (YYYY-MM-DD). |
| `type`       | `string` | No       | Filter reports by analysis type.          |

### Response (Success 200 OK)

```json
{
    "success": true,
    "reports": [
        {
            "id": 1,
            "type": "sentiment_analysis",
            "timestamp": "2023-10-26 10:00:00",
            "data": {
                "total_patterns": 100,
                "positive_count": 70,
                "negative_count": 20,
                "neutral_count": 10
            },
            "status": "completed"
        }
    ]
}