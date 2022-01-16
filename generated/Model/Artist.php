<?php

namespace App\Generated\Model;

class Artist
{
    /**
     * @var string
     */
    protected $name;
    /**
     * @var mixed
     */
    protected $normalized;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getNormalized()
    {
        return $this->normalized;
    }

    /**
     * @param mixed $normalized
     */
    public function setNormalized($normalized): self
    {
        $this->normalized = $normalized;

        return $this;
    }
}
