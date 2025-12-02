<?php
/**
 * Music Forge Admin Page Template
 *
 * @package AevovMusicForge
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get job manager instance
$job_manager = new \AevovMusicForge\JobManager();
$active_jobs = $job_manager->get_active_jobs(20);
$job_counts = $job_manager->get_job_counts();
$recent_jobs = $job_manager->get_jobs(['limit' => 10]);
?>
<div class="wrap">
    <h1><?php _e('Aevov Music Forge', 'aevov-music-forge'); ?></h1>

    <!-- Job Statistics -->
    <div class="aevov-stats-row" style="display: flex; gap: 20px; margin: 20px 0;">
        <div class="aevov-stat-box" style="background: #fff; padding: 15px 25px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span style="font-size: 24px; font-weight: bold; color: #2271b1;"><?php echo esc_html($job_counts['total']); ?></span>
            <span style="display: block; color: #666;"><?php _e('Total Tracks', 'aevov-music-forge'); ?></span>
        </div>
        <div class="aevov-stat-box" style="background: #fff; padding: 15px 25px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span style="font-size: 24px; font-weight: bold; color: #dba617;"><?php echo esc_html($job_counts['processing']); ?></span>
            <span style="display: block; color: #666;"><?php _e('Composing', 'aevov-music-forge'); ?></span>
        </div>
        <div class="aevov-stat-box" style="background: #fff; padding: 15px 25px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span style="font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo esc_html($job_counts['completed']); ?></span>
            <span style="display: block; color: #666;"><?php _e('Completed', 'aevov-music-forge'); ?></span>
        </div>
        <div class="aevov-stat-box" style="background: #fff; padding: 15px 25px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span style="font-size: 24px; font-weight: bold; color: #d63638;"><?php echo esc_html($job_counts['failed']); ?></span>
            <span style="display: block; color: #666;"><?php _e('Failed', 'aevov-music-forge'); ?></span>
        </div>
    </div>

    <div id="music-composer">
        <h2><?php _e('Compose Music', 'aevov-music-forge'); ?></h2>
        <form id="music-composer-form">
            <?php wp_nonce_field('aevov_music_compose', 'aevov_music_nonce'); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="prompt"><?php _e('Description', 'aevov-music-forge'); ?></label>
                        </th>
                        <td>
                            <textarea id="prompt" name="prompt" rows="3" cols="50" class="large-text" placeholder="<?php esc_attr_e('Describe the music you want to create...', 'aevov-music-forge'); ?>"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="genre"><?php _e('Genre', 'aevov-music-forge'); ?></label>
                        </th>
                        <td>
                            <select id="genre" name="genre">
                                <option value="rock"><?php _e('Rock', 'aevov-music-forge'); ?></option>
                                <option value="pop"><?php _e('Pop', 'aevov-music-forge'); ?></option>
                                <option value="jazz"><?php _e('Jazz', 'aevov-music-forge'); ?></option>
                                <option value="classical"><?php _e('Classical', 'aevov-music-forge'); ?></option>
                                <option value="electronic"><?php _e('Electronic', 'aevov-music-forge'); ?></option>
                                <option value="ambient"><?php _e('Ambient', 'aevov-music-forge'); ?></option>
                                <option value="hip-hop"><?php _e('Hip-Hop', 'aevov-music-forge'); ?></option>
                                <option value="cinematic"><?php _e('Cinematic', 'aevov-music-forge'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mood"><?php _e('Mood', 'aevov-music-forge'); ?></label>
                        </th>
                        <td>
                            <select id="mood" name="mood">
                                <option value="happy"><?php _e('Happy', 'aevov-music-forge'); ?></option>
                                <option value="sad"><?php _e('Sad', 'aevov-music-forge'); ?></option>
                                <option value="energetic"><?php _e('Energetic', 'aevov-music-forge'); ?></option>
                                <option value="calm"><?php _e('Calm', 'aevov-music-forge'); ?></option>
                                <option value="dramatic"><?php _e('Dramatic', 'aevov-music-forge'); ?></option>
                                <option value="mysterious"><?php _e('Mysterious', 'aevov-music-forge'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="duration"><?php _e('Duration', 'aevov-music-forge'); ?></label>
                        </th>
                        <td>
                            <select id="duration" name="duration">
                                <option value="30"><?php _e('30 seconds', 'aevov-music-forge'); ?></option>
                                <option value="60" selected><?php _e('1 minute', 'aevov-music-forge'); ?></option>
                                <option value="120"><?php _e('2 minutes', 'aevov-music-forge'); ?></option>
                                <option value="180"><?php _e('3 minutes', 'aevov-music-forge'); ?></option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e('Compose', 'aevov-music-forge'); ?></button>
            </p>
        </form>
    </div>

    <div id="music-player">
        <h2><?php _e('Generated Music', 'aevov-music-forge'); ?></h2>
        <div id="music-player-container">
            <?php
            $completed_jobs = $job_manager->get_jobs(['status' => 'completed', 'limit' => 6]);
            if (empty($completed_jobs)) :
            ?>
                <p style="color: #666;"><?php _e('No generated tracks yet.', 'aevov-music-forge'); ?></p>
            <?php else :
                foreach ($completed_jobs as $job) :
                    if (!empty($job->track_url)) :
                        $params = $job->params ?? [];
            ?>
                <div class="music-track" style="background: #fff; padding: 15px; margin-bottom: 15px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-weight: 500;">
                            <?php echo esc_html(ucfirst($params['genre'] ?? 'Music')); ?> - <?php echo esc_html(ucfirst($params['mood'] ?? 'Track')); ?>
                        </span>
                        <small style="color: #666;"><?php echo esc_html(date('M j, Y', strtotime($job->created_at))); ?></small>
                    </div>
                    <audio controls style="width: 100%;">
                        <source src="<?php echo esc_url($job->track_url); ?>" type="audio/mpeg">
                        <?php _e('Your browser does not support the audio element.', 'aevov-music-forge'); ?>
                    </audio>
                </div>
            <?php
                    endif;
                endforeach;
            endif;
            ?>
        </div>
    </div>

    <h2><?php _e('Active Jobs', 'aevov-music-forge'); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Job ID', 'aevov-music-forge'); ?></th>
                <th><?php _e('Genre', 'aevov-music-forge'); ?></th>
                <th><?php _e('Status', 'aevov-music-forge'); ?></th>
                <th><?php _e('Created', 'aevov-music-forge'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($active_jobs)) : ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #666;">
                        <?php _e('No active jobs.', 'aevov-music-forge'); ?>
                    </td>
                </tr>
            <?php else :
                foreach ($active_jobs as $job) :
                    $status_class = $job->status === 'processing' ? 'color: #dba617;' : 'color: #2271b1;';
                    $params = $job->params ?? [];
            ?>
                <tr data-job-id="<?php echo esc_attr($job->job_id); ?>">
                    <td><code><?php echo esc_html(substr($job->job_id, 0, 8)); ?>...</code></td>
                    <td><?php echo esc_html(ucfirst($params['genre'] ?? '-')); ?></td>
                    <td><span style="<?php echo $status_class; ?>"><?php echo esc_html(ucfirst($job->status)); ?></span></td>
                    <td><?php echo esc_html(human_time_diff(strtotime($job->created_at), current_time('timestamp')) . ' ago'); ?></td>
                </tr>
            <?php
                endforeach;
            endif;
            ?>
        </tbody>
    </table>

    <h2><?php _e('Recent Jobs', 'aevov-music-forge'); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Job ID', 'aevov-music-forge'); ?></th>
                <th><?php _e('Genre', 'aevov-music-forge'); ?></th>
                <th><?php _e('Status', 'aevov-music-forge'); ?></th>
                <th><?php _e('Created', 'aevov-music-forge'); ?></th>
                <th><?php _e('Result', 'aevov-music-forge'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recent_jobs)) : ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #666;">
                        <?php _e('No jobs yet.', 'aevov-music-forge'); ?>
                    </td>
                </tr>
            <?php else :
                foreach ($recent_jobs as $job) :
                    $status_colors = [
                        'queued' => '#2271b1',
                        'processing' => '#dba617',
                        'completed' => '#00a32a',
                        'failed' => '#d63638'
                    ];
                    $status_color = $status_colors[$job->status] ?? '#666';
                    $params = $job->params ?? [];
            ?>
                <tr>
                    <td><code><?php echo esc_html(substr($job->job_id, 0, 8)); ?>...</code></td>
                    <td><?php echo esc_html(ucfirst($params['genre'] ?? '-')); ?></td>
                    <td><span style="color: <?php echo $status_color; ?>;"><?php echo esc_html(ucfirst($job->status)); ?></span></td>
                    <td><?php echo esc_html(date('M j, Y g:i a', strtotime($job->created_at))); ?></td>
                    <td>
                        <?php if ($job->status === 'completed' && !empty($job->track_url)) : ?>
                            <a href="<?php echo esc_url($job->track_url); ?>" target="_blank" class="button button-small">
                                <?php _e('Play', 'aevov-music-forge'); ?>
                            </a>
                        <?php elseif ($job->status === 'failed') : ?>
                            <span style="color: #d63638;"><?php _e('Failed', 'aevov-music-forge'); ?></span>
                        <?php else : ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php
                endforeach;
            endif;
            ?>
        </tbody>
    </table>

    <h2><?php _e('Configuration', 'aevov-music-forge'); ?></h2>
    <form method="post" action="options.php">
        <?php
        settings_fields('aevov_music_forge_options');
        do_settings_sections('aevov_music_forge');
        submit_button();
        ?>
    </form>
</div>
