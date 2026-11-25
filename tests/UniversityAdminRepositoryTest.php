<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Repositories/UniversityAdminRepository.php';

use UniGuide\App\Repositories\UniversityAdminRepository;

function assertUniversityAdminSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

$database = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$database->exec(
    'CREATE TABLE sources (
        id INTEGER PRIMARY KEY,
        title TEXT,
        url TEXT,
        verification_status TEXT
    )'
);
$database->exec(
    'CREATE TABLE universities (
        id INTEGER PRIMARY KEY,
        slug TEXT,
        name TEXT,
        summary TEXT,
        city TEXT,
        campus_type TEXT,
        website_url TEXT,
        admissions_url TEXT,
        source_id INTEGER,
        ugc_status TEXT,
        verification_status TEXT,
        verified_at TEXT,
        status TEXT,
        created_at TEXT,
        updated_at TEXT
    )'
);
$database->exec(
    "INSERT INTO universities (id, slug, name, city, status, verification_status, created_at, updated_at)
     VALUES (1, 'zeta', 'Zeta University', 'Dhaka', 'draft', 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
            (2, 'alpha', 'Alpha University', 'Sylhet', 'draft', 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
);

$repository = new UniversityAdminRepository($database);
$result = $repository->list('', '', 20, 0, 'name', 'ASC');
assertUniversityAdminSame(2, $result['total'], 'Admin university listing should count all matching records.');
assertUniversityAdminSame('Alpha University', $result['items'][0]['name'], 'Admin university listing should support field sorting.');

echo "UniversityAdminRepository tests passed.\n";
