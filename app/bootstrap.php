<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('UTC');

spl_autoload_register(static function (string $class): void {
    $prefix = 'UniGuide\\App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
        . '.php';

    if (is_file($file)) {
        require $file;
    }
});

set_exception_handler(static function (Throwable $exception): void {
    error_log(sprintf(
        '[UniGuide] Unhandled %s at %s:%d: %s',
        get_class($exception),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getMessage()
    ));

    \UniGuide\App\Core\ApiResponse::error(
        'internal_error',
        'The request could not be completed.',
        500
    );
});

$environmentFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (is_file($environmentFile)) {
    $lines = file($environmentFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read the application environment file.');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            throw new RuntimeException('Invalid line in the application environment file.');
        }

        $name = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));
        if (!preg_match('/\A[A-Z][A-Z0-9_]*\z/', $name)) {
            throw new RuntimeException('Invalid environment variable name in the application environment file.');
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}
