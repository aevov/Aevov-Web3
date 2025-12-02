import React, { useState } from 'react';

function GlobalSearchBar() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);

    const handleSearch = () => {
        fetch(`/wp-json/aps-tools/v1/search?q=${query}`)
            .then(response => response.json())
            .then(data => setResults(data));
    };

    return (
        <div>
            <input
                type="text"
                placeholder="Search for patterns, chunks, etc."
                value={query}
                onChange={e => setQuery(e.target.value)}
            />
            <button onClick={handleSearch}>Search</button>
            <ul>
                {results.map(result => (
                    <li key={result.id}>
                        {result.title} ({result.type})
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default GlobalSearchBar;
