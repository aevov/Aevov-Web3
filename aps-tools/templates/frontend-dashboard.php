<?php
/* Template Name: APS Tools Dashboard */
get_header();
?>
<div id="aps-dashboard">
    <h1><?php _e('APS Tools Dashboard', 'aps-tools'); ?></h1>
    
    <div id="aps-analysis-section">
        <h2><?php _e('Pattern Analysis', 'aps-tools'); ?></h2>
        <?php echo do_shortcode('[aps_analysis_form]'); ?>
    </div>
    
    <div id="aps-comparison-section">  
        <h2><?php _e('Pattern Comparison', 'aps-tools'); ?></h2>
        <?php echo do_shortcode('[aps_comparison_form]'); ?>
    </div>
</div>
<?php
get_footer();