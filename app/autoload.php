<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Vote\\';
    $baseDir = APP_ROOT . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    $file = $baseDir . $relativePath;

    if (is_file($file)) {
        require_once $file;
    }
});

