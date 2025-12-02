/**
 * Sync Status Component
 *
 * Real-time view of database synchronization operations.
 */

import React, { useEffect, useState, useCallback } from 'react';
import { useSyncProStore, selectSyncOperations } from '../store';
import { useSyncOperations, useConfigApply } from '../hooks/useApi';
import type { SyncOperation } from '../types';

export const SyncStatus: React.FC = () => {
  const [operations, setOperations] = useState<SyncOperation[]>([]);
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [selectedOperation, setSelectedOperation] = useState<SyncOperation | null>(null);

  const { getSyncStatus } = useSyncOperations();
  const { rollbackExecution } = useConfigApply();

  const fetchOperations = useCallback(async () => {
    try {
      const ops = await getSyncStatus();
      setOperations(ops);
    } catch (error) {
      console.error('Failed to fetch operations:', error);
    }
  }, [getSyncStatus]);

  useEffect(() => {
    fetchOperations();

    if (autoRefresh) {
      const interval = setInterval(fetchOperations, 5000);
      return () => clearInterval(interval);
    }
  }, [fetchOperations, autoRefresh]);

  const handleRollback = useCallback(async (executionId: number) => {
    const confirmed = window.confirm('Rollback all changes from this execution?');
    if (!confirmed) return;

    try {
      const result = await rollbackExecution(executionId);
      if (result.success) {
        alert('Rollback successful!');
        fetchOperations();
      } else {
        alert('Rollback failed');
      }
    } catch (error) {
      console.error('Rollback failed:', error);
    }
  }, [rollbackExecution, fetchOperations]);

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'completed':
        return { icon: 'dashicons-yes-alt', color: '#16a34a' };
      case 'failed':
        return { icon: 'dashicons-warning', color: '#dc2626' };
      case 'pending':
        return { icon: 'dashicons-clock', color: '#f59e0b' };
      case 'in_progress':
        return { icon: 'dashicons-update', color: '#3b82f6' };
      default:
        return { icon: 'dashicons-marker', color: '#64748b' };
    }
  };

  const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleString();
  };

  const groupByExecution = (ops: SyncOperation[]) => {
    const groups: Record<string, SyncOperation[]> = {};
    ops.forEach(op => {
      const key = op.created_at.split(' ')[0]; // Group by date
      if (!groups[key]) groups[key] = [];
      groups[key].push(op);
    });
    return groups;
  };

  const groupedOperations = groupByExecution(operations);

  return (
    <div className="sync-status">
      <div className="status-header">
        <div className="header-left">
          <h2>Sync Status</h2>
          <p>Real-time view of database synchronization operations</p>
        </div>
        <div className="header-right">
          <label className="auto-refresh-toggle">
            <input
              type="checkbox"
              checked={autoRefresh}
              onChange={(e) => setAutoRefresh(e.target.checked)}
            />
            <span>Auto-refresh</span>
          </label>
          <button className="btn-refresh" onClick={fetchOperations}>
            <span className="dashicons dashicons-update"></span>
            Refresh
          </button>
        </div>
      </div>

      {/* Summary Stats */}
      <div className="stats-row">
        <div className="stat-box">
          <span className="stat-value">{operations.length}</span>
          <span className="stat-label">Total Operations</span>
        </div>
        <div className="stat-box success">
          <span className="stat-value">
            {operations.filter(op => op.status === 'completed').length}
          </span>
          <span className="stat-label">Completed</span>
        </div>
        <div className="stat-box warning">
          <span className="stat-value">
            {operations.filter(op => op.status === 'pending' || op.status === 'in_progress').length}
          </span>
          <span className="stat-label">Pending</span>
        </div>
        <div className="stat-box danger">
          <span className="stat-value">
            {operations.filter(op => op.status === 'failed').length}
          </span>
          <span className="stat-label">Failed</span>
        </div>
      </div>

      {/* Operations List */}
      <div className="operations-container">
        {operations.length === 0 ? (
          <div className="no-operations">
            <span className="dashicons dashicons-update"></span>
            <p>No sync operations recorded yet</p>
            <span className="hint">Operations will appear here when workflows are executed</span>
          </div>
        ) : (
          <div className="operations-list">
            {Object.entries(groupedOperations).map(([date, ops]) => (
              <div key={date} className="operation-group">
                <div className="group-header">
                  <span className="group-date">{date}</span>
                  <span className="group-count">{ops.length} operations</span>
                </div>
                {ops.map((operation) => {
                  const statusInfo = getStatusIcon(operation.status);
                  return (
                    <div
                      key={operation.id}
                      className={`operation-item status-${operation.status}`}
                      onClick={() => setSelectedOperation(operation)}
                    >
                      <div className="operation-status">
                        <span
                          className={`dashicons ${statusInfo.icon}`}
                          style={{ color: statusInfo.color }}
                        ></span>
                      </div>
                      <div className="operation-info">
                        <div className="operation-header">
                          <span className="operation-type">{operation.sync_type}</span>
                          <span className="operation-target">{operation.target_system}</span>
                        </div>
                        <div className="operation-meta">
                          <span className="operation-time">
                            {formatDate(operation.created_at)}
                          </span>
                          {operation.target_entity && (
                            <span className="operation-entity">{operation.target_entity}</span>
                          )}
                        </div>
                        {operation.error_message && (
                          <div className="operation-error">{operation.error_message}</div>
                        )}
                      </div>
                      <div className="operation-actions">
                        <span className={`status-badge ${operation.status}`}>
                          {operation.status}
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Operation Detail Modal */}
      {selectedOperation && (
        <div className="modal-overlay" onClick={() => setSelectedOperation(null)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Operation Details</h3>
              <button className="btn-close" onClick={() => setSelectedOperation(null)}>
                <span className="dashicons dashicons-no-alt"></span>
              </button>
            </div>
            <div className="modal-body">
              <div className="detail-row">
                <span className="detail-label">ID</span>
                <span className="detail-value">{selectedOperation.id}</span>
              </div>
              <div className="detail-row">
                <span className="detail-label">Type</span>
                <span className="detail-value">{selectedOperation.sync_type}</span>
              </div>
              <div className="detail-row">
                <span className="detail-label">Target</span>
                <span className="detail-value">{selectedOperation.target_system}</span>
              </div>
              <div className="detail-row">
                <span className="detail-label">Status</span>
                <span className={`status-badge ${selectedOperation.status}`}>
                  {selectedOperation.status}
                </span>
              </div>
              <div className="detail-row">
                <span className="detail-label">Created</span>
                <span className="detail-value">{formatDate(selectedOperation.created_at)}</span>
              </div>
              {selectedOperation.completed_at && (
                <div className="detail-row">
                  <span className="detail-label">Completed</span>
                  <span className="detail-value">{formatDate(selectedOperation.completed_at)}</span>
                </div>
              )}
              {selectedOperation.error_message && (
                <div className="detail-row">
                  <span className="detail-label">Error</span>
                  <span className="detail-value error">{selectedOperation.error_message}</span>
                </div>
              )}
              {selectedOperation.result_data && (
                <div className="detail-row full-width">
                  <span className="detail-label">Result Data</span>
                  <pre className="detail-json">
                    {JSON.stringify(selectedOperation.result_data, null, 2)}
                  </pre>
                </div>
              )}
            </div>
            <div className="modal-footer">
              {selectedOperation.status === 'completed' && (
                <button
                  className="btn-danger"
                  onClick={() => {
                    // Rollback this specific operation
                    setSelectedOperation(null);
                  }}
                >
                  Rollback
                </button>
              )}
              <button className="btn-secondary" onClick={() => setSelectedOperation(null)}>
                Close
              </button>
            </div>
          </div>
        </div>
      )}

      <style>{`
        .sync-status {
          animation: fadeIn 0.3s ease;
        }

        .status-header {
          display: flex;
          justify-content: space-between;
          align-items: flex-start;
          margin-bottom: 24px;
        }

        .header-left h2 {
          margin: 0 0 8px 0;
          font-size: 20px;
          color: #1e293b;
        }

        .header-left p {
          margin: 0;
          color: #64748b;
        }

        .header-right {
          display: flex;
          gap: 12px;
          align-items: center;
        }

        .auto-refresh-toggle {
          display: flex;
          align-items: center;
          gap: 8px;
          font-size: 14px;
          color: #64748b;
          cursor: pointer;
        }

        .auto-refresh-toggle input {
          accent-color: #8B5CF6;
        }

        .btn-refresh {
          display: flex;
          align-items: center;
          gap: 6px;
          padding: 8px 16px;
          background: #f1f5f9;
          border: none;
          border-radius: 8px;
          font-size: 13px;
          cursor: pointer;
          transition: all 0.2s;
        }

        .btn-refresh:hover {
          background: #e2e8f0;
        }

        .stats-row {
          display: grid;
          grid-template-columns: repeat(4, 1fr);
          gap: 16px;
          margin-bottom: 24px;
        }

        .stat-box {
          background: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: 10px;
          padding: 16px;
          text-align: center;
        }

        .stat-box.success { border-color: #22c55e; background: #f0fdf4; }
        .stat-box.warning { border-color: #f59e0b; background: #fffbeb; }
        .stat-box.danger { border-color: #dc2626; background: #fef2f2; }

        .stat-value {
          display: block;
          font-size: 28px;
          font-weight: 700;
          color: #1e293b;
        }

        .stat-box.success .stat-value { color: #16a34a; }
        .stat-box.warning .stat-value { color: #d97706; }
        .stat-box.danger .stat-value { color: #dc2626; }

        .stat-label {
          font-size: 13px;
          color: #64748b;
        }

        .operations-container {
          background: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: 12px;
          min-height: 400px;
        }

        .no-operations {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          padding: 48px;
          color: #64748b;
        }

        .no-operations .dashicons {
          font-size: 48px;
          width: 48px;
          height: 48px;
          margin-bottom: 16px;
          opacity: 0.5;
        }

        .no-operations p {
          margin: 0 0 8px 0;
          font-size: 16px;
        }

        .no-operations .hint {
          font-size: 13px;
          color: #94a3b8;
        }

        .operations-list {
          padding: 16px;
        }

        .operation-group {
          margin-bottom: 24px;
        }

        .group-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 8px 0;
          margin-bottom: 8px;
          border-bottom: 1px solid #e2e8f0;
        }

        .group-date {
          font-weight: 600;
          color: #334155;
        }

        .group-count {
          font-size: 12px;
          color: #64748b;
        }

        .operation-item {
          display: flex;
          align-items: flex-start;
          gap: 12px;
          padding: 12px 16px;
          background: white;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          margin-bottom: 8px;
          cursor: pointer;
          transition: all 0.2s;
        }

        .operation-item:hover {
          border-color: #8B5CF6;
        }

        .operation-status {
          padding-top: 2px;
        }

        .operation-status .dashicons {
          font-size: 20px;
          width: 20px;
          height: 20px;
        }

        .operation-info {
          flex: 1;
        }

        .operation-header {
          display: flex;
          gap: 8px;
          margin-bottom: 4px;
        }

        .operation-type {
          font-weight: 600;
          color: #334155;
        }

        .operation-target {
          color: #8B5CF6;
          font-size: 13px;
        }

        .operation-meta {
          display: flex;
          gap: 12px;
          font-size: 12px;
          color: #64748b;
        }

        .operation-error {
          margin-top: 8px;
          padding: 8px;
          background: #fef2f2;
          color: #dc2626;
          font-size: 12px;
          border-radius: 4px;
        }

        .status-badge {
          padding: 4px 10px;
          border-radius: 4px;
          font-size: 11px;
          font-weight: 600;
          text-transform: uppercase;
        }

        .status-badge.completed { background: #dcfce7; color: #16a34a; }
        .status-badge.failed { background: #fee2e2; color: #dc2626; }
        .status-badge.pending { background: #fef3c7; color: #d97706; }
        .status-badge.in_progress { background: #dbeafe; color: #2563eb; }

        .modal-overlay {
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

        .modal-content {
          background: white;
          border-radius: 16px;
          width: 100%;
          max-width: 500px;
          max-height: 80vh;
          overflow: hidden;
        }

        .modal-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 16px 20px;
          border-bottom: 1px solid #e2e8f0;
        }

        .modal-header h3 {
          margin: 0;
          font-size: 16px;
        }

        .btn-close {
          background: none;
          border: none;
          cursor: pointer;
          color: #64748b;
        }

        .modal-body {
          padding: 20px;
          max-height: 400px;
          overflow-y: auto;
        }

        .detail-row {
          display: flex;
          margin-bottom: 12px;
        }

        .detail-row.full-width {
          flex-direction: column;
        }

        .detail-label {
          width: 100px;
          font-size: 13px;
          color: #64748b;
          flex-shrink: 0;
        }

        .detail-value {
          font-size: 13px;
          color: #334155;
        }

        .detail-value.error {
          color: #dc2626;
        }

        .detail-json {
          margin-top: 8px;
          padding: 12px;
          background: #f8fafc;
          border-radius: 6px;
          font-size: 12px;
          overflow-x: auto;
        }

        .modal-footer {
          display: flex;
          justify-content: flex-end;
          gap: 12px;
          padding: 16px 20px;
          border-top: 1px solid #e2e8f0;
        }

        .btn-secondary {
          padding: 8px 16px;
          background: white;
          border: 1px solid #e2e8f0;
          border-radius: 6px;
          cursor: pointer;
        }

        .btn-danger {
          padding: 8px 16px;
          background: #dc2626;
          color: white;
          border: none;
          border-radius: 6px;
          cursor: pointer;
        }
      `}</style>
    </div>
  );
};
