/**
 * Template Gallery Component
 *
 * Browse and use pre-built workflow templates.
 */

import React, { useEffect, useCallback, useState } from 'react';
import { useSyncProStore, selectTemplates, selectIsLoading } from '../store';
import { useTemplates, useWorkflowGeneration } from '../hooks/useApi';
import type { Template } from '../types';

const DEFAULT_TEMPLATES = [
  {
    id: 'quick-setup',
    title: 'Quick System Setup',
    description: 'Get your Aevov system running in minutes with optimal defaults',
    icon: 'dashicons-admin-generic',
    color: '#8B5CF6',
  },
  {
    id: 'ai-optimization',
    title: 'AI Engine Optimization',
    description: 'Configure all AI engines for maximum performance and reliability',
    icon: 'dashicons-superhero',
    color: '#3b82f6',
  },
  {
    id: 'security-hardening',
    title: 'Security Hardening',
    description: 'Apply enterprise-grade security settings across the system',
    icon: 'dashicons-shield',
    color: '#dc2626',
  },
  {
    id: 'distributed-storage',
    title: 'Distributed Storage',
    description: 'Set up Cubbit CDN and redundant storage for reliability',
    icon: 'dashicons-cloud',
    color: '#22c55e',
  },
  {
    id: 'workflow-automation',
    title: 'Workflow Automation',
    description: 'Configure automated workflows for common tasks',
    icon: 'dashicons-randomize',
    color: '#f59e0b',
  },
  {
    id: 'pattern-sync',
    title: 'Pattern Synchronization',
    description: 'Enable BLOOM pattern detection and sync across nodes',
    icon: 'dashicons-filter',
    color: '#ec4899',
  },
];

