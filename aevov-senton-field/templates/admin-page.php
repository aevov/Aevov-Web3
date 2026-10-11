<?php
/**
 * Admin view for the Senton Field.
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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$organism = AevovSentonField\Senton_Organism::instance();
$snapshot = $organism->snapshot();
$field    = $snapshot['field'];
$avail    = $snapshot['availability'];
$tuning   = ! empty( $snapshot['tuning'] );
?>
<div class="wrap aevov-senton-field">
    <h1><?php esc_html_e( 'Aevov Senton Field', 'aevov-senton-field' ); ?></h1>
    <p class="aevov-senton-lede">
        <?php esc_html_e( 'A live phase-coupled lattice. Say something to the field and watch its coherence move. The maths is Kuramoto; the organism is not here.', 'aevov-senton-field' ); ?>
    </p>

    <?php if ( ! $tuning ) : ?>
        <div class="notice notice-error inline">
            <p>
                <?php esc_html_e( 'The model tuning table could not be recovered, so the field is running with zero step size and zero coupling: it will not move. This means the plugin files were truncated, edited, or split apart during install — not that the lattice is idle.', 'aevov-senton-field' ); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="aevov-senton-grid">
        <section class="aevov-senton-card aevov-senton-canvas-card">
            <h2><?php esc_html_e( 'The field', 'aevov-senton-field' ); ?></h2>
            <canvas id="aevov-senton-canvas" width="520" height="520" aria-hidden="true"></canvas>
            <p class="aevov-senton-caption">
                <?php
                printf(
                    /* translators: 1: node count, 2: interactions per cycle. */
                    esc_html__( '%1$s oscillators, %2$s coupling interactions per cycle.', 'aevov-senton-field' ),
                    esc_html( number_format( (int) $field['nodes'] ) ),
                    esc_html( number_format( (int) $field['interactions'] ) )
                );
                ?>
            </p>
        </section>

        <section class="aevov-senton-card">
            <h2><?php esc_html_e( 'State', 'aevov-senton-field' ); ?></h2>
            <table class="aevov-senton-stats">
                <tr><th><?php esc_html_e( 'Cycle', 'aevov-senton-field' ); ?></th><td id="sf-tick"><?php echo esc_html( $field['tick'] ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Coherence R', 'aevov-senton-field' ); ?></th><td id="sf-coherence"><?php echo esc_html( $field['coherence'] ); ?></td></tr>
                <tr><th><?php esc_html_e( 'delta-m', 'aevov-senton-field' ); ?></th><td id="sf-delta"><?php echo esc_html( $field['delta_m'] ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Reputation', 'aevov-senton-field' ); ?></th><td id="sf-reputation"><?php echo esc_html( $field['reputation'] ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Signature', 'aevov-senton-field' ); ?></th><td id="sf-signature" class="aevov-senton-mono"><?php echo esc_html( $field['signature'] ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Peers', 'aevov-senton-field' ); ?></th><td id="sf-peers"><?php echo esc_html( $snapshot['peer_count'] ); ?></td></tr>
            </table>

            <canvas id="aevov-senton-spark" width="360" height="72" aria-hidden="true"></canvas>
        </section>

        <section class="aevov-senton-card">
            <h2><?php esc_html_e( 'Perceive into the field', 'aevov-senton-field' ); ?></h2>
            <form id="aevov-senton-perceive">
                <p>
                    <label for="sf-input"><?php esc_html_e( 'Statement', 'aevov-senton-field' ); ?></label>
                    <textarea id="sf-input" rows="3" placeholder="<?php esc_attr_e( 'Type anything. The field responds to how much it says, not what it says.', 'aevov-senton-field' ); ?>"></textarea>
                </p>
                <p>
                    <label for="sf-salience"><?php esc_html_e( 'Salience (optional, 0-1)', 'aevov-senton-field' ); ?></label>
                    <input type="number" id="sf-salience" step="0.05" min="0" max="1" placeholder="<?php esc_attr_e( 'auto', 'aevov-senton-field' ); ?>" />
                </p>
                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Perceive', 'aevov-senton-field' ); ?></button>
                    <button type="button" class="button" id="sf-step"><?php esc_html_e( 'Step 16 cycles', 'aevov-senton-field' ); ?></button>
                    <button type="button" class="button" id="sf-announce"><?php esc_html_e( 'Announce to mesh', 'aevov-senton-field' ); ?></button>
                    <button type="button" class="button-link" id="sf-reset"><?php esc_html_e( 'Reset field', 'aevov-senton-field' ); ?></button>
                </p>
            </form>
            <pre id="sf-out" class="aevov-senton-out"></pre>
        </section>

        <section class="aevov-senton-card">
            <h2><?php esc_html_e( 'Pattern spine — the core three', 'aevov-senton-field' ); ?></h2>
            <?php
            $spine = [
                'aps'       => 'AevovPatternSyncProtocol',
                'bloom'     => 'bloom-pattern-recognition',
                'aps_tools' => 'aps-tools',
                'ledger'    => __( 'chunk ledger', 'aevov-senton-field' ),
            ];
            $engines = [
                'core'    => 'aevov-core',
                'ai'      => 'aevov-ai-core',
                'mesh'    => 'aevov-meshcore',
                'memory'  => 'aevov-memory-core',
                'network' => __( 'mesh loaded', 'aevov-senton-field' ),
            ];
            ?>
            <ul class="aevov-senton-badges">
                <?php foreach ( $spine as $key => $label ) : ?>
                    <li class="<?php echo ! empty( $avail[ $key ] ) ? 'is-on' : 'is-off'; ?>">
                        <span class="aevov-senton-dot"></span><?php echo esc_html( $label ); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <h3 class="aevov-senton-subhead"><?php esc_html_e( 'Optional engines', 'aevov-senton-field' ); ?></h3>
            <ul class="aevov-senton-badges">
                <?php foreach ( $engines as $key => $label ) : ?>
                    <li class="<?php echo ! empty( $avail[ $key ] ) ? 'is-on' : 'is-off'; ?>">
                        <span class="aevov-senton-dot"></span><?php echo esc_html( $label ); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="aevov-senton-caption">
                <?php esc_html_e( 'With nothing present the field still runs, still responds, and still scores salience locally. With APS present it becomes a pattern source; the ledger only ever sees a coarse shape, never the lattice.', 'aevov-senton-field' ); ?>
            </p>
        </section>

        <section class="aevov-senton-card aevov-senton-boundary">
            <h2><?php echo esc_html( $snapshot['boundary']['title'] ); ?></h2>
            <p><?php echo esc_html( $snapshot['boundary']['summary'] ); ?></p>
            <div class="aevov-senton-cols">
                <div>
                    <h3><?php esc_html_e( 'Deliberately absent', 'aevov-senton-field' ); ?></h3>
                    <ul>
                        <?php foreach ( $snapshot['boundary']['absent'] as $line ) : ?>
                            <li><?php echo esc_html( $line ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h3><?php esc_html_e( 'Present and running', 'aevov-senton-field' ); ?></h3>
                    <ul>
                        <?php foreach ( $snapshot['boundary']['present'] as $line ) : ?>
                            <li><?php echo esc_html( $line ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </section>

        <section class="aevov-senton-card">
            <h2><?php esc_html_e( 'Recent field events', 'aevov-senton-field' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Cycle', 'aevov-senton-field' ); ?></th>
                        <th><?php esc_html_e( 'Kind', 'aevov-senton-field' ); ?></th>
                        <th><?php esc_html_e( 'Salience', 'aevov-senton-field' ); ?></th>
                        <th><?php esc_html_e( 'From', 'aevov-senton-field' ); ?></th>
                        <th><?php esc_html_e( 'delta-m', 'aevov-senton-field' ); ?></th>
                        <th><?php esc_html_e( 'Input digest', 'aevov-senton-field' ); ?></th>
                    </tr>
                </thead>
                <tbody id="sf-log">
                    <?php foreach ( $snapshot['log'] as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( isset( $row['tick'] ) ? $row['tick'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $row['kind'] ) ? $row['kind'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $row['salience'] ) ? $row['salience'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $row['salience_from'] ) ? $row['salience_from'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $row['delta_m'] ) ? $row['delta_m'] : '' ); ?></td>
                            <td class="aevov-senton-mono"><?php echo esc_html( isset( $row['digest'] ) ? $row['digest'] : '—' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="aevov-senton-caption">
                <?php esc_html_e( 'Inputs are stored as a hash prefix. What you type is not kept.', 'aevov-senton-field' ); ?>
            </p>
        </section>
    </div>
</div>
