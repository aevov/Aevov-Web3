<?php

namespace Aevov\Decentralized;

use APS\DB\APS_Block_DB;
use APS\Integration\CubbitIntegration;
use APS\Core\Logger;

class DistributedLedger
{
    private $block_db;
    private $cubbit_integration;
    private $logger;
    private $currentTransactions;
    private $nodes;

    public function __construct()
    {
        $this->block_db = new APS_Block_DB();
        $this->cubbit_integration = new CubbitIntegration();
        $this->logger = Logger::get_instance();
        $this->currentTransactions = [];
        $this->nodes = [];

        // Ensure the blocks table exists
        $this->block_db->create_table();

        // Load the chain from the database
        $last_block = $this->block_db->get_last_block();
        if (!$last_block) {
            // Create the genesis block if no blocks exist
            $this->logger->log('info', 'No blocks found in DB, creating genesis block.');
            $this->newBlock(100, '1'); // Previous hash for genesis block can be '1' or '0'
        } else {
            $this->logger->log('info', 'Loaded existing blockchain from DB. Last block: ' . $last_block['block_hash']);
        }
    }

    /**
     * Creates a new block and adds it to the chain.
     *
     * @param int $proof
     * @param string $previousHash
     *
     * @return array
     */
    public function newBlock(int $proof, string $previousHash): array
    {
        $index = $this->block_db->get_block_count() + 1;
        $block = [
            'index' => $index,
            'timestamp' => time(),
            'transactions' => $this->currentTransactions,
            'proof' => $proof,
            'previous_hash' => $previousHash,
        ];

        $block_hash = self::hash($block);
        $block['hash'] = $block_hash; // Add hash to block data

        // Reset the current list of transactions.
        $this->currentTransactions = [];

        // Save block to database and Cubbit
        $insert_id = $this->block_db->insert_block($block);
        if ($insert_id) {
            $this->logger->log('info', 'New block created and persisted', ['block_hash' => $block_hash, 'index' => $index]);
        } else {
            $this->logger->log('error', 'Failed to persist new block', ['block_hash' => $block_hash, 'index' => $index]);
        }

        return $block;
    }

    /**
     * Adds a new transaction to the list of transactions.
     *
     * @param string $sender
     * @param string $recipient
     * @param float  $amount
     *
     * @return int
     */
    public function newTransaction(string $sender, string $recipient, float $amount): int
    {
        $transaction = [
            'sender' => $sender,
            'recipient' => $recipient,
            'amount' => $amount,
            'timestamp' => time(),
            'hash' => hash('sha256', uniqid('', true) . $sender . $recipient . $amount . time()) // Unique hash for transaction
        ];
        $this->currentTransactions[] = $transaction;

        $lastBlock = $this->lastBlock();
        return $lastBlock ? $lastBlock['index'] + 1 : 1; // Return index of next block
    }

    /**
     * Returns the last block in the chain from persistence.
     *
     * @return array|null
     */
    public function lastBlock(): ?array
    {
        return $this->block_db->get_last_block();
    }

    /**
     * Hashes a block.
     *
     * @param array $block
     *
     * @return string
     */
    public static function hash(array $block): string
    {
        // We must make sure that the Dictionary is Ordered, or we'll have inconsistent hashes
        $blockString = json_encode($block, SORT_REGULAR);

        return hash('sha256', $blockString);
    }

    /**
     * Registers a new node.
     *
     * @param string $address
     */
    public function registerNode(string $address): void
    {
        $parsedUrl = parse_url($address);
        if ($parsedUrl && isset($parsedUrl['host'])) {
            $this->nodes[] = $parsedUrl['host'];
            $this->logger->log('info', 'New node registered', ['node' => $parsedUrl['host']]);
        } else {
            $this->logger->log('error', 'Invalid node address provided', ['address' => $address]);
        }
    }

    /**
     * Determines if a given blockchain is valid.
     *
     * @param array $chain
     *
     * @return bool
     */
    public function validChain(array $chain): bool
    {
        $lastBlock = $chain[0];
        $currentIndex = 1;

        while ($currentIndex < count($chain)) {
            $block = $chain[$currentIndex];
            // Check that the hash of the block is correct
            if ($block['previous_hash'] !== self::hash($lastBlock)) {
                $this->logger->log('warning', 'Chain validation failed: previous hash mismatch', ['block_index' => $block['index']]);
                return false;
            }

            // Check that the Proof of Contribution is correct
            // For now, we pass a dummy contributor_id as validProof doesn't strictly need it for its current placeholder logic.
            // A more robust implementation might require embedding contributor info in the block or proof.
            if (!ConsensusMechanism::validProof($lastBlock['proof'], $block['proof'], 'dummy_contributor_id')) {
                $this->logger->log('warning', 'Chain validation failed: invalid proof', ['block_index' => $block['index']]);
                return false;
            }

            $lastBlock = $block;
            $currentIndex++;
        }
        $this->logger->log('info', 'Chain validated successfully');
        return true;
    }

