<?php
declare(strict_types=1);

namespace UniGuide\App\Repositories;

use PDO;

final class SourceAdminRepository
{
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
        $conditions = [];
        $parameters = [];
        if ($query !== '') {
            $conditions[] = '(title LIKE :title OR publisher LIKE :publisher OR url LIKE :url)';
            $like = '%' . $query . '%';
            $parameters = [':title' => $like, ':publisher' => $like, ':url' => $like];
        }
        if ($status !== '') {
            $conditions[] = 'verification_status = :status';
            $parameters[':status'] = $status;
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $count = $this->database->prepare('SELECT COUNT(*) FROM sources' . $where);
        foreach ($parameters as $name => $value) {
            $count->bindValue($name, $value, PDO::PARAM_STR);
        }
        $count->execute();

        $statement = $this->database->prepare(
            'SELECT id, title, source_type, url, publisher, notes, verification_status,
                    verified_at, created_at, updated_at
             FROM sources' . $where . '
             ORDER BY `' . $sort . '` ' . $direction . ', id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(), 'total' => (int) $count->fetchColumn()];
    }

    public function findById(int $id): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, title, source_type, url, publisher, notes, verification_status,
                    verified_at, created_at, updated_at
             FROM sources WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $source = $statement->fetch();

        return $source === false ? null : $source;
    }

    public function create(array $source, int $actorId): int
    {
        $this->database->beginTransaction();
        $statement = $this->database->prepare(
            'INSERT INTO sources (title, source_type, url, publisher, notes, verification_status,
                                  verified_at, created_by)
             VALUES (:title, :source_type, :url, :publisher, :notes, :verification_status,
                     :verified_at, :created_by)'
        );
        $statement->execute($source + ['created_by' => $actorId]);
        $id = (int) $this->database->lastInsertId();
        $this->log($actorId, 'create', $id);
        $this->database->commit();

        return $id;
    }

    public function update(int $id, array $source, int $actorId): bool
    {
        $this->database->beginTransaction();
        $statement = $this->database->prepare(
            'UPDATE sources SET title = :title, source_type = :source_type, url = :url,
                    publisher = :publisher, notes = :notes,
                    verification_status = :verification_status, verified_at = :verified_at
             WHERE id = :id'
        );
        $statement->execute($source + ['id' => $id]);
        if ($statement->rowCount() === 0 && $this->findById($id) === null) {
            $this->database->rollBack();
            return false;
        }

        $this->log($actorId, 'update', $id);
        $this->database->commit();

        return true;
    }

    public function delete(int $id, int $actorId): bool
    {
        $this->database->beginTransaction();
        $statement = $this->database->prepare('DELETE FROM sources WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) {
            $this->database->rollBack();
            return false;
        }
        $this->log($actorId, 'delete', $id);
        $this->database->commit();

        return true;
    }

    private function log(int $actorId, string $action, int $id): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO admin_activity_logs (actor_user_id, action, entity_type, entity_id, details)
             VALUES (:actor, :action, :entity, :id, :details)'
        );
        $statement->execute([
            'actor' => $actorId,
            'action' => $action,
            'entity' => 'source',
            'id' => $id,
            'details' => json_encode([], JSON_THROW_ON_ERROR),
        ]);
    }
}
