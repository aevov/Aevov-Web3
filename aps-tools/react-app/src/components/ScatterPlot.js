import React, { useEffect, useRef } from 'react';
import Chart from 'chart.js/auto';

function ScatterPlot({ data }) {
    const chartRef = useRef(null);

    useEffect(() => {
        if (chartRef.current) {
            const chart = new Chart(chartRef.current, {
                type: 'scatter',
                data: {
                    datasets: [{
                        label: 'Pattern Similarity',
                        data: data,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        x: {
                            type: 'linear',
                            position: 'bottom'
                        }
                    }
                }
            });

            return () => chart.destroy();
        }
    }, [data]);

    return (
        <div>
            <h2>Scatter Plot</h2>
            <canvas ref={chartRef} />
        </div>
    );
}

export default ScatterPlot;
