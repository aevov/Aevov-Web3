<?php
/**
 * Biome Classifier
 *
 * Classifies biomes based on elevation, temperature, and moisture
 */

namespace Aevov\PhysicsEngine\World;

class BiomeClassifier {

    /**
     * Classify biome based on environmental parameters
     */
    public function classify($params) {
        $elevation = $params['elevation'];
        $temperature = $params['temperature'];
        $moisture = $params['moisture'];
        $available_types = $params['available_types'] ?? [];

        // Biome classification logic
        $biome = null;

        // Ocean/Water
        if ($elevation < 0 && in_array('ocean', $available_types)) {
            $biome = 'ocean';
        }
        // Mountain
        elseif ($elevation > 40 && in_array('mountain', $available_types)) {
            $biome = 'mountain';
        }
        // Desert (hot and dry)
        elseif ($temperature > 20 && $moisture < 0.3 && in_array('desert', $available_types)) {
            $biome = 'desert';
        }
        // Forest (moderate temp, high moisture)
        elseif ($temperature > 5 && $temperature < 25 && $moisture > 0.5 && in_array('forest', $available_types)) {
            $biome = 'forest';
        }
        // Plains (default)
        elseif (in_array('plains', $available_types)) {
            $biome = 'plains';
        }
        // Fallback
        else {
            $biome = !empty($available_types) ? $available_types[0] : 'plains';
        }

        return $biome;
    }

    /**
     * Get biome color for visualization
     */
    public function get_biome_color($biome) {
        $colors = [
            'ocean' => '#1E90FF',
            'mountain' => '#A0522D',
            'desert' => '#EDC9AF',
            'forest' => '#228B22',
            'plains' => '#90EE90'
        ];

        return $colors[$biome] ?? '#808080';
    }
}
