<?php

namespace Aevov\Decentralized;

class Contribution
{
    /**
     * The contributor.
     *
     * @var Contributor
     */
    private $contributor;

    /**
     * The data.
     *
     * @var mixed
     */
    private $data;

    /**
     * Constructor.
     *
     * @param Contributor $contributor
     * @param mixed       $data
     */
    public function __construct(Contributor $contributor, $data)
    {
        $this->contributor = $contributor;
        $this->data = $data;
    }

    /**
     * Returns the contributor.
     *
     * @return Contributor
     */
    public function getContributor(): Contributor
    {
        return $this->contributor;
    }

    /**
     * Returns the data.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }
}
