import React, { useState, useEffect } from 'react';

const NetworkStatus = () => {
    const [nodes, setNodes] = useState([]);

    useEffect(() => {
        fetch('/wp-json/aps/v1/poc/nodes')
            .then(response => response.json())
            .then(data => setNodes(data.nodes));
    }, []);

    return (
        <div>
            <h1>Network Status</h1>
            <ul>
                {nodes.map(node => (
                    <li key={node}>{node}</li>
                ))}
            </ul>
        </div>
    );
};

export default NetworkStatus;