export const TemplateGallery: React.FC = () => {
  const [selectedTemplate, setSelectedTemplate] = useState<Template | null>(null);
  const templates = useSyncProStore(selectTemplates);
  const isLoading = useSyncProStore(selectIsLoading);

  const { fetchTemplates, deleteTemplate } = useTemplates();
  const { generateWorkflow } = useWorkflowGeneration();
  const setCurrentWorkflow = useSyncProStore((state) => state.setCurrentWorkflow);

  useEffect(() => {
    fetchTemplates();
  }, [fetchTemplates]);

  const handleUseTemplate = useCallback(async (template: Template) => {
    if (template.workflow_data) {
      setCurrentWorkflow(template.workflow_data);
    } else {
      // Generate workflow from template description
      await generateWorkflow(template.description, { target: 'all' });
    }
  }, [setCurrentWorkflow, generateWorkflow]);

  const handleDeleteTemplate = useCallback(async (id: number) => {
    const confirmed = window.confirm('Delete this template?');
    if (!confirmed) return;

    await deleteTemplate(id);
    fetchTemplates();
  }, [deleteTemplate, fetchTemplates]);

  return (
    <div className="template-gallery">
      <div className="gallery-header">
        <h2>Workflow Templates</h2>
        <p>Choose a template to quickly configure your Aevov system</p>
      </div>

      {/* Quick Start Templates */}
      <section className="template-section">
        <h3>Quick Start</h3>
        <div className="template-grid">
          {DEFAULT_TEMPLATES.map((template) => (
            <div
              key={template.id}
              className="template-card"
              style={{ '--card-color': template.color } as React.CSSProperties}
            >
              <div className="template-icon" style={{ background: template.color }}>
                <span className={`dashicons ${template.icon}`}></span>
              </div>
              <div className="template-info">
                <h4>{template.title}</h4>
                <p>{template.description}</p>
              </div>
              <button
                className="btn-use-template"
                onClick={() => generateWorkflow(template.description, { target: 'all' })}
              >
                Use Template
              </button>
            </div>
          ))}
        </div>
      </section>

      {/* Custom Templates */}
      {templates.length > 0 && (
        <section className="template-section">
          <h3>Custom Templates</h3>
          <div className="template-list">
            {templates.map((template) => (
              <div key={template.id} className="template-item">
                <div className="template-content">
                  <h4>{template.title}</h4>
                  <p>{template.description}</p>
                  {template.is_default && (
                    <span className="default-badge">Default</span>
                  )}
                </div>
                <div className="template-actions">
                  <button
                    className="btn-action"
                    onClick={() => setSelectedTemplate(template)}
                  >
                    Preview
                  </button>
                  <button
                    className="btn-action primary"
                    onClick={() => handleUseTemplate(template)}
                  >
                    Use
                  </button>
                  {!template.is_default && (
                    <button
                      className="btn-action danger"
                      onClick={() => handleDeleteTemplate(template.id)}
                    >
                      Delete
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* Template Preview Modal */}
      {selectedTemplate && (
        <div className="template-modal-overlay" onClick={() => setSelectedTemplate(null)}>
          <div className="template-modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>{selectedTemplate.title}</h3>
              <button className="btn-close" onClick={() => setSelectedTemplate(null)}>
                <span className="dashicons dashicons-no-alt"></span>
              </button>
            </div>
            <div className="modal-content">
              <p className="template-description">{selectedTemplate.description}</p>
              {selectedTemplate.workflow_data && (
                <div className="workflow-preview">
                  <h4>Workflow Steps</h4>
                  <div className="step-list">
                    {selectedTemplate.workflow_data.nodes?.map((node, i) => (
                      <div key={node.id} className="step-item">
                        <span className="step-number">{i + 1}</span>
                        <span className="step-label">{node.data.label}</span>
                        <span className="step-type">{node.type}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
            <div className="modal-footer">
              <button className="btn-secondary" onClick={() => setSelectedTemplate(null)}>
                Cancel
              </button>
              <button
                className="btn-primary"
                onClick={() => {
                  handleUseTemplate(selectedTemplate);
                  setSelectedTemplate(null);
                }}
              >
                Use This Template
              </button>
            </div>
          </div>
        </div>
      )}

      <style>{`
        .template-gallery {
          animation: fadeIn 0.3s ease;
        }

        .gallery-header {
          margin-bottom: 32px;
        }

        .gallery-header h2 {
          margin: 0 0 8px 0;
          font-size: 20px;
          color: #1e293b;
        }

        .gallery-header p {
          margin: 0;
          color: #64748b;
        }

        .template-section {
          margin-bottom: 32px;
        }

        .template-section h3 {
          margin: 0 0 16px 0;
          font-size: 16px;
          color: #334155;
        }

        .template-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
          gap: 16px;
        }

        .template-card {
          background: white;
          border: 2px solid #e2e8f0;
          border-radius: 12px;
          padding: 20px;
          transition: all 0.2s;
          cursor: pointer;
        }

        .template-card:hover {
          border-color: var(--card-color);
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .template-icon {
          width: 48px;
          height: 48px;
          border-radius: 10px;
          display: flex;
          align-items: center;
          justify-content: center;
          margin-bottom: 16px;
        }

        .template-icon .dashicons {
          color: white;
          font-size: 24px;
          width: 24px;
          height: 24px;
        }

        .template-info h4 {
          margin: 0 0 8px 0;
          font-size: 16px;
          color: #1e293b;
        }

        .template-info p {
          margin: 0 0 16px 0;
          font-size: 13px;
          color: #64748b;
          line-height: 1.5;
        }

        .btn-use-template {
          width: 100%;
          padding: 10px;
          background: #f1f5f9;
          border: none;
          border-radius: 8px;
          font-size: 14px;
          font-weight: 500;
          color: #334155;
          cursor: pointer;
          transition: all 0.2s;
        }

        .btn-use-template:hover {
          background: var(--card-color);
          color: white;
        }

        .template-list {
          display: flex;
          flex-direction: column;
          gap: 12px;
        }

        .template-item {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 16px 20px;
          background: #f8fafc;
          border-radius: 10px;
          border: 1px solid #e2e8f0;
        }

        .template-content {
          flex: 1;
        }

        .template-content h4 {
          margin: 0 0 4px 0;
          font-size: 15px;
          color: #1e293b;
        }

        .template-content p {
          margin: 0;
          font-size: 13px;
          color: #64748b;
        }

        .default-badge {
          display: inline-block;
          margin-top: 8px;
          padding: 2px 8px;
          background: #dbeafe;
          color: #2563eb;
          font-size: 11px;
          font-weight: 600;
          border-radius: 4px;
        }

        .template-actions {
          display: flex;
          gap: 8px;
        }

        .btn-action {
          padding: 8px 16px;
          background: white;
          border: 1px solid #e2e8f0;
          border-radius: 6px;
          font-size: 13px;
          cursor: pointer;
          transition: all 0.2s;
        }

        .btn-action:hover {
          background: #f1f5f9;
        }

        .btn-action.primary {
          background: #8B5CF6;
          color: white;
          border-color: #8B5CF6;
        }

        .btn-action.primary:hover {
          background: #7c3aed;
        }

        .btn-action.danger {
          color: #dc2626;
        }

        .btn-action.danger:hover {
          background: #fef2f2;
        }

        .template-modal-overlay {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 1000;
        }

        .template-modal {
          background: white;
          border-radius: 16px;
          width: 100%;
          max-width: 600px;
          max-height: 80vh;
          overflow: hidden;
          display: flex;
          flex-direction: column;
        }

        .modal-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 20px 24px;
          border-bottom: 1px solid #e2e8f0;
        }

        .modal-header h3 {
          margin: 0;
          font-size: 18px;
          color: #1e293b;
        }

        .btn-close {
          background: none;
          border: none;
          cursor: pointer;
          color: #64748b;
        }

        .modal-content {
          padding: 24px;
          overflow-y: auto;
        }

        .template-description {
          margin: 0 0 24px 0;
          color: #475569;
          line-height: 1.6;
        }

        .workflow-preview h4 {
          margin: 0 0 12px 0;
          font-size: 14px;
          color: #64748b;
        }

        .step-list {
          display: flex;
          flex-direction: column;
          gap: 8px;
        }

        .step-item {
          display: flex;
          align-items: center;
          gap: 12px;
          padding: 12px;
          background: #f8fafc;
          border-radius: 8px;
        }

        .step-number {
          width: 24px;
          height: 24px;
          display: flex;
          align-items: center;
          justify-content: center;
          background: #8B5CF6;
          color: white;
          border-radius: 50%;
          font-size: 12px;
          font-weight: 600;
        }

        .step-label {
          flex: 1;
          font-weight: 500;
          color: #334155;
        }

        .step-type {
          font-size: 12px;
          color: #8B5CF6;
          background: #f5f3ff;
          padding: 4px 8px;
          border-radius: 4px;
        }

        .modal-footer {
          display: flex;
          justify-content: flex-end;
          gap: 12px;
          padding: 20px 24px;
          border-top: 1px solid #e2e8f0;
        }

        .btn-secondary {
          padding: 10px 20px;
          background: white;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          font-size: 14px;
          cursor: pointer;
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
        }
      `}</style>
    </div>
  );
};