    /**
     * This is our consensus algorithm, it resolves conflicts by replacing our chain with the longest one in the network.
     *
     * @return bool
     */
    public function resolveConflicts(): bool
    {
        $neighbours = $this->nodes;
        $newChain = null;

        // We're only looking for chains longer than ours
        $maxLength = $this->block_db->get_block_count();

        // Grab and verify the chains from all the nodes in our network
        foreach ($neighbours as $node) {
            try {
                $cache_key = 'aps_chain_' . md5($node);
                $cached_chain = get_transient($cache_key);

                if (false !== $cached_chain) {
                    $data = $cached_chain;
                } else {
                    // Use wp_remote_get for external HTTP requests
                    $response = wp_remote_get("http://{$node}/chain", ['timeout' => 10]);

                    if (is_wp_error($response)) {
                        $this->logger->log('error', 'Failed to fetch chain from neighbour node', ['node' => $node, 'error' => $response->get_error_message()]);
                        continue;
                    }

                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    set_transient($cache_key, $data, MINUTE_IN_SECONDS); // Cache for 1 minute
                }

                if ($data && isset($data['length']) && isset($data['chain'])) {
                    $length = $data['length'];
                    $chain = $data['chain'];

                    // Check if the length is longer and the chain is valid
                    if ($length > $maxLength && $this->validChain($chain)) {
                        $maxLength = $length;
                        $newChain = $chain;
                    }
                } else {
                    $this->logger->log('warning', 'Invalid chain data received from neighbour node', ['node' => $node, 'response' => $body]);
                }
            } catch (\Exception $e) {
                $this->logger->log('error', 'Exception fetching chain from neighbour node', ['node' => $node, 'error' => $e->getMessage()]);
                continue;
            }
        }

        // Replace our chain if we discovered a new, valid chain longer than ours
        if ($newChain) {
            // Clear current database and replace with new chain
            $this->block_db->clear_all_blocks(); // Need to implement this in APS_Block_DB
            foreach ($newChain as $block) {
                $this->block_db->insert_block($block);
            }
            $this->logger->log('info', 'Blockchain replaced with a longer, valid chain');
            return true;
        }

        return false;
    }

    /**
     * Get a paginated list of blocks from persistence.
     *
     * @param array $args Query arguments (page, per_page, orderby, order)
     * @return array
     */
    public function get_blocks($args) {
        return $this->block_db->get_blocks($args);
    }

    /**
     * Get the total count of blocks from persistence.
     *
     * @param array $args Query arguments (for filtering, if implemented)
     * @return int
     */
    public function get_block_count($args) {
        return $this->block_db->get_block_count();
    }

    /**
     * Get a block by its hash from persistence.
     *
     * @param string $hash The hash of the block.
     * @return array|null
     */
    public function get_block_by_hash($hash) {
        return $this->block_db->get_block_by_hash($hash);
    }

    /**
     * Get a paginated list of transactions.
     *
     * @param array $args Query arguments (page, per_page, orderby, order)
     * @return array
     */
    public function get_transactions($args) {
        // This will need to iterate through blocks from DB to get transactions
        $all_transactions = [];
        $blocks = $this->block_db->get_blocks(['per_page' => 999999]); // Fetch all blocks for transactions
        foreach ($blocks as $block) {
            if (isset($block['block_data']['transactions'])) {
                $all_transactions = array_merge($all_transactions, $block['block_data']['transactions']);
            }
        }

        $page = $args['page'] ?? 1;
        $per_page = $args['per_page'] ?? 10;
        $orderby = $args['orderby'] ?? 'timestamp';
        $order = strtoupper($args['order'] ?? 'desc');

        $offset = ($page - 1) * $per_page;

        $sorted_transactions = $all_transactions;

        usort($sorted_transactions, function($a, $b) use ($orderby, $order) {
            if ($orderby === 'timestamp' && isset($a['timestamp']) && isset($b['timestamp'])) {
                return ($order === 'ASC') ? ($a['timestamp'] <=> $b['timestamp']) : ($b['timestamp'] <=> $a['timestamp']);
            }
            return 0;
        });

        return array_slice($sorted_transactions, $offset, $per_page);
    }

    /**
     * Get the total count of transactions.
     *
     * @param array $args Query arguments (for filtering, if implemented)
     * @return int
     */
    public function get_transaction_count($args) {
        $all_transactions = [];
        $blocks = $this->block_db->get_blocks(['per_page' => 999999]); // Fetch all blocks for transactions
        foreach ($blocks as $block) {
            if (isset($block['block_data']['transactions'])) {
                $all_transactions = array_merge($all_transactions, $block['block_data']['transactions']);
            }
        }
        return count($all_transactions);
    }

    /**
     * Get a transaction by its hash.
     *
     * @param string $hash The hash of the transaction.
     * @return array|null
     */
    public function get_transaction_by_hash($hash) {
        $blocks = $this->block_db->get_blocks(['per_page' => 999999]); // Fetch all blocks
        foreach ($blocks as $block) {
            if (isset($block['block_data']['transactions'])) {
                foreach ($block['block_data']['transactions'] as $transaction) {
                    if (isset($transaction['hash']) && $transaction['hash'] === $hash) {
                        return $transaction;
                    }
                }
            }
        }
        return null;
    }
}
