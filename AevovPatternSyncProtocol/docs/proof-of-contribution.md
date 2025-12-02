# Proof of Contribution

The Proof of Contribution (PoC) consensus algorithm is a novel approach to achieving consensus in a decentralized network. It is designed to be more energy-efficient than traditional Proof of Work (PoW) algorithms, and more decentralized than Proof of Stake (PoS) algorithms.

## How it works

The PoC algorithm works by rewarding contributors for their contributions to the network. Contributions can be anything that is valuable to the network, such as providing storage space, bandwidth, or computing power.

When a contributor makes a contribution to the network, they are rewarded with a certain number of tokens. These tokens can then be used to vote on the validity of transactions. The more tokens a contributor has, the more voting power they have.

## API

The PoC algorithm is exposed through a REST API. The following endpoints are available:

*   `/mine`: Mines a new block and adds it to the chain.
*   `/chain`: Returns the full blockchain.
*   `/nodes/register`: Registers new nodes with the network.
*   `/nodes/resolve`: Resolves conflicts between nodes.
