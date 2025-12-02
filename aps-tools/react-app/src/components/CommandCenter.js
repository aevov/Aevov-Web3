import React from 'react';

function CommandCenter() {
    const handleScanDirectory = () => {
        const directory = prompt('Enter directory to scan:');
        if (directory) {
            fetch('/wp-json/aps-tools/v1/scan-directory', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ directory }),
            });
        }
    };

    const handleAnalyzePattern = () => {
        const patternData = prompt('Enter pattern data to analyze:');
        if (patternData) {
            fetch('/wp-json/aps-tools/v1/analyze-pattern', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pattern_data: patternData }),
            });
        }
    };

    const handleComparePatterns = () => {
        const patterns = prompt('Enter patterns to compare (comma-separated):');
        if (patterns) {
            fetch('/wp-json/aps-tools/v1/compare-patterns', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ patterns: patterns.split(',') }),
            });
        }
    };

    return (
        <div>
            <button onClick={handleScanDirectory}>Scan Directory</button>
            <button onClick={handleAnalyzePattern}>Analyze Pattern</button>
            <button onClick={handleComparePatterns}>Compare Patterns</button>
        </div>
    );
}

export default CommandCenter;
