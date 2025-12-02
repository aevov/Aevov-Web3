# AevSyncPro API Reference

Complete REST API documentation for AevSyncPro - Intelligent AI-Powered Workflow Orchestration.

## Base URL

```
/wp-json/aevov-syncpro/v1/
```

## Authentication

All endpoints require authentication. Supported methods:

1. **WordPress Cookie Auth**: Standard logged-in user sessions
2. **Application Passwords**: WordPress application passwords (WP 5.6+)
3. **JWT Tokens**: If JWT authentication plugin is installed

### Permission Levels

| Level | Required Capability | Description |
|-------|---------------------|-------------|
| Read | `read` | View context and status |
| Write | `edit_posts` | Generate workflows and configs |
| Admin | `manage_options` | Apply configs, rollback, manage templates |

---

## Endpoints

### System Context

#### GET /context

Get full system context including all plugin states, capabilities, and configurations.

**Response:**
```json
{
    "plugins": {
        "aevov-core": {
            "name": "Aevov Core",
            "description": "Core infrastructure for the Aevov ecosystem",
            "is_active": true,
            "version": "1.0.0",
            "capabilities": ["core_management", "plugin_coordination"],
            "config_options": ["api_key", "debug_mode"]
        },
        // ... all 36 plugins
    },
    "capabilities": ["language_processing", "image_generation", ...],
    "current_configs": {...},
    "statistics": {
        "active_plugins": 28,
        "total_capabilities": 47,
        "workflows_executed": 156
    },
    "recommendations": [...],
    "storage": {...},
    "workflows": {...}
}
```

---

#### GET /context/{component}

Get context for a specific component/target.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| component | string | Target component (see below) |

**Valid Components:**
- `ai_engines` - All AI/ML engine plugins
- `storage` - Storage and CDN systems
- `workflows` - Workflow engine state
- `patterns` - Pattern recognition systems
- `memory` - Memory core systems
- `security` - Security plugins
- `network` - Network/Meshcore systems

**Response:**
```json
{
    "component": "ai_engines",
    "plugins": {
        "aevov-language-engine": {...},
        "aevov-image-engine": {...},
        "aevov-cognitive-engine": {...},
        "aevov-reasoning-engine": {...},
        "aevov-embedding-engine": {...},
        "aevov-vision-depth": {...},
        "aevov-neuro-architect": {...}
    },
    "capabilities": [...],
    "configurations": {...},
    "status": "healthy"
}
```

---

### Workflow Generation

#### POST /generate/workflow

Generate a complete workflow from natural language prompt.

**Request:**
```json
{
    "prompt": "Set up a high-performance AI content generation system with secure authentication",
    "options": {
        "target": "all",
        "auto_apply": false,
        "performance": "high",
        "security_level": "strict"
    }
}
```

**Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| prompt | string | Yes | - | Natural language description |
| options.target | string | No | "all" | Target system(s) |
| options.auto_apply | boolean | No | false | Auto-apply generated config |
| options.performance | string | No | "standard" | Performance mode |
| options.security_level | string | No | "standard" | Security level |

**Response:**
```json
{
    "success": true,
    "workflow": {
        "id": "wf_abc123",
        "name": "AI Content Generation Setup",
        "description": "High-performance content generation with security",
        "nodes": [
            {
                "id": "node_1",
                "type": "syncpro",
                "data": {
                    "label": "Configure AI Engines",
                    "mode": "generate",
                    "target": "ai_engines"
                },
                "position": {"x": 100, "y": 100}
            },
            {
                "id": "node_2",
                "type": "syncpro",
                "data": {
                    "label": "Setup Security",
                    "mode": "generate",
                    "target": "security"
                },
                "position": {"x": 300, "y": 100}
            }
        ],
        "edges": [
            {"source": "node_1", "target": "node_2", "id": "edge_1"}
        ]
    },
    "analysis": {
        "goal": "Set up high-performance AI content generation",
        "required_capabilities": [
            "language_processing",
            "content_generation",
            "authentication",
            "rate_limiting"
        ],
        "affected_plugins": [
            "aevov-language-engine",
            "aevov-cognitive-engine",
            "aevov-security"
        ],
        "estimated_operations": 12
    },
    "validation": {
        "valid": true,
        "issues": [],
        "warnings": []
    }
}
```

