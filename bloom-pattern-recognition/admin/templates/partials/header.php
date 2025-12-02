

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class('bloom-admin'); ?>>
    <div class="bloom-header">
        <div class="bloom-brand">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        </div>
        <div class="bloom-nav">
            <nav class="bloom-main-nav">
                <?php
                $current = $_GET['page'] ?? '';
                $menu_items = [
                    'bloom-dashboard' => __('Dashboard', 'bloom-pattern-system'),
                    'bloom-patterns' => __('Patterns', 'bloom-pattern-system'),
                    'bloom-monitor' => __('Monitor', 'bloom-pattern-system'),
                    'bloom-settings' => __('Settings', 'bloom-pattern-system')
                ];
                foreach ($menu_items as $slug => $label):
                    $active = $current === $slug ? 'active' : '';
                ?>
                <a href="?page=<?php echo esc_attr($slug); ?>" 
                   class="nav-item <?php echo esc_attr($active); ?>">
                    <?php echo esc_html($label); ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
    <div class="bloom-wrap">
