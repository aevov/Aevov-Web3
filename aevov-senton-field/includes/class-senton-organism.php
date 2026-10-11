<?php
/**
 * The organism-facing layer: one field, one clock, three bridges.
 *
 * Everything the UI and the workflow node touch goes through here, so the lattice
 * stays a pure model and the plugin keeps a single persistence path.
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

class Senton_Organism {

    /** @var Senton_Organism|null */
    private static $instance = null;

    /** @var Senton_Lattice */
    private $lattice;

    /** @var Senton_Bridges */
    private $bridges;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->bridges = new Senton_Bridges();
        $this->lattice = new Senton_Lattice(
            (int) get_option( 'aevov_senton_field_nodes', 64 ),
            null,
            home_url()
        );
    }

    public function lattice() {
        return $this->lattice;
    }

    /**
     * Let an event into the field.
     *
     * @param string     $input    The statement or prompt being perceived.
     * @param float|null $salience Override in [0,1]; null asks aevov-ai-core, then heuristics.
     * @return array
     */
    public function perceive( $input, $salience = null ) {
        $input = trim( (string) $input );

        if ( '' === $input ) {
            return new \WP_Error( 'aevov_senton_empty_input', __( 'The field cannot perceive an empty statement.', 'aevov-senton-field' ) );
        }

        $source       = 'heuristic';
        $resolved     = $salience;
        $requested    = $salience;

        if ( null === $resolved ) {
            $resolved = $this->bridges->ai_salience( $input );
            $source   = null === $resolved ? 'heuristic' : 'aevov-ai-core';
        }

        if ( null === $resolved ) {
            $resolved = $this->salience_of( $input );
        }

        $resolved = max( 0.0, min( 1.0, (float) $resolved ) );

        $before  = $this->lattice()->summary();
        $digest  = substr( hash( 'sha256', $input ), 0, 16 );
        $novelty = Senton_Aps::novelty(
            [
                'pattern_hash' => $digest,
                'pattern_type' => Senton_Aps::PATTERN_TYPE,
                'confidence'   => $before['coherence'],
                'psi'          => $before['psi'],
                'nodes'        => $before['nodes'],
                'tick'         => $before['tick'],
                'reputation'   => $before['reputation'],
                'observed_at'  => time(),
            ]
        );

        // A state the ledger has already seen is allowed to move the field less.
        $effective = $resolved * ( 1.0 - ( Senton_Tuning::of( 'a18' ) * $novelty ) );

        $moved = $this->lattice()->perceive( Senton_Lattice::phase_of( $input ), $effective );
        $this->lattice()->step( 1 );
        $this->persist();

        $summary = $this->lattice()->summary();

        $event = [
            'tick'        => $summary['tick'],
            'kind'        => 'perceive',
            'salience'    => round( $effective, 4 ),
            'salience_requested' => null === $requested ? null : round( (float) $requested, 4 ),
            'salience_from' => $source,
            'novelty'     => round( $novelty, 4 ),
            'delta_m'     => round( (float) $moved, 6 ),
            'coherence'   => $summary['coherence'],
            'coherence_before' => $before['coherence'],
            'digest'      => $digest,
        ];

        $this->bridges->memory_record( $event );
        $this->log( $event );

        return $event;
    }

    /**
     * Advance the clock, couple to peers, and announce ourselves as scheduled.
     *
     * @param int $ticks Cycles to integrate.
     * @return array
     */
    public function step( $ticks = 4 ) {
        $peers = $this->bridges->mesh_peers();

        foreach ( $peers as $announcement ) {
            if ( isset( $announcement['psi'], $announcement['coherence'] ) ) {
                $this->lattice()->couple_to_peer( (float) $announcement['psi'], (float) $announcement['coherence'] );
            }
        }

        $summary = $this->lattice()->step( (int) $ticks );
        $this->persist();

        $every = max( 1, (int) get_option( 'aevov_senton_field_publish_every', 8 ) );
        if ( 0 === $summary['tick'] % $every ) {
            $this->announce();
        }

        return $summary;
    }

    /**
     * Publish the coupling-relevant numbers and nothing else.
     *
     * @return array
     */
    public function announce() {
        $order = $this->lattice()->order_parameter();

        $announcement = [
            'node'        => $this->bridges->node_id(),
            'psi'         => round( $order[1], 6 ),
            'coherence'   => round( $order[0], 6 ),
            'reputation'  => round( $this->lattice()->summary()['reputation'], 6 ),
            'signature'   => $this->lattice()->signature(),
            'nodes'       => $this->lattice()->summary()['nodes'],
            'published'   => time(),
        ];

        $this->bridges->mesh_publish( $announcement );
        $announcement['aps'] = Senton_Aps::publish( $this->lattice()->summary() );
        do_action( 'aevov_senton_field_announced', $announcement );

        return $announcement;
    }

    /**
     * Everything the experience layer renders. Public by design: field state is not
     * secret, which is only true because nothing here belongs to the organism.
     *
     * @return array
     */
    public function snapshot() {
        $summary = $this->lattice()->summary();
        $peers   = $this->bridges->mesh_peers();

        return [
            'field'      => $summary,
            'phases'     => $this->lattice()->phases_for_display(),
            'history'    => $this->lattice()->history(),
            'availability' => array_merge( $this->bridges->availability(), Senton_Aps::availability() ),
            'tuning'     => Senton_Tuning::recovered(),
            'peers'      => array_values( $peers ),
            'peer_count' => count( $peers ),
            'log'        => $this->log( null ),
            'boundary'   => Senton_Workflow::boundary(),
        ];
    }

    public function reset() {
        delete_option( 'aevov_senton_field_state' );
        delete_option( 'aevov_senton_field_log' );

        $this->lattice = new Senton_Lattice(
            (int) get_option( 'aevov_senton_field_nodes', 64 ),
            null,
            home_url()
        );

        $this->persist();

        return $this->lattice->summary();
    }

    /**
     * Deterministic salience when no provider is configured: a field should still
     * respond to how much a statement says, not merely to it being long.
     *
     * @param string $input
     * @return float
     */
    private function salience_of( $input ) {
        $length = min( 1.0, strlen( $input ) / Senton_Tuning::of( 'a15' ) );

        $counts = [];
        foreach ( (array) count_chars( $input, 1 ) as $byte => $count ) {
            $counts[] = $count;
        }
        $total   = array_sum( $counts );
        $entropy = 0.0;
        foreach ( $counts as $count ) {
            $probability = $count / max( 1, $total );
            $entropy    -= $probability * log( $probability, 2 );
        }
        $novelty = min( 1.0, $entropy / Senton_Tuning::of( 'a16' ) );

        $urgency = 0.0;
        if ( preg_match( '/[?!]/', $input ) ) {
            $urgency += Senton_Tuning::of( 'a13' );
        }
        if ( preg_match( '/[A-Z]{3,}/', $input ) ) {
            $urgency += Senton_Tuning::of( 'a14' );
        }

        return max(
            Senton_Tuning::of( 'a17' ),
            min( 1.0, ( Senton_Tuning::of( 'a11' ) * $length ) + ( Senton_Tuning::of( 'a12' ) * $novelty ) + $urgency )
        );
    }

    private function persist() {
        update_option( 'aevov_senton_field_state', $this->lattice->state(), false );
    }

    /**
     * A short ring buffer of what the field just did, for the admin view.
     *
     * @param array|null $event Event to append, or null to read.
     * @return array
     */
    private function log( $event ) {
        $log = (array) get_option( 'aevov_senton_field_log', [] );

        if ( null !== $event ) {
            array_unshift( $log, $event );
            $log = array_slice( $log, 0, 24 );
            update_option( 'aevov_senton_field_log', $log, false );
        }

        return $log;
    }
}
