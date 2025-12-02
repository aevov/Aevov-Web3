# Aevov AI Core - Unified AI Provider System

## Overview

**Aevov AI Core** provides a unified interface for multiple AI providers with comprehensive support for:
- ✅ **DeepSeek** (DeepSeek-V3, DeepSeek-Coder, DeepSeek-R1)
- ✅ **MiniMax** (240K-1M context, TTS, Music Generation)
- ✅ **OpenAI** (GPT-4, GPT-3.5)
- ✅ **Anthropic** (Claude 3.5 Sonnet, Claude 3 Opus)

Additionally provides:
- ✅ **.aev Model Framework** (Custom model format for fine-tuning)
- ✅ **Model Extraction** (Convert from various formats to .aev)
- ✅ **Comprehensive Debugging Engine** (Ecosystem-wide monitoring)
- ✅ **Automatic Stealth Integration** (All requests routed through mesh)

## Why This Matters

### 1. Provider Flexibility
Switch between AI providers seamlessly:
```php
// Use DeepSeek
$ai->complete('deepseek', ['messages' => [...], 'model' => 'deepseek-chat']);

// Switch to MiniMax
$ai->complete('minimax', ['messages' => [...], 'model' => 'abab6.5s-chat']);

// All with the same interface!
```

### 2. Cost Optimization
Different providers for different tasks:
- **DeepSeek**: Best cost/performance ($0.14/$0.28 per 1M tokens)
- **MiniMax**: Ultra-long context (1M tokens!), TTS, Music
- **OpenAI**: Industry standard
- **Anthropic**: Best reasoning

### 3. .aev Models
Custom model format enables:
- Fine-tuning on your data
- Model versioning and rollback
- Cross-provider model transfer
- Model extraction from responses

## DeepSeek Integration

### Features
- **Models**: DeepSeek-Chat, DeepSeek-Coder, DeepSeek-R1 (Reasoner)
- **Context**: Up to 64K tokens (R1)
- **Streaming**: Yes
- **Function Calling**: Yes
- **Embeddings**: Yes
- **Cost**: $0.14/$0.28 per 1M tokens (very affordable!)

### Usage
```php
$ai_core = \Aevov\AICore\AICore::get_instance();
$provider = $ai_core->get_provider_manager();

$result = $provider->complete('deepseek', [
    'model' => 'deepseek-chat',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'Explain quantum computing']
    ],
    'temperature' => 0.7,
    'max_tokens' => 2000
]);

echo $result['content'];
```

### DeepSeek-Coder
Specialized for coding tasks:
```php
$result = $provider->complete('deepseek', [
    'model' => 'deepseek-coder',
    'messages' => [
        ['role' => 'user', 'content' => 'Write a Python function to calculate Fibonacci']
    ]
]);
```

### DeepSeek-R1 (Reasoner)
Advanced reasoning with chain-of-thought:
```php
$result = $provider->complete('deepseek', [
    'model' => 'deepseek-reasoner',
    'messages' => [
        ['role' => 'user', 'content' => 'Solve this logic puzzle: ...']
    ],
    'max_tokens' => 8000 // R1 can output up to 8K tokens
]);
```

## MiniMax Integration

### Features
- **Models**: abab6.5s-chat, abab6.5-chat, abab6.5g-chat
- **Context**: Up to 1M tokens! (abab6.5g-chat)
- **Text-to-Speech**: Multiple voices, Chinese and English
- **Music Generation**: AI-generated music from text
- **Embeddings**: High-quality text embeddings

### Usage
```php
// Standard chat
$result = $provider->complete('minimax', [
    'model' => 'abab6.5s-chat',
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!']
    ]
]);

// Ultra-long context (1M tokens!)
$result = $provider->complete('minimax', [
    'model' => 'abab6.5g-chat',
    'messages' => [
        ['role' => 'user', 'content' => $very_long_document]
    ]
]);
```

### Text-to-Speech
```php
$minimax = $provider->get_provider('minimax');

$audio = $minimax->text_to_speech('Hello, this is a test.', [
    'voice_id' => 'male-qn-qingse',
    'speed' => 1.0,
    'volume' => 1.0,
    'pitch' => 0
]);

// Save audio file
file_put_contents('output.mp3', base64_decode($audio['audio_file']));
```

