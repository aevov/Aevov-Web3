import { useWorkflowStore } from '../store';
import { X, Trash2 } from 'lucide-react';

export function ConfigPanel() {
    const { nodes, selectedNodeId, nodeTypes, updateNodeData, selectNode, removeNode } = useWorkflowStore();
    const node = nodes.find((n) => n.id === selectedNodeId);

    if (!node) return null;

    const nodeType = nodeTypes[node.data.nodeType];
    const configFields = nodeType?.configFields || [];

    const handleConfigChange = (key: string, value: unknown) => {
        updateNodeData(node.id, {
            config: {
                ...node.data.config,
                [key]: value,
            },
        });
    };

    const handleLabelChange = (label: string) => {
        updateNodeData(node.id, { label });
    };

    return (
        <aside className="w-80 bg-[var(--aevov-bg-card)] border-l border-[var(--aevov-border)] flex flex-col">
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b border-[var(--aevov-border)]">
                <h3 className="font-semibold text-[var(--aevov-text)]">Configure Node</h3>
                <button
                    onClick={() => selectNode(null)}
                    className="p-1 rounded hover:bg-white/10"
                >
                    <X className="w-4 h-4 text-[var(--aevov-text-muted)]" />
                </button>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto p-4 space-y-4">
                {/* Label */}
                <div>
                    <label className="block text-sm font-medium text-[var(--aevov-text-muted)] mb-1.5">
                        Label
                    </label>
                    <input
                        type="text"
                        value={node.data.label}
                        onChange={(e) => handleLabelChange(e.target.value)}
                        className="w-full px-3 py-2 bg-[var(--aevov-bg-dark)] border border-[var(--aevov-border)] rounded-md text-sm text-[var(--aevov-text)] focus:outline-none focus:border-[var(--aevov-primary)]"
                    />
                </div>

                {/* Type */}
                <div>
                    <label className="block text-sm font-medium text-[var(--aevov-text-muted)] mb-1.5">
                        Type
                    </label>
                    <div
                        className="px-3 py-2 bg-[var(--aevov-bg-dark)]/50 border-l-4 rounded-md text-sm text-[var(--aevov-text)]"
                        style={{ borderLeftColor: node.data.color }}
                    >
                        {nodeType?.label || node.data.nodeType}
                    </div>
                </div>

                {/* Description */}
                {nodeType?.description && (
                    <p className="text-sm text-[var(--aevov-text-muted)]">{nodeType.description}</p>
                )}

                {/* Config Fields */}
                {configFields.length > 0 && (
                    <div className="space-y-4">
                        <h4 className="text-sm font-medium text-[var(--aevov-text-muted)]">Configuration</h4>
                        {configFields.map((field) => (
                            <ConfigField
                                key={field.key}
                                field={field}
                                value={node.data.config?.[field.key] ?? field.defaultValue}
                                onChange={(value) => handleConfigChange(field.key, value)}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Footer */}
            <div className="p-4 border-t border-[var(--aevov-border)]">
                <button
                    onClick={() => removeNode(node.id)}
                    className="flex w-full items-center justify-center gap-2 px-4 py-2 rounded-md text-sm bg-red-500/10 text-red-400 hover:bg-red-500/20"
                >
                    <Trash2 className="w-4 h-4" />
                    Delete Node
                </button>
            </div>
        </aside>
    );
}

function ConfigField({
    field,
    value,
    onChange,
}: {
    field: {
        key: string;
        label: string;
        type: string;
        options?: { value: string; label: string }[];
        placeholder?: string;
    };
    value: unknown;
    onChange: (value: unknown) => void;
}) {
    const baseClass = "w-full px-3 py-2 bg-[var(--aevov-bg-dark)] border border-[var(--aevov-border)] rounded-md text-sm text-[var(--aevov-text)] focus:outline-none focus:border-[var(--aevov-primary)]";

    return (
        <div>
            <label className="block text-sm font-medium text-[var(--aevov-text-muted)] mb-1.5">
                {field.label}
            </label>
            {field.type === 'select' ? (
                <select
                    value={String(value || '')}
                    onChange={(e) => onChange(e.target.value)}
                    className={baseClass}
                >
                    <option value="">Select...</option>
                    {field.options?.map((opt) => (
                        <option key={opt.value} value={opt.value}>
                            {opt.label}
                        </option>
                    ))}
                </select>
            ) : field.type === 'textarea' ? (
                <textarea
                    value={String(value || '')}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={field.placeholder}
                    className={`${baseClass} min-h-[80px] resize-none`}
                />
            ) : field.type === 'number' ? (
                <input
                    type="number"
                    value={Number(value) || ''}
                    onChange={(e) => onChange(parseFloat(e.target.value))}
                    placeholder={field.placeholder}
                    className={baseClass}
                />
            ) : field.type === 'boolean' ? (
                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={Boolean(value)}
                        onChange={(e) => onChange(e.target.checked)}
                        className="rounded border-[var(--aevov-border)]"
                    />
                    <span className="text-sm text-[var(--aevov-text)]">Enabled</span>
                </label>
            ) : (
                <input
                    type="text"
                    value={String(value || '')}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={field.placeholder}
                    className={baseClass}
                />
            )}
        </div>
    );
}
