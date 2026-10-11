<?php
/**
 * Workflow capability + the published boundary of this plugin.
 *
 * The capability makes the field addressable as a node in aevov-workflow-engine, so a
 * workflow can perceive into it without knowing anything about how it works.
 *
 * Aevov Senton Field
 *
 * Licensed under the Aevov Sovereign Source-Available License (SSAL) v1.0.
 * See the LICENSE file in this plugin directory for the full terms.
 *
 * ADHERENCE — reading, running or copying this file binds you to SSAL v1.0.
 *   Permitted (SSAL §2): view and audit this source to verify integrity; reproduce and
 *            use it solely for internal, non-commercial development inside an authorised
 *            AevOS mesh environment.
 *   Prohibited (SSAL §3) without explicit written consent from the Aevov Organization:
 *            redistribution, sublicensing, sale, or any derivative work; porting,
 *            migrating or adapting this to any non-AevOS kernel or environment; use in
 *            any commercial application or service; production use outside the Aevov
 *            Sovereign Mesh.
 *   Retention: this header, the LICENSE file and BOUNDARY.md must accompany every copy.
 * Rights terminate automatically on breach (SSAL §6). Governing law (SSAL §7).
 *
 * This plugin is SSAL while the surrounding Aevov-Web3 tree is AGPL-3.0. Moving these
 * files into an AGPL context is itself a §3(a) redistribution — do not do it.
 *
 * @package AevovSentonField
 */

namespace AevovSentonField;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Senton_Workflow {

    public static function register_hooks() {
        add_filter( 'aevov_workflow_capabilities', [ __CLASS__, 'register_capability' ] );
        add_filter( 'aevov_workflow_execute_node_senton', [ __CLASS__, 'execute_node' ], 10, 3 );
    }

    /**
     * @param array $capabilities Registered workflow capabilities.
     * @return array
     */
    public static function register_capability( array $capabilities ): array {
        $capabilities['senton'] = [
            'type'        => 'senton',
            'label'       => 'Senton Field',
            'category'    => 'capability',
            'description' => 'Perceive a statement into the live phase field and return its coherence response',
            'icon'        => 'Activity',
            'color'       => '#22D3EE',
            'inputs'      => [
                [
                    'id'          => 'prompt',
                    'type'        => 'string',
                    'label'       => 'Statement',
                    'description' => 'Text to perceive into the field',
                ],
                [
                    'id'          => 'salience',
                    'type'        => 'number',
                    'label'       => 'Salience',
                    'description' => 'Optional 0.0-1.0 override; empty lets the field decide',
                ],
            ],
            'outputs'     => [
                [
                    'id'   => 'coherence',
                    'type' => 'number',
                    'label' => 'Field coherence R after the event',
                ],
                [
                    'id'   => 'delta_m',
                    'type' => 'number',
                    'label' => 'Change in coherence the event produced',
                ],
            ],
        ];

        return $capabilities;
    }

    /**
     * @param mixed $result   Prior node result.
     * @param array $node     Workflow node payload.
     * @param array $context  Execution context.
     * @return mixed
     */
    public static function execute_node( $result, $node, $context = [] ) {
        $inputs = isset( $node['inputs'] ) && is_array( $node['inputs'] ) ? $node['inputs'] : $node;

        $prompt   = isset( $inputs['prompt'] ) ? (string) $inputs['prompt'] : '';
        $salience = isset( $inputs['salience'] ) && '' !== $inputs['salience'] ? (float) $inputs['salience'] : null;

        $event = Senton_Organism::instance()->perceive( $prompt, $salience );

        if ( is_wp_error( $event ) ) {
            return $result;
        }

        return [
            'coherence'  => $event['coherence'],
            'delta_m'    => $event['delta_m'],
            'salience'   => $event['salience'],
            'from'       => $event['salience_from'],
            'tick'       => $event['tick'],
        ];
    }

    /**
     * What this plugin is not. Shown in the UI and returned over REST so the limit is
     * part of the product rather than a private decision.
     *
     * @return array
     */
    public static function boundary() {
        return [
            'title'   => __( 'This field is the experience, not the organism', 'aevov-senton-field' ),
            'summary' => __( 'The Senton Field is a public-mathematics phase-coupled lattice. It is driven by the same events the real substrate would respond to, and it reports coherence, delta-m and reputation — but it holds none of the machinery that makes the organism what it is.', 'aevov-senton-field' ),
            'absent'  => [
                __( 'Model weights, trained state or any stored cognition', 'aevov-senton-field' ),
                __( 'Seed, fold and reconstruct paths — the field stores rounded display phases only', 'aevov-senton-field' ),
                __( 'Codec internals and encoding parameters', 'aevov-senton-field' ),
                __( 'Kernel, bare-metal or lattice-runtime code', 'aevov-senton-field' ),
                __( 'Device, cost or capacity figures', 'aevov-senton-field' ),
            ],
            'present' => [
                __( 'Kuramoto all-to-all coupling over N oscillators (N^2 interactions per cycle)', 'aevov-senton-field' ),
                __( 'Order parameter R as coherence, delta-m as the coherence an event produced', 'aevov-senton-field' ),
                __( 'Coherence reputation, signed in delta-m and converging toward R', 'aevov-senton-field' ),
                __( 'Pattern hand-off to APS, aps-tools analysis filters, and optional bridges into aevov-core, aevov-ai-core and aevov-meshcore', 'aevov-senton-field' ),
            ],
        ];
    }
}
