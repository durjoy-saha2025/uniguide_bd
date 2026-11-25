<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Support/CatalogResources.php';
require dirname(__DIR__) . '/app/Repositories/CatalogAdminRepository.php';
require dirname(__DIR__) . '/app/Repositories/CatalogRepository.php';

use UniGuide\App\Repositories\CatalogAdminRepository;
use UniGuide\App\Repositories\CatalogRepository;
use UniGuide\App\Support\CatalogResources;

function assertCatalogSame($expected, $actual, string $message): void
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
        title TEXT NOT NULL,
        url TEXT NOT NULL,
        publisher TEXT,
        verification_status TEXT NOT NULL
    )'
);
$database->exec(
    'CREATE TABLE universities (
        id INTEGER PRIMARY KEY,
        source_id INTEGER,
        status TEXT NOT NULL,
        verification_status TEXT NOT NULL
    )'
);
$database->exec(
    'CREATE TABLE programs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        university_id INTEGER NOT NULL,
        slug TEXT NOT NULL,
        name TEXT NOT NULL,
        degree_level TEXT,
        field_of_study TEXT,
        duration_months INTEGER,
        description TEXT,
        source_id INTEGER,
        verification_status TEXT NOT NULL,
        verified_at TEXT,
        status TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);
$database->exec(
    'CREATE TABLE admin_activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        actor_user_id INTEGER,
        action TEXT,
        entity_type TEXT,
        entity_id INTEGER,
        details TEXT
    )'
);
$database->exec(
    "INSERT INTO sources (id, title, url, publisher, verification_status)
     VALUES (1, 'Official program page', 'https://example.edu.bd/program', 'Example University', 'verified')"
);
$database->exec(
    "INSERT INTO universities (id, source_id, status, verification_status)
     VALUES (1, 1, 'published', 'verified')"
);

$definition = CatalogResources::find('programs');
$admin = new CatalogAdminRepository($database);
$programId = $admin->create('programs', $definition, [
    'university_id' => 1,
    'slug' => 'computer-science',
    'name' => 'Computer Science',
    'degree_level' => 'Bachelor',
    'field_of_study' => 'Computing',
    'duration_months' => 48,
    'description' => null,
    'source_id' => 1,
    'verification_status' => 'verified',
    'verified_at' => '2026-01-01 00:00:00',
    'status' => 'published',
], 7);
assertCatalogSame(1, $admin->list('programs', $definition, 'computer', ['status' => 'published'], 10, 0, 'name', 'ASC')['total'], 'Admin search and filters should return the matching program.');

$public = new CatalogRepository($database);
$listing = $public->list('programs', $definition, '', ['university_id' => 1], 10, 0);
assertCatalogSame(1, $listing['total'], 'A verified, published program with verified university evidence should be public.');
assertCatalogSame('https://example.edu.bd/program', $listing['items'][0]['source_url'], 'Public catalog results should include source references.');

$database->exec("UPDATE sources SET verification_status = 'pending' WHERE id = 1");
assertCatalogSame(0, $public->list('programs', $definition, '', [], 10, 0)['total'], 'A program must disappear if its source becomes unverified.');

echo "CatalogRepository tests passed.\n";
