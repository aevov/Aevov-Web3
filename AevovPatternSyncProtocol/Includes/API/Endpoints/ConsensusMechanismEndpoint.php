<?php
/**
 * Consensus Mechanism API endpoints
 * 
 * @package APS
 * @subpackage API\Endpoints
 */

namespace APS\API\Endpoints;

use APS\Decentralized\ConsensusMechanism;
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;

class ConsensusMechanismEndpoint extends BaseEndpoint {
    protected $base = 'consensus';
    private $consensus_mechanism;

    public function __construct($namespace) {
        parent::__construct($namespace);
        $this->consensus_mechanism = new ConsensusMechanism();
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base . '/status', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_consensus_status'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/vote', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submit_vote'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => [
                    'proposal_id' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'ID of the proposal to vote on.'
                    ],
                    'vote' => [
                        'required' => true,
                        'type' => 'boolean',
                        'description' => 'True for approval, false for disapproval.'
                    ],
                    'contributor_id' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => 'ID of the contributor submitting the vote.'
                    ]
                ]
            ]
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/proposals', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_proposals'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_collection_params()
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'add_proposal'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_add_proposal_args()
            ]
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/proposals/(?P<id>[a-zA-Z0-9]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_proposal_details'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => [$this, 'validate_id']
                    ]
                ]
            ]
        ]);
    }

    public function get_consensus_status($request) {
        try {
            $status = $this->consensus_mechanism->get_status();
            return new WP_REST_Response($status);
        } catch (\Exception $e) {
            return new WP_Error('consensus_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function submit_vote($request) {
        $proposal_id = $request['proposal_id'];
        $vote = $request['vote'];
        $contributor_id = $request['contributor_id'];

        try {
            $result = $this->consensus_mechanism->submit_vote($proposal_id, $vote, $contributor_id);
            if (is_wp_error($result)) {
                return $result;
            }
            return new WP_REST_Response(['message' => 'Vote submitted successfully', 'result' => $result], 200);
        } catch (\Exception $e) {
            return new WP_Error('vote_submission_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function get_proposals($request) {
        $args = [
            'page' => $request->get_param('page') ?? 1,
            'per_page' => min($request->get_param('per_page') ?? 10, 100),
            'status' => $request->get_param('status') // e.g., 'open', 'closed', 'approved', 'rejected'
        ];

        try {
            $proposals = $this->consensus_mechanism->get_proposals($args);
            $total = $this->consensus_mechanism->get_proposal_count($args);

            $response = new WP_REST_Response($proposals);
            $response->header('X-WP-Total', $total);
            $response->header('X-WP-TotalPages', ceil($total / $args['per_page']));
            return $response;
        } catch (\Exception $e) {
            return new WP_Error('proposals_error', $e->getMessage(), ['status' => 500]);
        }
    }

    public function get_proposal_details($request) {
        $proposal_id = $request['id'];
        try {
            $proposal = $this->consensus_mechanism->get_proposal($proposal_id);
            if (!$proposal) {
                return new WP_Error('proposal_not_found', 'Proposal not found', ['status' => 404]);
            }
            return new WP_REST_Response($proposal);
        } catch (\Exception $e) {
            return new WP_Error('proposal_details_error', $e->getMessage(), ['status' => 500]);
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
            'status' => [
                'description' => 'Filter proposals by status.',
                'type' => 'string',
                'enum' => ['open', 'closed', 'approved', 'rejected'],
            ],
        ];
    }

    private function validate_id($id) {
        return preg_match('/^[a-zA-Z0-9]+$/', $id);
    }
    public function add_proposal($request) {
        $proposal_data = [
            'title' => $request['title'],
            'description' => $request['description'],
            'type' => $request['type'],
            'metadata' => $request['metadata'] ?? []
        ];

        try {
            $proposal_id = $this->consensus_mechanism->add_proposal($proposal_data);
            if (!$proposal_id) {
                return new WP_Error('proposal_creation_failed', 'Failed to create proposal.', ['status' => 500]);
            }
            return new WP_REST_Response(['message' => 'Proposal created successfully', 'proposal_id' => $proposal_id], 201);
        } catch (\Exception $e) {
            return new WP_Error('proposal_creation_error', $e->getMessage(), ['status' => 500]);
        }
    }

    protected function get_add_proposal_args() {
        return [
            'title' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Title of the proposal.'
            ],
            'description' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Detailed description of the proposal.'
            ],
            'type' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Type of the proposal (e.g., "feature", "bugfix", "governance").'
            ],
            'metadata' => [
                'required' => false,
                'type' => 'object',
                'description' => 'Additional metadata for the proposal.',
                'properties' => [], // Define specific properties if known
                'additionalProperties' => true,
            ],
        ];
    }
}