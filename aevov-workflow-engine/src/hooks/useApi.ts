import { useCallback } from 'react';
import { useWorkflowStore } from '../store';
import { Workflow, ExecutionResult, AevovCapability, NodeTypeDefinition } from '../types';

const getConfig = () => {
    if (typeof window !== 'undefined' && window.aevovWorkflowEngine) {
        return window.aevovWorkflowEngine;
    }
    // Fallback for standalone mode
    return {
        apiUrl: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080/wp-json/aevov-workflow/v1',
        nonce: '',
        userId: 0,
        userName: 'Guest',
        isAdmin: false,
        settings: { maxExecutionTime: 300, maxNodes: 100 },
        strings: {},
    };
};

async function request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const config = getConfig();
    const url = `${config.apiUrl}${endpoint}`;

    const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        ...(options.headers as Record<string, string>),
    };

    if (config.nonce) {
        headers['X-WP-Nonce'] = config.nonce;
    }

    const response = await fetch(url, {
        ...options,
        headers,
        credentials: 'include',
    });

    if (!response.ok) {
        const error = await response.json().catch(() => ({ error: 'Request failed' }));
        throw new Error(error.error || error.message || 'Request failed');
    }

    return response.json();
}

export function useApi() {
    const {
        workflowId,
        workflowName,
        nodes,
        edges,
        setWorkflowId,
        loadWorkflowData,
        setNodeTypes,
        setExecuting,
        setExecutionResults,
        setDirty,
    } = useWorkflowStore();

    const fetchCapabilities = useCallback(async () => {
        try {
            const response = await request<{
                capabilities: Record<string, AevovCapability>;
            }>('/capabilities?include_unavailable=true');

            // Convert capabilities to node types
            const capabilityNodeTypes: Record<string, NodeTypeDefinition> = {};
            Object.entries(response.capabilities).forEach(([key, cap]) => {
                capabilityNodeTypes[key] = {
                    type: key,
                    label: cap.name,
                    category: 'capability',
                    description: cap.description,
                    icon: cap.icon || 'Box',
                    color: cap.color || '#0ea5e9',
                    inputs: [{ id: 'input', label: 'Input', type: 'any' }],
                    outputs: [{ id: 'output', label: 'Output', type: 'any' }],
                    configFields: cap.endpoints.length > 1
                        ? [{
                            key: 'endpoint',
                            label: 'Action',
                            type: 'select',
                            options: cap.endpoints.map((e) => ({
                                value: e.route,
                                label: e.description,
                            })),
                        }]
                        : [],
                    available: cap.available,
                };
            });

            setNodeTypes(capabilityNodeTypes);
        } catch (error) {
            console.error('Failed to fetch capabilities:', error);
        }
    }, [setNodeTypes]);

    const loadWorkflow = useCallback(async (id: string) => {
        try {
            const workflow = await request<Workflow>(`/workflows/${id}`);
            loadWorkflowData({
                id: workflow.id,
                name: workflow.name,
                nodes: workflow.nodes as any,
                edges: workflow.edges as any,
            });
        } catch (error) {
            console.error('Failed to load workflow:', error);
            throw error;
        }
    }, [loadWorkflowData]);

    const saveWorkflow = useCallback(async () => {
        try {
            const data = {
                id: workflowId || undefined,
                name: workflowName,
                workflow: { nodes, edges },
            };

            if (workflowId) {
                await request(`/workflows/${workflowId}`, {
                    method: 'PUT',
                    body: JSON.stringify(data),
                });
            } else {
                const result = await request<{ id: string }>('/workflows', {
                    method: 'POST',
                    body: JSON.stringify(data),
                });
                setWorkflowId(result.id);
            }

            setDirty(false);
        } catch (error) {
            console.error('Failed to save workflow:', error);
            throw error;
        }
    }, [workflowId, workflowName, nodes, edges, setWorkflowId, setDirty]);

    const executeWorkflow = useCallback(async () => {
        setExecuting(true);
        setExecutionResults(null);

        try {
            const result = await request<ExecutionResult>('/execute', {
                method: 'POST',
                body: JSON.stringify({
                    workflow: { nodes, edges },
                    inputs: {},
                }),
            });

            setExecutionResults(result);
            return result;
        } catch (error) {
            const errorResult: ExecutionResult = {
                success: false,
                error: error instanceof Error ? error.message : 'Execution failed',
                log: [],
            };
            setExecutionResults(errorResult);
            throw error;
        }
    }, [nodes, edges, setExecuting, setExecutionResults]);

    return {
        fetchCapabilities,
        loadWorkflow,
        saveWorkflow,
        executeWorkflow,
    };
}
