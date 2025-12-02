<?php

namespace APS\Reasoning;

use AevovCognitiveEngine\HRMModule;

require_once dirname(__FILE__) . '/../../../aevov-cognitive-engine/includes/class-hrm-module.php';

class HRMReasoningEngine {
    private $parameters;
    private $connections;

    public function __construct($parameters, $connections) {
        $this->parameters = $parameters;
        $this->connections = $connections;
    }

    public function reason( $problem ) {
        $n_cycles = $this->parameters['n_cycles'] ?? 2;
        $t_timesteps = $this->parameters['t_timesteps'] ?? 2;

        $hrm = new HRMModule($n_cycles, $t_timesteps);
        return $hrm->reason( $problem );
    }
}
