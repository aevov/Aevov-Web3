<?php

namespace AevovMemoryCore;

class MemoryPattern {

    public $id;
    public $type;
    public $capacity;
    public $decay_rate;
    public $metadata;

    public function __construct( $id, $type, $capacity, $decay_rate, $metadata = [] ) {
        $this->id = $id;
        $this->type = $type;
        $this->capacity = $capacity;
        $this->decay_rate = $decay_rate;
        $this->metadata = $metadata;
    }
}
