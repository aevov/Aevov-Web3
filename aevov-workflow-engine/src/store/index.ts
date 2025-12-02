import { create } from 'zustand';
import { Node, Edge } from 'reactflow';
import { AevovNodeData, NodeTypeDefinition, ExecutionResult } from '../types';

interface WorkflowState {
    // Workflow data
    workflowId: string | null;
    workflowName: string;
    nodes: Node<AevovNodeData>[];
    edges: Edge[];
    isDirty: boolean;

    // Selection
    selectedNodeId: string | null;

    // Node types registry
    nodeTypes: Record<string, NodeTypeDefinition>;

    // Execution state
    isExecuting: boolean;
    executionResults: ExecutionResult | null;

    // Actions
    setWorkflowId: (id: string | null) => void;
    setWorkflowName: (name: string) => void;
    setNodes: (nodes: Node<AevovNodeData>[]) => void;
    setEdges: (edges: Edge[]) => void;
    addNode: (type: string, position: { x: number; y: number }, data?: Partial<AevovNodeData>) => string;
    removeNode: (id: string) => void;
    updateNodeData: (id: string, data: Partial<AevovNodeData>) => void;
    selectNode: (id: string | null) => void;
    setNodeTypes: (types: Record<string, NodeTypeDefinition>) => void;
    newWorkflow: () => void;
    loadWorkflowData: (data: { id: string; name: string; nodes: Node<AevovNodeData>[]; edges: Edge[] }) => void;
    setExecuting: (executing: boolean) => void;
    setExecutionResults: (results: ExecutionResult | null) => void;
    clearResults: () => void;
    setDirty: (dirty: boolean) => void;
}

let nodeCounter = 0;

export const useWorkflowStore = create<WorkflowState>((set, get) => ({
    workflowId: null,
    workflowName: 'Untitled Workflow',
    nodes: [],
    edges: [],
    isDirty: false,
    selectedNodeId: null,
    nodeTypes: getDefaultNodeTypes(),
    isExecuting: false,
    executionResults: null,

    setWorkflowId: (id) => set({ workflowId: id }),
    setWorkflowName: (name) => set({ workflowName: name, isDirty: true }),

    setNodes: (nodes) => set({ nodes, isDirty: true }),
    setEdges: (edges) => set({ edges, isDirty: true }),

    addNode: (type, position, data = {}) => {
        const nodeType = get().nodeTypes[type];
        const id = `node_${++nodeCounter}_${Date.now()}`;

        const newNode: Node<AevovNodeData> = {
            id,
            type: 'aevovNode',
            position,
            data: {
                label: data.label || nodeType?.label || type,
                nodeType: type,
                icon: nodeType?.icon || 'Box',
                color: nodeType?.color || '#64748b',
                inputs: nodeType?.inputs || [],
                outputs: nodeType?.outputs || [],
                config: {},
                ...data,
            },
        };

        set((state) => ({
            nodes: [...state.nodes, newNode],
            isDirty: true,
        }));

        return id;
    },

    removeNode: (id) => {
        set((state) => ({
            nodes: state.nodes.filter((n) => n.id !== id),
            edges: state.edges.filter((e) => e.source !== id && e.target !== id),
            selectedNodeId: state.selectedNodeId === id ? null : state.selectedNodeId,
            isDirty: true,
        }));
    },

    updateNodeData: (id, data) => {
        set((state) => ({
            nodes: state.nodes.map((node) =>
                node.id === id
                    ? { ...node, data: { ...node.data, ...data } }
                    : node
            ),
            isDirty: true,
        }));
    },

    selectNode: (id) => set({ selectedNodeId: id }),

    setNodeTypes: (types) => set({ nodeTypes: { ...getDefaultNodeTypes(), ...types } }),

    newWorkflow: () => {
        set({
            workflowId: null,
            workflowName: 'Untitled Workflow',
            nodes: [],
            edges: [],
            isDirty: false,
            selectedNodeId: null,
            executionResults: null,
        });
    },

    loadWorkflowData: (data) => {
        set({
            workflowId: data.id,
            workflowName: data.name,
            nodes: data.nodes,
            edges: data.edges,
            isDirty: false,
            selectedNodeId: null,
            executionResults: null,
        });
    },

    setExecuting: (executing) => set({ isExecuting: executing }),
    setExecutionResults: (results) => set({ executionResults: results, isExecuting: false }),
    clearResults: () => set({ executionResults: null }),
    setDirty: (dirty) => set({ isDirty: dirty }),
}));