---

#### POST /generate/config

Generate configuration for a specific target.

**Request:**
```json
{
    "target": "ai_engines",
    "requirements": {
        "prompt": "Optimize for low latency and high throughput",
        "performance": "high",
        "security": "standard"
    }
}
```

**Response:**
```json
{
    "success": true,
    "configuration": {
        "aevov-language-engine": {
            "model": "gpt-4-turbo",
            "max_tokens": 4096,
            "temperature": 0.7,
            "batch_size": 10,
            "cache_enabled": true
        },
        "aevov-cognitive-engine": {
            "reasoning_depth": 3,
            "parallel_processing": true,
            "memory_limit": "2GB"
        }
    },
    "validation": {
        "valid": true,
        "issues": []
    }
}
```

---

### Configuration Bundles

#### POST /bundle

Generate a complete configuration bundle.

**Request:**
```json
{
    "requirements": {
        "prompt": "Configure for maximum performance with distributed storage",
        "security": "strict",
        "storage": "distributed"
    },
    "options": {
        "include_rollback": true,
        "validate": true
    }
}
```

**Response:**
```json
{
    "success": true,
    "bundle": {
        "ai_engines": {...},
        "storage": {...},
        "security": {...},
        "workflows": {...}
    },
    "validation": {
        "valid": true,
        "issues": [],
        "warnings": ["CDN configuration requires external setup"]
    },
    "apply_instructions": [
        "1. Backup current configurations",
        "2. Apply AI engine settings first",
        "3. Configure storage systems",
        "4. Enable security policies"
    ],
    "rollback_plan": {
        "session_id": "session_xyz",
        "restore_points": [...]
    }
}
```

---

#### POST /apply

Apply a configuration bundle to the system.

**Request:**
```json
{
    "bundle": {
        "ai_engines": {...},
        "storage": {...}
    },
    "dry_run": false,
    "create_rollback": true
}
```

**Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| bundle | object | Yes | - | Configuration bundle |
| dry_run | boolean | No | false | Validate without applying |
| create_rollback | boolean | No | true | Create rollback point |

**Response (Success):**
```json
{
    "success": true,
    "applied": {
        "ai_engines": {"status": "applied", "changes": 5},
        "storage": {"status": "applied", "changes": 3}
    },
    "session_id": "session_abc123",
    "rollback_id": "rb_xyz789"
}
```

**Response (Dry Run):**
```json
{
    "success": true,
    "dry_run": true,
    "would_apply": {
        "ai_engines": {"changes": 5, "affected_options": [...]},
        "storage": {"changes": 3, "affected_options": [...]}
    },
    "validation": {"valid": true}
}
```

---

### Synchronization

#### POST /sync/start

Start a new sync session.

**Request:**
```json
{
    "name": "Production Update",
    "targets": ["ai_engines", "security"],
    "auto_commit": false
}
```

**Response:**
```json
{
    "success": true,
    "session_id": "sync_abc123",
    "started_at": "2024-01-15T10:30:00Z",
    "targets": ["ai_engines", "security"]
}
```

---

#### GET /sync/status

Get current sync session status.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| session_id | string | Optional, defaults to active session |

**Response:**
```json
{
    "session_id": "sync_abc123",
    "status": "in_progress",
    "started_at": "2024-01-15T10:30:00Z",
    "operations": [
        {
            "id": "op_1",
            "type": "plugin_config",
            "target": "aevov-language-engine",
            "status": "completed"
        },
        {
            "id": "op_2",
            "type": "plugin_config",
            "target": "aevov-security",
            "status": "pending"
        }
    ],
    "statistics": {
        "total": 5,
        "completed": 3,
        "pending": 2,
        "failed": 0
    }
}
```

---

#### POST /sync/commit

Commit current sync session.

**Response:**
```json
{
    "success": true,
    "session_id": "sync_abc123",
    "committed_at": "2024-01-15T10:35:00Z",
    "summary": {
        "operations_applied": 5,
        "plugins_updated": 3,
        "rollback_available": true
    }
}
```

