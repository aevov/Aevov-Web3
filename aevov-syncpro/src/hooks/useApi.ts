/**
 * AevSyncPro API Hooks
 */

import { useCallback } from 'react';
import { useSyncProStore } from '../store';
import type {
  SystemContext,
  Workflow,
  ConfigurationBundle,
  GenerateWorkflowResponse,
  GenerateBundleResponse,
  Template,
  SyncOperation,
} from '../types';

// Get API configuration from WordPress localized script
declare global {
  interface Window {
    aevSyncProConfig?: {
      apiUrl: string;
      nonce: string;
      systemContext?: SystemContext;
      capabilities?: Record<string, unknown>;
    };
  }
}

const getApiConfig = () => {
  const config = window.aevSyncProConfig ?? {
    apiUrl: '/wp-json/aevov-syncpro/v1',
    nonce: '',
  };
  return config;
};

const apiFetch = async <T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> => {
  const config = getApiConfig();
  const url = `${config.apiUrl}${endpoint}`;

  const response = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
      ...options.headers,
    },
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: 'Request failed' }));
    throw new Error(error.message || `HTTP ${response.status}`);
  }

  return response.json();
};

/**
 * Hook for fetching system context
 */
export function useSystemContext() {
  const { setContext, setLoading, setError } = useSyncProStore();

  const fetchContext = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiFetch<{ success: boolean; context: SystemContext }>('/context');
      if (response.success) {
        setContext(response.context);
      }
      return response.context;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to fetch context');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setContext, setLoading, setError]);

  const fetchComponentContext = useCallback(async (component: string) => {
    setLoading(true);
    try {
      const response = await apiFetch<{ success: boolean; context: unknown }>(
        `/context/${component}`
      );
      return response.context;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to fetch component context');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setLoading, setError]);

  return { fetchContext, fetchComponentContext };
}

/**
 * Hook for workflow generation
 */
export function useWorkflowGeneration() {
  const { setCurrentWorkflow, setLoading, setError } = useSyncProStore();

  const generateWorkflow = useCallback(async (
    prompt: string,
    options: Record<string, unknown> = {}
  ): Promise<GenerateWorkflowResponse> => {
    setLoading(true);
    try {
      const response = await apiFetch<GenerateWorkflowResponse>('/generate/workflow', {
        method: 'POST',
        body: JSON.stringify({ prompt, options }),
      });

      if (response.success && response.workflow) {
        setCurrentWorkflow(response.workflow);
      }

      return response;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to generate workflow');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setCurrentWorkflow, setLoading, setError]);

  return { generateWorkflow };
}

/**
 * Hook for configuration generation
 */
export function useConfigGeneration() {
  const { setCurrentBundle, setLoading, setError } = useSyncProStore();

  const generateBundle = useCallback(async (
    requirements: Record<string, unknown>,
    options: Record<string, unknown> = {}
  ): Promise<GenerateBundleResponse> => {
    setLoading(true);
    try {
      const response = await apiFetch<GenerateBundleResponse>('/bundle', {
        method: 'POST',
        body: JSON.stringify({ requirements, options }),
      });

      if (response.success && response.bundle) {
        setCurrentBundle(response.bundle);
      }

      return response;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to generate configuration');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setCurrentBundle, setLoading, setError]);

  const generateConfig = useCallback(async (
    target: string,
    requirements: Record<string, unknown>,
    options: Record<string, unknown> = {}
  ) => {
    setLoading(true);
    try {
      const response = await apiFetch<{ success: boolean; configuration: unknown }>('/generate/config', {
        method: 'POST',
        body: JSON.stringify({ target, requirements, options }),
      });
      return response;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to generate config');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setLoading, setError]);

  return { generateBundle, generateConfig };
}

/**
 * Hook for applying configurations
 */
export function useConfigApply() {
  const { addSyncOperation, updateSyncOperation, setLoading, setError } = useSyncProStore();

  const applyConfiguration = useCallback(async (
    bundle: ConfigurationBundle,
    dryRun = false
  ) => {
    setLoading(true);
    try {
      const response = await apiFetch<{
        success: boolean;
        summary?: { total_operations: number; successful: number; failed: number };
        validation?: Record<string, unknown>;
        dry_run?: boolean;
      }>('/apply', {
        method: 'POST',
        body: JSON.stringify({ bundle, dry_run: dryRun }),
      });

      return response;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to apply configuration');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setLoading, setError]);

  const rollbackExecution = useCallback(async (executionId: number) => {
    setLoading(true);
    try {
      const response = await apiFetch<{ success: boolean; result: unknown }>(
        `/sync/rollback/${executionId}`,
        { method: 'POST' }
      );
      return response;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to rollback');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setLoading, setError]);

  return { applyConfiguration, rollbackExecution };
}

/**
 * Hook for sync operations
 */
export function useSyncOperations() {
  const { setLoading, setError } = useSyncProStore();

  const getSyncStatus = useCallback(async (): Promise<SyncOperation[]> => {
    try {
      const response = await apiFetch<{ success: boolean; operations: SyncOperation[] }>(
        '/sync/status'
      );
      return response.operations;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to get sync status');
      throw error;
    }
  }, [setError]);

  const startSyncSession = useCallback(async () => {
    try {
      const response = await apiFetch<{ success: boolean; execution_id: number }>(
        '/sync/start',
        { method: 'POST' }
      );
      return response.execution_id;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to start sync session');
      throw error;
    }
  }, [setError]);

  return { getSyncStatus, startSyncSession };
}

/**
 * Hook for templates
 */
export function useTemplates() {
  const { setTemplates, setLoading, setError } = useSyncProStore();

  const fetchTemplates = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiFetch<{ success: boolean; templates: Template[] }>('/templates');
      if (response.success) {
        setTemplates(response.templates);
      }
      return response.templates;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to fetch templates');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setTemplates, setLoading, setError]);

  const createTemplate = useCallback(async (
    title: string,
    description: string,
    workflowData: Workflow
  ) => {
    setLoading(true);
    try {
      const response = await apiFetch<{ success: boolean; id: number }>('/templates', {
        method: 'POST',
        body: JSON.stringify({ title, description, workflow_data: workflowData }),
      });
      return response;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to create template');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setLoading, setError]);

  const deleteTemplate = useCallback(async (id: number) => {
    setLoading(true);
    try {
      const response = await apiFetch<{ success: boolean }>(`/templates/${id}`, {
        method: 'DELETE',
      });
      return response;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to delete template');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setLoading, setError]);

  return { fetchTemplates, createTemplate, deleteTemplate };
}

/**
 * Hook for executing SyncPro nodes (workflow integration)
 */
export function useSyncProExecution() {
  const { setLoading, setError } = useSyncProStore();

  const executeSyncPro = useCallback(async (
    mode: 'analyze' | 'generate' | 'modify',
    target: string,
    input: Record<string, unknown>
  ) => {
    setLoading(true);
    try {
      const response = await apiFetch<{
        success: boolean;
        analysis?: unknown;
        configuration?: unknown;
        sync_operations?: unknown[];
      }>('/execute', {
        method: 'POST',
        body: JSON.stringify({ mode, target, input }),
      });
      return response;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to execute SyncPro');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setLoading, setError]);

  return { executeSyncPro };
}

/**
 * Hook for export/import
 */
export function useConfigExportImport() {
  const { setLoading, setError } = useSyncProStore();

  const exportConfig = useCallback(async (
    bundle: ConfigurationBundle,
    format: 'json' | 'yaml' | 'php' = 'json'
  ) => {
    setLoading(true);
    try {
      const response = await apiFetch<{ success: boolean; content: string; format: string }>(
        '/export',
        {
          method: 'POST',
          body: JSON.stringify({ bundle, format }),
        }
      );
      return response;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to export');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setLoading, setError]);

  const importConfig = useCallback(async (
    content: string,
    format: 'json' | 'yaml' = 'json',
    apply = false
  ) => {
    setLoading(true);
    try {
      const response = await apiFetch<{
        success: boolean;
        bundle: ConfigurationBundle;
        applied?: boolean;
        summary?: unknown;
      }>('/import', {
        method: 'POST',
        body: JSON.stringify({ content, format, apply }),
      });
      return response;
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Failed to import');
      throw error;
    } finally {
      setLoading(false);
    }
  }, [setLoading, setError]);

  return { exportConfig, importConfig };
}

/**
 * Hook for health check
 */
export function useHealthCheck() {
  const healthCheck = useCallback(async () => {
    try {
      const response = await apiFetch<{
        status: string;
        version: string;
        active_plugins: number;
        capabilities: number;
        timestamp: string;
      }>('/health');
      return response;
    } catch {
      return { status: 'error', version: 'unknown', active_plugins: 0, capabilities: 0, timestamp: '' };
    }
  }, []);

  return { healthCheck };
}
