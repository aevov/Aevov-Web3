<?php

namespace Aevov\Features;

class PatternOfTheDay
{
    /**
     * The pattern DB.
     *
     * @var \APS\DB\APS_Pattern_DB
     */
    private $pattern_db;

    /**
     * Constructor.
     *
     * @param \APS\DB\APS_Pattern_DB $pattern_db
     */
    public function __construct(\APS\DB\APS_Pattern_DB $pattern_db)
    {
        $this->pattern_db = $pattern_db;
        add_action('init', [$this, 'register_shortcode']);
    }

    /**
     * Registers the shortcode.
     */
    public function register_shortcode()
    {
        add_shortcode('pattern_of_the_day', [$this, 'render_shortcode']);
    }

    /**
     * Renders the shortcode.
     *
     * @return string
     */
    public function render_shortcode()
    {
        $pattern = $this->get_pattern_of_the_day();

        if (!$pattern) {
            return '';
        }

        ob_start();
        ?>
        <div class="pattern-of-the-day">
            <h2><?php _e('Pattern of the Day', 'aps'); ?></h2>
            <h3><?php echo esc_html($pattern['pattern_hash']); ?></h3>
            <pre><?php echo esc_html(print_r($pattern['pattern_data'], true)); ?></pre>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Sets the pattern of the day.
     */
    public function set_pattern_of_the_day()
    {
        $pattern = $this->get_random_pattern();
        if ($pattern) {
            update_option('pattern_of_the_day', $pattern);
        }
    }

    /**
     * Gets the pattern of the day.
     *
     * @return array|null
     */
    public function get_pattern_of_the_day()
    {
        return get_option('pattern_of_the_day');
    }

    /**
     * Gets a random pattern from the database.
     *
     * @return array|null
     */
    private function get_random_pattern()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aps_patterns';
        $pattern = $wpdb->get_row("SELECT * FROM {$table_name} ORDER BY RAND() LIMIT 1", ARRAY_A);

        if ($pattern) {
            $pattern['pattern_data'] = json_decode($pattern['pattern_data'], true);
        }

        return $pattern;
    }
}
