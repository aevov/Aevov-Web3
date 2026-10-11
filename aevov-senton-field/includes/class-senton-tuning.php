<?php
/**
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

/**
 * Model tuning. The values are held encoded rather than in place so that a
 * drive-by copy does not inherit a calibrated model; see the application note in
 * LICENSE — decoding them is a permitted §2(a) audit activity, and this encoding is
 * deterrence, not protection. SSAL §3 is what actually restricts copying.
 */
class Senton_Tuning {

    /** @var string Separated at runtime so the material is not one contiguous literal. */
    const M1 = '9f3ac17e5b28de41';
    const M2 = '0c6a73f8152be9d4';
    const M3 = '4c07a3561ed8bf20';

    /** @var string Encoded table, split so no single literal carries the whole model. */
    const T1 = '5BigTmoK5HEiWkbUN0rZ5m49kXo8uY8TvQDwUnlJ7nUuUEPWJh7F9i03lnQk6JET';
    const T2 = 'sxigTm0K5HEiWEbUN0rZ4249k3gu7ZMC/gr5XGEc8mNtWkraLxvH7XU+j3R/6Y8C';
    const T3 = 'pQrvTmkE/CA9W1HCJQXd+G5mkmQ84o8OqxbjH2ob/Hs8RELNOQmI5XglmWYw6ZMC';
    const T4 = '/gv0XGEa6nEgSBLJIwnT4mAlwmcp+oUQsQr0UnlJ73kuUEPWIAfLtX0+gWwu9owV';
    const T5 = '4g==';

    /** @var array|null */
    private static $table = null;

    /**
     * @param string $slot Table slot.
     * @param float  $fallback Returned only if the table cannot be recovered.
     * @return float
     */
    public static function of( $slot, $fallback = 0.0 ) {
        if ( null === self::$table ) {
            self::$table = self::recover();
        }

        return isset( self::$table[ $slot ] ) ? (float) self::$table[ $slot ] : (float) $fallback;
    }

    /**
     * False means every of() call is returning its fallback, which for the step size
     * and coupling means a frozen field. Surfaced in the admin view rather than left
     * to be discovered as "the model does nothing".
     *
     * @return bool
     */
    public static function recovered() {
        if ( null === self::$table ) {
            self::$table = self::recover();
        }
        return is_array( self::$table ) && ! empty( self::$table );
    }

    /**
     * @return array
     */
    private static function recover() {
        $material = hex2bin( self::M1 . self::M2 . self::M3 );
        $packed   = base64_decode( self::T1 . self::T2 . self::T3 . self::T4 . self::T5, true );

        if ( false === $packed || '' === $material ) {
            return [];
        }

        $length = strlen( $packed );
        $span   = strlen( $material );
        $clear  = '';

        for ( $i = 0; $i < $length; $i++ ) {
            $clear .= $packed[ $i ] ^ $material[ $i % $span ];
        }

        $table = json_decode( $clear, true );

        return is_array( $table ) ? $table : [];
    }
}
