import React, { useState, useEffect } from 'react';
import KnowledgeGraph from './KnowledgeGraph';
import ScatterPlot from './ScatterPlot';
import HeatMap from './HeatMap';

function Visualizations() {
    const [visualizationsData, setVisualizationsData] = useState(null);

    useEffect(() => {
        // Fetch visualizations data from the API
        fetch('/wp-json/aps-tools/v1/visualizations')
            .then(response => response.json())
            .then(data => setVisualizationsData(data));
    }, []);

    if (!visualizationsData) {
        return <div className="loading">Loading...</div>;
    }

    return (
        <div>
            <h1>Visualizations</h1>
            <KnowledgeGraph data={visualizationsData.knowledge_graph} />
            <ScatterPlot data={visualizationsData.scatter_plot} />
            <HeatMap data={visualizationsData.heat_map} />
        </div>
    );
}

export default Visualizations;
