<?php
/**
 * Bridge to the core three: AevovPatternSyncProtocol, bloom-pattern-recognition,
 * aps-tools.
 *
 * APS owns the pattern ledger and the distribution protocol. The field's job here is
 * to be a pattern *source*: it emits into the protocol APS already declares, answers
 * the analysis filters aps-tools already apply, and reads novelty back from wherever
 * the ledger genuinely exists. Nothing in this file requires a table, an embedding
 * engine, or APS itself to be switched on.
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

class Senton_Aps {

    /** @var string Pattern type this plugin publishes under. */
    const PATTERN_TYPE = 'senton-field';

    public static function register_hooks() {
        add_filter( 'aps_pattern_analysis', [ __CLASS__, 'analyse' ] );
        add_filter( 'aps_tools_pattern_analysis', [ __CLASS__, 'extend_analysis' ], 10, 2 );
        add_filter( 'aps_tools_pattern_comparison', [ __CLASS__, 'extend_comparison' ], 10, 3 );
    }

    public static function availability() {
        return [
            'aps'        => defined( 'APS_VERSION' ) || class_exists( 'APS\\Network\\PatternDistributor' ) || has_action( 'aps_distribute_pattern' ) > 0,
            'bloom'      => function_exists( 'bloom' ) || class_exists( 'Bloom_Pattern_System' ),
            'aps_tools'  => function_exists( 'aps_tools_calculate_complexity' ) || has_filter( 'aps_tools_pattern_analysis' ) > 0,
            'ledger'     => class_exists( 'AevovChunkRegistry\\ChunkRegistry' ) && class_exists( 'AevovEmbeddingEngine\\EmbeddingManager' ),
        ];
    }

    /**
     * Hand the field state to APS as a pattern.
     *
     * `confidence` is the field's own coherence R, which means APS's 0.75 distribution
     * threshold does the thing a threshold here should do: a scattered field is not
     * worth propagating, and the ledger's existing gate decides that without us
     * inventing a second one.
     *
     * @param array $summary Lattice summary.
     * @return array What was handed off, and to which channel.
     */
    public static function publish( array $summary ) {
        $pattern = [
            'pattern_hash'   => $summary['signature'],
            'pattern_type'   => self::PATTERN_TYPE,
            'confidence'     => (float) $summary['coherence'],
            'psi'            => $summary['psi'],
            'nodes'          => $summary['nodes'],
            'tick'           => $summary['tick'],
            'reputation'     => $summary['reputation'],
            'site'           => get_current_blog_id(),
            'source'         => 'aevov-senton-field',
            'observed_at'    => time(),
        ];

        $result = [
            'pattern'   => $pattern,
            'distributed' => false,
            'ledger'    => false,
            'novelty'   => self::novelty( $pattern ),
        ];

        if ( has_action( 'aps_distribute_pattern' ) ) {
            do_action( 'aps_distribute_pattern', $pattern );
            $result['distributed'] = true;
        }

        $result['ledger'] = self::ledger_register( $pattern );

        return $result;
    }

    /**
     * Register the field state in the chunk ledger that APS comparison and bloom both
     * read. Skipped unless the ledger and its embedding engine are actually present,
     * because ChunkRegistry calls into the embedding engine unconditionally.
     *
     * @param array $pattern Pattern payload.
     * @return bool
     */
    public static function ledger_register( array $pattern ) {
        if ( ! self::availability()['ledger'] ) {
            return false;
        }

        try {
            $chunk = new \AevovChunkRegistry\AevovChunk(
                'senton:' . $pattern['pattern_hash'],
                self::PATTERN_TYPE,
                '',
                [
                    'type'       => self::PATTERN_TYPE,
                    'features'   => self::features( $pattern ),
                    'signature'  => $pattern['pattern_hash'],
                    'coherence'  => $pattern['confidence'],
                    'observed_at' => $pattern['observed_at'],
                ]
            );

            $registry = new \AevovChunkRegistry\ChunkRegistry();
            return (bool) $registry->register_chunk( $chunk );
        } catch ( \Throwable $e ) {
            do_action( 'aevov_error', 'senton-field', $e->getMessage() );
            return false;
        }
    }

    /**
     * How unlike the rest of the ledger this field state is. Used to scale salience, so
     * a genuinely new state is allowed to disturb the field more than a repeated one.
     *
     * Falls back to this site's own recent signatures when no ledger is present, which
     * still answers the only question the field needs asked: have I been here before?
     *
     * @param array $pattern
     * @return float 0 (novel) to 1 (already known).
     */
    public static function novelty( array $pattern ) {
        if ( self::availability()['ledger'] ) {
            try {
                $comparator = new \APS\Comparison\APS_Comparator();
                $matches    = $comparator->find_analogous_patterns(
                    [
                        'id'       => 'senton:' . $pattern['pattern_hash'],
                        'type'     => self::PATTERN_TYPE,
                        'features' => self::features( $pattern ),
                    ],
                    3
                );

                if ( is_array( $matches ) && ! empty( $matches ) && isset( $matches[0]['score'] ) ) {
                    return min( 1.0, max( 0.0, (float) $matches[0]['score'] ) );
                }
            } catch ( \Throwable $e ) {
                do_action( 'aevov_error', 'senton-field', $e->getMessage() );
            }
        }

        $seen = (array) get_option( 'aevov_senton_field_signatures', [] );
        $known = isset( $seen[ $pattern['pattern_hash'] ] );

        $seen[ $pattern['pattern_hash'] ] = time();
        if ( count( $seen ) > 200 ) {
            asort( $seen );
            $seen = array_slice( $seen, 0, 200, true );
        }
        update_option( 'aevov_senton_field_signatures', $seen, false );

        if ( $known ) {
            return 1.0;
        }

        return 0.0;
    }

    /**
     * Feature vector for the ledger: the field's coarse shape, not its full phase list.
     *
     * @param array $pattern
     * @return array
     */
    private static function features( array $pattern ) {
        return [
            'coherence'  => round( (float) $pattern['confidence'], 3 ),
            'psi_bucket' => (int) floor( ( (float) $pattern['psi'] / M_PI + 1.0 ) * 8 ),
            'nodes'      => (int) $pattern['nodes'],
            'reputation' => round( (float) $pattern['reputation'], 3 ),
        ];
    }

    /**
     * aps-tools POST /analyze handler passes arbitrary pattern data through this
     * filter. When the pattern is a field state, answer with the field's own verdict.
     *
     * @param mixed $pattern_data
     * @return mixed
     */
    public static function analyse( $pattern_data ) {
        if ( ! is_array( $pattern_data ) ) {
            return $pattern_data;
        }

        $type = isset( $pattern_data['pattern_type'] ) ? $pattern_data['pattern_type'] : null;

        if ( self::PATTERN_TYPE !== $type ) {
            return $pattern_data;
        }

        $summary = Senton_Organism::instance()->lattice()->summary();
        $moved   = Senton_Organism::instance()->perceive(
            isset( $pattern_data['pattern_hash'] ) ? $pattern_data['pattern_hash'] : wp_json_encode( $pattern_data ),
            null
        );

        $pattern_data['senton'] = [
            'coherence_before' => $summary['coherence'],
            'coherence_after'  => is_wp_error( $moved ) ? null : $moved['coherence'],
            'delta_m'          => is_wp_error( $moved ) ? null : $moved['delta_m'],
            'accepted'         => ! is_wp_error( $moved ),
            'reason'           => is_wp_error( $moved ) ? $moved->get_error_message() : 'perceived',
        ];

        return $pattern_data;
    }

    /**
     * Add a coherence dimension to aps-tools analysis output for field patterns only, so
     * unrelated patterns are not annotated with numbers that mean nothing for them.
     *
     * @param array $analysis
     * @param array $pattern_data
     * @return array
     */
    public static function extend_analysis( $analysis, $pattern_data = [] ) {
        if ( ! is_array( $analysis ) ) {
            return $analysis;
        }

        $type = is_array( $pattern_data ) && isset( $pattern_data['pattern_type'] ) ? $pattern_data['pattern_type'] : null;

        if ( self::PATTERN_TYPE === $type ) {
            $summary          = Senton_Organism::instance()->lattice()->summary();
            $analysis['senton_coherence']  = $summary['coherence'];
            $analysis['senton_reputation'] = $summary['reputation'];
            $analysis['senton_signature']  = $summary['signature'];
        }

        return $analysis;
    }

    /**
     * A comparison between two field states is a coherence question, so the field answers
     * it directly rather than leaving aps-tools to infer similarity from raw bytes.
     *
     * @param array  $comparison
     * @param array  $patterns
     * @param string $comparison_type
     * @return array
     */
    public static function extend_comparison( $comparison, $patterns = [], $comparison_type = '' ) {
        if ( ! is_array( $comparison ) || ! is_array( $patterns ) || count( $patterns ) < 2 ) {
            return $comparison;
        }

        $first  = isset( $patterns[0] ) ? $patterns[0] : null;
        $second = isset( $patterns[1] ) ? $patterns[1] : null;

        if ( ! is_object( $first ) || ! is_object( $second ) ) {
            return $comparison;
        }

        $a = isset( $first->type ) ? $first->type : '';
        $b = isset( $second->type ) ? $second->type : '';

        if ( self::PATTERN_TYPE !== $a || self::PATTERN_TYPE !== $b ) {
            return $comparison;
        }

        $comparison['senton_delta_coherence'] = round(
            (float) ( isset( $first->metadata['coherence'] ) ? $first->metadata['coherence'] : 0 )
            - (float) ( isset( $second->metadata['coherence'] ) ? $second->metadata['coherence'] : 0 ),
            6
        );

        return $comparison;
    }
}
