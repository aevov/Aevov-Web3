import React from 'react';
import ReactFlow, {
    Elements,
    Handle,
    Position,
    ReactFlowProvider,
  } from 'react-flow-renderer';

function KnowledgeGraph({ data }) {
    const elements = [
        ...data.nodes.map((node) => ({ ...node, type: 'default' })),
        ...data.edges,
    ];

    return (
        <div style={{ height: 500 }}>
            <h2>Knowledge Graph</h2>
            <ReactFlowProvider>
                <ReactFlow elements={elements} />
            </ReactFlowProvider>
        </div>
    );
}

export default KnowledgeGraph;
