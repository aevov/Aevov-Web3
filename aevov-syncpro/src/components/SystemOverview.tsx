/**
 * System Overview Component
 *
 * Displays comprehensive system status and recommendations.
 */

import React from 'react';
import { useSyncProStore, selectContext, selectRecommendations } from '../store';

export const SystemOverview: React.FC = () => {
  const context = useSyncProStore(selectContext);
  const recommendations = useSyncProStore(selectRecommendations);

  if (!context) {
    return <div className="loading">Loading system context...</div>;
  }

  const activePlugins = Object.entries(context.plugins).filter(([, p]) => p.is_active);
  const inactivePlugins = Object.entries(context.plugins).filter(([, p]) => !p.is_active);

  return (
    <div className="system-overview">
      <div className="overview-grid">
        {/* System Health */}
        <div className="overview-card health-card">
          <h3>System Health</h3>
          <div className="health-indicators">
            <div className="health-item">
              <span className="health-icon ok">
                <span className="dashicons dashicons-yes-alt"></span>
              </span>
              <div className="health-info">
                <strong>WordPress</strong>
                <span>{context.system.wordpress_version}</span>
              </div>
            </div>
            <div className="health-item">
              <span className="health-icon ok">
                <span className="dashicons dashicons-yes-alt"></span>
              </span>
              <div className="health-info">
                <strong>PHP</strong>
                <span>{context.system.php_version}</span>
              </div>
            </div>
            <div className="health-item">
              <span className={`health-icon ${context.system.ssl_enabled ? 'ok' : 'warning'}`}>
                <span className={`dashicons ${context.system.ssl_enabled ? 'dashicons-yes-alt' : 'dashicons-warning'}`}></span>
              </span>
              <div className="health-info">
                <strong>SSL</strong>
                <span>{context.system.ssl_enabled ? 'Enabled' : 'Disabled'}</span>
              </div>
            </div>
            <div className="health-item">
              <span className="health-icon ok">
                <span className="dashicons dashicons-yes-alt"></span>
              </span>
              <div className="health-info">
                <strong>MySQL</strong>
                <span>{context.storage.database.version}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Active Capabilities */}
        <div className="overview-card capabilities-card">
          <h3>Active Capabilities</h3>
          <div className="capability-list">
            {Object.entries(context.capabilities).slice(0, 8).map(([name, cap]) => (
              <div key={name} className="capability-item">
                <span className={`status-dot ${cap.available ? 'active' : 'inactive'}`}></span>
                <span className="capability-name">{name.replace(/_/g, ' ')}</span>
              </div>
            ))}
            {Object.keys(context.capabilities).length > 8 && (
              <div className="capability-item more">
                +{Object.keys(context.capabilities).length - 8} more
              </div>
            )}
          </div>
        </div>

        {/* Workflow Statistics */}
        <div className="overview-card stats-card">
          <h3>Workflow Statistics</h3>
          <div className="stats-grid">
            <div className="stat-item">
              <span className="stat-value">{context.workflows.total_workflows}</span>
              <span className="stat-label">Total Workflows</span>
            </div>
            <div className="stat-item">
              <span className="stat-value">{context.workflows.total_executions}</span>
              <span className="stat-label">Executions</span>
            </div>
            <div className="stat-item">
              <span className="stat-value">{context.workflows.success_rate?.toFixed(1) ?? 100}%</span>
              <span className="stat-label">Success Rate</span>
            </div>
            <div className="stat-item">
              <span className="stat-value">{context.workflows.templates.length}</span>
              <span className="stat-label">Templates</span>
            </div>
          </div>
        </div>

        {/* Storage Info */}
        <div className="overview-card storage-card">
          <h3>Storage Systems</h3>
          <div className="storage-list">
            <div className="storage-item">
              <span className="dashicons dashicons-database"></span>
              <div className="storage-info">
                <strong>Database</strong>
                <span>{context.storage.database.tables.length} Aevov tables</span>
              </div>
            </div>
            {context.storage.memory_core && (
              <div className="storage-item">
                <span className="dashicons dashicons-portfolio"></span>
                <div className="storage-info">
                  <strong>Memory Core</strong>
                  <span>{context.storage.memory_core.total_memories} memories</span>
                </div>
              </div>
            )}
            {context.storage.cdn && (
              <div className="storage-item">
                <span className="dashicons dashicons-cloud"></span>
                <div className="storage-info">
                  <strong>CDN ({context.storage.cdn.provider})</strong>
                  <span>{context.storage.cdn.configured ? 'Configured' : 'Not configured'}</span>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Active Plugins */}
        <div className="overview-card plugins-card">
          <h3>Active Plugins ({activePlugins.length})</h3>
          <div className="plugin-list">
            {activePlugins.slice(0, 6).map(([slug, plugin]) => (
              <div key={slug} className="plugin-item">
                <span className="plugin-status active"></span>
                <div className="plugin-info">
                  <strong>{slug}</strong>
                  <span>{plugin.capabilities.length} capabilities</span>
                </div>
              </div>
            ))}
            {activePlugins.length > 6 && (
              <div className="plugin-item more">
                +{activePlugins.length - 6} more active plugins
              </div>
            )}
          </div>
        </div>

        {/* Recommendations */}
        <div className="overview-card recommendations-card">
          <h3>Recommendations</h3>
          {recommendations.length === 0 ? (
            <div className="no-recommendations">
              <span className="dashicons dashicons-yes-alt"></span>
              <p>All systems optimized!</p>
            </div>
          ) : (
            <div className="recommendation-list">
              {recommendations.map((rec, i) => (
                <div key={i} className={`recommendation-item priority-${rec.priority}`}>
                  <span className={`priority-badge ${rec.priority}`}>{rec.priority}</span>
                  <span className="recommendation-message">{rec.message}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      <style>{`
        .system-overview {
          animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(10px); }
          to { opacity: 1; transform: translateY(0); }
        }

        .overview-grid {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 20px;
        }

        @media (max-width: 1200px) {
          .overview-grid {
            grid-template-columns: repeat(2, 1fr);
          }
        }

        @media (max-width: 768px) {
          .overview-grid {
            grid-template-columns: 1fr;
          }
        }

        .overview-card {
          background: #f8fafc;
          border-radius: 10px;
          padding: 20px;
          border: 1px solid #e2e8f0;
        }

        .overview-card h3 {
          margin: 0 0 16px 0;
          font-size: 16px;
          color: #334155;
        }

        .health-indicators {
          display: flex;
          flex-direction: column;
          gap: 12px;
        }

        .health-item {
          display: flex;
          align-items: center;
          gap: 12px;
        }

        .health-icon {
          width: 32px;
          height: 32px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .health-icon.ok {
          background: #dcfce7;
          color: #16a34a;
        }

        .health-icon.warning {
          background: #fef3c7;
          color: #d97706;
        }

        .health-info {
          display: flex;
          flex-direction: column;
        }

        .health-info strong {
          font-size: 14px;
          color: #334155;
        }

        .health-info span {
          font-size: 12px;
          color: #64748b;
        }

        .capability-list {
          display: flex;
          flex-wrap: wrap;
          gap: 8px;
        }

        .capability-item {
          display: flex;
          align-items: center;
          gap: 6px;
          padding: 6px 12px;
          background: white;
          border-radius: 6px;
          font-size: 13px;
          color: #475569;
          border: 1px solid #e2e8f0;
        }

        .capability-item.more {
          background: #8B5CF6;
          color: white;
          border-color: #8B5CF6;
        }

        .status-dot {
          width: 8px;
          height: 8px;
          border-radius: 50%;
        }

        .status-dot.active {
          background: #16a34a;
        }

        .status-dot.inactive {
          background: #94a3b8;
        }

        .stats-grid {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 16px;
        }

        .stat-item {
          text-align: center;
          padding: 12px;
          background: white;
          border-radius: 8px;
          border: 1px solid #e2e8f0;
        }

        .stat-value {
          display: block;
          font-size: 24px;
          font-weight: 700;
          color: #8B5CF6;
        }

        .stat-label {
          font-size: 12px;
          color: #64748b;
        }

        .storage-list, .plugin-list {
          display: flex;
          flex-direction: column;
          gap: 12px;
        }

        .storage-item, .plugin-item {
          display: flex;
          align-items: center;
          gap: 12px;
          padding: 10px;
          background: white;
          border-radius: 8px;
          border: 1px solid #e2e8f0;
        }

        .storage-item .dashicons {
          color: #8B5CF6;
        }

        .storage-info, .plugin-info {
          display: flex;
          flex-direction: column;
        }

        .storage-info strong, .plugin-info strong {
          font-size: 14px;
          color: #334155;
        }

        .storage-info span, .plugin-info span {
          font-size: 12px;
          color: #64748b;
        }

        .plugin-status {
          width: 10px;
          height: 10px;
          border-radius: 50%;
        }

        .plugin-status.active {
          background: #16a34a;
        }

        .plugin-item.more {
          justify-content: center;
          color: #8B5CF6;
          font-size: 13px;
          font-weight: 500;
        }

        .no-recommendations {
          display: flex;
          flex-direction: column;
          align-items: center;
          padding: 24px;
          color: #16a34a;
        }

        .no-recommendations .dashicons {
          font-size: 32px;
          width: 32px;
          height: 32px;
          margin-bottom: 8px;
        }

        .recommendation-list {
          display: flex;
          flex-direction: column;
          gap: 8px;
        }

        .recommendation-item {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 10px;
          background: white;
          border-radius: 8px;
          border: 1px solid #e2e8f0;
        }

        .priority-badge {
          padding: 2px 8px;
          border-radius: 4px;
          font-size: 11px;
          font-weight: 600;
          text-transform: uppercase;
        }

        .priority-badge.high {
          background: #fee2e2;
          color: #dc2626;
        }

        .priority-badge.medium {
          background: #fef3c7;
          color: #d97706;
        }

        .priority-badge.low {
          background: #dbeafe;
          color: #2563eb;
        }

        .recommendation-message {
          font-size: 13px;
          color: #475569;
        }
      `}</style>
    </div>
  );
};
