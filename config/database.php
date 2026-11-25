<?php
declare(strict_types=1);

return static function (): array {
    $required = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'];
    $values = [];

    foreach ($required as $name) {
        $value = getenv($name);
        if ($value === false || trim($value) === '') {
            throw new RuntimeException('Required database configuration is missing: ' . $name);
        }
        $values[$name] = trim($value);
    }

    if (!preg_match('/\A[a-zA-Z0-9._:-]+\z/', $values['DB_HOST'])) {
        throw new RuntimeException('DB_HOST contains invalid characters.');
    }
    if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $values['DB_DATABASE'])) {
        throw new RuntimeException('DB_DATABASE contains invalid characters.');
    }

    $port = getenv('DB_PORT');
    $port = $port === false || trim($port) === '' ? '3306' : trim($port);
    if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
        throw new RuntimeException('DB_PORT must be a valid TCP port.');
    }

    $password = getenv('DB_PASSWORD');
    if ($password === false) {
        throw new RuntimeException('Required database configuration is missing: DB_PASSWORD');
    }

    return [
        'host' => $values['DB_HOST'],
        'port' => (int) $port,
        'database' => $values['DB_DATABASE'],
        'username' => $values['DB_USERNAME'],
        'password' => $password,
    ];
};
