<?php
function pr($var) {
    $template = php_sapi_name() !== 'cli' ? '<pre>%s</pre>' : "\n%s\n";
    printf($template, print_r($var, true));
}

function vd($var) {
    $template = php_sapi_name() !== 'cli' ? '<pre>%s</pre>' : "\n%s\n";
    printf($template, var_dump($var));
}
include 'autoloader.php';
