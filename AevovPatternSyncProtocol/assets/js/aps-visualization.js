/**
 * APS Visualization Module
 * Handles pattern and data visualization using D3.js
 */

const APS = APS || {};

APS.Visualization = (function() {
    // Private variables
    let svgContainer = null;
    let width = 800;
    let height = 400;
    let margin = { top: 20, right: 30, bottom: 30, left: 40 };

    // Color scales
    const colorScale = d3.scaleSequential(d3.interpolateBlues);
    
    /**
     * Initialize visualization container
     */
    function init(containerId, options = {}) {
        const container = d3.select(`#${containerId}`);
        width = options.width || width;
        height = options.height || height;
        
        svgContainer = container.append('svg')
            .attr('width', width)
            .attr('height', height)
            .append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        return svgContainer;
    }

    /**
     * Create pattern heatmap visualization
     */
    function createHeatmap(data, options = {}) {
        const svg = init(options.containerId);
        
        const xScale = d3.scaleBand()
            .range([0, width - margin.left - margin.right])
            .domain(d3.range(data.dimensions.x))
            .padding(0.01);

        const yScale = d3.scaleBand()
            .range([0, height - margin.top - margin.bottom])
            .domain(d3.range(data.dimensions.y))
            .padding(0.01);

        colorScale.domain([0, d3.max(data.data, d => d.value)]);

        // Create cells
        svg.selectAll('rect')
            .data(data.data)
            .enter()
            .append('rect')
            .attr('x', d => xScale(d.x))
            .attr('y', d => yScale(d.y))
            .attr('width', xScale.bandwidth())
            .attr('height', yScale.bandwidth())
            .style('fill', d => colorScale(d.value));

        // Add tooltips
        if (options.showTooltips) {
            addTooltips(svg, options.tooltipFormat);
        }
    }

    /**
     * Create network graph visualization
     */
    function createNetworkGraph(data, options = {}) {
        const svg = init(options.containerId);

        // Create force simulation
        const simulation = d3.forceSimulation(data.nodes)
            .force('link', d3.forceLink(data.links).id(d => d.id))
            .force('charge', d3.forceManyBody())
            .force('center', d3.forceCenter(width / 2, height / 2));

        // Create links
        const links = svg.append('g')
            .selectAll('line')
            .data(data.links)
            .enter()
            .append('line')
            .style('stroke', '#999')
            .style('stroke-width', d => Math.sqrt(d.value));

        // Create// Create nodes
        const nodes = svg.append('g')
            .selectAll('circle')
            .data(data.nodes)
            .enter()
            .append('circle')
            .attr('r', 5)
            .style('fill', d => d.type === 'feature' ? '#69b3a2' : '#404080')
            .call(d3.drag()
                .on('start', dragStarted)
                .on('drag', dragging)
                .on('end', dragEnded));

        // Add node labels
        const labels = svg.append('g')
            .selectAll('text')
            .data(data.nodes)
            .enter()
            .append('text')
            .text(d => d.id)
            .attr('font-size', '12px')
            .attr('dx', 12)
            .attr('dy', 4);

        // Update positions on simulation tick
        simulation.on('tick', () => {
            links
                .attr('x1', d => d.source.x)
                .attr('y1', d => d.source.y)
                .attr('x2', d => d.target.x)
                .attr('y2', d => d.target.y);

            nodes
                .attr('cx', d => d.x)
                .attr('cy', d => d.y);

            labels
                .attr('x', d => d.x)
                .attr('y', d => d.y);
        });

        // Drag functions
        function dragStarted(event, d) {
            if (!event.active) simulation.alphaTarget(0.3).restart();
            d.fx = d.x;
            d.fy = d.y;
        }

        function dragging(event, d) {
            d.fx = event.x;
            d.fy = event.y;
        }

        function dragEnded(event, d) {
            if (!event.active) simulation.alphaTarget(0);
            d.fx = null;
            d.fy = null;
        }
    }

    /**
     * Create matrix visualization
     */
    function createMatrix(data, options = {}) {
        const svg = init(options.containerId);
        
        const size = data.matrix.length;
        const cellSize = Math.min(
            (width - margin.left - margin.right) / size,
            (height - margin.top - margin.bottom) / size
        );

        // Create color scale for matrix values
        const colorScale = d3.scaleSequential(d3.interpolateBlues)
            .domain([0, 1]);

        // Create matrix cells
        const cells = svg.selectAll('g')
            .data(data.matrix)
            .enter()
            .append('g')
            .attr('transform', (d, i) => `translate(0,${i * cellSize})`);

        cells.selectAll('rect')
            .data(d => d)
            .enter()
            .append('rect')
            .attr('x', (d, i) => i * cellSize)
            .attr('width', cellSize)
            .attr('height', cellSize)
            .style('fill', d => colorScale(d))
            .style('stroke', '#fff')
            .style('stroke-width', 1);

        // Add row labels
        if (data.labels) {
            svg.selectAll('.row-label')
                .data(data.labels)
                .enter()
                .append('text')
                .attr('class', 'row-label')
                .attr('x', -5)
                .attr('y', (d, i) => i * cellSize + cellSize / 2)
                .attr('text-anchor', 'end')
                .attr('alignment-baseline', 'middle')
                .text(d => d);

            // Add column labels
            svg.selectAll('.column-label')
                .data(data.labels)
                .enter()
                .append('text')
                .attr('class', 'column-label')
                .attr('x', (d, i) => i * cellSize + cellSize / 2)
                .attr('y', -5)
                .attr('text-anchor', 'middle')
                .attr('alignment-baseline', 'end')
                .text(d => d);
        }
    }

    /**
     * Add tooltips to visualization
     */
    function addTooltips(svg, formatFn) {
        const tooltip = d3.select('body')
            .append('div')
            .attr('class', 'aps-tooltip')
            .style('opacity', 0);

        svg.selectAll('*')
            .on('mouseover', function(event, d) {
                tooltip.transition()
                    .duration(200)
                    .style('opacity', .9);
                
                const text = formatFn ? formatFn(d) : JSON.stringify(d);
                
                tooltip.html(text)
                    .style('left', (event.pageX + 10) + 'px')
                    .style('top', (event.pageY - 28) + 'px');
            })
            .on('mouseout', function(d) {
                tooltip.transition()
                    .duration(500)
                    .style('opacity', 0);
            });
    }

    /**
     * Update existing visualization with new data
     */
    function updateVisualization(data, options = {}) {
        if (!svgContainer) {
            console.error('Visualization not initialized');
            return;
        }

        // Clear existing visualization
        svgContainer.selectAll('*').remove();

        // Create new visualization based on type
        switch (options.type) {
            case 'heatmap':
                createHeatmap(data, options);
                break;
            case 'network':
                createNetworkGraph(data, options);
                break;
            case 'matrix':
                createMatrix(data, options);
                break;
            default:
                console.error('Unknown visualization type');
        }
    }

    // Public API
    return {
        init,
        createHeatmap,
        createNetworkGraph,
        createMatrix,
        updateVisualization
    };
})();

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = APS.Visualization;
}