# AevSyncPro

**Intelligent AI-Powered Workflow Orchestration for the Aevov Ecosystem**

AevSyncPro is a revolutionary configuration and workflow generation system that integrates with all 36 plugins in the Aevov ecosystem. It provides "onboarding on steroids" - allowing users to interact with the entire Aevov engine through natural language workflows that generate ready-to-use configurations.

## Features

### Core Capabilities

- **Natural Language Configuration**: Describe what you want in plain English, and AevSyncPro generates complete configurations
- **Full Ecosystem Context**: Awareness of all 36 Aevov plugins, their capabilities, and interdependencies
- **Real-time Database Synchronization (BIDC)**: Bidirectional Incremental Data Connectivity tracks and syncs all configuration changes
- **Workflow Integration**: Registers as a `syncpro` node type in the Aevov Workflow Engine
- **Rollback Support**: Full rollback capability for any configuration changes
- **Ready-to-Use Outputs**: Every workflow produces production-ready configuration bundles

### Plugin Categories Covered

| Category | Plugins | Description |
|----------|---------|-------------|
| Core Infrastructure | 2 | aevov-core, aevov-ai-core |
| AI/ML Engines | 7 | Language, Image, Cognitive, Reasoning, Embedding, Neuro-Architect |
| Workflow & Orchestration | 2 | Workflow Engine, SyncPro |
| Memory & Knowledge | 4 | Memory Core, Chunk Registry, Meshcore, Pattern Sync |
| Application Generation | 3 | Application Forge, Super App Forge, Chat UI |
| Data & Storage | 4 | Cubbit CDN, Stream, Transcription |
| Visualization & Monitoring | 3 | Unified Dashboard, Vision Depth, Diagnostic Network |
| Security | 3 | Security, Security Monitor, Runtime |
| Pattern Recognition | 3 | BLOOM Pattern Recognition, Chunk Scanner, APS Tools |
| Specialized Processing | 5 | Music Forge, Simulation, Physics, Demo System, Onboarding |

## Installation

### Requirements

- WordPress 6.0+
- PHP 8.0+
- Node.js 18+ (for frontend build)
- Aevov Workflow Engine (recommended)

### Setup

1. Upload the `aevov-syncpro` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Navigate to **AevSyncPro** in the admin menu
4. (Optional) Build frontend: `cd aevov-syncpro && npm install && npm run build`

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           AevSyncPro                                     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                    React Dashboard (Frontend)                     │   │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │   │
│  │  │ System   │  │ Workflow │  │ Config   │  │ Template Gallery │  │   │
│  │  │ Overview │  │Generator │  │ Panel    │  │ + Sync Status    │  │   │
│  │  └──────────┘  └──────────┘  └──────────┘  └──────────────────┘  │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                    │                                     │
│                                    ▼                                     │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                    REST API Controller                            │   │
│  │  /context  /generate/workflow  /bundle  /apply  /sync  /templates │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                    │                                     │
│         ┌──────────────────────────┼──────────────────────────┐          │
│         ▼                          ▼                          ▼          │
│  ┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐     │
│  │ System Context  │    │  AI Orchestrator │    │ Database Sync   │     │
│  │    Provider     │    │                  │    │   Controller    │     │
│  │                 │    │  ┌────────────┐  │    │                 │     │
│  │ • 36 Plugins    │    │  │ Cognitive  │  │    │ • BIDC Tracking │     │
│  │ • Capabilities  │────│  │ Reasoning  │  │────│ • Rollback      │     │
│  │ • Configs       │    │  │ Language   │  │    │ • History       │     │
│  │ • Statistics    │    │  └────────────┘  │    │ • Apply         │     │
│  └─────────────────┘    └──────────────────┘    └─────────────────┘     │
│                                    │                                     │
│                                    ▼                                     │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                  Configuration Generator                          │   │
│  │  • Template-based generation    • Cross-system optimization      │   │
│  │  • Validation & verification    • Export (JSON/YAML/PHP)         │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                    │                                     │
│                                    ▼                                     │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │              Aevov Ecosystem (36 Plugins)                         │   │
│  │  AI Core • Language • Image • Cognitive • Memory • Workflows     │   │
│  │  Security • Patterns • Storage • Monitoring • Applications       │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

## Usage

### Natural Language Workflow Generation

```php
// Example: Generate a workflow from natural language
$response = wp_remote_post(rest_url('aevov-syncpro/v1/generate/workflow'), [
    'headers' => ['Content-Type' => 'application/json'],
    'body' => json_encode([
        'prompt' => 'Set up a high-performance AI content generation system with secure authentication',
        'options' => ['target' => 'all', 'auto_apply' => false]
    ])
]);
```

### Configuration Bundle Generation

```php
// Generate production-ready configurations
$bundle = wp_remote_post(rest_url('aevov-syncpro/v1/bundle'), [
    'body' => json_encode([
        'requirements' => [
            'prompt' => 'Configure for maximum performance',
            'security' => 'strict',
            'storage' => 'distributed'
        ]
    ])
]);
```

### Using SyncPro in Workflows

AevSyncPro registers as a workflow node type:

```json
{
    "id": "syncpro_node",
    "type": "syncpro",
    "data": {
        "label": "Configure AI Engines",
        "mode": "generate",
        "target": "ai_engines",
        "auto_apply": false
    }
}
```

**Available Modes:**
- `analyze` - Analyze current system state
- `generate` - Generate new configurations
- `modify` - Modify existing configurations
- `apply` - Apply configuration bundle

