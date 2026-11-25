<?php
declare(strict_types=1);

namespace UniGuide\App\Repositories;

use PDO;

final class CatalogRepository
{
    private const JSON_COLUMNS = [
        'accepted_ssc_groups',
        'accepted_hsc_groups',
        'subject_prerequisites',
        'eligibility_rules',
    ];

    public function __construct(private PDO $database)
    {
    }

    public function list(string $resource, array $definition, string $query, array $filters, int $limit, int $offset): array
    {
        [$where, $parameters] = $this->conditions($resource, $definition, $query, $filters);
        $table = $definition['table'];
        $join = $resource === 'guides' ? '' : ' INNER JOIN sources s ON s.id = r.source_id';
        $columns = array_map(static fn (string $field): string => 'r.`' . $field . '`', $definition['public_fields']);
        if ($resource !== 'guides') {
            $columns[] = 's.title AS source_title';
            $columns[] = 's.url AS source_url';
            $columns[] = 's.publisher AS source_publisher';
        }
        $statement = $this->database->prepare(
            'SELECT ' . implode(', ', $columns) . ' FROM `' . $table . '` r' . $join . $where
            . ' ORDER BY ' . $definition['public_order'] . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $items = $statement->fetchAll();
        $this->decodeJson($items);

        $count = $this->database->prepare('SELECT COUNT(*) FROM `' . $table . '` r' . $join . $where);
        $count->execute($parameters);

        return ['items' => $items, 'total' => (int) $count->fetchColumn()];
    }

    public function find(string $resource, array $definition, int $id): ?array
    {
        $result = $this->list($resource, $definition, '', ['id' => $id], 1, 0);

        return $result['items'][0] ?? null;
    }

    private function conditions(string $resource, array $definition, string $query, array $filters): array
    {
        $conditions = [];
        $parameters = [];
        switch ($resource) {
            case 'programs':
                $conditions[] = "r.status = 'published' AND r.verification_status = 'verified' AND s.verification_status = 'verified'";
                $conditions[] = "EXISTS (SELECT 1 FROM universities u INNER JOIN sources us ON us.id = u.source_id AND us.verification_status = 'verified' WHERE u.id = r.university_id AND u.status = 'published' AND u.verification_status = 'verified')";
                break;
            case 'program-fees':
            case 'admission-requirements':
                $conditions[] = "r.verification_status = 'verified' AND s.verification_status = 'verified'";
                $conditions[] = "EXISTS (
                    SELECT 1 FROM programs p
                    INNER JOIN sources ps ON ps.id = p.source_id AND ps.verification_status = 'verified'
                    INNER JOIN universities u ON u.id = p.university_id AND u.status = 'published' AND u.verification_status = 'verified'
                    INNER JOIN sources us ON us.id = u.source_id AND us.verification_status = 'verified'
                    WHERE p.id = r.program_id AND p.status = 'published' AND p.verification_status = 'verified'
                )";
                break;
            case 'scholarships':
                $conditions[] = "r.status = 'published' AND r.verification_status = 'verified' AND s.verification_status = 'verified'";
                $conditions[] = "EXISTS (SELECT 1 FROM universities u INNER JOIN sources us ON us.id = u.source_id AND us.verification_status = 'verified' WHERE u.id = r.university_id AND u.status = 'published' AND u.verification_status = 'verified')";
                $conditions[] = "(r.program_id IS NULL OR EXISTS (
                    SELECT 1 FROM programs p
                    INNER JOIN sources ps ON ps.id = p.source_id AND ps.verification_status = 'verified'
                    WHERE p.id = r.program_id AND p.status = 'published' AND p.verification_status = 'verified'
                ))";
                break;
            case 'rankings':
                $conditions[] = "r.verification_status = 'verified' AND s.verification_status = 'verified'";
                $conditions[] = "EXISTS (SELECT 1 FROM universities u INNER JOIN sources us ON us.id = u.source_id AND us.verification_status = 'verified' WHERE u.id = r.university_id AND u.status = 'published' AND u.verification_status = 'verified')";
                break;
            case 'news':
                $conditions[] = "r.workflow_status = 'published' AND r.verification_status = 'verified' AND s.verification_status = 'verified'";
                $conditions[] = "(r.university_id IS NULL OR EXISTS (SELECT 1 FROM universities u INNER JOIN sources us ON us.id = u.source_id AND us.verification_status = 'verified' WHERE u.id = r.university_id AND u.status = 'published' AND u.verification_status = 'verified'))";
                break;
            case 'guides':
                $conditions[] = "r.status = 'published'";
                break;
        }
        if ($query !== '') {
            $search = [];
            foreach ($definition['search'] as $index => $column) {
                $parameter = ':query_' . $index;
                $search[] = 'r.`' . $column . '` LIKE ' . $parameter;
                $parameters[$parameter] = '%' . $query . '%';
            }
            $conditions[] = '(' . implode(' OR ', $search) . ')';
        }
        foreach ($filters as $field => $value) {
            if ($field === 'id') {
                $conditions[] = 'r.id = :filter_id';
                $parameters[':filter_id'] = $value;
            } else {
                $conditions[] = 'r.`' . $field . '` = :filter_' . $field;
                $parameters[':filter_' . $field] = $value;
            }
        }

        return [' WHERE ' . implode(' AND ', $conditions), $parameters];
    }

    private function decodeJson(array &$items): void
    {
        foreach ($items as &$item) {
            foreach (self::JSON_COLUMNS as $column) {
                if (!array_key_exists($column, $item) || $item[$column] === null) {
                    continue;
                }
                $item[$column] = json_decode($item[$column], true, 512, JSON_THROW_ON_ERROR);
            }
        }
        unset($item);
    }
}
