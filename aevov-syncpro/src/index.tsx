/**
 * AevSyncPro - Intelligent Configuration Orchestration
 *
 * Main entry point for the AevSyncPro React application.
 * Mounts to WordPress admin pages.
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { Dashboard } from './components/Dashboard';

// Mount points for WordPress admin
const mountPoints = {
  root: 'aevov-syncpro-root',
  configs: 'aevov-syncpro-configs',
  templates: 'aevov-syncpro-templates',
};

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  // Main dashboard
  const rootElement = document.getElementById(mountPoints.root);
  if (rootElement) {
    const root = createRoot(rootElement);
    root.render(
      <React.StrictMode>
        <Dashboard />
      </React.StrictMode>
    );
  }

  // Configurations page (if separate)
  const configsElement = document.getElementById(mountPoints.configs);
  if (configsElement) {
    const root = createRoot(configsElement);
    root.render(
      <React.StrictMode>
        <Dashboard />
      </React.StrictMode>
    );
  }

  // Templates page (if separate)
  const templatesElement = document.getElementById(mountPoints.templates);
  if (templatesElement) {
    const root = createRoot(templatesElement);
    root.render(
      <React.StrictMode>
        <Dashboard />
      </React.StrictMode>
    );
  }
});

// Export for external use
export { Dashboard } from './components/Dashboard';
export { useSyncProStore } from './store';
export * from './hooks/useApi';
export * from './types';
