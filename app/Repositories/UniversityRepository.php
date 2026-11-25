<?php
declare(strict_types=1);

namespace UniGuide\App\Repositories;

use PDO;

final class UniversityRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function countPublished(string $query, string $city, string $subject): int
    {
        [$where, $parameters] = $this->buildFilters($query, $city, $subject);
        $statement = $this->database->prepare(
            'SELECT COUNT(*)
             FROM universities u
             INNER JOIN sources s ON s.id = u.source_id AND s.verification_status = :source_verification
             WHERE u.status = :status AND u.verification_status = :verification_status' . $where
        );
        $statement->execute(array_merge([
            ':status' => 'published',
            ':verification_status' => 'verified',
            ':source_verification' => 'verified',
        ], $parameters));

        return (int) $statement->fetchColumn();
    }

    public function listPublished(
        string $query,
        string $city,
        string $subject,
        int $limit,
        int $offset
    ): array {
        [$where, $parameters] = $this->buildFilters($query, $city, $subject);
        $statement = $this->database->prepare(
            'SELECT u.id, u.slug, u.name, u.summary, u.city, u.campus_type, u.website_url,
                    u.admissions_url, u.ugc_status, u.verification_status, u.verified_at,
                    s.title AS source_title, s.url AS source_url, s.publisher AS source_publisher
             FROM universities u
             INNER JOIN sources s ON s.id = u.source_id AND s.verification_status = :source_verification
             WHERE u.status = :status AND u.verification_status = :verification_status' . $where . '
             ORDER BY u.name ASC
             LIMIT :limit OFFSET :offset'
        );

        $statement->bindValue(':status', 'published', PDO::PARAM_STR);
        $statement->bindValue(':verification_status', 'verified', PDO::PARAM_STR);
        $statement->bindValue(':source_verification', 'verified', PDO::PARAM_STR);
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function findPublishedBySlug(string $slug): ?array
    {
        $statement = $this->database->prepare(
            'SELECT u.id, u.slug, u.name, u.summary, u.description, u.city, u.address, u.campus_type,
                    u.website_url, u.admissions_url, u.logo_url, u.cover_image_url,
                    u.ugc_status, u.verification_status, u.verified_at, u.updated_at,
                    s.title AS source_title, s.url AS source_url, s.publisher AS source_publisher
             FROM universities u
             INNER JOIN sources s ON s.id = u.source_id AND s.verification_status = :source_verification
             WHERE u.slug = :slug AND u.status = :status AND u.verification_status = :verification_status
             LIMIT 1'
        );
        $statement->execute([
            'slug' => $slug,
            'status' => 'published',
            'verification_status' => 'verified',
            'source_verification' => 'verified',
        ]);
        $university = $statement->fetch();

        return $university === false ? null : $university;
    }

    private function buildFilters(string $query, string $city, string $subject): array
    {
        $conditions = [];
        $parameters = [];

        if ($query !== '') {
            $conditions[] = '(u.name LIKE :query_name OR u.summary LIKE :query_summary OR EXISTS (
                SELECT 1 FROM programs qp
                INNER JOIN sources qs ON qs.id = qp.source_id AND qs.verification_status = \'verified\'
                WHERE qp.university_id = u.id AND qp.status = \'published\'
                    AND qp.verification_status = \'verified\'
                    AND (qp.name LIKE :query_program_name OR qp.field_of_study LIKE :query_program_field)
            ))';
            $search = '%' . $query . '%';
            $parameters[':query_name'] = $search;
            $parameters[':query_summary'] = $search;
            $parameters[':query_program_name'] = $search;
            $parameters[':query_program_field'] = $search;
        }

        if ($city !== '') {
            $conditions[] = 'u.city = :city';
            $parameters[':city'] = $city;
        }
        if ($subject !== '') {
            $conditions[] = "EXISTS (
                SELECT 1 FROM programs p
                INNER JOIN sources ps ON ps.id = p.source_id AND ps.verification_status = 'verified'
                WHERE p.university_id = u.id AND p.status = 'published'
                    AND p.verification_status = 'verified'
                    AND (p.name LIKE :subject_name OR p.field_of_study LIKE :subject_field)
            )";
            $parameters[':subject_name'] = '%' . $subject . '%';
            $parameters[':subject_field'] = '%' . $subject . '%';
        }

        return [
            $conditions === [] ? '' : ' AND ' . implode(' AND ', $conditions),
            $parameters,
        ];
    }
}
