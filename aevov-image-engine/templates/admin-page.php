<?php
/**
 * Image Engine Admin Page Template
 *
 * @package AevovImageEngine
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get job manager instance
$job_manager = new \AevovImageEngine\JobManager();
$active_jobs = $job_manager->get_active_jobs(20);
$job_counts = $job_manager->get_job_counts();
$recent_jobs = $job_manager->get_jobs(['limit' => 10]);
?>
<div class="wrap">
    <h1><?php _e('Aevov Image Engine', 'aevov-image-engine'); ?></h1>

    <!-- Job Statistics -->
    <div class="aevov-stats-row" style="display: flex; gap: 20px; margin: 20px 0;">
        <div class="aevov-stat-box" style="background: #fff; padding: 15px 25px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span style="font-size: 24px; font-weight: bold; color: #2271b1;"><?php echo esc_html($job_counts['total']); ?></span>
            <span style="display: block; color: #666;"><?php _e('Total Jobs', 'aevov-image-engine'); ?></span>
        </div>
        <div class="aevov-stat-box" style="background: #fff; padding: 15px 25px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span style="font-size: 24px; font-weight: bold; color: #dba617;"><?php echo esc_html($job_counts['processing']); ?></span>
            <span style="display: block; color: #666;"><?php _e('Processing', 'aevov-image-engine'); ?></span>
        </div>
        <div class="aevov-stat-box" style="background: #fff; padding: 15px 25px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span style="font-size: 24px; font-weight: bold; color: #00a32a;"><?php echo esc_html($job_counts['completed']); ?></span>
            <span style="display: block; color: #666;"><?php _e('Completed', 'aevov-image-engine'); ?></span>
        </div>
        <div class="aevov-stat-box" style="background: #fff; padding: 15px 25px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <span style="font-size: 24px; font-weight: bold; color: #d63638;"><?php echo esc_html($job_counts['failed']); ?></span>
            <span style="display: block; color: #666;"><?php _e('Failed', 'aevov-image-engine'); ?></span>
        </div>
    </div>

    <div id="image-generator">
        <h2><?php _e('Generate Image', 'aevov-image-engine'); ?></h2>
        <form id="image-generator-form">
            <?php wp_nonce_field('aevov_image_generate', 'aevov_image_nonce'); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="prompt"><?php _e('Prompt', 'aevov-image-engine'); ?></label>
                        </th>
                        <td>
                            <textarea id="prompt" name="prompt" rows="5" cols="50" class="large-text"></textarea>
                            <p class="description"><?php _e('Describe the image you want to generate.', 'aevov-image-engine'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="model"><?php _e('Model', 'aevov-image-engine'); ?></label>
                        </th>
                        <td>
                            <select id="model" name="model">
                                <option value="dall-e-3">DALL-E 3</option>
                                <option value="dall-e-2">DALL-E 2</option>
                                <option value="stable-diffusion">Stable Diffusion</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="size"><?php _e('Size', 'aevov-image-engine'); ?></label>
                        </th>
                        <td>
                            <select id="size" name="size">
                                <option value="1024x1024">1024x1024</option>
                                <option value="1792x1024">1792x1024 (Wide)</option>
                                <option value="1024x1792">1024x1792 (Tall)</option>
                                <option value="512x512">512x512</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php _e('Generate', 'aevov-image-engine'); ?></button>
            </p>
        </form>
    </div>

    <div id="image-gallery">
        <h2><?php _e('Generated Images', 'aevov-image-engine'); ?></h2>
        <div id="image-gallery-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
            <?php
            $completed_jobs = $job_manager->get_jobs(['status' => 'completed', 'limit' => 12]);
            if (empty($completed_jobs)) :
            ?>
                <p style="grid-column: 1/-1; color: #666;"><?php _e('No generated images yet.', 'aevov-image-engine'); ?></p>
            <?php else :
                foreach ($completed_jobs as $job) :
                    if (!empty($job->image_url)) :
            ?>
                <div class="image-item" style="background: #fff; border-radius: 5px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <img src="<?php echo esc_url($job->image_url); ?>" alt="" style="width: 100%; height: 150px; object-fit: cover;">
                    <div style="padding: 10px;">
                        <small style="color: #666;"><?php echo esc_html(date('M j, Y g:i a', strtotime($job->created_at))); ?></small>
                    </div>
                </div>
            <?php
                    endif;
                endforeach;
            endif;
            ?>
        </div>
    </div>

    <h2><?php _e('Active Jobs', 'aevov-image-engine'); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Job ID', 'aevov-image-engine'); ?></th>
                <th><?php _e('Status', 'aevov-image-engine'); ?></th>
                <th><?php _e('Created', 'aevov-image-engine'); ?></th>
                <th><?php _e('Actions', 'aevov-image-engine'); ?></th>
            </tr>
        </thead>
        <tbody id="active-jobs-tbody">
            <?php if (empty($active_jobs)) : ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #666;">
                        <?php _e('No active jobs.', 'aevov-image-engine'); ?>
                    </td>
                </tr>
            <?php else :
                foreach ($active_jobs as $job) :
                    $status_class = $job->status === 'processing' ? 'color: #dba617;' : 'color: #2271b1;';
            ?>
                <tr data-job-id="<?php echo esc_attr($job->job_id); ?>">
                    <td><code><?php echo esc_html(substr($job->job_id, 0, 8)); ?>...</code></td>
                    <td><span style="<?php echo $status_class; ?>"><?php echo esc_html(ucfirst($job->status)); ?></span></td>
                    <td><?php echo esc_html(human_time_diff(strtotime($job->created_at), current_time('timestamp')) . ' ago'); ?></td>
                    <td>
                        <button class="button button-small cancel-job" data-job-id="<?php echo esc_attr($job->job_id); ?>">
                            <?php _e('Cancel', 'aevov-image-engine'); ?>
                        </button>
                    </td>
                </tr>
            <?php
                endforeach;
            endif;
            ?>
        </tbody>
    </table>

    <h2><?php _e('Recent Jobs', 'aevov-image-engine'); ?></h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Job ID', 'aevov-image-engine'); ?></th>
                <th><?php _e('Status', 'aevov-image-engine'); ?></th>
                <th><?php _e('Created', 'aevov-image-engine'); ?></th>
                <th><?php _e('Result', 'aevov-image-engine'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recent_jobs)) : ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #666;">
                        <?php _e('No jobs yet.', 'aevov-image-engine'); ?>
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
            ?>
                <tr>
                    <td><code><?php echo esc_html(substr($job->job_id, 0, 8)); ?>...</code></td>
                    <td><span style="color: <?php echo $status_color; ?>;"><?php echo esc_html(ucfirst($job->status)); ?></span></td>
                    <td><?php echo esc_html(date('M j, Y g:i a', strtotime($job->created_at))); ?></td>
                    <td>
                        <?php if ($job->status === 'completed' && !empty($job->image_url)) : ?>
                            <a href="<?php echo esc_url($job->image_url); ?>" target="_blank" class="button button-small">
                                <?php _e('View Image', 'aevov-image-engine'); ?>
                            </a>
                        <?php elseif ($job->status === 'failed') : ?>
                            <span style="color: #d63638;"><?php _e('Failed', 'aevov-image-engine'); ?></span>
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

    <h2><?php _e('Configuration', 'aevov-image-engine'); ?></h2>
    <form method="post" action="options.php">
        <?php
        settings_fields('aevov_image_engine_options');
        do_settings_sections('aevov_image_engine');
        submit_button();
        ?>
    </form>
</div>
