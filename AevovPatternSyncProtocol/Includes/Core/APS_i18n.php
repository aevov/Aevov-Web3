<?php

/**
 * includes/core/class-aps-i18n.php
 */
class APS_i18n {
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'aps',
            false,
            dirname(dirname(plugin_basename(APS_FILE))) . '/languages/'
        );
    }
}