### Music Generation
```php
$music = $minimax->generate_music('Upbeat electronic dance music with heavy bass', [
    'duration' => 30,
    'genre' => 'edm'
]);

file_put_contents('music.mp3', base64_decode($music['music_file']));
```

## .aev Model Framework

### What is .aev?
Custom model format for the Aevov ecosystem:
- **Portable**: Transfer models between systems
- **Versioned**: Track model versions and changes
- **Fine-tunable**: Train on your specific data
- **Compatible**: Convert from/to other formats

### .aev File Structure
```json
{
    "model_id": "my-model-v1",
    "base_model": "deepseek-chat",
    "provider": "deepseek",
    "version": "1.0.0",
    "capabilities": {
        "chat": true,
        "completion": true,
        "embeddings": true
    },
    "parameters": {
        "temperature": 0.7,
        "top_p": 0.9,
        "max_tokens": 2000
    },
    "fine_tuning": {
        "dataset": "path/to/dataset.jsonl",
        "epochs": 3,
        "learning_rate": 0.0001
    },
    "weights": {
        "format": "safetensors",
        "path": "weights/model.safetensors",
        "sha256": "abc123..."
    },
    "metadata": {
        "description": "Fine-tuned for customer support",
        "author": "Your Company",
        "created_at": "2025-01-15T10:00:00Z"
    }
}
```

### Creating .aev Models
```php
$model_manager = $ai_core->get_model_manager();

// Create from scratch
$model = $model_manager->create_model([
    'model_name' => 'Support Bot v1',
    'base_model' => 'deepseek-chat',
    'provider' => 'deepseek',
    'fine_tuning_data' => [
        ['input' => 'How do I reset password?', 'output' => '...'],
        ['input' => 'Where is my order?', 'output' => '...']
    ]
]);

// Save as .aev file
$model_manager->save_model($model, 'support-bot-v1.aev');

// Load .aev model
$model = $model_manager->load_model('support-bot-v1.aev');

// Use the model
$result = $model_manager->complete($model, [
    'messages' => [['role' => 'user', 'content' => 'Help!']]
]);
```

### Model Extraction
Extract model knowledge from API responses:
```php
$extractor = $model_manager->get_extractor();

// Extract from conversation history
$extracted_model = $extractor->extract_from_conversations([
    // Array of conversations
]);

// Convert to .aev
$aev_model = $extractor->convert_to_aev($extracted_model);
$model_manager->save_model($aev_model, 'extracted.aev');
```

## Comprehensive Debugging Engine

### Features
- ✅ **Real-time Logging** - All ecosystem events captured
- ✅ **Stack Trace Analysis** - Automatic error tracking
- ✅ **Performance Profiling** - Identify bottlenecks
- ✅ **Memory Tracking** - Memory usage monitoring
- ✅ **Network Monitoring** - All API calls logged
- ✅ **Error Aggregation** - Group similar errors
- ✅ **Live Dashboard** - Real-time debugging UI

### Usage
```php
$debug = $ai_core->get_debug_engine();

// Enable debugging
$debug->enable();

// Log custom events
$debug->log('info', 'MyComponent', 'Processing started', [
    'user_id' => 123,
    'action' => 'generate'
]);

// Profile code
$debug->profile('expensive_operation', function() {
    // Your code here
});

// Get performance stats
$stats = $debug->get_stats();
```

### Debug Dashboard
Access at: **Admin → AI Core → Debug Console**

Shows:
- Recent log entries (filterable by level/component)
- Performance metrics (response times, memory usage)
- Error summary (grouped by type)
- API call history (with request/response)
- System health (memory, CPU, disk)

## Integration with Aevov Plugins

### Automatic Integration
AI Core automatically integrates with:
- ✅ **Language Engine** - Text generation
- ✅ **Image Engine** - Image generation (future)
- ✅ **Cognitive Engine** - Reasoning and planning
- ✅ **Music Forge** - Music generation (via MiniMax)
- ✅ **BLOOM** - Pattern recognition
- ✅ **AROS** - Robot control and planning

### Language Engine Example
```php
// Language Engine automatically uses AI Core
$language_engine = AevovLanguageEngine::get_instance();

// Specify provider
$result = $language_engine->generate([
    'provider' => 'deepseek',
    'model' => 'deepseek-chat',
    'prompt' => 'Write a blog post about AI'
]);

// Or use default (configured in settings)
$result = $language_engine->generate([
    'prompt' => 'Write a blog post about AI'
]);
```

