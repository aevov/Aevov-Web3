import { useState, useMemo } from 'react';
import { useWorkflowStore } from '../store';
import { ChevronDown, ChevronRight, Search, X } from 'lucide-react';
import * as Icons from 'lucide-react';

const categories = [
    { id: 'input', label: 'Inputs', icon: 'ArrowRightCircle' },
    { id: 'output', label: 'Outputs', icon: 'ArrowLeftCircle' },
    { id: 'transform', label: 'Transform', icon: 'Wand2' },
    { id: 'control', label: 'Control Flow', icon: 'GitBranch' },
    { id: 'capability', label: 'AI Capabilities', icon: 'Sparkles' },
    { id: 'utility', label: 'Utilities', icon: 'Wrench' },
];

export function Sidebar() {
    const { nodeTypes } = useWorkflowStore();
    const [search, setSearch] = useState('');
    const [expanded, setExpanded] = useState<string[]>(['capability', 'input', 'output']);

    const filteredTypes = useMemo(() => {
        if (!search) return null;
        return Object.values(nodeTypes).filter(
            (node) =>
                node.label.toLowerCase().includes(search.toLowerCase()) ||
                node.description?.toLowerCase().includes(search.toLowerCase())
        );
    }, [search, nodeTypes]);

    const groupedTypes = useMemo(() => {
        const groups: Record<string, typeof nodeTypes[string][]> = {};
        Object.values(nodeTypes).forEach((node) => {
            const category = node.category || 'utility';
            if (!groups[category]) groups[category] = [];
            groups[category].push(node);
        });
        return groups;
    }, [nodeTypes]);

    const toggleCategory = (id: string) => {
        setExpanded((prev) =>
            prev.includes(id) ? prev.filter((c) => c !== id) : [...prev, id]
        );
    };

    const onDragStart = (e: React.DragEvent, type: string, label: string) => {
        e.dataTransfer.setData('application/aevov-node/type', type);
        e.dataTransfer.setData('application/aevov-node/label', label);
        e.dataTransfer.effectAllowed = 'move';
    };

    return (
        <aside className="w-72 bg-[var(--aevov-bg-card)] border-r border-[var(--aevov-border)] flex flex-col">
            {/* Search */}
            <div className="p-3 border-b border-[var(--aevov-border)]">
                <div className="relative">
                    <Search className="absolute left-3 top-2.5 w-4 h-4 text-[var(--aevov-text-muted)]" />
                    <input
                        type="text"
                        placeholder="Search nodes..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full pl-9 pr-8 py-2 bg-[var(--aevov-bg-dark)] border border-[var(--aevov-border)] rounded-md text-sm text-[var(--aevov-text)] placeholder:text-[var(--aevov-text-muted)] focus:outline-none focus:border-[var(--aevov-primary)]"
                    />
                    {search && (
                        <button
                            onClick={() => setSearch('')}
                            className="absolute right-3 top-2.5 text-[var(--aevov-text-muted)] hover:text-[var(--aevov-text)]"
                        >
                            <X className="w-4 h-4" />
                        </button>
                    )}
                </div>
            </div>

            {/* Node List */}
            <div className="flex-1 overflow-y-auto p-2">
                {filteredTypes ? (
                    <div className="space-y-1">
                        {filteredTypes.map((node) => (
                            <NodeItem key={node.type} node={node} onDragStart={onDragStart} />
                        ))}
                        {filteredTypes.length === 0 && (
                            <p className="text-sm text-[var(--aevov-text-muted)] text-center py-4">
                                No nodes found
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="space-y-1">
                        {categories.map((category) => {
                            const nodes = groupedTypes[category.id] || [];
                            const isExpanded = expanded.includes(category.id);
                            const Icon = (Icons as Record<string, React.ComponentType<{ className?: string }>>)[category.icon] || Icons.Box;

                            return (
                                <div key={category.id}>
                                    <button
                                        onClick={() => toggleCategory(category.id)}
                                        className="flex w-full items-center gap-2 px-3 py-2 rounded-md text-sm font-medium text-[var(--aevov-text)] hover:bg-white/5"
                                    >
                                        {isExpanded ? (
                                            <ChevronDown className="w-4 h-4" />
                                        ) : (
                                            <ChevronRight className="w-4 h-4" />
                                        )}
                                        <Icon className="w-4 h-4" />
                                        <span>{category.label}</span>
                                        <span className="ml-auto text-xs text-[var(--aevov-text-muted)]">
                                            {nodes.length}
                                        </span>
                                    </button>
                                    {isExpanded && (
                                        <div className="ml-4 space-y-1">
                                            {nodes.map((node) => (
                                                <NodeItem key={node.type} node={node} onDragStart={onDragStart} />
                                            ))}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Footer */}
            <div className="p-3 border-t border-[var(--aevov-border)]">
                <p className="text-xs text-[var(--aevov-text-muted)] text-center">
                    Drag nodes onto the canvas
                </p>
            </div>
        </aside>
    );
}

function NodeItem({
    node,
    onDragStart,
}: {
    node: {
        type: string;
        label: string;
        color: string;
        available?: boolean;
    };
    onDragStart: (e: React.DragEvent, type: string, label: string) => void;
}) {
    const available = node.available !== false;

    return (
        <div
            draggable={available}
            onDragStart={(e) => onDragStart(e, node.type, node.label)}
            className={`
                flex items-center gap-2 px-3 py-2 rounded-md text-sm cursor-grab active:cursor-grabbing
                transition-all border border-transparent
                ${available
                    ? 'hover:bg-[var(--aevov-primary)]/10 hover:border-[var(--aevov-primary)]/50'
                    : 'opacity-50 cursor-not-allowed'
                }
            `}
            title={available ? `Drag to add ${node.label}` : 'Not available'}
        >
            <div
                className="w-3 h-3 rounded-full"
                style={{ backgroundColor: node.color }}
            />
            <span className="text-[var(--aevov-text)]">{node.label}</span>
        </div>
    );
}
