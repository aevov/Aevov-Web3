<?php

namespace APS\API\Endpoints;

use Aevov\Decentralized\ProofOfContribution;
use Aevov\Decentralized\ConsensusMechanism;
use Aevov\Decentralized\DistributedLedger;
use Aevov\Decentralized\Contributor; // New import
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;

class ProofOfContributionEndpoint extends BaseEndpoint
{
    private $poc;
    private $consensus_mechanism;
    private $distributed_ledger;

    public function __construct($namespace)
    {
        parent::__construct($namespace);
        $this->poc = new ProofOfContribution();
        $this->consensus_mechanism = new ConsensusMechanism();
        $this->distributed_ledger = new DistributedLedger();
        $this->base = 'poc';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->base . '/mine', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'mine'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => [
                    'contributor_id' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'The ID of the contributor mining the block.'
                    ]
                ]
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/chain', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'chain'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_collection_params()
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/nodes/register', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'register_nodes'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => [
                    'nodes' => [
                        'required' => true,
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Array of node URLs to register.'
                    ]
                ]
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/nodes/resolve', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'resolve_nodes'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/contributor-score/(?P<contributor_id>[a-zA-Z0-9]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_contributor_score'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'contributor_id' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'ID of the contributor.'
                    ]
                ]
            ]
        ]);
    }

    public function resolve_nodes(\WP_REST_Request $request)
    {
        try {
            $replaced = $this->distributed_ledger->resolveConflicts();

            if ($replaced) {
                $response = [
                    'message' => 'Our chain was replaced',
                    'new_chain_length' => $this->distributed_ledger->get_block_count([]),
                ];
            } else {
                $response = [
                    'message' => 'Our chain is authoritative',
                    'chain_length' => $this->distributed_ledger->get_block_count([]),
                ];
            }
            return new WP_REST_Response($response, 200);
        } catch (\Exception $e) {
            return new WP_Error('conflict_resolution_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function register_nodes(\WP_REST_Request $request)
    {
        $nodes = $request->get_param('nodes');

        if (!is_array($nodes)) {
            return new WP_REST_Response(['message' => 'Error: Please supply a valid list of nodes'], 400);
        }

        try {
            foreach ($nodes as $node) {
                $this->distributed_ledger->registerNode($node);
            }

            $response = [
                'message' => 'New nodes have been added',
                'total_nodes' => count($this->distributed_ledger->get_nodes()),
            ];
            return new WP_REST_Response($response, 201);
        } catch (\Exception $e) {
            return new WP_Error('node_registration_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function chain(\WP_REST_Request $request)
    {
        $args = [
            'page' => $request->get_param('page') ?? 1,
            'per_page' => min($request->get_param('per_page') ?? 10, 100),
            'orderby' => $request->get_param('orderby') ?? 'created_at',
            'order' => $request->get_param('order') ?? 'desc'
        ];

        try {
            $chain = $this->distributed_ledger->get_blocks($args);
            $length = $this->distributed_ledger->get_block_count([]);

            $response = new WP_REST_Response($chain);
            $response->header('X-WP-Total', $length);
            $response->header('X-WP-TotalPages', ceil($length / $args['per_page']));
            return $response;
        } catch (\Exception $e) {
            return new WP_Error('chain_fetch_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function mine(\WP_REST_Request $request)
    {
        $contributor_id = $request['contributor_id'];

        try {
            // We run the proof of contribution algorithm to get the next proof...
            $last_block = $this->distributed_ledger->lastBlock();
            $last_proof = $last_block ? $last_block['proof'] : 0;
            $proof = $this->consensus_mechanism->proofOfContribution($contributor_id, $last_proof);

            // We must receive a reward for finding the proof.
            // The sender is "0" to signify that this node has mined a new coin.
            $this->distributed_ledger->newTransaction(
                '0',
                $contributor_id,
                1.0
            );

            // Forge the new Block by adding it to the chain
            $previous_hash = $last_block ? $this->distributed_ledger->hash($last_block) : '1';
            $block = $this->distributed_ledger->newBlock($proof, $previous_hash);

            // Reward the contributor
            $contributor = new Contributor($contributor_id);
            $this->poc->rewards->reward($contributor);

            $response = [
                'message' => 'New Block Forged',
                'index' => $block['index'],
                'transactions' => $block['transactions'],
                'proof' => $block['proof'],
                'previous_hash' => $block['previous_hash'],
            ];
            return new WP_REST_Response($response, 200);
        } catch (\Exception $e) {
            return new WP_Error('mine_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function get_contributor_score(\WP_REST_Request $request) {
        $contributor_id = $request['contributor_id'];
        try {
            $score = $this->consensus_mechanism->get_contributor_contribution_score($contributor_id);
            return new WP_REST_Response(['contributor_id' => $contributor_id, 'score' => $score], 200);
        } catch (\Exception $e) {
            return new WP_Error('contributor_score_failed', $e->getMessage(), ['status' => 500]);
        }
    }
}
