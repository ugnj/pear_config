<?php
declare(strict_types=1);

namespace PEAR\Config\Driver;

use PEAR\Config\Config;
use PEAR\Config\Container;

interface DriverInterface
{
    public function parseData($source, Config $config): bool;

    public function getOptions(): array;

    public function toString(Container $container): string;
}