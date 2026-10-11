<?php
/**
 * The phase lattice.
 *
 * N oscillators, all-to-all coupled (N^2 interactions per cycle), integrated as a
 * discrete Kuramoto step. The order parameter R in [0,1] is the field's coherence;
 * the change in R across a cycle is delta-m, the quantity the rest of the plugin
 * treats as "did that event help the field hold together".
 *
 * This is deliberately public mathematics. No organism state, weights, seeds or
 * codecs live here — see BOUNDARY.md.
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

class Senton_Lattice {

    const TWO_PI = 6.283185307179586;

    /** @var int Node count. */
    private $n;

    /** @var float[] Phase of each node, radians. */
    private $phases = [];

    /** @var float[] Natural frequency of each node. */
    private $omegas = [];

    /** @var float Kuramoto coupling constant K. */
    private $coupling;

    /** @var float Integration step. */
    private $dt;

    /** @var int Cycles completed. */
    private $tick = 0;

    /** @var float Current order parameter R. */
    private $coherence = 0.0;

    /** @var float delta-m from the most recent cycle. */
    private $delta_m = 0.0;

    /** @var float Coherence reputation, [0,1]. Signed in delta-m, converges toward R. */
    private $reputation = 0.0;

    /** @var float[] Rolling R history for the sparkline. */
    private $history = [];

    /**
     * @param int        $n        Node count.
     * @param float|null $coupling Coupling constant; defaults to the tuning table.
     * @param string     $seed     Text that makes this field's layout specific to this site.
     */
    public function __construct( $n = 64, $coupling = null, $seed = '' ) {
        $this->n        = max( 4, min( 256, (int) $n ) );
        $this->coupling = null === $coupling ? (float) get_option( 'aevov_senton_field_coupling', Senton_Tuning::of( 'a02' ) ) : (float) $coupling;
        $this->dt       = (float) get_option( 'aevov_senton_field_dt', Senton_Tuning::of( 'a01' ) );

        $state  = get_option( 'aevov_senton_field_state', false );
        $layout = is_array( $state ) && isset( $state['omegas'] ) ? $state : false;

        if ( $layout && count( $layout['omegas'] ) === $this->n ) {
            $this->restore( $state );
        } else {
            $this->seed_lattice( (string) $seed );
            $this->coherence = $this->order_parameter()[0];
            $this->history    = [ $this->coherence ];
        }
    }

    /**
     * Deterministic layout: natural frequencies spread symmetrically about a base,
     * with per-site jitter from a hash so two sites are not the same field.
     *
     * @param string $seed Site-specific text.
     */
    private function seed_lattice( $seed ) {
        $hash  = hash( 'sha256', $seed . '|' . wp_salt( 'auth' ) );
        $drift = ( ( (int) substr( $hash, 0, 6 ) ) / 0xFFFFFF ) - 0.5;

        for ( $i = 0; $i < $this->n; $i++ ) {
            $position = ( $i / $this->n ) - 0.5;
            $jitter   = ( ( (int) substr( $hash, ( $i % 60 ) + 2, 4 ) ) / 0xFFFF ) - 0.5;

            $this->omegas[ $i ] = Senton_Tuning::of( 'a03' ) + ( 2.0 * $position * Senton_Tuning::of( 'a04' ) ) + ( $jitter * Senton_Tuning::of( 'a05' ) ) + $drift;
            $this->phases[ $i ] = $position * self::TWO_PI;
        }
    }

    /**
     * Advance the field by $steps cycles.
     *
     * Synchronous update: every delta is computed against the current phase set
     * before any phase moves, which is what makes N^2 coupling stable at this dt.
     *
     * @param int $steps Cycles.
     * @return array {tick, coherence, delta_m, reputation}
     */
    public function step( $steps = 1 ) {
        $steps = max( 1, min( 512, (int) $steps ) );

        for ( $s = 0; $s < $steps; $s++ ) {
            $before = $this->coherence;
            $deltas = [];

            for ( $i = 0; $i < $this->n; $i++ ) {
                $sum = 0.0;
                for ( $j = 0; $j < $this->n; $j++ ) {
                    $sum += sin( $this->phases[ $j ] - $this->phases[ $i ] );
                }
                $deltas[ $i ] = $this->dt * ( $this->omegas[ $i ] + ( $this->coupling / $this->n ) * $sum );
            }

            for ( $i = 0; $i < $this->n; $i++ ) {
                $this->phases[ $i ] = $this->wrap( $this->phases[ $i ] + $deltas[ $i ] );
            }

            $this->tick++;
            $this->coherence = $this->order_parameter()[0];
            $this->delta_m   = $this->coherence - $before;
            $this->accrue_reputation();

            $this->history[] = $this->coherence;
            if ( count( $this->history ) > 120 ) {
                array_shift( $this->history );
            }
        }

        return $this->summary();
    }

    /**
     * Reputation is what a peer may expect of this field's coherence: signed in delta-m
     * so decoherence costs, and converging toward R. Accumulating only positive deltas
     * would score a field that jitters symmetrically around zero as reputable.
     */
    private function accrue_reputation() {
        $this->reputation = $this->clamp01(
            ( $this->reputation * Senton_Tuning::of( 'a09' ) )
            + ( Senton_Tuning::of( 'a08' ) * $this->delta_m )
            + ( Senton_Tuning::of( 'a10' ) * ( $this->coherence - $this->reputation ) )
        );
    }

    /**
     * An external event bends the field toward the event's phase.
     *
     * @param float $target_phase Attractor phase, radians.
     * @param float $salience     [0,1] — how much the field is allowed to care.
     * @return float delta-m produced by the perturbation alone.
     */
    public function perceive( $target_phase, $salience = null ) {
        if ( null === $salience ) {
            $salience = Senton_Tuning::of( 'a19' );
        }

        $salience = $this->clamp01( (float) $salience );
        $before   = $this->coherence;
        $strength = $salience * $this->coupling * Senton_Tuning::of( 'a06' );

        for ( $i = 0; $i < $this->n; $i++ ) {
            $this->phases[ $i ] = $this->wrap( $this->phases[ $i ] + ( $strength * sin( $target_phase - $this->phases[ $i ] ) ) );
        }

        $this->coherence = $this->order_parameter()[0];
        $this->delta_m   = $this->coherence - $before;

        return $this->delta_m;
    }

    /**
     * A peer field's mean phase tugs this one, weighted by the peer's own coherence.
     *
     * @param float $peer_phase Peer mean phase, radians.
     * @param float $peer_coherence Peer R.
     */
    public function couple_to_peer( $peer_phase, $peer_coherence ) {
        $weight = Senton_Tuning::of( 'a07' ) * $this->clamp01( (float) $peer_coherence );

        for ( $i = 0; $i < $this->n; $i++ ) {
            $this->phases[ $i ] = $this->wrap( $this->phases[ $i ] + ( $weight * sin( $peer_phase - $this->phases[ $i ] ) ) );
        }

        $this->coherence = $this->order_parameter()[0];
    }

    /**
     * Kuramoto order parameter: R and psi, where R is phase-locking strength and
     * psi is the phase the field has settled on.
     *
     * @return array [R, psi]
     */
    public function order_parameter() {
        $real = 0.0;
        $imag = 0.0;

        for ( $i = 0; $i < $this->n; $i++ ) {
            $real += cos( $this->phases[ $i ] );
            $imag += sin( $this->phases[ $i ] );
        }

        $real /= $this->n;
        $imag /= $this->n;

        return [ sqrt( ( $real * $real ) + ( $imag * $imag ) ), atan2( $imag, $real ) ];
    }

    /**
     * Map arbitrary input text onto a phase deterministically.
     *
     * @param string $text Input.
     * @return float Radians.
     */
    public static function phase_of( $text ) {
        $hash = hash( 'sha256', (string) $text );
        return ( ( (int) substr( $hash, 0, 8 ) ) / 0xFFFFFFFF ) * self::TWO_PI;
    }

    /**
     * A short, shareable fingerprint of the field's current state. Published to the
     * mesh in place of anything about how the field works.
     *
     * @return string
     */
    public function signature() {
        $quantised = [];

        foreach ( $this->phases as $phase ) {
            $quantised[] = dechex( (int) round( ( $phase / self::TWO_PI ) * 15 ) );
        }

        return substr( hash( 'sha256', implode( '', $quantised ) ), 0, 32 );
    }

    public function summary() {
        return [
            'tick'       => $this->tick,
            'nodes'      => $this->n,
            'interactions' => $this->n * $this->n,
            'coherence'  => round( $this->coherence, 6 ),
            'delta_m'    => round( $this->delta_m, 6 ),
            'reputation' => round( $this->reputation, 6 ),
            'psi'        => round( $this->order_parameter()[1], 6 ),
            'signature'  => $this->signature(),
        ];
    }

    /**
     * Phases rounded for display — enough to draw the field, not enough to rebuild it.
     *
     * @return float[]
     */
    public function phases_for_display() {
        return array_map(
            function ( $phase ) {
                return round( $phase, 3 );
            },
            $this->phases
        );
    }

    public function history() {
        return array_map(
            function ( $value ) {
                return round( $value, 4 );
            },
            $this->history
        );
    }

    public function state() {
        return [
            'n'          => $this->n,
            'phases'     => $this->phases_for_display(),
            'omegas'     => array_map( [ $this, 'round_state' ], $this->omegas ),
            'coupling'   => $this->coupling,
            'dt'         => $this->dt,
            'tick'       => $this->tick,
            'coherence'  => $this->coherence,
            'delta_m'    => $this->delta_m,
            'reputation' => $this->reputation,
            'history'    => $this->history,
        ];
    }

    public function round_state( $value ) {
        return round( (float) $value, 6 );
    }

    private function restore( $state ) {
        $this->phases     = array_values( array_map( 'floatval', $state['phases'] ) );
        $this->omegas     = array_values( array_map( 'floatval', $state['omegas'] ) );
        $this->tick       = isset( $state['tick'] ) ? (int) $state['tick'] : 0;
        $this->coherence  = isset( $state['coherence'] ) ? (float) $state['coherence'] : $this->order_parameter()[0];
        $this->delta_m    = isset( $state['delta_m'] ) ? (float) $state['delta_m'] : 0.0;
        $this->reputation = isset( $state['reputation'] ) ? (float) $state['reputation'] : 0.0;
        $this->history    = isset( $state['history'] ) && is_array( $state['history'] ) ? array_values( $state['history'] ) : [];
    }

    private function wrap( $phase ) {
        $wrapped = fmod( (float) $phase, self::TWO_PI );
        return $wrapped < 0 ? $wrapped + self::TWO_PI : $wrapped;
    }

    private function clamp01( $value ) {
        return max( 0.0, min( 1.0, (float) $value ) );
    }
}
