--TEST--
bug 2780 regression
--FILE--
<?php
    set_include_path(dirname(dirname(__FILE__)) . ':' . get_include_path());
    require_once 'Config.php' ;
    $c1 = new Container('section', 'root');
    $c2 = new Container();

    $c1->addItem($c2);
    // Convert your ini file to a php array config
    echo $c1->toString('phparray', array('name' => 'php_ini'));
?>
--EXPECT--
