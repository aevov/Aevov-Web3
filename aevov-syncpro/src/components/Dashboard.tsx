/**
 * AevSyncPro Dashboard Component
 *
 * Main dashboard for intelligent workflow generation and configuration management.
 */

import React, { useEffect, useState } from 'react';
import { useSyncProStore, selectContext, selectIsLoading, selectError } from '../store';
import { useSystemContext, useWorkflowGeneration, useConfigGeneration, useTemplates } from '../hooks/useApi';
import { SystemOverview } from './SystemOverview';
import { WorkflowGenerator } from './WorkflowGenerator';
import { ConfigurationPanel } from './ConfigurationPanel';
import { TemplateGallery } from './TemplateGallery';
import { SyncStatus } from './SyncStatus';

type TabType = 'overview' | 'generate' | 'configure' | 'templates' | 'sync';

export const Dashboard: React.FC = () => {
  const [activeTab, setActiveTab] = useState<TabType>('overview');
  const context = useSyncProStore(selectContext);
  const isLoading = useSyncProStore(selectIsLoading);
  const error = useSyncProStore(selectError);
  const { fetchContext } = useSystemContext();
  const { fetchTemplates } = useTemplates();

  useEffect(() => {
    // Load initial data
    fetchContext();
    fetchTemplates();
  }, [fetchContext, fetchTemplates]);

  const tabs: Array<{ id: TabType; label: string; icon: string }> = [
    { id: 'overview', label: 'System Overview', icon: 'dashicons-chart-area' },
    { id: 'generate', label: 'Generate Workflow', icon: 'dashicons-admin-generic' },
    { id: 'configure', label: 'Configuration', icon: 'dashicons-admin-settings' },
    { id: 'templates', label: 'Templates', icon: 'dashicons-portfolio' },
    { id: 'sync', label: 'Sync Status', icon: 'dashicons-update' },
  ];

  return (
    <div className="aevov-syncpro-dashboard">
      <header className="syncpro-header">
        <div className="syncpro-header-content">
          <h1 className="syncpro-title">
            <span className="syncpro-logo">AevSyncPro</span>
            <span className="syncpro-subtitle">Intelligent Configuration Orchestration</span>
          </h1>
          {context && (
            <div className="syncpro-stats">
              <span className="stat">
                <strong>{Object.values(context.plugins).filter(p => p.is_active).length}</strong> Active Plugins
              </span>
              <span className="stat">
                <strong>{Object.keys(context.capabilities).length}</strong> Capabilities
              </span>
              <span className="stat">
                <strong>{context.workflows.total_workflows}</strong> Workflows
              </span>
            </div>
          )}
        </div>
      </header>

      {error && (
        <div className="syncpro-error notice notice-error">
          <p>{error}</p>
        </div>
      )}

      <nav className="syncpro-tabs">
        {tabs.map((tab) => (
          <button
            key={tab.id}
            className={`syncpro-tab ${activeTab === tab.id ? 'active' : ''}`}
            onClick={() => setActiveTab(tab.id)}
          >
            <span className={`dashicons ${tab.icon}`}></span>
            <span className="tab-label">{tab.label}</span>
          </button>
        ))}
      </nav>

      <main className="syncpro-content">
        {isLoading && (
          <div className="syncpro-loading">
            <span className="spinner is-active"></span>
            <p>Loading...</p>
          </div>
        )}

        {!isLoading && (
          <>
            {activeTab === 'overview' && <SystemOverview />}
            {activeTab === 'generate' && <WorkflowGenerator />}
            {activeTab === 'configure' && <ConfigurationPanel />}
            {activeTab === 'templates' && <TemplateGallery />}
            {activeTab === 'sync' && <SyncStatus />}
          </>
        )}
      </main>

      <style>{`
        .aevov-syncpro-dashboard {
          max-width: 1400px;
          margin: 20px auto;
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .syncpro-header {
          background: linear-gradient(135deg, #8B5CF6 0%, #6366F1 100%);
          color: white;
          padding: 24px 32px;
          border-radius: 12px;
          margin-bottom: 24px;
        }

        .syncpro-header-content {
          display: flex;
          justify-content: space-between;
          align-items: center;
          flex-wrap: wrap;
          gap: 16px;
        }

        .syncpro-title {
          margin: 0;
          display: flex;
          flex-direction: column;
        }

        .syncpro-logo {
          font-size: 28px;
          font-weight: 700;
        }

        .syncpro-subtitle {
          font-size: 14px;
          opacity: 0.9;
          font-weight: 400;
        }

        .syncpro-stats {
          display: flex;
          gap: 24px;
        }

        .syncpro-stats .stat {
          background: rgba(255, 255, 255, 0.15);
          padding: 8px 16px;
          border-radius: 8px;
          font-size: 14px;
        }

        .syncpro-stats .stat strong {
          font-size: 18px;
          margin-right: 4px;
        }

        .syncpro-error {
          margin-bottom: 16px;
        }

        .syncpro-tabs {
          display: flex;
          gap: 4px;
          background: #f1f5f9;
          padding: 4px;
          border-radius: 10px;
          margin-bottom: 24px;
        }

        .syncpro-tab {
          display: flex;
          align-items: center;
          gap: 8px;
          padding: 12px 20px;
          border: none;
          background: transparent;
          border-radius: 8px;
          cursor: pointer;
          font-size: 14px;
          font-weight: 500;
          color: #64748b;
          transition: all 0.2s;
        }

        .syncpro-tab:hover {
          background: rgba(139, 92, 246, 0.1);
          color: #8B5CF6;
        }

        .syncpro-tab.active {
          background: white;
          color: #8B5CF6;
          box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .syncpro-tab .dashicons {
          font-size: 18px;
          width: 18px;
          height: 18px;
        }

        .syncpro-content {
          background: white;
          border-radius: 12px;
          padding: 24px;
          box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
          min-height: 500px;
        }

        .syncpro-loading {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          padding: 48px;
          color: #64748b;
        }

        .syncpro-loading .spinner {
          margin-bottom: 16px;
        }
      `}</style>
    </div>
  );
};
