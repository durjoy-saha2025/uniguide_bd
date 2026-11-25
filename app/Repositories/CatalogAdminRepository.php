<?php
declare(strict_types=1);

namespace UniGuide\App\Repositories;

use PDO;

final class CatalogAdminRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function list(
        string $resource,
        array $definition,
        string $query,
        array $filters,
        int $limit,
        int $offset,
        string $sort,
        string $direction
    ): array {
        [$where, $parameters] = $this->conditions($definition, $query, $filters);
        $table = $definition['table'];
        $count = $this->database->prepare('SELECT COUNT(*) FROM `' . $table . '`' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $columns = array_merge(['id'], array_keys($definition['rules']), ['created_at', 'updated_at']);
        $columns = array_values(array_unique(array_filter(
            $columns,
            static fn (string $column): bool => in_array($column, $definition['columns'], true)
        )));
        $select = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
        $statement = $this->database->prepare(
            'SELECT ' . $select . ' FROM `' . $table . '`' . $where
            . ' ORDER BY `' . $sort . '` ' . $direction . ', id DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return ['items' => $statement->fetchAll(), 'total' => $total];
    }

    public function find(string $resource, array $definition, int $id): ?array
    {
        $columns = implode(', ', array_map(
            static fn (string $column): string => '`' . $column . '`',
            $definition['columns']
        ));
        $statement = $this->database->prepare(
            'SELECT ' . $columns . ' FROM `' . $definition['table'] . '` WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();

        return $record === false ? null : $record;
    }

    public function sourceIsVerified(int $id): bool
    {
        $statement = $this->database->prepare(
            "SELECT 1 FROM sources WHERE id = :id AND verification_status = 'verified' LIMIT 1"
        );
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    public function create(string $resource, array $definition, array $data, int $actorId): int
    {
        $this->database->beginTransaction();
        if ($definition['created_by'] ?? false) {
            $data['created_by'] = $actorId;
        }
        $fields = array_keys($data);
        $columns = implode(', ', array_map(static fn (string $field): string => '`' . $field . '`', $fields));
        $placeholders = implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields));
        $statement = $this->database->prepare(
            'INSERT INTO `' . $definition['table'] . '` (' . $columns . ')
             VALUES (' . $placeholders . ')'
        );
        $statement->execute($data);
        $id = (int) $this->database->lastInsertId();
        $this->log($resource, 'create', $id, $actorId);
        $this->database->commit();

        return $id;
    }

    public function update(string $resource, array $definition, int $id, array $data, int $actorId): bool
    {
        $this->database->beginTransaction();
        $assignments = implode(', ', array_map(
            static fn (string $field): string => '`' . $field . '` = :' . $field,
            array_keys($data)
        ));
        $statement = $this->database->prepare(
            'UPDATE `' . $definition['table'] . '` SET ' . $assignments . ' WHERE id = :id'
        );
        $statement->execute($data + ['id' => $id]);
        if ($statement->rowCount() === 0 && $this->find($resource, $definition, $id) === null) {
            $this->database->rollBack();
            return false;
        }
        $this->log($resource, 'update', $id, $actorId);
        $this->database->commit();

        return true;
    }

    public function delete(string $resource, array $definition, int $id, int $actorId): bool
    {
        $this->database->beginTransaction();
        $statement = $this->database->prepare('DELETE FROM `' . $definition['table'] . '` WHERE id = :id');
        $statement->execute(['id' => $id]);
        if ($statement->rowCount() !== 1) {
            $this->database->rollBack();
            return false;
        }
        $this->log($resource, 'delete', $id, $actorId);
        $this->database->commit();

        return true;
    }

    private function conditions(array $definition, string $query, array $filters): array
    {
        $conditions = [];
        $parameters = [];
        if ($query !== '') {
            $search = [];
            foreach ($definition['search'] as $index => $column) {
                $parameter = ':search_' . $index;
                $search[] = '`' . $column . '` LIKE ' . $parameter;
                $parameters[$parameter] = '%' . $query . '%';
            }
            $conditions[] = '(' . implode(' OR ', $search) . ')';
        }
        foreach ($filters as $field => $value) {
            $parameter = ':filter_' . $field;
            $conditions[] = '`' . $field . '` = ' . $parameter;
            $parameters[$parameter] = $value;
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    private function log(string $resource, string $action, int $id, int $actorId): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO admin_activity_logs (actor_user_id, action, entity_type, entity_id, details)
             VALUES (:actor, :action, :entity, :id, :details)'
        );
        $statement->execute([
            'actor' => $actorId,
            'action' => $action,
            'entity' => $resource,
            'id' => $id,
            'details' => json_encode([], JSON_THROW_ON_ERROR),
        ]);
    }
}
