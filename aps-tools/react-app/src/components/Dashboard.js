import React, { useState, useEffect } from 'react';
import GlobalSearchBar from './GlobalSearchBar';
import CommandCenter from './CommandCenter';
import ActivityFeed from './ActivityFeed';

function Dashboard() {
    const [dashboardData, setDashboardData] = useState(null);

    useEffect(() => {
        // Fetch dashboard data from the API
        fetch('/wp-json/aps-tools/v1/dashboard')
            .then(response => response.json())
            .then(data => setDashboardData(data));
    }, []);

    if (!dashboardData) {
        return <div className="loading">Loading...</div>;
    }

    return (
        <div>
            <h1>Unified Dashboard</h1>
            <GlobalSearchBar />
            <CommandCenter />
            <ActivityFeed />
            <div>
                <p>Total Patterns: {dashboardData.total_patterns}</p>
                <p>Patterns Processed Today: {dashboardData.patterns_today}</p>
                <p>System Health: {dashboardData.system_health}</p>
            </div>
        </div>
    );
}

export default Dashboard;
