<?php
declare(strict_types=1);

namespace PEAR\Config\Driver;

use PEAR\Config\Container;

interface WritableInterface
{
    public function writeData(string $fileName, Container $obj);
}