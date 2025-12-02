<?php

namespace Aevov\Decentralized;

class Contributor
{
    /**
     * The contributor ID.
     *
     * @var string
     */
    private $id;

    /**
     * Constructor.
     *
     * @param string $id
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * Returns the contributor ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }
}
