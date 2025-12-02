import React, { useState, useEffect } from 'react';

const Blockchain = () => {
    const [chain, setChain] = useState([]);

    useEffect(() => {
        fetch('/wp-json/aps/v1/poc/chain')
            .then(response => response.json())
            .then(data => setChain(data.chain));
    }, []);

    return (
        <div>
            <h1>Blockchain</h1>
            <table>
                <thead>
                    <tr>
                        <th>Index</th>
                        <th>Timestamp</th>
                        <th>Transactions</th>
                        <th>Proof</th>
                        <th>Previous Hash</th>
                    </tr>
                </thead>
                <tbody>
                    {chain.map(block => (
                        <tr key={block.index}>
                            <td>{block.index}</td>
                            <td>{block.timestamp}</td>
                            <td>{JSON.stringify(block.transactions)}</td>
                            <td>{block.proof}</td>
                            <td>{block.previous_hash}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};

export default Blockchain;
