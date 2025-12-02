import { useWorkflowStore } from '../store';
import { X, CheckCircle2, XCircle, Loader2 } from 'lucide-react';

export function ResultsPanel() {
    const { executionResults, isExecuting, clearResults } = useWorkflowStore();

    return (
        <div className="absolute bottom-0 left-0 right-0 max-h-72 bg-[var(--aevov-bg-card)] border-t border-[var(--aevov-border)] flex flex-col">
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-2 border-b border-[var(--aevov-border)]">
                <div className="flex items-center gap-2">
                    {isExecuting ? (
                        <>
                            <Loader2 className="w-4 h-4 animate-spin text-[var(--aevov-primary)]" />
                            <span className="text-sm font-medium text-[var(--aevov-text)]">Running...</span>
                        </>
                    ) : executionResults?.success ? (
                        <>
                            <CheckCircle2 className="w-4 h-4 text-green-500" />
                            <span className="text-sm font-medium text-[var(--aevov-text)]">Completed</span>
                        </>
                    ) : (
                        <>
                            <XCircle className="w-4 h-4 text-red-500" />
                            <span className="text-sm font-medium text-[var(--aevov-text)]">Failed</span>
                        </>
                    )}
                    {executionResults?.execution_time && (
                        <span className="text-xs text-[var(--aevov-text-muted)]">
                            ({executionResults.execution_time.toFixed(2)}s)
                        </span>
                    )}
                </div>
                <button
                    onClick={clearResults}
                    className="p-1 rounded hover:bg-white/10"
                >
                    <X className="w-4 h-4 text-[var(--aevov-text-muted)]" />
                </button>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-auto p-4 font-mono text-xs">
                {executionResults?.error && (
                    <div className="text-red-400 mb-4">
                        Error: {executionResults.error}
                    </div>
                )}

                {executionResults?.log && (
                    <div className="space-y-1">
                        {executionResults.log.map((entry, index) => (
                            <div
                                key={index}
                                className={`flex gap-2 ${
                                    entry.level === 'error' ? 'text-red-400' :
                                    entry.level === 'warning' ? 'text-yellow-400' :
                                    'text-[var(--aevov-text)]'
                                }`}
                            >
                                <span className="text-[var(--aevov-text-muted)] shrink-0">
                                    [{entry.elapsed?.toFixed(3) || '0.000'}s]
                                </span>
                                <span>{entry.message}</span>
                            </div>
                        ))}
                    </div>
                )}

                {executionResults?.outputs && (
                    <div className="mt-4">
                        <div className="text-[var(--aevov-text-muted)] mb-2">Output:</div>
                        <pre className="text-[var(--aevov-text)] bg-[var(--aevov-bg-dark)] p-2 rounded overflow-auto">
                            {JSON.stringify(executionResults.outputs, null, 2)}
                        </pre>
                    </div>
                )}
            </div>
        </div>
    );
}
