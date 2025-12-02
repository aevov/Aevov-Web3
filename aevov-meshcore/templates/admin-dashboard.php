<?php
/**
 * Admin Dashboard Template
 *
 * @package AevovMeshcore
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$plugin = \Aevov\Meshcore\MeshcorePlugin::get_instance();
$node_manager = $plugin->get_node_manager();
$connection_manager = $plugin->get_connection_manager();
$mesh_router = $plugin->get_mesh_router();
$relay_manager = $plugin->get_relay_manager();

$node_info = $node_manager->get_node_info();
$connections = $connection_manager->get_connections();
$routing_stats = $mesh_router->get_stats();
$relay_stats = $relay_manager->get_stats();
?>

<div class="wrap">
    <h1><?php echo esc_html__('Meshcore Network Dashboard', 'aevov-meshcore'); ?></h1>

    <div class="meshcore-dashboard">
        <!-- Node Information -->
        <div class="meshcore-card">
            <h2><?php echo esc_html__('Node Information', 'aevov-meshcore'); ?></h2>
            <table class="widefat">
                <tr>
                    <th><?php echo esc_html__('Node ID', 'aevov-meshcore'); ?></th>
                    <td><code><?php echo esc_html($node_info['node_id']); ?></code></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Status', 'aevov-meshcore'); ?></th>
                    <td><span class="status-active"><?php echo esc_html__('Active', 'aevov-meshcore'); ?></span></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Reputation', 'aevov-meshcore'); ?></th>
                    <td><?php echo esc_html($node_info['status']['reputation']); ?>%</td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Uptime', 'aevov-meshcore'); ?></th>
                    <td><?php echo esc_html(round($node_info['status']['uptime'], 2)); ?>%</td>
                </tr>
            </table>
        </div>

        <!-- Connection Statistics -->
        <div class="meshcore-card">
            <h2><?php echo esc_html__('Connections', 'aevov-meshcore'); ?></h2>
            <table class="widefat">
                <tr>
                    <th><?php echo esc_html__('Active Connections', 'aevov-meshcore'); ?></th>
                    <td><strong><?php echo count($connections); ?></strong></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Average Quality', 'aevov-meshcore'); ?></th>
                    <td>
                        <?php
                        $avg_quality = 0;
                        foreach ($connections as $conn) {
                            $avg_quality += (float) $conn['quality_score'];
                        }
                        $avg_quality = count($connections) > 0 ? $avg_quality / count($connections) : 0;
                        echo esc_html(round($avg_quality * 100, 2));
                        ?>%
                    </td>
                </tr>
            </table>
        </div>

        <!-- Routing Statistics -->
        <div class="meshcore-card">
            <h2><?php echo esc_html__('Routing', 'aevov-meshcore'); ?></h2>
            <table class="widefat">
                <tr>
                    <th><?php echo esc_html__('Known Routes', 'aevov-meshcore'); ?></th>
                    <td><?php echo esc_html($routing_stats['total_routes']); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Average Hop Count', 'aevov-meshcore'); ?></th>
                    <td><?php echo esc_html($routing_stats['average_hop_count']); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Average Path Quality', 'aevov-meshcore'); ?></th>
                    <td><?php echo esc_html(round($routing_stats['average_path_quality'] * 100, 2)); ?>%</td>
                </tr>
            </table>
        </div>

        <!-- Relay & Tokens -->
        <div class="meshcore-card">
            <h2><?php echo esc_html__('Bandwidth Sharing', 'aevov-meshcore'); ?></h2>
            <table class="widefat">
                <tr>
                    <th><?php echo esc_html__('Bytes Relayed', 'aevov-meshcore'); ?></th>
                    <td><?php echo esc_html(size_format($relay_stats['total_bytes_relayed'])); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Tokens Earned', 'aevov-meshcore'); ?></th>
                    <td><strong><?php echo esc_html(number_format($relay_stats['tokens_earned'])); ?></strong></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Tokens Spent', 'aevov-meshcore'); ?></th>
                    <td><?php echo esc_html(number_format($relay_stats['tokens_spent'])); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Token Balance', 'aevov-meshcore'); ?></th>
                    <td><strong style="color: green;"><?php echo esc_html(number_format($relay_stats['tokens_balance'])); ?></strong></td>
                </tr>
            </table>
        </div>

        <!-- Active Connections List -->
        <div class="meshcore-card full-width">
            <h2><?php echo esc_html__('Active Connections', 'aevov-meshcore'); ?></h2>
            <?php if (empty($connections)): ?>
                <p><?php echo esc_html__('No active connections', 'aevov-meshcore'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Remote Node', 'aevov-meshcore'); ?></th>
                            <th><?php echo esc_html__('Quality', 'aevov-meshcore'); ?></th>
                            <th><?php echo esc_html__('Latency', 'aevov-meshcore'); ?></th>
                            <th><?php echo esc_html__('Upload', 'aevov-meshcore'); ?></th>
                            <th><?php echo esc_html__('Download', 'aevov-meshcore'); ?></th>
                            <th><?php echo esc_html__('Established', 'aevov-meshcore'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($connections as $conn): ?>
                            <tr>
                                <td><code><?php echo esc_html(substr($conn['remote_node_id'], 0, 12)); ?>...</code></td>
                                <td><?php echo esc_html(round((float) $conn['quality_score'] * 100, 2)); ?>%</td>
                                <td><?php echo esc_html($conn['latency']); ?> ms</td>
                                <td><?php echo esc_html(size_format($conn['bandwidth_up'])); ?>/s</td>
                                <td><?php echo esc_html(size_format($conn['bandwidth_down'])); ?>/s</td>
                                <td><?php echo esc_html(human_time_diff(strtotime($conn['established_at']))); ?> ago</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.meshcore-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.meshcore-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.meshcore-card.full-width {
    grid-column: 1 / -1;
}

.meshcore-card h2 {
    margin-top: 0;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    color: #555;
}

.meshcore-card table {
    margin-top: 15px;
}

.meshcore-card table th {
    text-align: left;
    padding: 8px;
    font-weight: 600;
}

.meshcore-card table td {
    padding: 8px;
}

.status-active {
    color: #46b450;
    font-weight: 600;
}
</style>
