/**
 * AevSyncPro Type Definitions
 */

// System Context Types
export interface SystemContext {
  system: SystemInfo;
  plugins: Record<string, PluginInfo>;
  capabilities: Record<string, CapabilityInfo>;
  storage: StorageInfo;
  ai_engines: Record<string, AIEngineInfo>;
  workflows: WorkflowInfo;
  patterns: PatternInfo;
  memory: MemoryInfo;
  network: NetworkInfo;
  security: SecurityInfo;
  configurations: Record<string, unknown>;
  statistics: SystemStatistics;
  recommendations: Recommendation[];
}

export interface SystemInfo {
  wordpress_version: string;
  php_version: string;
  mysql_version: string;
  site_url: string;
  home_url: string;
  is_multisite: boolean;
  memory_limit: string;
  max_execution_time: string;
  upload_max_filesize: string;
  timezone: string;
  locale: string;
  debug_mode: boolean;
  ssl_enabled: boolean;
}

export interface PluginInfo {
  slug: string;
  description: string;
  is_active: boolean;
  capabilities: string[];
  config_options: string[];
  endpoints?: string[];
  current_config?: Record<string, unknown>;
}

export interface CapabilityInfo {
  provider: string;
  available: boolean;
  description: string;
  usage_example?: string;
  node_type?: string;
}

export interface StorageInfo {
  database: {
    type: string;
    version: string;
    prefix: string;
    tables: string[];
  };
  uploads: {
    dir: Record<string, string>;
    max_size: number;
  };
  memory_core?: {
    type: string;
    post_type: string;
    total_memories: number;
    supports_cubbit: boolean;
  };
  cdn?: {
    provider: string;
    type: string;
    configured: boolean;
  };
}

export interface AIEngineInfo {
  plugin: string;
  active: boolean;
  capabilities: string[];
  endpoints: string[];
  config: Record<string, unknown>;
  usage_stats: {
    total_requests: number;
    last_used: string | null;
  };
}

export interface WorkflowInfo {
  total_workflows: number;
  total_executions: number;
  success_rate?: number;
  templates: Array<{ id: string; name: string; description: string }>;
  node_types: Record<string, NodeTypeDefinition>;
  recent_executions: Array<{
    id: number;
    workflow_id: string;
    status: string;
    started_at: string;
    completed_at: string;
  }>;
}

export interface PatternInfo {
  bloom_active: boolean;
  sync_protocol_active: boolean;
  total_patterns: number;
  sync_status: string;
}

export interface MemoryInfo {
  active: boolean;
  total_memories: number;
  storage_used: number;
  categories: Array<{ term_id: number; name: string; slug: string }>;
}

export interface NetworkInfo {
  meshcore_active: boolean;
  peers: string[];
  sync_status: string;
}

export interface SecurityInfo {
  security_plugin_active: boolean;
  ssl_enabled: boolean;
  two_factor_enabled: boolean;
  audit_logging: boolean;
  rate_limiting: Record<string, unknown>;
  allowed_origins: string[];
}

export interface SystemStatistics {
  users: { total: number };
  content: { posts: number; pages: number };
  performance: {
    db_queries: number;
    memory_usage: number;
    peak_memory: number;
  };
  workflows?: {
    total_executions: number;
    successful: number;
    failed: number;
  };
}

export interface Recommendation {
  type: string;
  priority: 'high' | 'medium' | 'low';
  message: string;
  action: string;
}

// Workflow Types
export interface WorkflowNode {
  id: string;
  type: string;
  position: { x: number; y: number };
  data: {
    label: string;
    [key: string]: unknown;
  };
}

export interface WorkflowEdge {
  id: string;
  source: string;
  target: string;
  sourceHandle?: string;
  targetHandle?: string;
}

export interface Workflow {
  id: string;
  name: string;
  description?: string;
  nodes: WorkflowNode[];
  edges: WorkflowEdge[];
  metadata?: {
    generated_by: string;
    generated_at: string;
    analysis?: Record<string, unknown>;
  };
}

export interface NodeTypeDefinition {
  type: string;
  label: string;
  category: string;
  description: string;
  icon: string;
  color: string;
  inputs: HandleDefinition[];
  outputs: HandleDefinition[];
  configFields: ConfigField[];
  available: boolean;
}

export interface HandleDefinition {
  id: string;
  type: string;
  label: string;
}

export interface ConfigField {
  name: string;
  label: string;
  type: 'text' | 'number' | 'boolean' | 'select' | 'textarea' | 'json';
  options?: Array<{ value: string; label: string }>;
  default?: unknown;
  required?: boolean;
}

// Configuration Types
export interface ConfigurationBundle {
  ai_engines?: AIEngineConfig;
  storage?: StorageConfig;
  workflows?: WorkflowConfig;
  security?: SecurityConfig;
  patterns?: PatternConfig;
  memory?: MemoryConfig;
  network?: NetworkConfig;
}

