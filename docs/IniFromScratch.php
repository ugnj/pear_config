<?php
/**
 * Config.php example
 * Lots of different manipulations to show Config features.
 * @author     Bertrand Mansion <bmansion@mamasam.com>
 * @package    Config
 */
// $Id: IniFromScratch.php 120857 2003-03-21 18:04:21Z mansion $

use PEAR\Config\Config;
use PEAR\Config\Container;
use PEAR\Config\Driver\IniCommented;
use PEAR\Config\Driver\PHPArray;

// Creates a PHPArray config with attributes, from scratch

$dsn = [
    'type' => 'mysql',
    'host' => 'localhost',
    'user' => 'some',
    'pass' => 'foobar',
];

$c = new Container('section', 'root');
try {
    $c->createComment('DB Config');
    $db = $c->createSection('DB', $dsn);
    $fields = $db->createSection('fields');
    $fields->createDirective('username', 'USERNAME', ['type' => 'varchar', 'size' => 32]);
    $fields->createDirective('password', 'PASSWD', ['type' => 'varchar', 'size' => 32]);
    $c->createBlank();
    $c->createComment('Support config');
    $c->createDirective('support', 'See my wishlist...');

    echo '<pre>' . $c->toString(new PHPArray()) . '</pre>';
} catch (\PEAR\Config\Exception $e) {
    die($e->getMessage());
}
unset($c);

// Parses and writes an existing php array $conf

$conf['storage']['driver'] = 'sql';
$conf['storage']['params']['php-type'] = 'mysql';
$conf['storage']['params']['host-spec'] = 'localhost';
$conf['storage']['params']['username'] = 'some';
$conf['storage']['params']['password'] = 'foobar';
$conf['menu']['apps'] = ['imp', 'turbo'];
$conf['std-content']['para'][0] = 'This is really cool !';
$conf['std-content']['para'][1] = 'It just rocks...';

$c = new Config();
try {
    $root = $c->parseConfig($conf, Config::PHP_ARRAY);

    $storage = $root->getItem('section', 'storage');
    $storage->removeItem();
    $root->addItem($storage);

    echo '<pre>' . $c->toString(Config::PHP_ARRAY, ['name' => 'test']) . '</pre>';

    if ($c->writeConfig('/tmp/Config_Test.php', Config::PHP_ARRAY, ['name' => 'test']) === true) {
        echo 'Config written into /tmp/Config_Test.php';
    }
} catch (\PEAR\Config\Exception $e) {
    die($e->getMessage());
}

// Making a php ini file with $storage only

$ini = new Config();
try {
    $iniRoot = $ini->getRoot();
    $iniRoot->addItem($storage);

    $comment = new Container('comment', null, 'This is the php ini version of storage');
    $iniRoot->addItem($comment, 'top');
    $iniRoot->createBlank('after', $comment);
    echo '<pre>' . $iniRoot->toString(new IniCommented()) . '</pre>';

    // Gonna make an array with it

    echo '<pre>';
    var_dump($iniRoot->toArray());
    echo '</pre>';
} catch (\PEAR\Config\Exception $e) {
    die($e->getMessage());
}

// Now, I'll parse you php.ini file and make it a php array

$phpIni = new Config();
try {
    $phpIni->parseConfig('/usr/local/lib/php.ini', Config::INI_CONF);
    echo '<pre>' . $phpIni->toString(Config::PHP_ARRAY, ['name' => 'php_ini']) . '</pre>';
} catch (\PEAR\Config\Exception $e) {
    die($e->getMessage());
}