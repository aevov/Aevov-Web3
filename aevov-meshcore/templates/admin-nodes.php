<?php
/**
 * Admin Nodes Template
 *
 * @package AevovMeshcore
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php echo esc_html__('Mesh Nodes', 'aevov-meshcore'); ?></h1>
    <p><?php echo esc_html__('Discovered and connected mesh nodes in the network.', 'aevov-meshcore'); ?></p>

    <div id="meshcore-nodes-list">
        <p><?php echo esc_html__('Loading nodes...', 'aevov-meshcore'); ?></p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load nodes via API
    fetch('<?php echo rest_url('aevov-meshcore/v1/peers?limit=100'); ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.peers) {
                displayNodes(data.peers);
            }
        });

    function displayNodes(peers) {
        const container = $('#meshcore-nodes-list');
        if (peers.length === 0) {
            container.html('<p>No nodes discovered yet.</p>');
            return;
        }

        let html = '<table class="widefat striped"><thead><tr>';
        html += '<th>Node ID</th><th>Status</th><th>Reputation</th><th>Last Seen</th>';
        html += '</tr></thead><tbody>';

        peers.forEach(peer => {
            html += `<tr>
                <td><code>${peer.node_id.substring(0, 16)}...</code></td>
                <td>${peer.status}</td>
                <td>${peer.reputation_score}</td>
                <td>${peer.last_seen}</td>
            </tr>`;
        });

        html += '</tbody></table>';
        container.html(html);
    }
});
</script>