### Music Forge with MiniMax
```php
// Music Forge uses MiniMax for generation
$music_forge = AevovMusicForge::get_instance();

$music = $music_forge->generate([
    'provider' => 'minimax',
    'prompt' => 'Ambient space music with synthesizers',
    'duration' => 60
]);

// Returns MP3 file
```

## Stealth Integration

**All AI requests automatically routed through Meshcore for privacy!**

When making any AI API call:
1. Request intercepted by Stealth Manager
2. Routed through onion network (3 hops)
3. Exit node makes final request to AI provider
4. AI provider sees exit node IP, not yours

Result: **Complete anonymity. No detection of AI usage or provider.**

## Cost Tracking

Built-in cost tracking for all providers:

```php
// Get usage statistics
$usage = $provider->get_usage_stats([
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-31'
]);

// Returns:
// [
//     'total_tokens_input' => 1000000,
//     'total_tokens_output' => 500000,
//     'total_cost' => 0.75,
//     'by_provider' => [
//         'deepseek' => ['tokens' => 800000, 'cost' => 0.22],
//         'minimax' => ['tokens' => 700000, 'cost' => 0.14]
//     ]
// ]
```

## Configuration

### API Keys
```php
// DeepSeek
update_option('aevov_deepseek_api_key', 'your-api-key');

// MiniMax
update_option('aevov_minimax_api_key', 'your-api-key');
update_option('aevov_minimax_group_id', 'your-group-id');

// OpenAI
update_option('aevov_openai_api_key', 'your-api-key');

// Anthropic
update_option('aevov_anthropic_api_key', 'your-api-key');
```

### Default Provider
```php
// Set default provider for all Aevov plugins
update_option('aevov_default_ai_provider', 'deepseek');
update_option('aevov_default_ai_model', 'deepseek-chat');
```

### Debug Settings
```php
// Enable debugging
update_option('aevov_debug_enabled', true);

// Set log level (debug, info, warning, error)
update_option('aevov_debug_level', 'debug');

// Log retention (days)
update_option('aevov_debug_retention', 7);
```

## Pricing Comparison

| Provider | Model | Input (1M tokens) | Output (1M tokens) | Context | Notes |
|----------|-------|-------------------|-------------------|---------|-------|
| DeepSeek | Chat | $0.14 | $0.28 | 32K | Best value |
| DeepSeek | R1 | $0.55 | $2.19 | 64K | Reasoning |
| MiniMax | 6.5s | ~$0.10 | ~$0.10 | 240K | Long context |
| MiniMax | 6.5g | ~$0.20 | ~$0.20 | **1M** | Ultra-long |
| OpenAI | GPT-4 | $10.00 | $30.00 | 128K | Industry standard |
| Anthropic | Opus | $15.00 | $75.00 | 200K | Best reasoning |

**DeepSeek offers 50-100x cost reduction compared to OpenAI/Anthropic!**

## Performance

### Latency (with Stealth)
- DeepSeek: ~800-2000ms (including onion routing)
- MiniMax: ~900-2100ms
- OpenAI: ~700-1900ms
- Anthropic: ~800-2000ms

### Throughput
- Supports parallel requests across providers
- Automatic load balancing
- Request queue with prioritization

## Requirements

- WordPress 6.3+
- PHP 7.4+
- Aevov Meshcore (for stealth routing)
- API keys for desired providers

## Installation

1. Upload `aevov-ai-core` to `/wp-content/plugins/`
2. Activate the plugin
3. Go to **AI Core → Providers**
4. Enter API keys
5. Test configuration
6. Select default provider

## Roadmap

- [ ] **Vision Support** - Image understanding (GPT-4V, Claude)
- [ ] **Voice Cloning** - Custom voices (MiniMax TTS)
- [ ] **Model Fine-Tuning UI** - Web-based fine-tuning
- [ ] **A/B Testing** - Compare providers automatically
- [ ] **Caching Layer** - Cache responses for cost savings
- [ ] **Fallback Chain** - Auto-retry with different provider
- [ ] **Local Models** - Support for local LLMs (Ollama, LM Studio)

---

**Unified AI. Maximum Flexibility. Complete Privacy.** 🤖✨
