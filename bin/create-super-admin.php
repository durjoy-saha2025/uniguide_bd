<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$name = trim($argv[1] ?? '');
$email = strtolower(trim($argv[2] ?? ''));
$password = getenv('UNIGUIDE_ADMIN_PASSWORD');

if ($name === '' || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/create-super-admin.php \"Name\" email@example.com\n");
    exit(2);
}
if ($password === false || strlen($password) < 16) {
    fwrite(STDERR, "Set UNIGUIDE_ADMIN_PASSWORD to a password of at least 16 characters, then retry.\n");
    exit(2);
}

$database = \UniGuide\App\Core\Database::connection();
$existing = (int) $database->query(
    "SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND account_status = 'active'"
)->fetchColumn();
if ($existing > 0) {
    fwrite(STDERR, "An active super admin already exists; refusing to create another.\n");
    exit(1);
}

$statement = $database->prepare(
    'INSERT INTO users (name, email, password_hash, role)
     VALUES (:name, :email, :password_hash, :role)'
);
$statement->execute([
    'name' => $name,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'super_admin',
]);
putenv('UNIGUIDE_ADMIN_PASSWORD');

fwrite(STDOUT, "Super admin created. Store the password securely and unset UNIGUIDE_ADMIN_PASSWORD.\n");
