<?php

namespace AevovChunkRegistry;

class AevovChunk {

    public $id;
    public $type;
    public $cubbit_key;
    public $metadata;
    public $dependencies;

    public function __construct( $id, $type, $cubbit_key, $metadata = [], $dependencies = [] ) {
        $this->id = $id;
        $this->type = $type;
        $this->cubbit_key = $cubbit_key;
        $this->metadata = $metadata;
        $this->dependencies = $dependencies;
    }
}
