/**
 * Workflow Generator Component
 *
 * AI-powered workflow generation from natural language descriptions.
 * This is the core "onboarding on steroids" interface.
 */

import React, { useState, useCallback } from 'react';
import { useSyncProStore, selectContext, selectCurrentWorkflow, selectIsLoading, selectSelectedTarget } from '../store';
import { useWorkflowGeneration, useConfigGeneration, useConfigApply } from '../hooks/useApi';
import type { Workflow, GenerateWorkflowResponse } from '../types';

const TARGET_OPTIONS = [
  { value: 'all', label: 'All Systems', description: 'Configure entire Aevov ecosystem' },
  { value: 'ai_engines', label: 'AI Engines', description: 'Language, Image, Cognitive, etc.' },
  { value: 'storage', label: 'Storage', description: 'Memory Core, CDN, Database' },
  { value: 'workflows', label: 'Workflows', description: 'Workflow engine settings' },
  { value: 'security', label: 'Security', description: 'Auth, encryption, rate limiting' },
  { value: 'patterns', label: 'Patterns', description: 'BLOOM pattern recognition' },
  { value: 'network', label: 'Network', description: 'Meshcore P2P settings' },
];

const EXAMPLE_PROMPTS = [
  'Set up a high-performance AI content generation system',
  'Configure secure authentication with JWT and 2FA',
  'Enable distributed storage with Cubbit CDN integration',
  'Create an automated workflow for processing user uploads',
  'Optimize the system for maximum concurrent users',
  'Set up pattern recognition for content moderation',
];

