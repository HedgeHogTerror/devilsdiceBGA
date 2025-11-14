<?php

declare(strict_types=1);

spl_autoload_register(function ($class) {
    $prefixes = [
        'Bga\\Games\\DevilsDice\\',
        'Bga\\Games\\devilsdice\\' // Handle case sensitivity
    ];

    $relativeClass = null;
    foreach ($prefixes as $prefix) {
        if (str_starts_with($class, $prefix)) {
            $relativeClass = substr($class, strlen($prefix));
            break;
        }
    }

    if ($relativeClass === null)
        return;

    $dir = __DIR__ . DIRECTORY_SEPARATOR;
    $file = ltrim(str_replace("\\", DIRECTORY_SEPARATOR, $relativeClass), DIRECTORY_SEPARATOR) . '.inc.php';

    if (file_exists($dir . $file))
        require_once($dir . $file);
});
