import { memo } from 'react';
import { Handle, Position, NodeProps } from 'reactflow';
import { AevovNodeData } from '../types';
import * as Icons from 'lucide-react';

function AevovNodeComponent({ data, selected }: NodeProps<AevovNodeData>) {
    const IconComponent = (Icons as Record<string, React.ComponentType<{ className?: string }>>)[data.icon || 'Box'] || Icons.Box;
    const inputs = data.inputs || [];
    const outputs = data.outputs || [];

    return (
        <div
            className={`
                min-w-[180px] rounded-lg border-2 shadow-lg transition-all
                ${selected ? 'border-[var(--aevov-primary)] ring-2 ring-[var(--aevov-primary)]/30' : 'border-[var(--aevov-border)]'}
            `}
            style={{
                background: 'var(--aevov-bg-card)',
                borderColor: selected ? undefined : data.color,
            }}
        >
            {/* Header */}
            <div
                className="flex items-center gap-2 px-3 py-2 rounded-t-md"
                style={{ backgroundColor: `${data.color}20` }}
            >
                <IconComponent className="w-4 h-4" style={{ color: data.color }} />
                <span className="font-medium text-sm text-[var(--aevov-text)]">{data.label}</span>
            </div>

            {/* Body */}
            <div className="px-3 py-2">
                {/* Inputs */}
                {inputs.map((input, index) => (
                    <div key={input.id} className="relative flex items-center py-1">
                        <Handle
                            type="target"
                            position={Position.Left}
                            id={input.id}
                            className="!w-3 !h-3 !bg-[var(--aevov-primary)] !border-2 !border-[var(--aevov-bg-dark)]"
                            style={{ left: -6, top: 'auto' }}
                        />
                        <span className="text-xs text-[var(--aevov-text-muted)] ml-2">{input.label}</span>
                    </div>
                ))}

                {/* Outputs */}
                {outputs.map((output, index) => (
                    <div key={output.id} className="relative flex items-center justify-end py-1">
                        <span className="text-xs text-[var(--aevov-text-muted)] mr-2">{output.label}</span>
                        <Handle
                            type="source"
                            position={Position.Right}
                            id={output.id}
                            className="!w-3 !h-3 !bg-[var(--aevov-primary)] !border-2 !border-[var(--aevov-bg-dark)]"
                            style={{ right: -6, top: 'auto' }}
                        />
                    </div>
                ))}

                {/* Default handles if no inputs/outputs defined */}
                {inputs.length === 0 && outputs.length === 0 && (
                    <>
                        <Handle
                            type="target"
                            position={Position.Left}
                            className="!w-3 !h-3 !bg-[var(--aevov-primary)] !border-2 !border-[var(--aevov-bg-dark)]"
                        />
                        <Handle
                            type="source"
                            position={Position.Right}
                            className="!w-3 !h-3 !bg-[var(--aevov-primary)] !border-2 !border-[var(--aevov-bg-dark)]"
                        />
                    </>
                )}
            </div>
        </div>
    );
}

export const AevovNode = memo(AevovNodeComponent);