function getDefaultNodeTypes(): Record<string, NodeTypeDefinition> {
    return {
        input: {
            type: 'input',
            label: 'Input',
            category: 'input',
            description: 'Starting point for workflow data',
            icon: 'ArrowRightCircle',
            color: '#22c55e',
            inputs: [],
            outputs: [{ id: 'output', label: 'Output', type: 'any' }],
            configFields: [
                { key: 'defaultValue', label: 'Default Value', type: 'textarea' },
            ],
        },
        output: {
            type: 'output',
            label: 'Output',
            category: 'output',
            description: 'Final result of the workflow',
            icon: 'ArrowLeftCircle',
            color: '#ef4444',
            inputs: [{ id: 'input', label: 'Input', type: 'any' }],
            outputs: [],
            configFields: [],
        },
        transform: {
            type: 'transform',
            label: 'Transform',
            category: 'transform',
            description: 'Transform data between nodes',
            icon: 'Wand2',
            color: '#a855f7',
            inputs: [{ id: 'input', label: 'Input', type: 'any' }],
            outputs: [{ id: 'output', label: 'Output', type: 'any' }],
            configFields: [
                {
                    key: 'type',
                    label: 'Transform Type',
                    type: 'select',
                    options: [
                        { value: 'passthrough', label: 'Passthrough' },
                        { value: 'json_parse', label: 'Parse JSON' },
                        { value: 'json_stringify', label: 'Stringify JSON' },
                        { value: 'extract', label: 'Extract Path' },
                        { value: 'template', label: 'Template' },
                    ],
                },
                { key: 'path', label: 'Path', type: 'text', placeholder: 'data.items.0' },
                { key: 'template', label: 'Template', type: 'textarea', placeholder: '{{input}}' },
            ],
        },
        condition: {
            type: 'condition',
            label: 'Condition',
            category: 'control',
            description: 'Branch based on condition',
            icon: 'GitBranch',
            color: '#f59e0b',
            inputs: [{ id: 'input', label: 'Input', type: 'any' }],
            outputs: [
                { id: 'true', label: 'True', type: 'any' },
                { id: 'false', label: 'False', type: 'any' },
            ],
            configFields: [
                { key: 'condition', label: 'Condition', type: 'text', placeholder: 'input > 10' },
            ],
        },
        loop: {
            type: 'loop',
            label: 'Loop',
            category: 'control',
            description: 'Iterate over array',
            icon: 'Repeat',
            color: '#f59e0b',
            inputs: [{ id: 'items', label: 'Items', type: 'array' }],
            outputs: [{ id: 'output', label: 'Results', type: 'array' }],
            configFields: [
                { key: 'maxIterations', label: 'Max Iterations', type: 'number', defaultValue: 100 },
            ],
        },
        http: {
            type: 'http',
            label: 'HTTP Request',
            category: 'utility',
            description: 'Make HTTP requests',
            icon: 'Globe',
            color: '#3b82f6',
            inputs: [{ id: 'body', label: 'Body', type: 'any' }],
            outputs: [{ id: 'output', label: 'Response', type: 'any' }],
            configFields: [
                { key: 'url', label: 'URL', type: 'text', placeholder: 'https://api.example.com' },
                {
                    key: 'method',
                    label: 'Method',
                    type: 'select',
                    options: [
                        { value: 'GET', label: 'GET' },
                        { value: 'POST', label: 'POST' },
                        { value: 'PUT', label: 'PUT' },
                        { value: 'DELETE', label: 'DELETE' },
                    ],
                },
            ],
        },
        delay: {
            type: 'delay',
            label: 'Delay',
            category: 'utility',
            description: 'Wait before continuing',
            icon: 'Clock',
            color: '#64748b',
            inputs: [{ id: 'input', label: 'Input', type: 'any' }],
            outputs: [{ id: 'output', label: 'Output', type: 'any' }],
            configFields: [
                { key: 'seconds', label: 'Seconds', type: 'number', defaultValue: 1 },
            ],
        },
        // AI Capabilities
        language: {
            type: 'language',
            label: 'Language AI',
            category: 'capability',
            description: 'Natural language processing',
            icon: 'MessageSquare',
            color: '#0ea5e9',
            inputs: [{ id: 'prompt', label: 'Prompt', type: 'string' }],
            outputs: [{ id: 'output', label: 'Response', type: 'string' }],
            configFields: [
                {
                    key: 'endpoint',
                    label: 'Action',
                    type: 'select',
                    options: [
                        { value: '/generate', label: 'Generate Text' },
                        { value: '/analyze', label: 'Analyze Text' },
                    ],
                },
            ],
        },
        image: {
            type: 'image',
            label: 'Image AI',
            category: 'capability',
            description: 'AI image generation',
            icon: 'Image',
            color: '#ec4899',
            inputs: [{ id: 'prompt', label: 'Prompt', type: 'string' }],
            outputs: [{ id: 'output', label: 'Image URL', type: 'string' }],
            configFields: [
                {
                    key: 'endpoint',
                    label: 'Action',
                    type: 'select',
                    options: [
                        { value: '/generate', label: 'Generate Image' },
                        { value: '/edit', label: 'Edit Image' },
                    ],
                },
            ],
        },
        music: {
            type: 'music',
            label: 'Music AI',
            category: 'capability',
            description: 'AI music composition',
            icon: 'Music',
            color: '#8b5cf6',
            inputs: [{ id: 'params', label: 'Parameters', type: 'object' }],
            outputs: [{ id: 'output', label: 'Track', type: 'object' }],
            configFields: [
                { key: 'genre', label: 'Genre', type: 'text' },
                { key: 'duration', label: 'Duration (s)', type: 'number', defaultValue: 30 },
            ],
        },
        cognitive: {
            type: 'cognitive',
            label: 'Cognitive',
            category: 'capability',
            description: 'Complex reasoning',
            icon: 'Brain',
            color: '#f97316',
            inputs: [{ id: 'problem', label: 'Problem', type: 'any' }],
            outputs: [{ id: 'output', label: 'Solution', type: 'any' }],
            configFields: [],
        },
        memory: {
            type: 'memory',
            label: 'Memory',
            category: 'capability',
            description: 'Persistent storage',
            icon: 'Database',
            color: '#14b8a6',
            inputs: [{ id: 'data', label: 'Data', type: 'any' }],
            outputs: [{ id: 'output', label: 'Result', type: 'any' }],
            configFields: [
                {
                    key: 'operation',
                    label: 'Operation',
                    type: 'select',
                    options: [
                        { value: 'store', label: 'Store' },
                        { value: 'retrieve', label: 'Retrieve' },
                    ],
                },
                { key: 'address', label: 'Address', type: 'text' },
            ],
        },
        pattern: {
            type: 'pattern',
            label: 'Pattern Recognition',
            category: 'capability',
            description: 'Detect patterns',
            icon: 'Scan',
            color: '#84cc16',
            inputs: [{ id: 'data', label: 'Data', type: 'any' }],
            outputs: [{ id: 'output', label: 'Patterns', type: 'array' }],
            configFields: [],
        },
        vision: {
            type: 'vision',
            label: 'Web Scraper',
            category: 'capability',
            description: 'Extract web data',
            icon: 'Eye',
            color: '#06b6d4',
            inputs: [{ id: 'url', label: 'URL', type: 'string' }],
            outputs: [{ id: 'output', label: 'Data', type: 'object' }],
            configFields: [
                { key: 'selector', label: 'CSS Selector', type: 'text' },
            ],
        },
        transcription: {
            type: 'transcription',
            label: 'Transcription',
            category: 'capability',
            description: 'Speech to text',
            icon: 'Mic',
            color: '#f59e0b',
            inputs: [{ id: 'audio', label: 'Audio', type: 'string' }],
            outputs: [{ id: 'output', label: 'Text', type: 'string' }],
            configFields: [],
        },
        super_app: {
            type: 'super_app',
            label: 'App Generator',
            category: 'capability',
            description: 'Generate applications',
            icon: 'Rocket',
            color: '#f43f5e',
            inputs: [{ id: 'spec', label: 'Specification', type: 'object' }],
            outputs: [{ id: 'output', label: 'Application', type: 'object' }],
            configFields: [],
        },
    };
}
