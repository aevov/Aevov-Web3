<?php
/**
 * System Context Provider
 *
 * Provides comprehensive context about the entire Aevov ecosystem to enable
 * intelligent workflow generation and configuration.
 *
 * @package AevovSyncPro
 */

namespace AevovSyncPro\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * System Context Provider Class
 *
 * This is the brain of AevSyncPro - it gathers complete knowledge about every
 * aspect of the Aevov system to enable AI-powered configuration generation.
 *
 * Total plugins in ecosystem: 36
 */
class SystemContextProvider {

    private array $context_cache = [];
    private int $cache_ttl = 300; // 5 minutes

    /**
     * Complete Aevov Ecosystem - All 36 Plugins
     *
     * Categories:
     * - Core Infrastructure (2)
     * - AI/ML Engines (7)
     * - Workflow & Orchestration (2)
     * - Memory & Knowledge (4)
     * - Application Generation (3)
     * - Data & Storage (4)
     * - Visualization & Monitoring (3)
     * - Security (3)
     * - Pattern Recognition (3)
     * - Specialized Processing (5)
     */
    private const AEVOV_PLUGINS = [
        // ═══════════════════════════════════════════════════════════════════
        // CORE INFRASTRUCTURE (2 plugins)
        // ═══════════════════════════════════════════════════════════════════
        'aevov-core' => [
            'class' => 'AevovCore\\AevovCore',
            'category' => 'core',
            'description' => 'Core infrastructure providing API key management, rate limiting, and shared utilities',
            'capabilities' => ['api_key_management', 'rate_limiting', 'system_utilities'],
            'endpoints' => ['/test', '/status'],
            'config_options' => ['api_keys', 'rate_limits', 'redis_config'],
            'priority' => 1,
        ],
        'aevov-ai-core' => [
            'class' => 'Aevov\\AICore\\AICore',
            'category' => 'core',
            'description' => 'Unified AI provider system with DeepSeek, MiniMax, OpenAI, Anthropic and .aev model framework',
            'capabilities' => ['provider_management', 'model_selection', 'api_routing', 'model_extraction', 'debugging'],
            'endpoints' => ['/providers', '/providers/{provider}/complete', '/models', '/models/extract', '/debug/logs', '/debug/stats'],
            'config_options' => ['default_provider', 'api_keys', 'model_preferences', 'rate_limits', 'debug_enabled'],
            'database_tables' => ['aev_models', 'aev_model_usage', 'aev_debug_logs', 'aev_performance'],
            'priority' => 1,
        ],

        // ═══════════════════════════════════════════════════════════════════
        // AI/ML ENGINES (7 plugins)
        // ═══════════════════════════════════════════════════════════════════
        'aevov-language-engine' => [
            'class' => 'AevovLanguageEngine\\LanguageEngine',
            'category' => 'ai_engine',
            'description' => 'Large Language Model engine with chunked ingestion, text generation, and semantic analysis',
            'capabilities' => ['text_generation', 'sentiment_analysis', 'summarization', 'translation', 'intent_classification', 'tokenization'],
            'endpoints' => ['/generate', '/analyze', '/summarize'],
            'config_options' => ['default_model', 'max_tokens', 'temperature', 'system_prompts'],
        ],
        'aevov-language-engine-v2' => [
            'class' => 'AevovLanguageEngineV2\\LanguageEngineV2',
            'category' => 'ai_engine',
            'description' => 'Enhanced Language Engine v2 with improved capabilities',
            'capabilities' => ['advanced_text_generation', 'multi_model_support', 'context_management'],
            'endpoints' => ['/generate', '/chat', '/complete'],
            'config_options' => ['model_version', 'context_window', 'streaming'],
        ],
        'aevov-cognitive-engine' => [
            'class' => 'AevovCognitiveEngine\\CognitiveEngine',
            'category' => 'ai_engine',
            'description' => 'Cognitive layer implementing Hierarchical Reasoning Model (HRM) for complex problem solving',
            'capabilities' => ['hierarchical_reasoning', 'planning', 'problem_solving', 'intent_analysis'],
            'endpoints' => ['/reason', '/plan', '/analyze'],
            'config_options' => ['reasoning_depth', 'context_window', 'chain_of_thought'],
        ],
        'aevov-reasoning-engine' => [
            'class' => 'AevovReasoningEngine\\ReasoningEngine',
            'category' => 'ai_engine',
            'description' => 'Analogy-based reasoning engine for logical inference and deduction',
            'capabilities' => ['logical_inference', 'deduction', 'hypothesis_testing', 'pattern_matching'],
            'endpoints' => ['/infer', '/deduce', '/validate'],
            'config_options' => ['inference_rules', 'confidence_threshold'],
        ],
        'aevov-embedding-engine' => [
            'class' => 'AevovEmbeddingEngine\\EmbeddingEngine',
            'category' => 'ai_engine',
            'description' => 'Vector embedding service for text, images, and multi-modal data',
            'capabilities' => ['text_embedding', 'image_embedding', 'similarity_search', 'clustering'],
            'endpoints' => ['/embed', '/search', '/cluster'],
            'config_options' => ['embedding_model', 'dimensions', 'index_type'],
        ],
        'aevov-image-engine' => [
            'class' => 'AevovImageEngine\\ImageEngine',
            'category' => 'ai_engine',
            'description' => 'Advanced image generation with upscaling and worker-based processing',
            'capabilities' => ['image_generation', 'image_upscaling', 'style_transfer', 'image_editing'],
            'endpoints' => ['/generate', '/upscale', '/edit', '/variations'],
            'config_options' => ['default_size', 'quality', 'style_presets', 'worker_enabled'],
            'database_tables' => ['aevov_image_jobs'],
            'scheduled_tasks' => ['aevov_image_engine_cron'],
        ],
        'aevov-neuro-architect' => [
            'class' => 'AevovNeuroArchitect\\NeuroArchitect',
            'category' => 'ai_engine',
            'description' => 'Neural architecture design and optimization system',
            'capabilities' => ['architecture_design', 'neural_optimization', 'model_synthesis'],
            'endpoints' => ['/design', '/optimize', '/synthesize'],
            'config_options' => ['architecture_type', 'optimization_target'],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // WORKFLOW & ORCHESTRATION (2 plugins)
        // ═══════════════════════════════════════════════════════════════════
        'aevov-workflow-engine' => [
            'class' => 'AevovWorkflowEngine\\WorkflowEngine',
            'category' => 'workflow',
            'description' => 'Visual drag-and-drop workflow builder with execution, scheduling, and template support',
            'capabilities' => ['workflow_design', 'workflow_execution', 'scheduling', 'templating', 'node_management'],
            'endpoints' => ['/workflows', '/workflows/{id}', '/workflows/{id}/execute', '/executions', '/templates', '/node-types', '/capabilities'],
            'config_options' => ['max_execution_time', 'max_nodes', 'concurrent_executions', 'cors_enabled'],
            'database_tables' => ['aevov_workflows', 'aevov_workflow_executions', 'aevov_workflow_schedules'],
        ],
        'aevov-syncpro' => [
            'class' => 'AevovSyncPro\\AevSyncPro',
            'category' => 'workflow',
            'description' => 'Intelligent AI-powered workflow orchestration with real-time database synchronization',
            'capabilities' => ['natural_language_config', 'system_context', 'config_generation', 'db_sync', 'rollback'],
            'endpoints' => ['/context', '/generate/workflow', '/generate/config', '/bundle', '/apply', '/sync/status', '/templates'],
            'config_options' => ['auto_apply', 'sync_interval', 'rollback_enabled'],
            'database_tables' => ['aevsync_config_history', 'aevsync_operations', 'aevsync_context_cache'],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // MEMORY & KNOWLEDGE (4 plugins)
        // ═══════════════════════════════════════════════════════════════════
        'aevov-memory-core' => [
            'class' => 'AevovMemoryCore\\MemoryCore',
            'category' => 'memory',
            'description' => 'Dynamic biologically-inspired memory system with astrocyte architecture',
            'capabilities' => ['memory_storage', 'retrieval', 'pattern_association', 'knowledge_graph'],
            'endpoints' => ['/memory', '/memory/{address}', '/query', '/graph'],
            'config_options' => ['storage_backend', 'max_memory_size', 'retention_policy', 'cubbit_offload'],
            'database_tables' => ['aevov_memory_data'],
            'custom_post_types' => ['astrocyte'],
        ],
        'aevov-chunk-registry' => [
            'class' => 'AevovChunkRegistry\\ChunkRegistry',
            'category' => 'memory',
            'description' => 'Central registry for all Aevov chunks with metadata and dependency tracking',
            'capabilities' => ['chunk_storage', 'chunk_retrieval', 'metadata_management', 'dependency_tracking'],
            'config_options' => ['chunk_size', 'index_strategy', 'compression', 'cubbit_integration'],
            'database_tables' => ['aevov_chunks'],
        ],
        'aevov-meshcore' => [
            'class' => 'AevovMeshcore\\Meshcore',
            'category' => 'memory',
            'description' => 'P2P distributed networking layer for mesh coordination',
            'capabilities' => ['peer_discovery', 'data_sync', 'distributed_storage', 'mesh_coordination'],
            'endpoints' => ['/peers', '/sync', '/broadcast'],
            'config_options' => ['bootstrap_nodes', 'sync_strategy', 'encryption', 'max_peers'],
        ],
        'aevov-pattern-sync-protocol' => [
            'class' => 'AevovPatternSyncProtocol\\SyncProtocol',
            'category' => 'memory',
            'description' => 'Core pattern synchronization and blockchain protocol for distributed pattern management',
            'capabilities' => ['pattern_sync', 'blockchain_verification', 'distributed_consensus'],
            'endpoints' => ['/sync', '/verify', '/consensus'],
            'config_options' => ['sync_interval', 'verification_mode', 'consensus_algorithm'],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // APPLICATION GENERATION (3 plugins)
        // ═══════════════════════════════════════════════════════════════════
        'aevov-application-forge' => [
            'class' => 'AevovApplicationForge\\ApplicationForge',
            'category' => 'app_generation',
            'description' => 'Real-time application generation and streaming with WebSocket support',
            'capabilities' => ['code_generation', 'realtime_streaming', 'websocket_server', 'job_management'],
            'endpoints' => ['/generate', '/stream', '/jobs'],
            'config_options' => ['worker_url', 'streaming_enabled', 'output_format'],
        ],
        'aevov-super-app-forge' => [
            'class' => 'AevovSuperAppForge\\SuperAppForge',
            'category' => 'app_generation',
            'description' => 'Comprehensive AI-powered super application generation framework',
            'capabilities' => ['app_generation', 'ui_generation', 'api_generation', 'full_stack_generation'],
            'endpoints' => ['/generate', '/preview', '/deploy'],
            'config_options' => ['framework', 'styling', 'deployment_target', 'include_backend'],
        ],
        'aevov-chat-ui' => [
            'class' => 'AevovChatUI\\ChatUI',
            'category' => 'app_generation',
            'description' => 'Reusable chat interface component for Aevov network integration',
            'capabilities' => ['chat_interface', 'realtime_chat', 'plugin_integration'],
            'config_options' => ['theme', 'position', 'integration_mode'],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // DATA & STORAGE (4 plugins)
        // ═══════════════════════════════════════════════════════════════════
        'aevov-cubbit-cdn' => [
            'class' => 'AevovCubbitCdn\\CubbitCdn',
            'category' => 'storage',
            'description' => 'Web3 CDN integration with Cubbit for distributed storage',
            'capabilities' => ['cdn_storage', 'geo_distribution', 'caching', 'chunk_retrieval'],
            'endpoints' => ['/get-chunk-url/{id}'],
            'config_options' => ['access_key', 'secret_key', 'bucket', 'region', 'cache_ttl'],
        ],
        'aevov-cubbit-downloader' => [
            'class' => 'AevovCubbitDownloader\\CubbitDownloader',
            'category' => 'storage',
            'description' => 'Content download management from Cubbit CDN',
            'capabilities' => ['content_download', 'resume_support', 'integrity_check'],
            'config_options' => ['download_path', 'chunk_size', 'verify_integrity'],
        ],
        'aevov-stream' => [
            'class' => 'AevovStream\\Stream',
            'category' => 'storage',
            'description' => 'Data streaming system for video and audio content',
            'capabilities' => ['video_streaming', 'audio_streaming', 'live_broadcast', 'recording'],
            'endpoints' => ['/stream', '/broadcast', '/record'],
            'config_options' => ['streaming_quality', 'protocols', 'cdn_settings', 'buffer_size'],
        ],
        'aevov-transcription-engine' => [
            'class' => 'AevovTranscriptionEngine\\TranscriptionEngine',
            'category' => 'storage',
            'description' => 'Audio transcription and speech-to-text processing',
            'capabilities' => ['speech_to_text', 'real_time_transcription', 'speaker_diarization', 'language_detection'],
            'endpoints' => ['/transcribe', '/stream', '/diarize'],
            'config_options' => ['language', 'model_size', 'punctuation', 'speaker_labels'],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // VISUALIZATION & MONITORING (3 plugins)
        // ═══════════════════════════════════════════════════════════════════
        'aevov-unified-dashboard' => [
            'class' => 'AevovUnifiedDashboard\\UnifiedDashboard',
            'category' => 'monitoring',
            'description' => 'Sophisticated control center integrating all 36 Aevov plugins with comprehensive monitoring',
            'capabilities' => ['plugin_management', 'status_monitoring', 'pattern_visualization', 'performance_analytics', 'onboarding'],
            'endpoints' => ['/dashboard/stats', '/plugins/status', '/patterns/create'],
            'config_options' => ['refresh_interval', 'widgets_enabled', 'notification_settings'],
        ],
        'aevov-vision-depth' => [
            'class' => 'AevovVisionDepth\\Vision_Depth',
            'category' => 'monitoring',
            'description' => 'Privacy-first behavioral intelligence system using Ultimate Web Scraper',
            'capabilities' => ['web_scraping', 'behavioral_analysis', 'consent_management', 'privacy_analytics'],
            'endpoints' => ['/scrape', '/consent', '/data'],
            'config_options' => ['privacy_level', 'consent_required', 'data_retention'],
        ],
        'aevov-diagnostic-network' => [
            'class' => 'AevovDiagnosticNetwork\\DiagnosticNetwork',
            'category' => 'monitoring',
            'description' => 'System health and diagnostic monitoring across the Aevov ecosystem',
            'capabilities' => ['health_monitoring', 'diagnostics', 'alerting', 'performance_tracking'],
            'endpoints' => ['/health', '/diagnostics', '/alerts'],
            'config_options' => ['check_interval', 'alert_thresholds', 'notification_channels'],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // SECURITY (3 plugins)
        // ═══════════════════════════════════════════════════════════════════
        'aevov-security' => [
            'class' => 'AevovSecurity\\Security',
            'category' => 'security',
            'description' => 'Core security module for authentication, encryption, and access control',
            'capabilities' => ['authentication', 'encryption', 'audit_logging', 'access_control'],
            'config_options' => ['auth_methods', 'encryption_algorithm', 'session_duration', 'rate_limiting'],
        ],
        'aevov-security-monitor' => [
            'class' => 'AevovSecurityMonitor\\SecurityMonitor',
            'category' => 'security',
            'description' => 'Advanced security monitoring with Ghost-inspired detection, YARA rules, and AevIP integration',
            'capabilities' => ['process_scanning', 'file_integrity', 'malware_detection', 'yara_rules', 'threat_intelligence'],
            'endpoints' => ['/scan', '/threats', '/rules'],
            'config_options' => ['scan_interval', 'yara_enabled', 'mitre_mapping', 'aevip_integration'],
            'database_tables' => ['aevov_security_events', 'aevov_security_scans', 'aevov_security_yara_rules'],
            'scheduled_tasks' => ['aevov_security_hourly_scan', 'aevov_security_daily_scan'],
        ],
        'aevov-runtime' => [
            'class' => 'AevovRuntime\\Runtime',
            'category' => 'security',
            'description' => 'Ultra-low-latency AI inference runtime with tile-based task decomposition (TileRT-inspired)',
            'capabilities' => ['tile_scheduling', 'low_latency_inference', 'resource_allocation', 'aevip_coordination'],
            'endpoints' => ['/schedule', '/execute', '/metrics'],
            'config_options' => ['tile_size', 'priority_mode', 'compute_overlap', 'aevip_nodes'],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // PATTERN RECOGNITION (3 plugins)
        // ═══════════════════════════════════════════════════════════════════
        'bloom-pattern-recognition' => [
            'class' => 'BLOOM_Pattern_System',
            'category' => 'patterns',
            'description' => 'Distributed pattern recognition system using BLOOM tensor chunks',
            'capabilities' => ['tensor_analysis', 'pattern_detection', 'pattern_sync', 'neural_processing', 'distributed_learning'],
            'endpoints' => ['/detect', '/sync', '/match', '/learn'],
            'config_options' => ['sync_interval', 'pattern_threshold', 'bloom_filters', 'multisite_enabled'],
        ],
        'bloom-chunk-scanner' => [
            'class' => 'BloomChunkScanner\\ChunkScanner',
            'category' => 'patterns',
            'description' => 'Scans media library for BLOOM JSON chunks with APS Tools integration',
            'capabilities' => ['chunk_scanning', 'json_detection', 'validation', 'progress_tracking'],
            'config_options' => ['scan_interval', 'max_file_size', 'json_validation'],
            'database_tables' => ['bloom_chunks'],
            'scheduled_tasks' => ['bloom_chunk_scanner_cron'],
        ],
        'aps-tools' => [
            'class' => 'APSTools\\APSTools',
            'category' => 'patterns',
            'description' => 'APS Tools for pattern processing and integration utilities',
            'capabilities' => ['pattern_processing', 'integration_utilities', 'data_transformation'],
            'config_options' => ['processing_mode', 'output_format'],
        ],

        // ═══════════════════════════════════════════════════════════════════
        // SPECIALIZED PROCESSING (5 plugins)
        // ═══════════════════════════════════════════════════════════════════
        'aevov-music-forge' => [
            'class' => 'AevovMusicForge\\MusicForge',
            'category' => 'specialized',
            'description' => 'AI-powered music composition and audio generation',
            'capabilities' => ['music_generation', 'audio_processing', 'sound_effects', 'midi_generation'],
            'endpoints' => ['/compose', '/remix', '/effects'],
            'config_options' => ['default_genre', 'tempo_range', 'output_format', 'quality'],
        ],
        'aevov-simulation-engine' => [
            'class' => 'AevovSimulationEngine\\SimulationEngine',
            'category' => 'specialized',
            'description' => 'Physics and environment simulation for modeling and testing',
            'capabilities' => ['physics_simulation', 'environment_modeling', 'scenario_testing', '3d_rendering'],
            'endpoints' => ['/simulate', '/model', '/test'],
            'config_options' => ['physics_engine', 'time_step', 'collision_detection', 'render_quality'],
        ],
        'aevov-physics-engine' => [
            'class' => 'AevovPhysicsEngine\\PhysicsEngine',
            'category' => 'specialized',
            'description' => 'Advanced physics computation engine for realistic simulations',
            'capabilities' => ['physics_computation', 'collision_detection', 'particle_systems', 'fluid_dynamics'],
            'endpoints' => ['/compute', '/collision', '/particles'],
            'config_options' => ['precision', 'solver_type', 'gpu_acceleration'],
        ],
        'aevov-demo-system' => [
            'class' => 'AevovDemo\\AevovDemoSystem',
            'category' => 'specialized',
            'description' => 'Comprehensive demo system with automated workflows and SLM integration',
            'capabilities' => ['demo_automation', 'slm_integration', 'typebot_integration', 'guided_demos'],
            'endpoints' => ['/demo/start', '/demo/status'],
            'config_options' => ['demo_mode', 'slm_model', 'typebot_api'],
            'dependencies' => ['aevov-chat-ui', 'aps-tools', 'bloom-pattern-recognition'],
        ],
        'aevov-onboarding-system' => [
            'class' => 'AevovOnboarding\\OnboardingSystem',
            'category' => 'specialized',
            'description' => 'Multi-step onboarding wizard for new users and system setup',
            'capabilities' => ['guided_setup', 'dependency_management', 'system_checks', 'configuration_wizard'],
            'endpoints' => ['/onboarding/start', '/onboarding/step', '/onboarding/complete'],
            'config_options' => ['steps_enabled', 'skip_optional', 'auto_activate'],
        ],
        'aevov-playground' => [
            'class' => 'AevovPlayground\\Playground',
            'category' => 'specialized',
            'description' => 'Development and testing sandbox for Aevov experiments',
            'capabilities' => ['sandbox_execution', 'testing', 'experimentation', 'debugging'],
            'config_options' => ['isolation_level', 'resource_limits', 'logging_enabled'],
        ],
    ];

    /**
     * Get complete system context
     */
    public function get_full_context(): array {
        $cache_key = 'full_context';

        if (isset($this->context_cache[$cache_key])) {
            return $this->context_cache[$cache_key];
        }

        $context = [
            'system' => $this->get_system_info(),
            'plugins' => $this->get_plugin_status(),
            'capabilities' => $this->get_available_capabilities(),
            'storage' => $this->get_storage_info(),
            'ai_engines' => $this->get_ai_engine_info(),
            'workflows' => $this->get_workflow_info(),
            'patterns' => $this->get_pattern_info(),
            'memory' => $this->get_memory_info(),
            'network' => $this->get_network_info(),
            'security' => $this->get_security_info(),
            'configurations' => $this->get_current_configurations(),
            'statistics' => $this->get_system_statistics(),
            'recommendations' => $this->generate_recommendations(),
        ];

        $this->context_cache[$cache_key] = $context;

        return $context;
    }

    /**
     * Get system information
     */
    public function get_system_info(): array {
        global $wpdb;

        return [
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $wpdb->db_version(),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'is_multisite' => is_multisite(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'timezone' => get_option('timezone_string') ?: 'UTC',
            'locale' => get_locale(),
            'debug_mode' => WP_DEBUG,
            'ssl_enabled' => is_ssl(),
        ];
    }

    /**
     * Get plugin status
     */
    public function get_plugin_status(): array {
        $plugins = [];

        foreach (self::AEVO_PLUGINS as $slug => $info) {
            $is_active = class_exists($info['class']);
            $plugins[$slug] = [
                'slug' => $slug,
                'description' => $info['description'],
                'is_active' => $is_active,
                'capabilities' => $info['capabilities'],
                'config_options' => $info['config_options'] ?? [],
                'endpoints' => $info['endpoints'] ?? [],
                'current_config' => $is_active ? $this->get_plugin_config($slug) : null,
            ];
        }

        return $plugins;
    }

    /**
     * Get available capabilities
     */
    public function get_available_capabilities(): array {
        $capabilities = [];

        // Check each plugin's capabilities
        foreach (self::AEVO_PLUGINS as $slug => $info) {
            if (class_exists($info['class'])) {
                foreach ($info['capabilities'] as $cap) {
                    $capabilities[$cap] = [
                        'provider' => $slug,
                        'available' => true,
                        'description' => $this->get_capability_description($cap),
                        'usage_example' => $this->get_capability_example($cap),
                    ];
                }
            }
        }

        // Add workflow engine capabilities if available
        if (class_exists('AevovWorkflowEngine\\WorkflowEngine')) {
            $workflow_caps = apply_filters('aevov_workflow_capabilities', []);
            foreach ($workflow_caps as $key => $cap) {
                $capabilities["workflow_{$key}"] = [
                    'provider' => 'aevov-workflow-engine',
                    'available' => $cap['available'] ?? true,
                    'description' => $cap['description'] ?? '',
                    'node_type' => $cap['type'] ?? $key,
                ];
            }
        }

        return $capabilities;
    }

    /**
     * Get storage information
     */
    public function get_storage_info(): array {
        global $wpdb;

        $info = [
            'database' => [
                'type' => 'mysql',
                'version' => $wpdb->db_version(),
                'prefix' => $wpdb->prefix,
                'tables' => $this->get_aevo_tables(),
            ],
            'uploads' => [
                'dir' => wp_upload_dir(),
                'max_size' => wp_max_upload_size(),
            ],
        ];

        // Memory Core storage
        if (class_exists('AevovMemoryCore\\MemoryCore')) {
            $memory_count = wp_count_posts('astrocyte');
            $info['memory_core'] = [
                'type' => 'wordpress_cpt',
                'post_type' => 'astrocyte',
                'total_memories' => $memory_count->publish ?? 0,
                'supports_cubbit' => class_exists('AevovCubbitCdn\\CubbitCdn'),
            ];
        }

        // Pattern storage
        if (class_exists('BloomPatternRecognition\\PatternEngine')) {
            $info['patterns'] = [
                'storage_type' => 'bloom_filter',
                'sync_enabled' => true,
            ];
        }

        // Cubbit CDN
        if (class_exists('AevovCubbitCdn\\CubbitCdn')) {
            $info['cdn'] = [
                'provider' => 'cubbit',
                'type' => 'web3_storage',
                'configured' => $this->is_cubbit_configured(),
            ];
        }

        return $info;
    }

    /**
     * Get AI engine information
     */
    public function get_ai_engine_info(): array {
        $engines = [];

        $ai_plugins = [
            'language' => 'aevov-language-engine',
            'language_v2' => 'aevov-language-engine-v2',
            'image' => 'aevov-image-engine',
            'music' => 'aevov-music-forge',
            'cognitive' => 'aevov-cognitive-engine',
            'reasoning' => 'aevov-reasoning-engine',
            'embedding' => 'aevov-embedding-engine',
            'transcription' => 'aevov-transcription-engine',
            'simulation' => 'aevov-simulation-engine',
            'physics' => 'aevov-physics-engine',
            'neuro_architect' => 'aevov-neuro-architect',
        ];

        foreach ($ai_plugins as $name => $slug) {
            $info = self::AEVO_PLUGINS[$slug] ?? null;
            if ($info && class_exists($info['class'])) {
                $engines[$name] = [
                    'plugin' => $slug,
                    'active' => true,
                    'capabilities' => $info['capabilities'],
                    'endpoints' => $info['endpoints'] ?? [],
                    'config' => $this->get_plugin_config($slug),
                    'usage_stats' => $this->get_engine_usage_stats($slug),
                ];
            }
        }

        // AI Core provider info
        if (class_exists('AevovAICore\\AICore')) {
            $engines['_core'] = [
                'providers' => $this->get_configured_providers(),
                'default_provider' => get_option('aevov_default_ai_provider', 'openai'),
                'models_available' => $this->get_available_models(),
            ];
        }

        return $engines;
    }

    /**
     * Get workflow information
     */
    public function get_workflow_info(): array {
        global $wpdb;

        $info = [
            'total_workflows' => 0,
            'total_executions' => 0,
            'templates' => [],
            'node_types' => [],
            'recent_executions' => [],
        ];

        $workflows_table = $wpdb->prefix . 'aevov_workflows';
        $executions_table = $wpdb->prefix . 'aevov_workflow_executions';

        // Check if tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$workflows_table'") === $workflows_table) {
            $info['total_workflows'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $workflows_table");
            $info['templates'] = $wpdb->get_results(
                "SELECT id, name, description FROM $workflows_table WHERE is_template = 1 LIMIT 10",
                ARRAY_A
            ) ?: [];
        }

        if ($wpdb->get_var("SHOW TABLES LIKE '$executions_table'") === $executions_table) {
            $info['total_executions'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $executions_table");
            $info['success_rate'] = $this->calculate_success_rate();
            $info['recent_executions'] = $wpdb->get_results(
                "SELECT id, workflow_id, status, started_at, completed_at
                 FROM $executions_table ORDER BY started_at DESC LIMIT 5",
                ARRAY_A
            ) ?: [];
        }

        // Get node types from workflow engine
        if (class_exists('AevovWorkflowEngine\\WorkflowEngine')) {
            $info['node_types'] = apply_filters('aevov_workflow_capabilities', []);
        }

        return $info;
    }

    /**
     * Get pattern information
     */
    public function get_pattern_info(): array {
        $info = [
            'bloom_active' => class_exists('BloomPatternRecognition\\PatternEngine'),
            'sync_protocol_active' => class_exists('AevovPatternSyncProtocol\\SyncProtocol'),
            'total_patterns' => 0,
            'sync_status' => 'unknown',
        ];

        if ($info['bloom_active']) {
            // Get pattern statistics
            $patterns = get_posts([
                'post_type' => 'bloom_pattern',
                'posts_per_page' => -1,
                'fields' => 'ids',
            ]);
            $info['total_patterns'] = count($patterns);
        }

        return $info;
    }

    /**
     * Get memory information
     */
    public function get_memory_info(): array {
        $info = [
            'active' => class_exists('AevovMemoryCore\\MemoryCore'),
            'total_memories' => 0,
            'storage_used' => 0,
            'categories' => [],
        ];

        if ($info['active']) {
            $memories = wp_count_posts('astrocyte');
            $info['total_memories'] = $memories->publish ?? 0;

            // Get memory categories/types
            $info['categories'] = get_terms([
                'taxonomy' => 'memory_type',
                'hide_empty' => false,
            ]) ?: [];
        }

        return $info;
    }

    /**
     * Get network information
     */
    public function get_network_info(): array {
        $info = [
            'meshcore_active' => class_exists('AevovMeshcore\\Meshcore'),
            'peers' => [],
            'sync_status' => 'unknown',
        ];

        if ($info['meshcore_active']) {
            // Get peer information
            $info['peers'] = get_option('aevov_meshcore_peers', []);
            $info['sync_status'] = get_option('aevov_meshcore_sync_status', 'idle');
        }

        return $info;
    }

    /**
     * Get security information
     */
    public function get_security_info(): array {
        return [
            'security_plugin_active' => class_exists('AevovSecurity\\Security'),
            'ssl_enabled' => is_ssl(),
            'two_factor_enabled' => get_option('aevov_2fa_enabled', false),
            'audit_logging' => get_option('aevov_audit_logging', false),
            'rate_limiting' => get_option('aevov_rate_limiting', []),
            'allowed_origins' => get_option('aevov_cors_origins', []),
        ];
    }

    /**
     * Get current configurations
     */
    public function get_current_configurations(): array {
        $configs = [];

        foreach (array_keys(self::AEVO_PLUGINS) as $slug) {
            $option_key = str_replace('-', '_', $slug) . '_settings';
            $config = get_option($option_key, []);
            if (!empty($config)) {
                $configs[$slug] = $config;
            }
        }

        return $configs;
    }

    /**
     * Get system statistics
     */
    public function get_system_statistics(): array {
        global $wpdb;

        $stats = [
            'users' => [
                'total' => count_users()['total_users'],
            ],
            'content' => [
                'posts' => wp_count_posts()->publish,
                'pages' => wp_count_posts('page')->publish,
            ],
            'performance' => [
                'db_queries' => get_num_queries(),
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ],
        ];

        // Add workflow statistics
        $executions_table = $wpdb->prefix . 'aevov_workflow_executions';
        if ($wpdb->get_var("SHOW TABLES LIKE '$executions_table'") === $executions_table) {
            $stats['workflows'] = [
                'total_executions' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $executions_table"),
                'successful' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $executions_table WHERE status = 'completed'"),
                'failed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $executions_table WHERE status = 'failed'"),
            ];
        }

        return $stats;
    }

    /**
     * Generate recommendations based on system state
     */
    public function generate_recommendations(): array {
        $recommendations = [];

        // Check for missing core plugins
        $core_plugins = ['aevov-ai-core', 'aevov-language-engine', 'aevov-memory-core', 'aevov-workflow-engine'];
        foreach ($core_plugins as $plugin) {
            $info = self::AEVO_PLUGINS[$plugin] ?? null;
            if ($info && !class_exists($info['class'])) {
                $recommendations[] = [
                    'type' => 'plugin_activation',
                    'priority' => 'high',
                    'message' => "Activate {$plugin} for full functionality",
                    'action' => "activate_plugin:{$plugin}",
                ];
            }
        }

        // Check security settings
        if (!is_ssl()) {
            $recommendations[] = [
                'type' => 'security',
                'priority' => 'high',
                'message' => 'Enable SSL/HTTPS for secure connections',
                'action' => 'enable_ssl',
            ];
        }

        // Check for CDN configuration
        if (class_exists('AevovCubbitCdn\\CubbitCdn') && !$this->is_cubbit_configured()) {
            $recommendations[] = [
                'type' => 'configuration',
                'priority' => 'medium',
                'message' => 'Configure Cubbit CDN for distributed storage',
                'action' => 'configure_cubbit',
            ];
        }

        return $recommendations;
    }

    /**
     * Get context for AI prompts
     */
    public function get_ai_context_prompt(): string {
        $context = $this->get_full_context();

        $prompt = "You are configuring an Aevov ecosystem with the following components:\n\n";

        // Active plugins
        $active_plugins = array_filter($context['plugins'], fn($p) => $p['is_active']);
        $prompt .= "ACTIVE PLUGINS:\n";
        foreach ($active_plugins as $slug => $plugin) {
            $prompt .= "- {$slug}: {$plugin['description']}\n";
            $prompt .= "  Capabilities: " . implode(', ', $plugin['capabilities']) . "\n";
        }

        // Available capabilities
        $prompt .= "\nAVAILABLE CAPABILITIES:\n";
        foreach ($context['capabilities'] as $name => $cap) {
            $prompt .= "- {$name}: {$cap['description']}\n";
        }

        // Storage info
        $prompt .= "\nSTORAGE SYSTEMS:\n";
        $prompt .= "- Database: {$context['storage']['database']['type']} v{$context['storage']['database']['version']}\n";
        if (isset($context['storage']['memory_core'])) {
            $prompt .= "- Memory Core: {$context['storage']['memory_core']['total_memories']} memories\n";
        }
        if (isset($context['storage']['cdn'])) {
            $prompt .= "- CDN: {$context['storage']['cdn']['provider']} ({$context['storage']['cdn']['type']})\n";
        }

        // Current recommendations
        if (!empty($context['recommendations'])) {
            $prompt .= "\nSYSTEM RECOMMENDATIONS:\n";
            foreach ($context['recommendations'] as $rec) {
                $prompt .= "- [{$rec['priority']}] {$rec['message']}\n";
            }
        }

        return $prompt;
    }

    /**
     * Get specific context for a system component
     */
    public function get_component_context(string $component): array {
        $context = $this->get_full_context();

        return match ($component) {
            'ai_engines' => $context['ai_engines'],
            'storage' => $context['storage'],
            'workflows' => $context['workflows'],
            'patterns' => $context['patterns'],
            'memory' => $context['memory'],
            'network' => $context['network'],
            'security' => $context['security'],
            default => $context,
        };
    }

    /**
     * Helper methods
     */
    private function get_plugin_config(string $slug): array {
        $option_key = str_replace('-', '_', $slug) . '_settings';
        return get_option($option_key, []);
    }

    private function get_capability_description(string $capability): string {
        $descriptions = [
            'text_generation' => 'Generate text content using AI language models',
            'sentiment_analysis' => 'Analyze emotional tone of text',
            'image_generation' => 'Create images from text descriptions',
            'music_generation' => 'Compose music and audio tracks',
            'reasoning' => 'Perform complex logical reasoning',
            'memory_storage' => 'Store and retrieve persistent memories',
            'pattern_detection' => 'Detect and match patterns in data',
            'peer_discovery' => 'Discover and connect to network peers',
        ];

        return $descriptions[$capability] ?? $capability;
    }

    private function get_capability_example(string $capability): string {
        return "Example usage of {$capability}";
    }

    private function get_aevo_tables(): array {
        global $wpdb;

        $tables = $wpdb->get_col(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->prefix . 'aevov%'
            )
        );

        return $tables ?: [];
    }

    private function is_cubbit_configured(): bool {
        $settings = get_option('aevov_cubbit_settings', []);
        return !empty($settings['access_key']) && !empty($settings['secret_key']);
    }

    private function get_configured_providers(): array {
        return get_option('aevov_ai_providers', ['openai']);
    }

    private function get_available_models(): array {
        return get_option('aevov_available_models', [
            'gpt-4', 'gpt-3.5-turbo', 'claude-3', 'gemini-pro'
        ]);
    }

    private function get_engine_usage_stats(string $slug): array {
        return get_option($slug . '_usage_stats', [
            'total_requests' => 0,
            'last_used' => null,
        ]);
    }

    private function calculate_success_rate(): float {
        global $wpdb;
        $executions_table = $wpdb->prefix . 'aevov_workflow_executions';

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $executions_table");
        if ($total === 0) return 100.0;

        $successful = (int) $wpdb->get_var("SELECT COUNT(*) FROM $executions_table WHERE status = 'completed'");
        return round(($successful / $total) * 100, 2);
    }
}
