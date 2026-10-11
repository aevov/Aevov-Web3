<?php
/**
 * REST surface for the field.
 *
 * Reads are open by design — the point of this plugin is that someone can stand in
 * front of a live lattice. Writes are gated through the house permission helper.
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

class Senton_Rest_Api {

    const REST_NAMESPACE = 'aevov-senton/v1';

    public static function register_routes() {
        $read   = [ 'methods' => 'GET', 'permission_callback' => '__return_true' ];
        $write  = [ 'methods' => 'POST', 'permission_callback' => [ __CLASS__, 'can_write' ] ];

        register_rest_route( self::REST_NAMESPACE, '/field', $read + [ 'callback' => [ __CLASS__, 'read_field' ] ] );

        register_rest_route( self::REST_NAMESPACE, '/boundary', $read + [ 'callback' => [ __CLASS__, 'read_boundary' ] ] );

        register_rest_route( self::REST_NAMESPACE, '/perceive', $write + [
            'callback' => [ __CLASS__, 'perceive' ],
            'args'     => [
                'input'    => [ 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'salience' => [ 'type' => 'number', 'required' => false ],
            ],
        ] );

        register_rest_route( self::REST_NAMESPACE, '/step', $write + [
            'callback' => [ __CLASS__, 'step' ],
            'args'     => [
                'ticks' => [ 'type' => 'integer', 'required' => false, 'default' => 4, 'minimum' => 1, 'maximum' => 512 ],
            ],
        ] );

        register_rest_route( self::REST_NAMESPACE, '/announce', $write + [ 'callback' => [ __CLASS__, 'announce' ] ] );

        register_rest_route( self::REST_NAMESPACE, '/reset', $write + [ 'callback' => [ __CLASS__, 'reset' ] ] );
    }

    /**
     * Delegates to the house helper when aevov-security is present, so the plugin does
     * not invent a second definition of "allowed to edit Aevov things".
     *
     * @return bool
     */
    public static function can_write() {
        if ( class_exists( 'Aevov\Security\SecurityHelper' ) && method_exists( 'Aevov\Security\SecurityHelper', 'can_edit_aevov' ) ) {
            return (bool) \Aevov\Security\SecurityHelper::can_edit_aevov();
        }

        return current_user_can( 'manage_options' );
    }

    public static function read_field() {
        return rest_ensure_response( Senton_Organism::instance()->snapshot() );
    }

    public static function read_boundary() {
        return rest_ensure_response( Senton_Workflow::boundary() );
    }

    public static function perceive( $request ) {
        $salience = $request->get_param( 'salience' );
        $event    = Senton_Organism::instance()->perceive(
            (string) $request->get_param( 'input' ),
            null === $salience ? null : (float) $salience
        );

        if ( is_wp_error( $event ) ) {
            return $event;
        }

        return rest_ensure_response( $event );
    }

    public static function step( $request ) {
        $summary = Senton_Organism::instance()->step( (int) $request->get_param( 'ticks' ) );
        return rest_ensure_response( $summary );
    }

    public static function announce() {
        return rest_ensure_response( Senton_Organism::instance()->announce() );
    }

    public static function reset() {
        return rest_ensure_response( Senton_Organism::instance()->reset() );
    }
}
