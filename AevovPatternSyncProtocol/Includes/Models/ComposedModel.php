<?php

namespace APS\Models;

class ComposedModel {
    public $model_id;
    public $name;
    public $description;
    public $layers; // Array of instantiated pattern objects
    public $memory_system; // Instantiated memory system object
    public $reasoning_engine; // Instantiated reasoning engine object
    public $training_config; // Training configuration
    public $metadata; // Additional metadata

    public function __construct($model_id, $name, $description, $metadata = []) {
        $this->model_id = $model_id;
        $this->name = $name;
        $this->description = $description;
        $this->layers = [];
        $this->memory_system = null;
        $this->reasoning_engine = null;
        $this->training_config = null;
        $this->metadata = $metadata;
    }

    public function add_layer($layer_patterns) {
        $this->layers[] = $layer_patterns;
    }

    public function set_memory_system($memory_system) {
        $this->memory_system = $memory_system;
    }

    public function set_reasoning_engine($reasoning_engine) {
        $this->reasoning_engine = $reasoning_engine;
    }

    public function set_training_config($training_config) {
        $this->training_config = $training_config;
    }

    // Method to get a simplified representation for API responses
    public function to_array() {
        return [
            'model_id' => $this->model_id,
            'name' => $this->name,
            'description' => $this->description,
            'layers' => array_map(function($layer) {
                return array_map(function($pattern) {
                    return [
                        'id' => $pattern->id,
                        'pattern_id' => $pattern->pattern_id,
                        'pattern_type' => $pattern->pattern_type,
                        'model_source' => $pattern->model_source,
                        'metadata' => $pattern->metadata,
                    ];
                }, $layer);
            }, $this->layers),
            'memory_system' => $this->memory_system ? $this->memory_system : null,
            'reasoning_engine' => $this->reasoning_engine ? $this->reasoning_engine : null,
            'training_config' => $this->training_config ? $this->training_config : null,
            'metadata' => $this->metadata,
        ];
    }
}