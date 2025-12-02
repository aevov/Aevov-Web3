import { createRoot } from 'react-dom/client';
import { WorkflowBuilder } from './components/WorkflowBuilder';
import './styles/index.css';

declare global {
    interface Window {
        aevovWorkflowEngine: {
            apiUrl: string;
            nonce: string;
            userId: number;
            userName: string;
            isAdmin: boolean;
            settings: {
                maxExecutionTime: number;
                maxNodes: number;
            };
            strings: Record<string, string>;
        };
    }
}

// Initialize the workflow builder
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('aevov-workflow-builder');
    if (container) {
        const workflowId = container.dataset.workflowId || null;
        const root = createRoot(container);
        root.render(<WorkflowBuilder initialWorkflowId={workflowId} />);
    }
});
