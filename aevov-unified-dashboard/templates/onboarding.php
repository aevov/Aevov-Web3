<?php
/**
 * Onboarding Template
 *
 * @package AevovUnifiedDashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_complete = get_option('aud_onboarding_complete', false);
$current_step = get_option('aud_current_onboarding_step', 'welcome');

$steps = [
    'welcome' => '👋',
    'system_check' => '✅',
    'architecture' => '🏛️',
    'pattern_creation' => '✨',
    'exploration' => '🚀',
    'completion' => '🎯'
];
?>

<div class="aud-onboarding-page">
    <div class="aud-dashboard-wrap">
        <!-- Progress Indicators -->
        <div class="aud-onboarding-progress">
            <?php foreach ($steps as $step_key => $icon) : ?>
                <div class="aud-onboarding-step <?php echo ($step_key === $current_step) ? 'active' : ''; ?>"
                     data-step="<?php echo esc_attr($step_key); ?>">
                    <div class="aud-step-circle"><?php echo $icon; ?></div>
                    <div class="aud-step-label"><?php echo ucwords(str_replace('_', ' ', $step_key)); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Onboarding Content -->
        <div class="aud-onboarding">
            <div class="aud-onboarding-content">
                <!-- Content will be dynamically loaded by JavaScript -->
                <span class="aud-onboarding-icon">👋</span>
                <h1 class="aud-onboarding-title">Welcome to Aevov</h1>
                <h2 class="aud-onboarding-subtitle">Your Neurosymbolic Intelligence Platform</h2>
                <p class="aud-onboarding-description">
                    Get started with the most comprehensive AI ecosystem featuring 29 powerful plugins working in harmony.
                </p>
                <p class="aud-onboarding-duration">⏱️ Estimated time: 12 minutes</p>
                <div class="aud-onboarding-actions">
                    <button class="aud-button aud-button-secondary aud-onboarding-skip">
                        Skip Tour
                    </button>
                    <button class="aud-button aud-button-primary aud-button-large aud-onboarding-next">
                        Let's Get Started →
                    </button>
                </div>
            </div>
        </div>

        <?php if ($is_complete) : ?>
            <div class="aud-alert aud-alert-success" style="max-width: 800px; margin: 32px auto;">
                <span style="font-size: 1.5rem;">🎉</span>
                <div>
                    <strong>Onboarding Complete!</strong>
                    <p style="margin: 8px 0 0 0;">
                        You've successfully completed the onboarding tour.
                        <a href="admin.php?page=aevov-unified-dashboard" style="color: inherit; text-decoration: underline;">
                            Go to Dashboard
                        </a>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