export interface AIEngineConfig {
  default_provider: string;
  fallback_provider?: string;
  rate_limiting: {
    enabled: boolean;
    requests_per_minute: number;
    tokens_per_minute: number;
  };
  model_preferences: {
    text: string;
    image: string;
    embedding: string;
  };
  retry_policy: {
    max_retries: number;
    backoff_multiplier: number;
  };
}

export interface StorageConfig {
  primary_backend: string;
  secondary_backend?: string;
  max_memory_size: string;
  chunk_size: string;
  compression: {
    enabled: boolean;
    algorithm: string;
    level: number;
  };
  retention_policy: {
    default_ttl: number;
    auto_cleanup: boolean;
  };
}

export interface WorkflowConfig {
  max_execution_time: number;
  max_nodes: number;
  max_concurrent_executions: number;
  scheduling: {
    enabled: boolean;
    timezone: string;
  };
  logging: {
    level: string;
    retention_days: number;
  };
}

export interface SecurityConfig {
  authentication: {
    methods: string[];
    jwt_expiry: number;
    session_duration: number;
  };
  encryption: {
    algorithm: string;
    key_rotation: boolean;
    rotation_interval: number;
  };
  rate_limiting: {
    enabled: boolean;
    window: number;
    max_requests: number;
  };
  cors: {
    enabled: boolean;
    allowed_origins: string[];
    allowed_methods: string[];
  };
}

export interface PatternConfig {
  bloom_filter_size: number;
  false_positive_rate: number;
  sync_interval: number;
  auto_detect: boolean;
}

export interface MemoryConfig {
  storage_backend: string;
  index_strategy: string;
  cache_enabled: boolean;
  cache_ttl: number;
}

export interface NetworkConfig {
  protocol: string;
  bootstrap_nodes: string[];
  max_peers: number;
  sync_strategy: string;
  encryption: boolean;
}

// API Response Types
export interface GenerateWorkflowResponse {
  success: boolean;
  workflow: Workflow;
  analysis: {
    goal: string;
    required_capabilities: string[];
    configurations: string[];
    sequence: string[];
    prerequisites: string[];
    considerations: string[];
  };
  validation: {
    valid: boolean;
    issues: ValidationIssue[];
  };
  estimated_steps: number;
  capabilities_used: string[];
}

export interface GenerateBundleResponse {
  success: boolean;
  bundle: ConfigurationBundle;
  validation: {
    valid: boolean;
    issues: Record<string, ValidationIssue[]>;
  };
  metadata: {
    generated_at: string;
    generated_by: string;
    version: string;
    targets: string[];
  };
  apply_instructions: ApplyInstruction[];
  rollback_plan: RollbackPlan;
}

export interface ValidationIssue {
  type: string;
  message: string;
  severity: 'error' | 'warning' | 'info';
  field?: string;
  node_id?: string;
}

export interface ApplyInstruction {
  step: number;
  target?: string;
  action: string;
  option_key?: string;
  description: string;
}

export interface RollbackPlan {
  snapshots: Record<string, {
    option_key: string;
    original_value: unknown;
    captured_at: string;
  }>;
  steps: Array<{
    target: string;
    action: string;
    description: string;
  }>;
}

export interface SyncOperation {
  id: string;
  sync_type: string;
  target_system: string;
  target_entity?: string;
  status: 'pending' | 'in_progress' | 'completed' | 'failed';
  result_data?: Record<string, unknown>;
  error_message?: string;
  created_at: string;
  completed_at?: string;
}

export interface Template {
  id: number;
  title: string;
  description: string;
  workflow_data: Workflow;
  is_default?: boolean;
}

// Store Types
export interface SyncProState {
  context: SystemContext | null;
  isLoading: boolean;
  error: string | null;
  currentWorkflow: Workflow | null;
  currentBundle: ConfigurationBundle | null;
  syncOperations: SyncOperation[];
  templates: Template[];
  selectedTarget: string;
  prompt: string;
}

export interface SyncProActions {
  setContext: (context: SystemContext) => void;
  setLoading: (loading: boolean) => void;
  setError: (error: string | null) => void;
  setCurrentWorkflow: (workflow: Workflow | null) => void;
  setCurrentBundle: (bundle: ConfigurationBundle | null) => void;
  addSyncOperation: (operation: SyncOperation) => void;
  updateSyncOperation: (id: string, updates: Partial<SyncOperation>) => void;
  setTemplates: (templates: Template[]) => void;
  setSelectedTarget: (target: string) => void;
  setPrompt: (prompt: string) => void;
  reset: () => void;
}

export type SyncProStore = SyncProState & SyncProActions;
