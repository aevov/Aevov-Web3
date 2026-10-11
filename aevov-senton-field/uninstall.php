<?php
/**
 * Removes every trace of the field.
 *
 * Nothing here is archived off-site: the lattice state is the only thing this plugin
 * keeps, and it is the least valuable thing in the ecosystem.
 *
 * @package AevovSentonField
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
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$aevov_senton_field_options = [
    'aevov_senton_field_state',
    'aevov_senton_field_log',
    'aevov_senton_field_node',
    'aevov_senton_field_peer_board',
    'aevov_senton_field_known_peers',
    'aevov_senton_field_coupling',
    'aevov_senton_field_dt',
    'aevov_senton_field_nodes',
    'aevov_senton_field_publish_every',
];

foreach ( $aevov_senton_field_options as $aevov_senton_field_option ) {
    delete_option( $aevov_senton_field_option );
}

wp_clear_scheduled_hook( 'aevov_senton_field_tick' );
