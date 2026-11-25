<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Repositories/UniversityRepository.php';

use UniGuide\App\Repositories\UniversityRepository;

function expectSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}

$database = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$database->exec(
    'CREATE TABLE universities (
        id INTEGER PRIMARY KEY,
        slug TEXT NOT NULL,
        name TEXT NOT NULL,
        summary TEXT,
        description TEXT,
        city TEXT,
        address TEXT,
        campus_type TEXT,
        website_url TEXT,
        admissions_url TEXT,
        logo_url TEXT,
        cover_image_url TEXT,
        source_id INTEGER,
        ugc_status TEXT,
        verification_status TEXT,
        verified_at TEXT,
        status TEXT NOT NULL,
        updated_at TEXT
    )'
);
$database->exec(
    'CREATE TABLE programs (
        id INTEGER PRIMARY KEY,
        university_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        field_of_study TEXT,
        source_id INTEGER,
        status TEXT NOT NULL,
        verification_status TEXT NOT NULL
    )'
);
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
    "INSERT INTO programs (id, university_id, name, field_of_study, source_id, status, verification_status)
     VALUES (1, 1, 'BSc in Computing', 'Computer Science', 1, 'published', 'verified'),
            (2, 2, 'Draft Computing', 'Computer Science', 1, 'draft', 'verified')"
);
$database->exec(
    "INSERT INTO sources (id, title, url, publisher, verification_status)
     VALUES (1, 'Official source', 'https://example.edu.bd', 'University', 'verified')"
);
$insert = $database->prepare(
    'INSERT INTO universities (id, slug, name, summary, city, status, verification_status, source_id)
     VALUES (:id, :slug, :name, :summary, :city, :status, :verification_status, :source_id)'
);
$rows = [
    [1, 'north-south', 'North South University', 'Engineering and business programs', 'Dhaka', 'published', 'verified', 1],
    [2, 'chattogram-sample', 'Chattogram Sample University', 'Business programs', 'Chattogram', 'published', 'verified', 1],
    [3, 'hidden-sample', 'Hidden Sample University', 'Engineering programs', 'Dhaka', 'draft', 'verified', null],
    [4, 'pending-sample', 'Pending Sample University', 'Engineering programs', 'Dhaka', 'published', 'pending', null],
    [5, 'unverified-source', 'Unverified Source University', 'Business programs', 'Dhaka', 'published', 'verified', 2],
];
$database->exec(
    "INSERT INTO sources (id, title, url, publisher, verification_status)
     VALUES (2, 'Pending source', 'https://pending.example', 'Publisher', 'pending')"
);
foreach ($rows as $row) {
    $insert->execute([
        ':id' => $row[0],
        ':slug' => $row[1],
        ':name' => $row[2],
        ':summary' => $row[3],
        ':city' => $row[4],
        ':status' => $row[5],
        ':verification_status' => $row[6],
        ':source_id' => $row[7],
    ]);
}

$repository = new UniversityRepository($database);
expectSame(2, $repository->countPublished('', '', ''), 'Only published universities should be counted.');
expectSame(1, $repository->countPublished('engineering', 'Dhaka', ''), 'Search and city filters should combine.');
expectSame(1, $repository->countPublished('', '', 'Computer Science'), 'Subject search must require a published verified program.');
expectSame(1, $repository->countPublished('Computer Science', '', ''), 'Text search should include verified published programs.');
expectSame(1, count($repository->listPublished('', '', '', 1, 1)), 'The requested page size should be respected.');
expectSame(
    'North South University',
    $repository->listPublished('', '', '', 1, 1)[0]['name'],
    'Pagination should use the stable alphabetical order.'
);
expectSame(
    'north-south',
    $repository->findPublishedBySlug('north-south')['slug'],
    'A published university should be retrievable by slug.'
);
expectSame(
    'https://example.edu.bd',
    $repository->findPublishedBySlug('north-south')['source_url'],
    'Published university profiles should include their verified source.'
);
expectSame(
    null,
    $repository->findPublishedBySlug('hidden-sample'),
    'Draft universities must not be visible through public profile lookup.'
);
expectSame(
    null,
    $repository->findPublishedBySlug('pending-sample'),
    'Unverified universities must not be visible through public profile lookup.'
);
expectSame(
    null,
    $repository->findPublishedBySlug('unverified-source'),
    'A university must not be public when its linked source is not verified.'
);

echo "UniversityRepository tests passed.\n";
