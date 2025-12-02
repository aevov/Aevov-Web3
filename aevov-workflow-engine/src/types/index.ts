export interface AevovNodeData {
    label: string;
    nodeType: string;
    icon: string;
    color: string;
    inputs: HandleDefinition[];
    outputs: HandleDefinition[];
    config: Record<string, unknown>;
}

export interface HandleDefinition {
    id: string;
    label: string;
    type: 'any' | 'string' | 'number' | 'boolean' | 'array' | 'object';
}

export interface NodeTypeDefinition {
    type: string;
    label: string;
    category: 'input' | 'output' | 'transform' | 'control' | 'capability' | 'utility';
    description?: string;
    icon: string;
    color: string;
    inputs: HandleDefinition[];
    outputs: HandleDefinition[];
    configFields: ConfigField[];
    available?: boolean;
}

export interface ConfigField {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'select' | 'boolean' | 'json';
    options?: { value: string; label: string }[];
    defaultValue?: unknown;
    placeholder?: string;
}

export interface Workflow {
    id: string;
    name: string;
    description?: string;
    nodes: unknown[];
    edges: unknown[];
    is_published?: boolean;
    version?: number;
    created_at?: string;
    updated_at?: string;
}

export interface ExecutionResult {
    success: boolean;
    outputs?: Record<string, unknown>;
    all_outputs?: Record<string, unknown>;
    error?: string;
    failed_node?: string;
    execution_time?: number;
    execution_id?: number;
    log: ExecutionLogEntry[];
}

export interface ExecutionLogEntry {
    timestamp: number;
    elapsed?: number;
    level: 'info' | 'warning' | 'error';
    message: string;
    data?: Record<string, unknown>;
}

export interface AevovCapability {
    name: string;
    description: string;
    namespace: string;
    icon: string;
    color: string;
    endpoints: {
        method: string;
        route: string;
        description: string;
    }[];
    available: boolean;
}
