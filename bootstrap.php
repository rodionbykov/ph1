<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $baseDir = __DIR__;

    $prefixes = [
        'Framework\\' => $baseDir . '/framework/',
        'App\\Controllers\\' => $baseDir . '/controllers/',
        'App\\Model\\Services\\' => $baseDir . '/model/services/',
        'App\\Model\\Beans\\' => $baseDir . '/model/beans/',
        'App\\' => $baseDir . '/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $relativePath = str_replace('\\', '/', $relative) . '.php';
        $file = $directory . $relativePath;

        if (is_file($file)) {
            require $file;
        }

        return;
    }
});