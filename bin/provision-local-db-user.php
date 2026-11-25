<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$loadConfiguration = require dirname(__DIR__) . '/config/database.php';
$configuration = $loadConfiguration();
$database = \UniGuide\App\Core\Database::connection();
$username = 'uniguide_app';
$host = '127.0.0.1';

$check = $database->prepare(
    'SELECT COUNT(*) FROM mysql.user WHERE User = :username AND Host = :host'
);
$check->execute([
    'username' => $username,
    'host' => $host,
]);
if ((int) $check->fetchColumn() > 0) {
    fwrite(STDERR, "The local application database account already exists; refusing to rotate its password.\n");
    exit(1);
}

$password = bin2hex(random_bytes(32));
$quotedPassword = $database->quote($password);
$database->exec(
    "CREATE USER 'uniguide_app'@'127.0.0.1' IDENTIFIED BY " . $quotedPassword
);
$databaseName = '`' . $configuration['database'] . '`';
$database->exec(
    'GRANT SELECT, INSERT, UPDATE, DELETE ON ' . $databaseName
    . ".* TO 'uniguide_app'@'127.0.0.1'"
);

$environmentContents = implode("\n", [
    'DB_HOST=' . $configuration['host'],
    'DB_PORT=' . $configuration['port'],
    'DB_DATABASE=' . $configuration['database'],
    'DB_USERNAME=' . $username,
    'DB_PASSWORD=' . $password,
    '',
]);
$environmentPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
$written = file_put_contents($environmentPath, $environmentContents, LOCK_EX);
if ($written === false) {
    $database->exec("DROP USER 'uniguide_app'@'127.0.0.1'");
    throw new RuntimeException('Unable to write the local application environment file.');
}

fwrite(STDOUT, "Created a least-privilege local database account and updated the ignored .env file.\n");
