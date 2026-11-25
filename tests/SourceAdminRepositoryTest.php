<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Repositories/SourceAdminRepository.php';

use UniGuide\App\Repositories\SourceAdminRepository;

function assertSameValue($expected, $actual, string $message): void
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
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        source_type TEXT NOT NULL,
        url TEXT NOT NULL,
        publisher TEXT,
        notes TEXT,
        verification_status TEXT NOT NULL,
        verified_at TEXT,
        created_by INTEGER,
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

$repository = new SourceAdminRepository($database);
$sourceId = $repository->create([
    'title' => 'Official admissions page',
    'source_type' => 'official_website',
    'url' => 'https://example.edu.bd/admission',
    'publisher' => 'Example University',
    'notes' => null,
    'verification_status' => 'pending',
    'verified_at' => null,
], 1);
$database->exec(
    "INSERT INTO sources (title, source_type, url, publisher, verification_status)
     VALUES ('A later source', 'official_website', 'https://example.edu.bd/later', 'Example University', 'pending')"
);
assertSameValue('Official admissions page', $repository->findById($sourceId)['title'], 'Created source should be readable.');
assertSameValue(1, $repository->list('admissions', 'pending', 20, 0)['total'], 'Search and status filtering should work.');
$sortedSources = $repository->list('', '', 20, 0, 'title', 'ASC');
assertSameValue('A later source', $sortedSources['items'][0]['title'], 'Admin source lists should support validated field sorting.');

$updated = $repository->update($sourceId, [
    'title' => 'Verified admissions page',
    'source_type' => 'official_website',
    'url' => 'https://example.edu.bd/admission',
    'publisher' => 'Example University',
    'notes' => null,
    'verification_status' => 'verified',
    'verified_at' => '2026-01-01 00:00:00',
], 1);
assertSameValue(true, $updated, 'Existing source should update.');
assertSameValue('verified', $repository->findById($sourceId)['verification_status'], 'Updated status should persist.');
assertSameValue(true, $repository->delete($sourceId, 1), 'Existing source should delete.');
assertSameValue(null, $repository->findById($sourceId), 'Deleted source should no longer be readable.');
assertSameValue(3, (int) $database->query('SELECT COUNT(*) FROM admin_activity_logs')->fetchColumn(), 'All mutations should be audited.');

echo "SourceAdminRepository tests passed.\n";