---

#### POST /sync/rollback/{session_id}

Rollback a sync session.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| session_id | string | Session ID to rollback |

**Response:**
```json
{
    "success": true,
    "session_id": "sync_abc123",
    "rolled_back_at": "2024-01-15T10:40:00Z",
    "restored_operations": 5,
    "status": "restored"
}
```

---

### Templates

#### GET /templates

List all available workflow templates.

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| category | string | Filter by category |
| search | string | Search in name/description |

**Response:**
```json
{
    "templates": [
        {
            "id": "complete_system_setup",
            "name": "Complete System Setup",
            "description": "Full ecosystem configuration for new installations",
            "category": "setup",
            "targets": ["all"],
            "estimated_time": "5-10 minutes"
        },
        {
            "id": "ai_engine_optimization",
            "name": "AI Engine Optimization",
            "description": "Optimize all AI engines for maximum performance",
            "category": "optimization",
            "targets": ["ai_engines"]
        },
        // ... more templates
    ],
    "total": 8,
    "categories": ["setup", "optimization", "security", "content"]
}
```

---

#### GET /templates/{id}

Get a specific template.

**Response:**
```json
{
    "id": "ai_engine_optimization",
    "name": "AI Engine Optimization",
    "description": "Optimize all AI engines for maximum performance",
    "category": "optimization",
    "template": {
        "nodes": [...],
        "edges": [...],
        "variables": [...]
    },
    "requirements": {
        "plugins": ["aevov-ai-core", "aevov-language-engine"],
        "capabilities": ["ai_orchestration"]
    },
    "outputs": {
        "configuration": "object",
        "performance_report": "object"
    }
}
```

---

#### POST /templates

Create a new template.

**Request:**
```json
{
    "name": "Custom AI Setup",
    "description": "My custom AI configuration template",
    "category": "custom",
    "template": {
        "nodes": [...],
        "edges": [...]
    },
    "targets": ["ai_engines"]
}
```

**Response:**
```json
{
    "success": true,
    "template_id": "tpl_abc123",
    "created_at": "2024-01-15T10:30:00Z"
}
```

---

#### PUT /templates/{id}

Update an existing template.

**Request:**
```json
{
    "name": "Updated Custom AI Setup",
    "description": "Updated description",
    "template": {...}
}
```

---

#### DELETE /templates/{id}

Delete a template.

**Response:**
```json
{
    "success": true,
    "deleted_id": "tpl_abc123"
}
```

---

### History

#### GET /history

Get configuration change history.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| page | int | 1 | Page number |
| per_page | int | 20 | Items per page |
| plugin | string | - | Filter by plugin |
| session_id | string | - | Filter by session |
| from | string | - | Start date (ISO 8601) |
| to | string | - | End date (ISO 8601) |

**Response:**
```json
{
    "history": [
        {
            "id": 123,
            "session_id": "sync_abc",
            "plugin": "aevov-language-engine",
            "config_key": "model",
            "old_value": "gpt-3.5-turbo",
            "new_value": "gpt-4-turbo",
            "operation_type": "update",
            "created_at": "2024-01-15T10:30:00Z",
            "user_id": 1,
            "rollback_executed": false
        }
    ],
    "total": 156,
    "page": 1,
    "per_page": 20,
    "total_pages": 8
}
```

---

### Node Execution

#### POST /execute

Execute a SyncPro workflow node directly.

**Request:**
```json
{
    "node_data": {
        "mode": "generate",
        "target": "ai_engines",
        "auto_apply": false,
        "performance": "high",
        "security_level": "standard"
    },
    "inputs": {
        "prompt": "Configure language engine for creative writing",
        "context": {}
    }
}
```

**Response:**
```json
{
    "success": true,
    "outputs": {
        "configuration": {...},
        "sync_operations": [...],
        "analysis": {...}
    },
    "execution_time_ms": 1250
}
```

---

### System Health

#### GET /health

Get system health status.

