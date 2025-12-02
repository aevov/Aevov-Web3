/**
 * AevSyncPro State Management
 */

import { create } from 'zustand';
import { devtools } from 'zustand/middleware';
import type {
  SyncProStore,
  SyncProState,
  SystemContext,
  Workflow,
  ConfigurationBundle,
  SyncOperation,
  Template,
} from '../types';

const initialState: SyncProState = {
  context: null,
  isLoading: false,
  error: null,
  currentWorkflow: null,
  currentBundle: null,
  syncOperations: [],
  templates: [],
  selectedTarget: 'all',
  prompt: '',
};

export const useSyncProStore = create<SyncProStore>()(
  devtools(
    (set) => ({
      ...initialState,

      setContext: (context: SystemContext) =>
        set({ context, error: null }),

      setLoading: (isLoading: boolean) =>
        set({ isLoading }),

      setError: (error: string | null) =>
        set({ error, isLoading: false }),

      setCurrentWorkflow: (workflow: Workflow | null) =>
        set({ currentWorkflow: workflow }),

      setCurrentBundle: (bundle: ConfigurationBundle | null) =>
        set({ currentBundle: bundle }),

      addSyncOperation: (operation: SyncOperation) =>
        set((state) => ({
          syncOperations: [operation, ...state.syncOperations],
        })),

      updateSyncOperation: (id: string, updates: Partial<SyncOperation>) =>
        set((state) => ({
          syncOperations: state.syncOperations.map((op) =>
            op.id === id ? { ...op, ...updates } : op
          ),
        })),

      setTemplates: (templates: Template[]) =>
        set({ templates }),

      setSelectedTarget: (target: string) =>
        set({ selectedTarget: target }),

      setPrompt: (prompt: string) =>
        set({ prompt }),

      reset: () =>
        set(initialState),
    }),
    { name: 'AevSyncPro' }
  )
);

// Selectors
export const selectContext = (state: SyncProStore) => state.context;
export const selectIsLoading = (state: SyncProStore) => state.isLoading;
export const selectError = (state: SyncProStore) => state.error;
export const selectCurrentWorkflow = (state: SyncProStore) => state.currentWorkflow;
export const selectCurrentBundle = (state: SyncProStore) => state.currentBundle;
export const selectSyncOperations = (state: SyncProStore) => state.syncOperations;
export const selectTemplates = (state: SyncProStore) => state.templates;
export const selectSelectedTarget = (state: SyncProStore) => state.selectedTarget;
export const selectPrompt = (state: SyncProStore) => state.prompt;

// Derived selectors
export const selectActivePlugins = (state: SyncProStore) => {
  if (!state.context?.plugins) return [];
  return Object.entries(state.context.plugins)
    .filter(([, plugin]) => plugin.is_active)
    .map(([slug]) => slug);
};

export const selectAvailableCapabilities = (state: SyncProStore) => {
  if (!state.context?.capabilities) return [];
  return Object.entries(state.context.capabilities)
    .filter(([, cap]) => cap.available)
    .map(([name]) => name);
};

export const selectRecommendations = (state: SyncProStore) => {
  return state.context?.recommendations ?? [];
};

export const selectSystemStats = (state: SyncProStore) => {
  return state.context?.statistics ?? null;
};
