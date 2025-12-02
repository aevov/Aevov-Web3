<?php
/**
 * Integration Manager
 *
 * Coordinates integrations with APS, Bloom, and APS Tools
 *
 * @package AevovVisionDepth\Integrations
 * @since 1.0.0
 */

namespace AevovVisionDepth\Integrations;

class Integration_Manager {

    private $aps_integration;
    private $bloom_integration;
    private $aps_tools_integration;

    public function init() {
        // Initialize APS integration
        if (class_exists('\\APS\\Core\\APS_Core') || class_exists('APS_Pattern_DB')) {
            $this->aps_integration = new APS_Integration();
            $this->aps_integration->init();
        }

        // Initialize Bloom integration
        if (class_exists('\\BLOOM_Pattern_System') || class_exists('BLOOM\\Models\\Pattern_Model')) {
            $this->bloom_integration = new Bloom_Integration();
            $this->bloom_integration->init();
        }

        // Initialize APS Tools integration
        if (class_exists('\\APSTools\\APSTools')) {
            $this->aps_tools_integration = new APS_Tools_Integration();
            $this->aps_tools_integration->init();
        }
    }

    public function render_settings_page() {
        $status = [
            'aps' => class_exists('\\APS\\Core\\APS_Core'),
            'bloom' => class_exists('\\BLOOM_Pattern_System'),
            'aps_tools' => class_exists('\\APSTools\\APSTools'),
        ];

        include AVD_PATH . 'templates/integrations-settings.php';
    }

    public function get_integration_status() {
        return [
            'aps' => [
                'available' => class_exists('\\APS\\Core\\APS_Core'),
                'enabled' => get_option('avd_enable_aps_integration', true),
                'patterns_synced' => $this->aps_integration ? $this->aps_integration->get_synced_count() : 0,
            ],
            'bloom' => [
                'available' => class_exists('\\BLOOM_Pattern_System'),
                'enabled' => get_option('avd_enable_bloom_integration', true),
                'patterns_analyzed' => $this->bloom_integration ? $this->bloom_integration->get_analyzed_count() : 0,
            ],
            'aps_tools' => [
                'available' => class_exists('\\APSTools\\APSTools'),
                'enabled' => get_option('avd_enable_aps_tools_integration', true),
            ],
        ];
    }
}