export const WorkflowGenerator: React.FC = () => {
  const [prompt, setPrompt] = useState('');
  const [target, setTarget] = useState('all');
  const [autoApply, setAutoApply] = useState(false);
  const [result, setResult] = useState<GenerateWorkflowResponse | null>(null);
  const [showPreview, setShowPreview] = useState(false);

  const context = useSyncProStore(selectContext);
  const currentWorkflow = useSyncProStore(selectCurrentWorkflow);
  const isLoading = useSyncProStore(selectIsLoading);

  const { generateWorkflow } = useWorkflowGeneration();
  const { generateBundle } = useConfigGeneration();
  const { applyConfiguration } = useConfigApply();

  const handleGenerate = useCallback(async () => {
    if (!prompt.trim()) return;

    try {
      const response = await generateWorkflow(prompt, {
        target,
        auto_apply: autoApply,
      });
      setResult(response);
      setShowPreview(true);
    } catch (error) {
      console.error('Generation failed:', error);
    }
  }, [prompt, target, autoApply, generateWorkflow]);

  const handleApply = useCallback(async () => {
    if (!result?.workflow) return;

    try {
      // Generate configuration bundle from workflow
      const bundleResponse = await generateBundle(
        { workflow: result.workflow, prompt },
        { target }
      );

      if (bundleResponse.success && bundleResponse.bundle) {
        // Apply the configuration
        const applyResult = await applyConfiguration(bundleResponse.bundle, false);
        alert(applyResult.success ? 'Configuration applied successfully!' : 'Failed to apply configuration');
      }
    } catch (error) {
      console.error('Apply failed:', error);
    }
  }, [result, prompt, target, generateBundle, applyConfiguration]);

  const handleExampleClick = (example: string) => {
    setPrompt(example);
  };

  return (
    <div className="workflow-generator">
      <div className="generator-header">
        <h2>Generate Intelligent Workflow</h2>
        <p>Describe what you want to configure, and AevSyncPro will create a ready-to-use workflow and configuration.</p>
      </div>

      <div className="generator-form">
        <div className="form-section">
          <label>What do you want to configure?</label>
          <textarea
            value={prompt}
            onChange={(e) => setPrompt(e.target.value)}
            placeholder="Describe your configuration needs in natural language..."
            rows={4}
          />
          <div className="example-prompts">
            <span className="example-label">Examples:</span>
            {EXAMPLE_PROMPTS.map((example, i) => (
              <button
                key={i}
                className="example-chip"
                onClick={() => handleExampleClick(example)}
              >
                {example}
              </button>
            ))}
          </div>
        </div>

        <div className="form-row">
          <div className="form-section target-section">
            <label>Target System</label>
            <div className="target-grid">
              {TARGET_OPTIONS.map((option) => (
                <button
                  key={option.value}
                  className={`target-option ${target === option.value ? 'selected' : ''}`}
                  onClick={() => setTarget(option.value)}
                >
                  <strong>{option.label}</strong>
                  <span>{option.description}</span>
                </button>
              ))}
            </div>
          </div>
        </div>

        <div className="form-section options-section">
          <label className="checkbox-label">
            <input
              type="checkbox"
              checked={autoApply}
              onChange={(e) => setAutoApply(e.target.checked)}
            />
            <span>Auto-apply configuration after generation</span>
          </label>
        </div>

        <div className="form-actions">
          <button
            className="btn-primary btn-generate"
            onClick={handleGenerate}
            disabled={isLoading || !prompt.trim()}
          >
            {isLoading ? (
              <>
                <span className="spinner is-active"></span>
                Generating...
              </>
            ) : (
              <>
                <span className="dashicons dashicons-admin-generic"></span>
                Generate Workflow
              </>
            )}
          </button>
        </div>
      </div>

      {result && showPreview && (
        <div className="generation-result">
          <div className="result-header">
            <h3>Generated Workflow</h3>
            <button className="btn-close" onClick={() => setShowPreview(false)}>
              <span className="dashicons dashicons-no-alt"></span>
            </button>
          </div>

          <div className="result-content">
            {/* Analysis Summary */}
            <div className="result-section analysis-section">
              <h4>Analysis</h4>
              <div className="analysis-grid">
                <div className="analysis-item">
                  <strong>Goal</strong>
                  <p>{result.analysis.goal}</p>
                </div>
                <div className="analysis-item">
                  <strong>Required Capabilities</strong>
                  <div className="tag-list">
                    {result.analysis.required_capabilities.map((cap, i) => (
                      <span key={i} className="tag">{cap}</span>
                    ))}
                  </div>
                </div>
                <div className="analysis-item">
                  <strong>Estimated Steps</strong>
                  <span className="step-count">{result.estimated_steps}</span>
                </div>
              </div>
            </div>

            {/* Workflow Preview */}
            <div className="result-section workflow-section">
              <h4>Workflow Structure</h4>
              <div className="workflow-preview">
                <div className="node-flow">
                  {result.workflow.nodes.map((node, i) => (
                    <React.Fragment key={node.id}>
                      <div className={`workflow-node node-${node.type}`}>
                        <span className="node-type">{node.type}</span>
                        <span className="node-label">{node.data.label}</span>
                      </div>
                      {i < result.workflow.nodes.length - 1 && (
                        <span className="node-connector">→</span>
                      )}
                    </React.Fragment>
                  ))}
                </div>
              </div>
            </div>

            {/* Validation */}
            <div className="result-section validation-section">
              <h4>Validation</h4>
              {result.validation.valid ? (
                <div className="validation-success">
                  <span className="dashicons dashicons-yes-alt"></span>
                  Workflow is valid and ready to execute
                </div>
              ) : (
                <div className="validation-issues">
                  {result.validation.issues.map((issue, i) => (
                    <div key={i} className={`validation-issue ${issue.severity}`}>
                      <span className="issue-type">{issue.type}</span>
                      <span className="issue-message">{issue.message}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Actions */}
            <div className="result-actions">
              <button className="btn-secondary" onClick={() => setShowPreview(false)}>
                Cancel
              </button>
              <button className="btn-secondary" onClick={() => {
                // Save as template
              }}>
                Save as Template
              </button>
              <button
                className="btn-primary"
                onClick={handleApply}
                disabled={!result.validation.valid || isLoading}
              >
                {isLoading ? 'Applying...' : 'Apply Configuration'}
              </button>
            </div>
          </div>
        </div>
      )}

      <style>{`
        .workflow-generator {
          animation: fadeIn 0.3s ease;
        }

        .generator-header {
          margin-bottom: 24px;
        }

        .generator-header h2 {
          margin: 0 0 8px 0;
          font-size: 20px;
          color: #1e293b;
        }

        .generator-header p {
          margin: 0;
          color: #64748b;
        }

        .generator-form {
          background: #f8fafc;
          border-radius: 12px;
          padding: 24px;
          border: 1px solid #e2e8f0;
        }

        .form-section {
          margin-bottom: 24px;
        }

        .form-section label {
          display: block;
          font-weight: 600;
          color: #334155;
          margin-bottom: 8px;
        }

        .form-section textarea {
          width: 100%;
          padding: 12px;
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          font-size: 14px;
          resize: vertical;
          transition: border-color 0.2s;
        }

        .form-section textarea:focus {
          outline: none;
          border-color: #8B5CF6;
        }

        .example-prompts {
          display: flex;
          flex-wrap: wrap;
          gap: 8px;
          margin-top: 12px;
          align-items: center;
        }

        .example-label {
          font-size: 13px;
          color: #64748b;
        }

        .example-chip {
          padding: 6px 12px;
          background: white;
          border: 1px solid #e2e8f0;
          border-radius: 20px;
          font-size: 12px;
          color: #475569;
          cursor: pointer;
          transition: all 0.2s;
        }

        .example-chip:hover {
          background: #8B5CF6;
          color: white;
          border-color: #8B5CF6;
        }

        .target-grid {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
          gap: 12px;
        }

        .target-option {
          display: flex;
          flex-direction: column;
          align-items: flex-start;
          padding: 14px;
          background: white;
          border: 2px solid #e2e8f0;
          border-radius: 10px;
          cursor: pointer;
          transition: all 0.2s;
          text-align: left;
        }

        .target-option:hover {
          border-color: #8B5CF6;
        }

        .target-option.selected {
          border-color: #8B5CF6;
          background: #f5f3ff;
        }

        .target-option strong {
          font-size: 14px;
          color: #334155;
          margin-bottom: 4px;
        }

        .target-option span {
          font-size: 12px;
          color: #64748b;
        }

        .checkbox-label {
          display: flex;
          align-items: center;
          gap: 10px;
          cursor: pointer;
        }

        .checkbox-label input {
          width: 18px;
          height: 18px;
          accent-color: #8B5CF6;
        }

        .form-actions {
          display: flex;
          justify-content: flex-end;
          margin-top: 24px;
        }

        .btn-primary {
          display: flex;
          align-items: center;
          gap: 8px;
          padding: 12px 24px;
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

        .btn-secondary {
          padding: 12px 24px;
          background: white;
          color: #475569;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          font-size: 14px;
          font-weight: 500;
          cursor: pointer;
          transition: all 0.2s;
        }

        .btn-secondary:hover {
          background: #f8fafc;
        }

        .generation-result {
          margin-top: 24px;
          background: white;
          border: 2px solid #8B5CF6;
          border-radius: 12px;
          overflow: hidden;
        }

        .result-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 16px 20px;
          background: #f5f3ff;
          border-bottom: 1px solid #e2e8f0;
        }

        .result-header h3 {
          margin: 0;
          color: #5b21b6;
        }

        .btn-close {
          background: none;
          border: none;
          cursor: pointer;
          color: #64748b;
        }

        .result-content {
          padding: 20px;
        }

        .result-section {
          margin-bottom: 24px;
        }

        .result-section h4 {
          margin: 0 0 12px 0;
          font-size: 14px;
          color: #64748b;
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }

        .analysis-grid {
          display: grid;
          gap: 16px;
        }

        .analysis-item {
          padding: 16px;
          background: #f8fafc;
          border-radius: 8px;
        }

        .analysis-item strong {
          display: block;
          font-size: 12px;
          color: #64748b;
          margin-bottom: 8px;
        }

        .analysis-item p {
          margin: 0;
          color: #334155;
        }

        .tag-list {
          display: flex;
          flex-wrap: wrap;
          gap: 6px;
        }

        .tag {
          padding: 4px 10px;
          background: #8B5CF6;
          color: white;
          border-radius: 4px;
          font-size: 12px;
        }

        .step-count {
          font-size: 24px;
          font-weight: 700;
          color: #8B5CF6;
        }

        .workflow-preview {
          padding: 20px;
          background: #f8fafc;
          border-radius: 8px;
          overflow-x: auto;
        }

        .node-flow {
          display: flex;
          align-items: center;
          gap: 8px;
          min-width: max-content;
        }

        .workflow-node {
          display: flex;
          flex-direction: column;
          align-items: center;
          padding: 12px 16px;
          background: white;
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          min-width: 120px;
        }

        .workflow-node.node-input {
          border-color: #22c55e;
          background: #f0fdf4;
        }

        .workflow-node.node-output {
          border-color: #3b82f6;
          background: #eff6ff;
        }

        .workflow-node.node-syncpro {
          border-color: #8B5CF6;
          background: #f5f3ff;
        }

        .node-type {
          font-size: 10px;
          text-transform: uppercase;
          color: #64748b;
        }

        .node-label {
          font-size: 13px;
          font-weight: 500;
          color: #334155;
        }

        .node-connector {
          color: #94a3b8;
          font-size: 20px;
        }

        .validation-success {
          display: flex;
          align-items: center;
          gap: 8px;
          padding: 12px 16px;
          background: #f0fdf4;
          color: #16a34a;
          border-radius: 8px;
        }

        .validation-issues {
          display: flex;
          flex-direction: column;
          gap: 8px;
        }

        .validation-issue {
          display: flex;
          gap: 12px;
          padding: 12px;
          background: #fef2f2;
          border-radius: 8px;
        }

        .validation-issue.warning {
          background: #fffbeb;
        }

        .issue-type {
          font-weight: 600;
          color: #dc2626;
        }

        .result-actions {
          display: flex;
          justify-content: flex-end;
          gap: 12px;
          padding-top: 16px;
          border-top: 1px solid #e2e8f0;
        }
      `}</style>
    </div>
  );
};