**Response:**
```json
{
    "status": "healthy",
    "components": {
        "database": {"status": "ok", "latency_ms": 5},
        "ai_engines": {"status": "ok", "available": 7},
        "sync_controller": {"status": "ok", "active_sessions": 0},
        "cache": {"status": "ok", "hit_rate": 0.85}
    },
    "version": "1.0.0",
    "uptime_seconds": 86400
}
```

---

### Export/Import

#### POST /export

Export configurations.

**Request:**
```json
{
    "targets": ["ai_engines", "security"],
    "format": "json",
    "include_history": false
}
```

**Response:**
```json
{
    "success": true,
    "export": {
        "version": "1.0.0",
        "exported_at": "2024-01-15T10:30:00Z",
        "configurations": {...}
    },
    "download_url": "/wp-json/aevov-syncpro/v1/export/download/abc123"
}
```

**Formats Available:**
- `json` - JSON format
- `yaml` - YAML format
- `php` - PHP array format

---

#### POST /import

Import configurations.

**Request:**
```json
{
    "data": {...},
    "merge": true,
    "validate_only": false
}
```

**Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| data | object | - | Configuration data to import |
| merge | boolean | true | Merge with existing configs |
| validate_only | boolean | false | Only validate, don't import |

**Response:**
```json
{
    "success": true,
    "imported": {
        "ai_engines": 5,
        "security": 3
    },
    "skipped": 0,
    "errors": []
}
```

---

## Error Responses

All endpoints return errors in this format:

```json
{
    "success": false,
    "error": {
        "code": "validation_error",
        "message": "Invalid configuration format",
        "details": {
            "field": "ai_engines.model",
            "issue": "Unknown model specified"
        }
    }
}
```

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `unauthorized` | 401 | Authentication required |
| `forbidden` | 403 | Insufficient permissions |
| `not_found` | 404 | Resource not found |
| `validation_error` | 400 | Invalid request data |
| `plugin_not_found` | 400 | Target plugin not installed |
| `session_not_found` | 404 | Sync session not found |
| `rollback_failed` | 500 | Could not rollback changes |
| `ai_error` | 500 | AI engine processing error |
| `database_error` | 500 | Database operation failed |

---

## Rate Limiting

API requests are rate limited per user:

| Endpoint Category | Limit |
|-------------------|-------|
| Read operations | 100/minute |
| Write operations | 30/minute |
| Generate operations | 10/minute |

Rate limit headers:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1705312200
```

---

## Webhooks

Configure webhooks for real-time notifications:

```php
add_action('aevov_syncpro_config_updated', function($plugin, $new_config, $old_config) {
    // Send webhook notification
    wp_remote_post($webhook_url, [
        'body' => json_encode([
            'event' => 'config_updated',
            'plugin' => $plugin,
            'timestamp' => current_time('c')
        ])
    ]);
}, 10, 3);
```

---

## SDK Examples

### PHP

```php
// Generate a workflow
$response = wp_remote_post(
    rest_url('aevov-syncpro/v1/generate/workflow'),
    [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        ],
        'body' => json_encode([
            'prompt' => 'Set up AI content generation',
            'options' => ['target' => 'ai_engines']
        ])
    ]
);

$workflow = json_decode(wp_remote_retrieve_body($response), true);
```

### JavaScript

```javascript
// Using fetch
const response = await fetch('/wp-json/aevov-syncpro/v1/generate/workflow', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        prompt: 'Set up AI content generation',
        options: { target: 'ai_engines' }
    })
});

const workflow = await response.json();
```

### cURL

```bash
# Get system context
curl -X GET "https://example.com/wp-json/aevov-syncpro/v1/context" \
    -H "Authorization: Basic $(echo -n 'user:application_password' | base64)"

# Generate workflow
curl -X POST "https://example.com/wp-json/aevov-syncpro/v1/generate/workflow" \
    -H "Content-Type: application/json" \
    -H "Authorization: Basic $(echo -n 'user:application_password' | base64)" \
    -d '{"prompt": "Set up distributed storage"}'
```

---

## Changelog

### v1.0.0
- Initial release
- Full 36-plugin integration
- BIDC synchronization
- 8 pre-built templates
- Complete REST API
