<?php
/**
* Config.php example with Apache container
* @author   Bertrand Mansion <bmansion@mamasam.com>
* @package  Config
*/
// $Id: Apache.php 120908 2003-03-22 17:44:12Z mansion $

use PEAR\Config\Config;
use PEAR\Config\Exception;

$dataSrc = '/path/to/httpd.conf';
$conf = new Config();

try {
    $content = $conf->parseConfig($dataSrc, Config::APACHE_CONF);
} catch (Exception $e) {
    die($e->getMessage());
}

try {
    // adding a new virtual-host
    $content->createBlank();
    $content->createComment('My virtual host');
    $content->createBlank();

    $vhost = $content->createSection('VirtualHost', ['127.0.0.1:82']);
    $vhost->createDirective('DocumentRoot', '/usr/share/www');
    $vhost->createDirective('ServerName', 'www.mamasam.com');

    $location = $vhost->createSection('Location', ['/admin']);
    $location->createDirective('AuthType', 'basic');
    $location->createDirective('Require', 'group admin');

    // adding some directives Listen
    try {
        $listen = $content->getItem('directive', 'Listen');
        $res = $content->createDirective('Listen', '82', null, 'after', $listen);
    } catch (Exception $e) {
        try {
            $listen = $content->createDirective('Listen', '81', null, 'bottom');
        } catch (Exception $e) {
            die($e->getMessage());
        }

        $content->createDirective('Listen', '82', null, 'after', $listen);
    }

    echo '<pre>' . htmlspecialchars($content->toString('apache')) . '</pre>';
} catch (Exception $e) {
    die($e->getMessage());
}

// Writing the files
/*try {
    $conf->writeConfig('/tmp/httpd.conf', 'apache');
    echo 'done writing config<br>';
} catch (Exception $e) {
    die($e->getMessage());
}

try {
    $vhost->writeDatasrc('/tmp/vhost.conf', 'apache');
    echo 'done writing vhost<br>';
} catch (Exception $e) {
    die($e->getMessage());
}*/