**Available Targets:**
- `all` - All systems
- `ai_engines` - AI/ML engines
- `storage` - Storage systems
- `workflows` - Workflow settings
- `security` - Security configuration
- `patterns` - Pattern recognition
- `memory` - Memory systems
- `network` - Network/Meshcore

## API Reference

### Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/context` | GET | Get full system context |
| `/context/{component}` | GET | Get specific component context |
| `/generate/workflow` | POST | Generate workflow from prompt |
| `/generate/config` | POST | Generate configuration for target |
| `/bundle` | POST | Generate configuration bundle |
| `/apply` | POST | Apply configuration bundle |
| `/sync/start` | POST | Start sync session |
| `/sync/status` | GET | Get sync operation status |
| `/sync/rollback/{id}` | POST | Rollback execution |
| `/templates` | GET/POST | List/create templates |
| `/templates/{id}` | GET/PUT/DELETE | Manage specific template |
| `/history` | GET | Configuration change history |
| `/execute` | POST | Execute SyncPro node |
| `/health` | GET | Health check |
| `/export` | POST | Export configuration |
| `/import` | POST | Import configuration |

### Request/Response Examples

#### Generate Workflow
```bash
curl -X POST /wp-json/aevov-syncpro/v1/generate/workflow \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Set up distributed storage with Cubbit CDN"}'
```

Response:
```json
{
    "success": true,
    "workflow": {
        "id": "wf_abc123",
        "name": "Distributed Storage Setup",
        "nodes": [...],
        "edges": [...]
    },
    "analysis": {
        "goal": "Set up distributed storage",
        "required_capabilities": ["cdn_storage", "chunk_retrieval"],
        "configurations": ["storage", "network"]
    },
    "validation": {"valid": true, "issues": []}
}
```

#### Apply Configuration
```bash
curl -X POST /wp-json/aevov-syncpro/v1/apply \
  -H "Content-Type: application/json" \
  -d '{"bundle": {...}, "dry_run": false}'
```

## Pre-built Templates

AevSyncPro includes 8 pre-built workflow templates:

1. **Complete System Setup** - Full ecosystem configuration
2. **AI Engine Optimization** - Optimize all AI engines
3. **Security Hardening** - Enterprise-grade security
4. **Distributed Storage Setup** - Cubbit CDN integration
5. **Content Pipeline** - AI content generation workflow
6. **Pattern Synchronization** - BLOOM pattern setup
7. **User Onboarding Flow** - Guided user setup
8. **Analytics Dashboard** - Monitoring configuration

## Database Tables

AevSyncPro creates these tables:

| Table | Purpose |
|-------|---------|
| `{prefix}aevsync_config_history` | Configuration change history |
| `{prefix}aevsync_operations` | Sync operation log |
| `{prefix}aevsync_context_cache` | Performance cache |

## Custom Post Types

- `aevsync_config` - Stored configurations
- `aevsync_template` - Workflow templates

## Hooks & Filters

### Actions
```php
// After configuration is applied
do_action('aevov_syncpro_config_updated', $plugin, $new_config, $old_config);

// Per-plugin configuration update
do_action('aevov_syncpro_config_updated_{plugin}', $new_config, $old_config);
```

### Filters
```php
// Modify configuration before applying
add_filter('aevov_syncpro_config_{target}', function($config, $requirements) {
    return $config;
}, 10, 2);

// Modify any configuration
add_filter('aevov_syncpro_config', function($config, $target, $requirements) {
    return $config;
}, 10, 3);
```

## Frontend Components

The React dashboard includes:

- **Dashboard** - Main interface with tabs
- **SystemOverview** - Real-time system health
- **WorkflowGenerator** - Natural language input
- **ConfigurationPanel** - View/edit configurations
- **TemplateGallery** - Browse templates
- **SyncStatus** - Monitor sync operations

### Building Frontend

```bash
cd aevov-syncpro
npm install
npm run build    # Production build
npm run dev      # Development with hot reload
```

## Integration with Workflow Engine

AevSyncPro automatically integrates with aevov-workflow-engine:

```php
// SyncPro registers its capability
add_filter('aevov_workflow_capabilities', function($caps) {
    $caps['syncpro'] = [
        'type' => 'syncpro',
        'label' => 'AevSyncPro',
        'category' => 'capability',
        // ... full node definition
    ];
    return $caps;
});

// Hook into workflow execution
add_action('aevov_workflow_node_executed', [$db_sync, 'on_node_executed'], 10, 3);
add_action('aevov_workflow_completed', [$db_sync, 'on_workflow_completed'], 10, 2);
```

## Security Considerations

- All endpoints require authentication
- Admin-level permission required for `apply` and `rollback`
- JWT token support for API access
- All sync operations are logged for audit
- Rollback available for any applied configuration

## Troubleshooting

### Common Issues

1. **"No configuration loaded"** - Generate a workflow first
2. **"Plugin not found"** - Ensure target plugin is installed
3. **"Sync operation failed"** - Check database permissions
4. **"Rollback not available"** - Check config history table

### Debug Mode

Enable debug logging:
```php
define('AEVOV_SYNCPRO_DEBUG', true);
```

Logs are written to: `wp-content/debug.log`

## Contributing

1. Fork the repository
2. Create feature branch
3. Submit pull request

## License

GPL-2.0-or-later

## Version History

- **1.0.0** - Initial release with full 36-plugin integration
