/**
 * Configuration Panel Component
 *
 * View and manage generated configurations.
 */

import React, { useState, useCallback } from 'react';
import { useSyncProStore, selectCurrentBundle, selectIsLoading } from '../store';
import { useConfigApply, useConfigExportImport } from '../hooks/useApi';
import type { ConfigurationBundle } from '../types';

const CONFIG_SECTIONS = [
  { key: 'ai_engines', label: 'AI Engines', icon: 'dashicons-admin-generic' },
  { key: 'storage', label: 'Storage', icon: 'dashicons-database' },
  { key: 'workflows', label: 'Workflows', icon: 'dashicons-randomize' },
  { key: 'security', label: 'Security', icon: 'dashicons-shield' },
  { key: 'patterns', label: 'Patterns', icon: 'dashicons-filter' },
  { key: 'memory', label: 'Memory', icon: 'dashicons-portfolio' },
  { key: 'network', label: 'Network', icon: 'dashicons-networking' },
];

export const ConfigurationPanel: React.FC = () => {
  const [selectedSection, setSelectedSection] = useState<string>('ai_engines');
  const [editMode, setEditMode] = useState(false);
  const [editedConfig, setEditedConfig] = useState<ConfigurationBundle | null>(null);

  const currentBundle = useSyncProStore(selectCurrentBundle);
  const isLoading = useSyncProStore(selectIsLoading);

  const { applyConfiguration } = useConfigApply();
  const { exportConfig } = useConfigExportImport();

  const displayBundle = editedConfig || currentBundle;

  const handleApply = useCallback(async () => {
    if (!displayBundle) return;

    const confirmed = window.confirm('Apply this configuration to the system?');
    if (!confirmed) return;

    try {
      const result = await applyConfiguration(displayBundle, false);
      if (result.success) {
        alert('Configuration applied successfully!');
        setEditMode(false);
        setEditedConfig(null);
      } else {
        alert('Failed to apply configuration');
      }
    } catch (error) {
      console.error('Apply failed:', error);
    }
  }, [displayBundle, applyConfiguration]);

  const handleExport = useCallback(async (format: 'json' | 'yaml' | 'php') => {
    if (!displayBundle) return;

    try {
      const result = await exportConfig(displayBundle, format);
      if (result.success) {
        // Create download
        const blob = new Blob([result.content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `aevov-config.${format === 'php' ? 'php' : format}`;
        a.click();
        URL.revokeObjectURL(url);
      }
    } catch (error) {
      console.error('Export failed:', error);
    }
  }, [displayBundle, exportConfig]);

  const handleDryRun = useCallback(async () => {
    if (!displayBundle) return;

    try {
      const result = await applyConfiguration(displayBundle, true);
      console.log('Dry run result:', result);
      alert('Dry run complete - check console for details');
    } catch (error) {
      console.error('Dry run failed:', error);
    }
  }, [displayBundle, applyConfiguration]);

  const getSectionConfig = (section: string) => {
    if (!displayBundle) return null;
    return displayBundle[section as keyof ConfigurationBundle];
  };

  const renderConfigValue = (value: unknown, depth = 0): React.ReactNode => {
    if (value === null || value === undefined) {
      return <span className="config-null">null</span>;
    }
    if (typeof value === 'boolean') {
      return <span className={`config-bool ${value ? 'true' : 'false'}`}>{value.toString()}</span>;
    }
    if (typeof value === 'number') {
      return <span className="config-number">{value}</span>;
    }
    if (typeof value === 'string') {
      return <span className="config-string">"{value}"</span>;
    }
    if (Array.isArray(value)) {
      if (value.length === 0) return <span className="config-array">[]</span>;
      return (
        <div className="config-array" style={{ marginLeft: depth * 16 }}>
          [
          {value.map((item, i) => (
            <div key={i} className="array-item">
              {renderConfigValue(item, depth + 1)}
              {i < value.length - 1 && ','}
            </div>
          ))}
          ]
        </div>
      );
    }
    if (typeof value === 'object') {
      const entries = Object.entries(value);
      if (entries.length === 0) return <span className="config-object">{'{}'}</span>;
      return (
        <div className="config-object" style={{ marginLeft: depth * 16 }}>
          {'{'}
          {entries.map(([key, val], i) => (
            <div key={key} className="object-entry">
              <span className="config-key">{key}</span>: {renderConfigValue(val, depth + 1)}
              {i < entries.length - 1 && ','}
            </div>
          ))}
          {'}'}
        </div>
      );
    }
    return String(value);
  };

  return (
    <div className="configuration-panel">
      <div className="panel-header">
        <h2>Configuration Management</h2>
        <div className="panel-actions">
          <button className="btn-icon" onClick={() => handleExport('json')} title="Export as JSON">
            <span className="dashicons dashicons-download"></span>
          </button>
          <button className="btn-icon" onClick={handleDryRun} title="Dry Run">
            <span className="dashicons dashicons-visibility"></span>
          </button>
          <button
            className="btn-primary"
            onClick={handleApply}
            disabled={!displayBundle || isLoading}
          >
            {isLoading ? 'Applying...' : 'Apply Configuration'}
          </button>
        </div>
      </div>

      {!displayBundle ? (
        <div className="no-config">
          <span className="dashicons dashicons-info-outline"></span>
          <p>No configuration loaded. Generate a workflow first to create a configuration bundle.</p>
        </div>
      ) : (
        <div className="config-layout">
          <div className="config-sidebar">
            {CONFIG_SECTIONS.map((section) => {
              const config = getSectionConfig(section.key);
              const hasConfig = config && Object.keys(config).length > 0;

              return (
                <button
                  key={section.key}
                  className={`section-btn ${selectedSection === section.key ? 'active' : ''} ${hasConfig ? 'has-config' : ''}`}
                  onClick={() => setSelectedSection(section.key)}
                >
                  <span className={`dashicons ${section.icon}`}></span>
                  <span className="section-label">{section.label}</span>
                  {hasConfig && <span className="config-indicator"></span>}
                </button>
              );
            })}
          </div>

          <div className="config-content">
            <div className="section-header">
              <h3>{CONFIG_SECTIONS.find(s => s.key === selectedSection)?.label}</h3>
              <button
                className="btn-text"
                onClick={() => setEditMode(!editMode)}
              >
                {editMode ? 'View Mode' : 'Edit Mode'}
              </button>
            </div>

            <div className="config-viewer">
              {getSectionConfig(selectedSection) ? (
                editMode ? (
                  <textarea
                    className="config-editor"
                    value={JSON.stringify(getSectionConfig(selectedSection), null, 2)}
                    onChange={(e) => {
                      try {
                        const parsed = JSON.parse(e.target.value);
                        setEditedConfig({
                          ...displayBundle,
                          [selectedSection]: parsed,
                        });
                      } catch {
                        // Invalid JSON, ignore
                      }
                    }}
                  />
                ) : (
                  <div className="config-tree">
                    {renderConfigValue(getSectionConfig(selectedSection))}
                  </div>
                )
              ) : (
                <div className="no-section-config">
                  <p>No configuration for this section</p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      <style>{`
        .configuration-panel {
          animation: fadeIn 0.3s ease;
        }

        .panel-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 24px;
        }

        .panel-header h2 {
          margin: 0;
          font-size: 20px;
          color: #1e293b;
        }

        .panel-actions {
          display: flex;
          gap: 12px;
          align-items: center;
        }

        .btn-icon {
          width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          background: #f1f5f9;
          border: none;
          border-radius: 8px;
          cursor: pointer;
          color: #64748b;
          transition: all 0.2s;
        }

        .btn-icon:hover {
          background: #e2e8f0;
          color: #334155;
        }

        .btn-primary {
          padding: 10px 20px;
          background: #8B5CF6;
          color: white;
          border: none;
          border-radius: 8px;
          font-size: 14px;
          font-weight: 600;
          cursor: pointer;
          transition: background 0.2s;
        }

        .btn-primary:hover:not(:disabled) {
          background: #7c3aed;
        }

        .btn-primary:disabled {
          opacity: 0.6;
          cursor: not-allowed;
        }

        .no-config {
          display: flex;
          flex-direction: column;
          align-items: center;
          padding: 48px;
          color: #64748b;
        }

        .no-config .dashicons {
          font-size: 48px;
          width: 48px;
          height: 48px;
          margin-bottom: 16px;
        }

        .config-layout {
          display: grid;
          grid-template-columns: 200px 1fr;
          gap: 24px;
          background: #f8fafc;
          border-radius: 12px;
          padding: 20px;
          border: 1px solid #e2e8f0;
        }

        .config-sidebar {
          display: flex;
          flex-direction: column;
          gap: 4px;
        }

        .section-btn {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 12px;
          background: transparent;
          border: none;
          border-radius: 8px;
          cursor: pointer;
          text-align: left;
          color: #64748b;
          transition: all 0.2s;
          position: relative;
        }

        .section-btn:hover {
          background: white;
          color: #334155;
        }

        .section-btn.active {
          background: white;
          color: #8B5CF6;
          box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .section-btn .dashicons {
          font-size: 18px;
          width: 18px;
          height: 18px;
        }

        .section-label {
          font-size: 14px;
          font-weight: 500;
        }

        .config-indicator {
          position: absolute;
          right: 12px;
          width: 8px;
          height: 8px;
          background: #22c55e;
          border-radius: 50%;
        }

        .config-content {
          background: white;
          border-radius: 8px;
          padding: 20px;
          min-height: 400px;
        }

        .section-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 16px;
          padding-bottom: 16px;
          border-bottom: 1px solid #e2e8f0;
        }

        .section-header h3 {
          margin: 0;
          font-size: 16px;
          color: #334155;
        }

        .btn-text {
          background: none;
          border: none;
          color: #8B5CF6;
          cursor: pointer;
          font-size: 13px;
          font-weight: 500;
        }

        .config-viewer {
          font-family: 'Menlo', 'Monaco', 'Courier New', monospace;
          font-size: 13px;
        }

        .config-tree {
          line-height: 1.6;
        }

        .config-key {
          color: #0d9488;
        }

        .config-string {
          color: #16a34a;
        }

        .config-number {
          color: #2563eb;
        }

        .config-bool {
          color: #8B5CF6;
        }

        .config-bool.true {
          color: #16a34a;
        }

        .config-bool.false {
          color: #dc2626;
        }

        .config-null {
          color: #64748b;
          font-style: italic;
        }

        .object-entry, .array-item {
          padding-left: 16px;
        }

        .config-editor {
          width: 100%;
          min-height: 300px;
          padding: 16px;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          font-family: 'Menlo', 'Monaco', 'Courier New', monospace;
          font-size: 13px;
          resize: vertical;
        }

        .config-editor:focus {
          outline: none;
          border-color: #8B5CF6;
        }

        .no-section-config {
          padding: 24px;
          text-align: center;
          color: #94a3b8;
        }
      `}</style>
    </div>
  );
};
