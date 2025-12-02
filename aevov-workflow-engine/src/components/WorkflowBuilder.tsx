import { useCallback, useEffect, useRef, useState } from 'react';
import ReactFlow, {
    Background,
    Controls,
    MiniMap,
    NodeTypes,
    Connection,
    Node,
    Edge,
    addEdge,
    applyNodeChanges,
    applyEdgeChanges,
    NodeChange,
    EdgeChange,
} from 'reactflow';
import 'reactflow/dist/style.css';

import { AevovNode } from './AevovNode';
import { Sidebar } from './Sidebar';
import { Toolbar } from './Toolbar';
import { ConfigPanel } from './ConfigPanel';
import { ResultsPanel } from './ResultsPanel';
import { useApi } from '../hooks/useApi';
import { useWorkflowStore } from '../store';
import { AevovNodeData } from '../types';

const nodeTypes: NodeTypes = {
    aevovNode: AevovNode,
};

interface WorkflowBuilderProps {
    initialWorkflowId?: string | null;
}

export function WorkflowBuilder({ initialWorkflowId }: WorkflowBuilderProps) {
    const reactFlowWrapper = useRef<HTMLDivElement>(null);
    const { fetchCapabilities, loadWorkflow } = useApi();
    const {
        nodes,
        edges,
        selectedNodeId,
        setNodes,
        setEdges,
        addNode,
        selectNode,
        executionResults,
        isExecuting,
    } = useWorkflowStore();

    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        async function init() {
            try {
                await fetchCapabilities();
                if (initialWorkflowId) {
                    await loadWorkflow(initialWorkflowId);
                }
            } catch (error) {
                console.error('Failed to initialize:', error);
            } finally {
                setIsLoading(false);
            }
        }
        init();
    }, [fetchCapabilities, loadWorkflow, initialWorkflowId]);

    const onNodesChange = useCallback(
        (changes: NodeChange[]) => {
            setNodes(applyNodeChanges(changes, nodes));
        },
        [nodes, setNodes]
    );

    const onEdgesChange = useCallback(
        (changes: EdgeChange[]) => {
            setEdges(applyEdgeChanges(changes, edges));
        },
        [edges, setEdges]
    );

    const onConnect = useCallback(
        (connection: Connection) => {
            setEdges(
                addEdge(
                    {
                        ...connection,
                        type: 'smoothstep',
                        animated: true,
                    },
                    edges
                )
            );
        },
        [edges, setEdges]
    );

    const onDragOver = useCallback((event: React.DragEvent) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }, []);

    const onDrop = useCallback(
        (event: React.DragEvent) => {
            event.preventDefault();
            const type = event.dataTransfer.getData('application/aevov-node/type');
            const label = event.dataTransfer.getData('application/aevov-node/label');

            if (!type || !reactFlowWrapper.current) return;

            const bounds = reactFlowWrapper.current.getBoundingClientRect();
            const position = {
                x: event.clientX - bounds.left - 90,
                y: event.clientY - bounds.top - 20,
            };

            addNode(type, position, { label });
        },
        [addNode]
    );

    const onNodeClick = useCallback(
        (_: React.MouseEvent, node: Node) => {
            selectNode(node.id);
        },
        [selectNode]
    );

    const onPaneClick = useCallback(() => {
        selectNode(null);
    }, [selectNode]);

    if (isLoading) {
        return (
            <div className="flex items-center justify-center h-full">
                <div className="text-center">
                    <div className="w-12 h-12 border-4 border-[var(--aevov-primary)] border-t-transparent rounded-full animate-spin mx-auto mb-4" />
                    <p className="text-[var(--aevov-text-muted)]">Loading Workflow Engine...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="flex h-full">
            <Sidebar />
            <div className="flex-1 flex flex-col relative">
                <Toolbar />
                <div ref={reactFlowWrapper} className="flex-1">
                    <ReactFlow
                        nodes={nodes}
                        edges={edges}
                        onNodesChange={onNodesChange}
                        onEdgesChange={onEdgesChange}
                        onConnect={onConnect}
                        onDrop={onDrop}
                        onDragOver={onDragOver}
                        onNodeClick={onNodeClick}
                        onPaneClick={onPaneClick}
                        nodeTypes={nodeTypes}
                        fitView
                        snapToGrid
                        snapGrid={[15, 15]}
                        defaultEdgeOptions={{
                            type: 'smoothstep',
                            animated: true,
                        }}
                    >
                        <Background color="#333" gap={15} />
                        <Controls />
                        <MiniMap
                            nodeStrokeWidth={3}
                            nodeColor={(node) => {
                                const data = node.data as AevovNodeData;
                                return data.color || '#0ea5e9';
                            }}
                        />
                    </ReactFlow>
                </div>
                {(executionResults || isExecuting) && <ResultsPanel />}
            </div>
            {selectedNodeId && <ConfigPanel />}
        </div>
    );
}
