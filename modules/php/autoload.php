<?php

declare(strict_types=1);

spl_autoload_register(function ($class) {
    $prefix = 'Bga\\Games\\DevilsDice\\';

    if (!str_starts_with($class, $prefix))
        return;

    $relativeClass = substr($class, strlen($prefix));
    $dir = __DIR__ . DIRECTORY_SEPARATOR;
    $file = ltrim(str_replace("\\", DIRECTORY_SEPARATOR, $relativeClass), DIRECTORY_SEPARATOR) . '.inc.php';

    if (file_exists($dir . $file))
        require_once($dir . $file);
});
