import React, { useEffect, useRef } from 'react';
import Chart from 'chart.js/auto';

function HeatMap({ data }) {
    const chartRef = useRef(null);

    useEffect(() => {
        if (chartRef.current) {
            const chart = new Chart(chartRef.current, {
                type: 'heatmap',
                data: {
                    datasets: [{
                        label: 'Pattern Density',
                        data: data,
                        backgroundColor: (context) => {
                            const value = context.dataset.data[context.dataIndex].v;
                            const alpha = value / 10;
                            return `rgba(255, 99, 132, ${alpha})`;
                        },
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        x: {
                            type: 'category',
                            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
                        },
                        y: {
                            type: 'category',
                            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4']
                        }
                    }
                }
            });

            return () => chart.destroy();
        }
    }, [data]);

    return (
        <div>
            <h2>Heat Map</h2>
            <canvas ref={chartRef} />
        </div>
    );
}

export default HeatMap;
