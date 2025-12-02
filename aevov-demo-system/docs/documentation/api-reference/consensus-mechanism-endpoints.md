# Consensus Mechanism Endpoints

**Base Path**: `/consensus`

These endpoints allow interaction with the Proof of Contribution consensus mechanism, including getting status, submitting votes, and managing proposals.

---

## 1. Get Consensus Status

Retrieves the current status of the consensus mechanism.

*   **URL**: `/consensus/status`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Response (Success 200 OK)

```json
{
    "last_proof": 12345,
    "active_proposals_count": 3,
    "total_votes_cast": 15,
    "health": "operational"
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "consensus_error",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 2. Submit Vote

Submits a vote for a specific proposal.

*   **URL**: `/consensus/vote`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter        | Type     | Required | Description                               |
| :--------------- | :------- | :------- | :---------------------------------------- |
| `proposal_id`    | `string` | Yes      | ID of the proposal to vote on.            |
| `vote`           | `boolean`| Yes      | `true` for approval, `false` for disapproval. |
| `contributor_id` | `string` | Yes      | ID of the contributor submitting the vote. |

### Request Body Example

```json
{
    "proposal_id": "proposal_123abc",
    "vote": true,
    "contributor_id": "user_456def"
}
```

### Response (Success 200 OK)

```json
{
    "message": "Vote submitted successfully",
    "result": true
}
```

### Response (Error 400 Bad Request)

```json
{
    "code": "proposal_not_found",
    "message": "Proposal not found.",
    "data": {
        "status": 400
    }
}
```

### Response (Error 400 Bad Request)

```json
{
    "code": "duplicate_vote",
    "message": "You have already voted on this proposal.",
    "data": {
        "status": 400
    }
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "vote_submission_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 3. Get Proposals

Retrieves a paginated list of proposals.

*   **URL**: `/consensus/proposals`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter  | Type      | Required | Description                               |
| :--------- | :-------- | :------- | :---------------------------------------- |
| `page`     | `integer` | No       | Current page of the collection (default: 1). |
| `per_page` | `integer` | No       | Maximum number of items to return (default: 10, max: 100). |
| `status`   | `string`  | No       | Filter proposals by status (`open`, `closed`, `approved`, `rejected`). |

### Response (Success 200 OK)

```json
[
    {
        "id": "proposal_123abc",
        "title": "Implement Feature X",
        "description": "This proposal aims to add feature X to the system.",
        "created_at": 1678886400,
        "status": "open",
        "votes": {}
    }
]
```

### Response Headers

*   `X-WP-Total`: Total number of proposals.
*   `X-WP-TotalPages`: Total number of pages.

---

## 4. Get Proposal Details

Retrieves details for a specific proposal.

*   **URL**: `/consensus/proposals/<id>`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Path Parameters

| Parameter | Type     | Required | Description          |
| :-------- | :------- | :------- | :------------------- |
| `id`      | `string` | Yes      | ID of the proposal.  |

### Response (Success 200 OK)

```json
{
    "id": "proposal_123abc",
    "title": "Implement Feature X",
    "description": "This proposal aims to add feature X to the system.",
    "created_at": 1678886400,
    "status": "open",
    "votes": {
        "user_456def": true
    }
}
```

### Response (Error 404 Not Found)

```json
{
    "code": "proposal_not_found",
    "message": "Proposal not found",
    "data": {
        "status": 404
    }
}
```

---

## 5. Add New Proposal

Adds a new proposal to the consensus mechanism.

*   **URL**: `/consensus/proposals`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter    | Type     | Required | Description                               |
| :----------- | :------- | :------- | :---------------------------------------- |
| `title`      | `string` | Yes      | Title of the proposal.                    |
| `description`| `string` | Yes      | Detailed description of the proposal.     |
| `type`       | `string` | Yes      | Type of the proposal (e.g., `feature`, `bugfix`, `governance`). |
| `metadata`   | `object` | No       | Additional metadata for the proposal.     |

### Request Body Example

```json
{
    "title": "New Feature Integration",
    "description": "This proposal outlines the integration of a new AI-powered feature for enhanced pattern recognition.",
    "type": "feature",
    "metadata": {
        "priority": "high",
        "estimated_effort": "2 weeks"
    }
}
```

### Response (Success 201 Created)

```json
{
    "message": "Proposal created successfully",
    "proposal_id": "proposal_new_id_789xyz"
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "proposal_creation_failed",
    "message": "Failed to create proposal.",
    "data": {
        "status": 500
    }
}