<?php
/**
 * Admin Connections Template
 *
 * @package AevovMeshcore
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php echo esc_html__('Active Connections', 'aevov-meshcore'); ?></h1>
    <p><?php echo esc_html__('Currently active peer-to-peer connections.', 'aevov-meshcore'); ?></p>

    <div id="meshcore-connections-list">
        <p><?php echo esc_html__('Loading connections...', 'aevov-meshcore'); ?></p>
    </div>
</div>
