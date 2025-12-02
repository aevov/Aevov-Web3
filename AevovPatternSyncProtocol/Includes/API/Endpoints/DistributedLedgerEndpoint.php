<?php
/**
 * Distributed Ledger API endpoints
 * 
 * @package APS
 * @subpackage API\Endpoints
 */

namespace APS\API\Endpoints;

use APS\Decentralized\DistributedLedger;
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;

class DistributedLedgerEndpoint extends BaseEndpoint {
    protected $base = 'ledger';
    private $distributed_ledger;

    public function __construct($namespace) {
        parent::__construct($namespace);
        $this->distributed_ledger = new DistributedLedger();
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base . '/blocks', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_blocks'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_collection_params()
            ]
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/blocks/(?P<hash>[a-zA-Z0-9]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_block_by_hash'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'hash' => [
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => [$this, 'validate_hash']
                    ]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/transactions', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_transactions'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_collection_params()
            ]
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/transactions/(?P<hash>[a-zA-Z0-9]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_transaction_by_hash'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'hash' => [
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => [$this, 'validate_hash']
                    ]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/nodes', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'register_node'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => [
                    'address' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'The URL of the new node.'
                    ]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/resolve-conflicts', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'resolve_conflicts'],
                'permission_callback' => [$this, 'check_write_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/transactions/new', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'new_transaction'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => [
                    'sender' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'The address of the sender.'
                    ],
                    'recipient' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'The address of the recipient.'
                    ],
                    'amount' => [
                        'required' => true,
                        'type' => 'number',
                        'description' => 'The amount of the transaction.'
                    ]
                ]
            ]
        ]);
    }

    public function get_blocks($request) {
        $args = [
            'page' => $request->get_param('page') ?? 1,
            'per_page' => min($request->get_param('per_page') ?? 10, 100),
            'orderby' => $request->get_param('orderby') ?? 'timestamp',
            'order' => $request->get_param('order') ?? 'desc'
        ];

        try {
            $blocks = $this->distributed_ledger->get_blocks($args);
            $total = $this->distributed_ledger->get_block_count($args);

            $response = new WP_REST_Response($blocks);
            $response->header('X-WP-Total', $total);
            $response->header('X-WP-TotalPages', ceil($total / $args['per_page']));
            return $response;
        } catch (\Exception $e) {
            return new WP_Error('ledger_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function get_block_by_hash($request) {
        $hash = $request['hash'];
        try {
            $block = $this->distributed_ledger->get_block_by_hash($hash);
            if (!$block) {
                return new WP_Error('block_not_found', 'Block not found', ['status' => 404]);
            }
            return new WP_REST_Response($block);
        } catch (\Exception $e) {
            return new WP_Error('ledger_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function get_transactions($request) {
        $args = [
            'page' => $request->get_param('page') ?? 1,
            'per_page' => min($request->get_param('per_page') ?? 10, 100),
            'orderby' => $request->get_param('orderby') ?? 'timestamp',
            'order' => $request->get_param('order') ?? 'desc'
        ];

        try {
            $transactions = $this->distributed_ledger->get_transactions($args);
            $total = $this->distributed_ledger->get_transaction_count($args);

            $response = new WP_REST_Response($transactions);
            $response->header('X-WP-Total', $total);
            $response->header('X-WP-TotalPages', ceil($total / $args['per_page']));
            return $response;
        } catch (\Exception $e) {
            return new WP_Error('ledger_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function get_transaction_by_hash($request) {
        $hash = $request['hash'];
        try {
            $transaction = $this->distributed_ledger->get_transaction_by_hash($hash);
            if (!$transaction) {
                return new WP_Error('transaction_not_found', 'Transaction not found', ['status' => 404]);
            }
            return new WP_REST_Response($transaction);
        } catch (\Exception $e) {
            return new WP_Error('ledger_error', $e->getMessage(), ['status' => 500]);
        }
    }

    protected function get_collection_params() {
        return [
            'page' => [
                'description' => 'Current page of the collection.',
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
            ],
            'per_page' => [
                'description' => 'Maximum number of items to be returned in result set.',
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'orderby' => [
                'description' => 'Order collection by object attribute.',
                'type' => 'string',
                'default' => 'timestamp',
                'enum' => ['timestamp', 'hash'],
            ],
            'order' => [
                'description' => 'Order sort attribute ascending or descending.',
                'type' => 'string',
                'default' => 'desc',
                'enum' => ['asc', 'desc'],
            ],
        ];
    }

    private function validate_hash($hash) {
        return preg_match('/^[a-zA-Z0-9]+$/', $hash);
    }
    public function register_node($request) {
        $address = $request['address'];
        try {
            $this->distributed_ledger->registerNode($address);
            return new WP_REST_Response(['message' => 'Node registered successfully'], 200);
        } catch (\Exception $e) {
            return new WP_Error('node_registration_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function resolve_conflicts($request) {
        try {
            $replaced = $this->distributed_ledger->resolveConflicts();
            if ($replaced) {
                return new WP_REST_Response(['message' => 'Our chain was replaced by a longer one.'], 200);
            } else {
                return new WP_REST_Response(['message' => 'Our chain is authoritative.'], 200);
            }
        } catch (\Exception $e) {
            return new WP_Error('conflict_resolution_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function new_transaction($request) {
        $sender = $request['sender'];
        $recipient = $request['recipient'];
        $amount = $request['amount'];

        try {
            $index = $this->distributed_ledger->newTransaction($sender, $recipient, $amount);
            return new WP_REST_Response(['message' => "Transaction will be added to Block {$index}"], 201);
        } catch (\Exception $e) {
            return new WP_Error('transaction_creation_failed', $e->getMessage(), ['status' => 500]);
        }
    }
}