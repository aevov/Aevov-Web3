<?php
require_once BLOOM_PATH . 'admin/templates/partials/header.php';
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields('bloom_settings'); ?>
        <?php do_settings_sections('bloom_settings'); ?>
        <?php submit_button(); ?>
    </form>
</div>

<?php require_once BLOOM_PATH . 'admin/templates/partials/footer.php'; ?>