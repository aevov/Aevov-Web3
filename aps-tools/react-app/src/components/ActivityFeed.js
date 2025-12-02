import React, { useState, useEffect } from 'react';

function ActivityFeed() {
    const [activities, setActivities] = useState([]);

    useEffect(() => {
        const interval = setInterval(() => {
            fetch('/wp-json/aps-tools/v1/activity-feed')
                .then(response => response.json())
                .then(data => setActivities(data));
        }, 5000); // Poll every 5 seconds

        return () => clearInterval(interval);
    }, []);

    return (
        <div>
            <h2>Activity Feed</h2>
            <ul>
                {activities.map(activity => (
                    <li key={activity.id}>{activity.sync_type}: {activity.sync_data}</li>
                ))}
            </ul>
        </div>
    );
}

export default ActivityFeed;
