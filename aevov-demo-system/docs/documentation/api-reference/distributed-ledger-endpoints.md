# Distributed Ledger Endpoints

**Base Path**: `/ledger`

These endpoints provide access to the Distributed Ledger (blockchain) data, including blocks and transactions, and allow for node management and conflict resolution.

---

## 1. Get Blocks

Retrieves a paginated list of blocks from the Distributed Ledger.

*   **URL**: `/ledger/blocks`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter  | Type      | Required | Description                               |
| :--------- | :-------- | :------- | :---------------------------------------- |
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

*   `X-WP-Total`: Total number of blocks.
*   `X-WP-TotalPages`: Total number of pages.

---

## 2. Get Block by Hash

Retrieves details for a specific block by its hash.

*   **URL**: `/ledger/blocks/<hash>`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Path Parameters

| Parameter | Type     | Required | Description          |
| :-------- | :------- | :------- | :------------------- |
| `hash`    | `string` | Yes      | The hash of the block. |

### Response (Success 200 OK)

```json
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
```

### Response (Error 404 Not Found)

```json
{
    "code": "block_not_found",
    "message": "Block not found",
    "data": {
        "status": 404
    }
}
```

---

## 3. Get Transactions

Retrieves a paginated list of transactions from the Distributed Ledger.

*   **URL**: `/ledger/transactions`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Arguments

| Parameter  | Type      | Required | Description                               |
| :--------- | :-------- | :------- | :---------------------------------------- |
| `page`     | `integer` | No       | Current page of the collection (default: 1). |
| `per_page` | `integer` | No       | Maximum number of items to return (default: 10, max: 100). |
| `orderby`  | `string`  | No       | Order collection by attribute (`timestamp`, `hash`). |
| `order`    | `string`  | No       | Order sort attribute (`asc`, `desc`).     |

### Response (Success 200 OK)

```json
[
    {
        "sender": "wallet_abc",
        "recipient": "wallet_xyz",
        "amount": 5.0,
        "timestamp": 1678886400,
        "hash": "tx_hash_1"
    }
]
```

### Response Headers

*   `X-WP-Total`: Total number of transactions.
*   `X-WP-TotalPages`: Total number of pages.

---

## 4. Get Transaction by Hash

Retrieves details for a specific transaction by its hash.

*   **URL**: `/ledger/transactions/<hash>`
*   **Method**: `GET`
*   **Permission**: `check_read_permission`

### Path Parameters

| Parameter | Type     | Required | Description             |
| :-------- | :------- | :------- | :---------------------- |
| `hash`    | `string` | Yes      | The hash of the transaction. |

### Response (Success 200 OK)

```json
{
    "sender": "wallet_abc",
    "recipient": "wallet_xyz",
    "amount": 5.0,
    "timestamp": 1678886400,
    "hash": "tx_hash_1"
}
```

### Response (Error 404 Not Found)

```json
{
    "code": "transaction_not_found",
    "message": "Transaction not found",
    "data": {
        "status": 404
    }
}
```

---

## 5. Register Nodes

Registers one or more new nodes with the Distributed Ledger.

*   **URL**: `/ledger/nodes`
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

## 6. Resolve Conflicts

Resolves conflicts by replacing the current chain with the longest valid chain found in the network.

*   **URL**: `/ledger/resolve-conflicts`
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

## 7. Create New Transaction

Adds a new transaction to the list of pending transactions, to be included in the next forged block.

*   **URL**: `/ledger/transactions/new`
*   **Method**: `POST` (or `PUT`)
*   **Permission**: `check_write_permission`

### Arguments

| Parameter   | Type     | Required | Description                               |
| :---------- | :------- | :------- | :---------------------------------------- |
| `sender`    | `string` | Yes      | The address of the sender.                |
| `recipient` | `string` | Yes      | The address of the recipient.             |
| `amount`    | `number` | Yes      | The amount of the transaction.            |

### Request Body Example

```json
{
    "sender": "user_wallet_1",
    "recipient": "user_wallet_2",
    "amount": 10.5
}
```

### Response (Success 201 Created)

```json
{
    "message": "Transaction will be added to Block 51"
}
```

### Response (Error 500 Internal Server Error)

```json
{
    "code": "transaction_creation_failed",
    "message": "Error message details",
    "data": {
        "status": 500
    }
}