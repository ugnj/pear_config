<?php
/**
* Config.php example with IniCommented container
* This container is for PHP .ini files, when you want
* to keep your comments. If you don't use comments, you'd rather
* use the IniFile.php container.
* @author 	Bertrand Mansion <bmansion@mamasam.com>
* @package	Config
*/
// $Id: IniCommented.php 203590 2005-12-24 02:16:58Z aashley $

use PEAR\Config\Config;

$source = '/usr/local/php5/lib/php.ini';

$phpIni = new Config();
try {
	$root = $phpIni->parseConfig($source, Config::INI_COMMENTED);

    // Convert your ini file to a php array config

    echo '<pre>', $phpIni->toString(Config::PHP_ARRAY, ['name' => 'php_ini']), '</pre>';
} catch (\PEAR\Config\Exception $e) {
	die($e->getMessage());
}