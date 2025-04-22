<?php

namespace Yew\Nikic\FastRoute;

require __DIR__ . '/functions.php';

spl_autoload_register(function ($class) {
    if (strpos($class, 'Yew\\Nikic\\FastRoute\\') === 0) {
        $name = substr($class, strlen('Yew\\Nikic\\FastRoute'));
        require __DIR__ . strtr($name, '\\', DIRECTORY_SEPARATOR) . '.php';
    }
});
