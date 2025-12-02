# Proof of Contribution Endpoints

**Base Path**: `/poc`

These endpoints provide functionalities for interacting with the Proof of Contribution (PoC) mechanism, including mining new blocks and retrieving contributor scores.

---

## 1. Mine New Block

Triggers the mining process to forge a new block on the Distributed Ledger using the Proof of Contribution algorithm.

*   **URL**: `/poc/mine`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter        | Type     | Required | Description                               |
| :--------------- | :------- | :------- | :---------------------------------------- |
| `contributor_id` | `string` | Yes      | The ID of the contributor initiating the mining process. |

### Request Body Example

```json
{
    "contributor_id": "user_123"
}
```

### Response (Success 200 OK)

```json
{
    "message": "New Block Forged",
    "index": 5,
    "transactions": [
        {
            "sender": "0",
            "recipient": "user_123",
            "amount": 1.0,
            "timestamp": 1678886400,
            "hash": "tx_hash_abc"
        }
    ],
    "proof": 12345,
    "previous_hash": "previous_block_hash"
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "mine_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 2. Get Blockchain Chain

Retrieves the full blockchain (Distributed Ledger) or a paginated portion of it.

*   **URL**: `/poc/chain`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter  | Type      | Required | Description                               |
| :--------- | :------- | :------- | :---------------------------------------- |
| `page`     | `integer` | No       | Current page of the collection (default: 1). |
| `per_page` | `integer` | No       | Maximum number of items to return (default: 10, max: 100). |
| `orderby`  | `string`  | No       | Order collection by attribute (`created_at`, `timestamp`). |
| `order`    | `string`  | No       | Order sort attribute (`asc`, `desc`).     |

### Response (Success 200 OK)

```json
[
    {
        "id": 1,
        "block_hash": "a1b2c3d4e5f6...",
        "block_data": {
            "index": 1,
            "timestamp": 1678886400,
            "transactions": [
                {"sender": "0", "recipient": "node_id_1", "amount": 1.0}
            ],
            "proof": 100,
            "previous_hash": "1"
        },
        "cubbit_key": "blocks/a1b2c3d4e5f6.json",
        "created_at": "2023-03-15 10:00:00"
    }
]
```

### Response Headers

*   `X-WP-Total`: Total number of blocks in the chain.
*   `X-WP-TotalPages`: Total number of pages.

---

## 3. Register Nodes

Registers one or more new nodes with the Proof of Contribution network.

*   **URL**: `/poc/nodes/register`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter | Type    | Required | Description                               |
| :-------- | :------ | :------- | :---------------------------------------- |
| `nodes`   | `array` | Yes      | An array of node URLs to register.        |

### Request Body Example

```json
{
    "nodes": [
        "http://node1.example.com",
        "http://node2.example.com"
    ]
}
```

### Response (Success 201 Created)

```json
{
    "message": "New nodes have been added",
    "total_nodes": 3
}
```

### Response (Error 400 Bad Request)

```json
{
    "code": "invalid_nodes",
    "message": "Error: Please supply a valid list of nodes",
    "data": {
        "status": 400
    }
}
```

---

## 4. Resolve Node Conflicts

Resolves conflicts by replacing the current node's chain with the longest valid chain found in the network.

*   **URL**: `/poc/nodes/resolve`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Response (Success 200 OK)

```json
{
    "message": "Our chain was replaced",
    "new_chain_length": 50
}
```

Or

```json
{
    "message": "Our chain is authoritative",
    "chain_length": 45
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "conflict_resolution_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}
```

---

## 5. Get Contributor Score

Retrieves the contribution score for a specific contributor.

*   **URL**: `/poc/contributor-score/<contributor_id>`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Path Parameters

| Parameter        | Type     | Required | Description          |
| :--------------- | :------- | :------- | :------------------- |
| `contributor_id` | `string` | Yes      | The ID of the contributor. |

### Response (Success 200 OK)

```json
{
    "contributor_id": "user_123",
    "score": 8.75
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "contributor_score_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}