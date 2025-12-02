import React, { useState, useEffect } from 'react';

const TransactionPool = () => {
    const [transactions, setTransactions] = useState([]);

    useEffect(() => {
        fetch('/wp-json/aps/v1/poc/transactions')
            .then(response => response.json())
            .then(data => setTransactions(data.transactions));
    }, []);

    return (
        <div>
            <h1>Transaction Pool</h1>
            <table>
                <thead>
                    <tr>
                        <th>Sender</th>
                        <th>Recipient</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    {transactions.map(transaction => (
                        <tr key={transaction.timestamp}>
                            <td>{transaction.sender}</td>
                            <td>{transaction.recipient}</td>
                            <td>{transaction.amount}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};

export default TransactionPool;
