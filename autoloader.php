<?php
function autoloader($class)
{
    $namespaces = include 'namespaces.php';
    foreach ($namespaces as $ns => $lib_folder) {
        $pattern = '/^'.str_replace('\\', '\\\\', $ns).'/';
        if (preg_match($pattern, $class)) {
            $filepath = preg_replace('/^'.$ns.'/', $lib_folder, $class, 1);
            $filepath = realpath(dirname(__FILE__)) . "/" . str_replace("\\", "/", $filepath) . '.php';
            include_once($filepath);
        }
    }
}

spl_autoload_register('autoloader');
