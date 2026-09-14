<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(function (string $class) use ($root): void {
        if (str_starts_with($class, 'App\\')) {
            $path = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($path)) require_once $path;
        } elseif (str_starts_with($class, 'Database\\')) {
            $path = $root . '/database/' . str_replace('\\', '/', substr($class, 9)) . '.php';
            if (is_file($path)) require_once $path;
        }
    });
}

$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '') {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

date_default_timezone_set('America/Sao_Paulo');

try {
    \Database\Migrations::run();
    fwrite(STDOUT, "Migrations OK - version " . \Database\Migrations::VERSION . PHP_EOL);
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
