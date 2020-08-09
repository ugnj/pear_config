<?php
declare(strict_types=1);

namespace PEAR\Config\Driver;

abstract class AbstractDriver
{
    private array $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}