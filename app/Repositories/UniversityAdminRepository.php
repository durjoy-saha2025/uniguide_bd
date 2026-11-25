<?php
declare(strict_types=1);

namespace UniGuide\App\Repositories;

use PDO;

final class UniversityAdminRepository
{
    private const FIELDS = [
        'name',
        'slug',
        'summary',
        'description',
        'city',
        'address',
        'campus_type',
        'website_url',
        'admissions_url',
        'logo_url',
        'cover_image_url',
        'source_id',
        'ugc_status',
        'verification_status',
        'status',
        'verified_at',
    ];

    public function __construct(private PDO $database)
    {
    }

    public function list(
        string $query,
        string $status,
        int $limit,
        int $offset,
        string $sort = 'updated_at',
        string $direction = 'DESC'
    ): array
    {
        [$where, $parameters] = $this->filters($query, $status);
        $count = $this->database->prepare('SELECT COUNT(*) FROM universities' . $where);
        foreach ($parameters as $name => $value) {
            $count->bindValue($name, $value, PDO::PARAM_STR);
        }
        $count->execute();
        $total = (int) $count->fetchColumn();

        $statement = $this->database->prepare(
            'SELECT u.id, u.slug, u.name, u.summary, u.city, u.campus_type, u.website_url,
                    u.admissions_url, u.source_id, s.title AS source_title,
                    s.url AS source_url, s.verification_status AS source_verification_status,
                    u.ugc_status, u.verification_status, u.verified_at,
                    u.status, u.created_at, u.updated_at
             FROM universities u
             LEFT JOIN sources s ON s.id = u.source_id' . $where . '
             ORDER BY u.`' . $sort . '` ' . $direction . ', u.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
        ];
    }

    public function findById(int $id): ?array
    {
        $statement = $this->database->prepare(
            'SELECT u.id, u.slug, u.name, u.summary, u.description, u.city, u.address, u.campus_type,
                    u.website_url, u.admissions_url, u.logo_url, u.cover_image_url,
                    u.source_id, s.title AS source_title, s.url AS source_url,
                    s.verification_status AS source_verification_status,
                    u.ugc_status, u.verification_status, u.verified_at, u.status,
                    u.created_at, u.updated_at
             FROM universities u
             LEFT JOIN sources s ON s.id = u.source_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $university = $statement->fetch();

        return $university === false ? null : $university;
    }

    public function isVerifiedSource(int $id): bool
    {
        $statement = $this->database->prepare(
            "SELECT 1 FROM sources WHERE id = :id AND verification_status = 'verified' LIMIT 1"
        );
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    public function create(array $university, int $actorId): int
    {
        $this->database->beginTransaction();
        $fields = array_keys($university);
        $columns = implode(', ', array_map(static function (string $field): string {
            return '`' . $field . '`';
        }, $fields));
        $placeholders = implode(', ', array_map(static function (string $field): string {
            return ':' . $field;
        }, $fields));

        $statement = $this->database->prepare(
            'INSERT INTO universities (' . $columns . ', created_by)
             VALUES (' . $placeholders . ', :created_by)'
        );
        $statement->execute(array_merge($university, ['created_by' => $actorId]));
        $id = (int) $this->database->lastInsertId();
        $this->log($actorId, 'create', $id, $university['status']);
        $this->database->commit();

        return $id;
    }

    public function update(int $id, array $university, int $actorId): bool
    {
        $this->database->beginTransaction();
        $fields = array_keys($university);
        $assignments = implode(', ', array_map(static function (string $field): string {
            return '`' . $field . '` = :' . $field;
        }, $fields));
        $statement = $this->database->prepare(
            'UPDATE universities SET ' . $assignments . ' WHERE id = :id'
        );
        $statement->execute(array_merge($university, ['id' => $id]));

        if ($statement->rowCount() === 0 && $this->findById($id) === null) {
            $this->database->rollBack();
            return false;
        }

        $this->log($actorId, 'update', $id, $university['status']);
        $this->database->commit();

        return true;
    }

    public function delete(int $id, int $actorId): bool
    {
        $this->database->beginTransaction();
        $statement = $this->database->prepare('DELETE FROM universities WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) {
            $this->database->rollBack();
            return false;
        }

        $this->log($actorId, 'delete', $id, null);
        $this->database->commit();

        return true;
    }

    private function filters(string $query, string $status): array
    {
        $conditions = [];
        $parameters = [];
        if ($query !== '') {
            $conditions[] = '(name LIKE :query_name OR slug LIKE :query_slug OR city LIKE :query_city)';
            $like = '%' . $query . '%';
            $parameters[':query_name'] = $like;
            $parameters[':query_slug'] = $like;
            $parameters[':query_city'] = $like;
        }
        if ($status !== '') {
            $conditions[] = 'status = :status';
            $parameters[':status'] = $status;
        }

        return [
            $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions),
            $parameters,
        ];
    }

    private function log(int $actorId, string $action, int $universityId, ?string $status): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO admin_activity_logs (actor_user_id, action, entity_type, entity_id, details)
             VALUES (:actor_user_id, :action, :entity_type, :entity_id, :details)'
        );
        $statement->execute([
            'actor_user_id' => $actorId,
            'action' => $action,
            'entity_type' => 'university',
            'entity_id' => $universityId,
            'details' => json_encode(['status' => $status], JSON_THROW_ON_ERROR),
        ]);
    }
}
