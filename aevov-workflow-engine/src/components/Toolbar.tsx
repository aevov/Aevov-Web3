import { useState } from 'react';
import { useWorkflowStore } from '../store';
import { useApi } from '../hooks/useApi';
import {
    Play,
    Save,
    FolderOpen,
    FilePlus,
    Loader2,
    CheckCircle2,
    XCircle,
    Sparkles,
} from 'lucide-react';

export function Toolbar() {
    const { saveWorkflow, executeWorkflow } = useApi();
    const {
        workflowId,
        workflowName,
        nodes,
        edges,
        isDirty,
        isExecuting,
        executionResults,
        setWorkflowName,
        newWorkflow,
    } = useWorkflowStore();

    const [saving, setSaving] = useState(false);
    const [showAI, setShowAI] = useState(false);

    const handleSave = async () => {
        setSaving(true);
        try {
            await saveWorkflow();
        } catch (error) {
            console.error('Save failed:', error);
        } finally {
            setSaving(false);
        }
    };

    const handleRun = async () => {
        try {
            await executeWorkflow();
        } catch (error) {
            console.error('Execution failed:', error);
        }
    };

    return (
        <header className="h-14 bg-[var(--aevov-bg-card)] border-b border-[var(--aevov-border)] flex items-center justify-between px-4">
            {/* Left side */}
            <div className="flex items-center gap-4">
                {/* Logo */}
                <div className="flex items-center gap-2">
                    <div className="w-8 h-8 rounded-lg bg-[var(--aevov-primary)] flex items-center justify-center">
                        <span className="text-white font-bold text-sm">A</span>
                    </div>
                    <span className="font-semibold text-[var(--aevov-text)]">Workflow Engine</span>
                </div>

                <div className="h-6 w-px bg-[var(--aevov-border)]" />

                {/* Actions */}
                <div className="flex items-center gap-1">
                    <button
                        onClick={newWorkflow}
                        className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm text-[var(--aevov-text)] hover:bg-white/10"
                    >
                        <FilePlus className="w-4 h-4" />
                        <span className="hidden sm:inline">New</span>
                    </button>

                    <button
                        onClick={handleSave}
                        disabled={saving}
                        className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm text-[var(--aevov-text)] hover:bg-white/10 disabled:opacity-50"
                    >
                        {saving ? (
                            <Loader2 className="w-4 h-4 animate-spin" />
                        ) : (
                            <Save className="w-4 h-4" />
                        )}
                        <span className="hidden sm:inline">Save</span>
                        {isDirty && <span className="w-2 h-2 rounded-full bg-[var(--aevov-primary)]" />}
                    </button>
                </div>

                <div className="h-6 w-px bg-[var(--aevov-border)]" />

                {/* Workflow name */}
                <input
                    type="text"
                    value={workflowName}
                    onChange={(e) => setWorkflowName(e.target.value)}
                    className="bg-transparent border-none text-sm font-medium text-[var(--aevov-text)] focus:outline-none focus:ring-0 max-w-[200px]"
                    placeholder="Workflow name..."
                />
            </div>

            {/* Right side */}
            <div className="flex items-center gap-2">
                <button
                    onClick={() => setShowAI(true)}
                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm bg-[var(--aevov-border)] text-[var(--aevov-text)] hover:bg-white/20"
                >
                    <Sparkles className="w-4 h-4" />
                    <span className="hidden sm:inline">AI Generate</span>
                </button>

                <button
                    onClick={handleRun}
                    disabled={isExecuting || nodes.length === 0}
                    className="flex items-center gap-1.5 px-4 py-1.5 rounded-md text-sm bg-[var(--aevov-primary)] text-white hover:bg-[var(--aevov-primary-dark)] disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {isExecuting ? (
                        <Loader2 className="w-4 h-4 animate-spin" />
                    ) : (
                        <Play className="w-4 h-4" />
                    )}
                    <span>Run</span>
                </button>

                {executionResults && (
                    <div className="flex items-center">
                        {executionResults.success ? (
                            <CheckCircle2 className="w-5 h-5 text-green-500" />
                        ) : (
                            <XCircle className="w-5 h-5 text-red-500" />
                        )}
                    </div>
                )}
            </div>

            {/* AI Modal */}
            {showAI && <AIModal onClose={() => setShowAI(false)} />}
        </header>
    );
}

function AIModal({ onClose }: { onClose: () => void }) {
    const [prompt, setPrompt] = useState('');
    const [generating, setGenerating] = useState(false);
    const { addNode } = useWorkflowStore();

    const handleGenerate = () => {
        setGenerating(true);
        const lower = prompt.toLowerCase();

        // Simple node generation based on keywords
        const nodesToAdd: { type: string; label: string; x: number; y: number }[] = [];
        nodesToAdd.push({ type: 'input', label: 'Input', x: 100, y: 200 });

        if (lower.includes('image')) {
            nodesToAdd.push({ type: 'image', label: 'Generate Image', x: 350, y: 200 });
        }
        if (lower.includes('text') || lower.includes('language')) {
            nodesToAdd.push({ type: 'language', label: 'Process Text', x: 350, y: 300 });
        }
        if (lower.includes('music')) {
            nodesToAdd.push({ type: 'music', label: 'Compose Music', x: 350, y: 400 });
        }

        nodesToAdd.push({ type: 'output', label: 'Output', x: 600, y: 200 });

        nodesToAdd.forEach((n) => {
            addNode(n.type, { x: n.x, y: n.y }, { label: n.label });
        });

        setGenerating(false);
        onClose();
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            onClick={onClose}
        >
            <div
                className="w-full max-w-lg bg-[var(--aevov-bg-card)] rounded-lg p-6 shadow-xl"
                onClick={(e) => e.stopPropagation()}
            >
                <h2 className="text-lg font-semibold text-[var(--aevov-text)] mb-4 flex items-center gap-2">
                    <Sparkles className="w-5 h-5 text-[var(--aevov-primary)]" />
                    Generate Workflow with AI
                </h2>
                <textarea
                    value={prompt}
                    onChange={(e) => setPrompt(e.target.value)}
                    placeholder="Describe what you want your workflow to do..."
                    className="w-full h-32 px-3 py-2 bg-[var(--aevov-bg-dark)] border border-[var(--aevov-border)] rounded-md text-sm text-[var(--aevov-text)] placeholder:text-[var(--aevov-text-muted)] resize-none focus:outline-none focus:border-[var(--aevov-primary)]"
                />
                <div className="flex justify-end gap-2 mt-4">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 rounded-md text-sm text-[var(--aevov-text)] hover:bg-white/10"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleGenerate}
                        disabled={generating || !prompt.trim()}
                        className="flex items-center gap-2 px-4 py-2 rounded-md text-sm bg-[var(--aevov-primary)] text-white hover:bg-[var(--aevov-primary-dark)] disabled:opacity-50"
                    >
                        {generating && <Loader2 className="w-4 h-4 animate-spin" />}
                        Generate
                    </button>
                </div>
            </div>
        </div>
    );
}
