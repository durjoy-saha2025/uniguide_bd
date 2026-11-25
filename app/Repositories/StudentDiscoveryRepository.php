<?php
declare(strict_types=1);

namespace UniGuide\App\Repositories;

use PDO;

final class StudentDiscoveryRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function listSaved(int $userId): array
    {
        $statement = $this->database->prepare(
            'SELECT u.id, u.slug, u.name, u.city, u.campus_type, u.website_url,
                    s.title AS source_title, s.url AS source_url, s.publisher AS source_publisher
             FROM saved_universities saved
             INNER JOIN universities u ON u.id = saved.university_id
             INNER JOIN sources s ON s.id = u.source_id AND s.verification_status = :source_status
             WHERE saved.user_id = :user_id AND u.status = :status
                   AND u.verification_status = :verification_status
             ORDER BY saved.created_at DESC, u.name ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'source_status' => 'verified',
            'status' => 'published',
            'verification_status' => 'verified',
        ]);

        return $statement->fetchAll();
    }

    public function listComparison(int $userId): array
    {
        $statement = $this->database->prepare(
            'SELECT u.id, u.slug, u.name, u.city, u.campus_type, u.website_url,
                    s.title AS source_title, s.url AS source_url, s.publisher AS source_publisher
             FROM student_comparison_lists lists
             INNER JOIN student_comparison_items items ON items.comparison_id = lists.id
             INNER JOIN universities u ON u.id = items.university_id
             INNER JOIN sources s ON s.id = u.source_id AND s.verification_status = :source_status
             WHERE lists.user_id = :user_id AND u.status = :status
                   AND u.verification_status = :verification_status
             ORDER BY items.created_at ASC, u.name ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'source_status' => 'verified',
            'status' => 'published',
            'verification_status' => 'verified',
        ]);

        return $statement->fetchAll();
    }

    public function addSaved(int $userId, int $universityId): bool
    {
        if (!$this->isPublicUniversity($universityId)) {
            return false;
        }
        $statement = $this->database->prepare(
            'INSERT IGNORE INTO saved_universities (user_id, university_id)
             VALUES (:user_id, :university_id)'
        );
        $statement->execute(['user_id' => $userId, 'university_id' => $universityId]);

        return true;
    }

    public function removeSaved(int $userId, int $universityId): bool
    {
        $statement = $this->database->prepare(
            'DELETE FROM saved_universities WHERE user_id = :user_id AND university_id = :university_id'
        );
        $statement->execute(['user_id' => $userId, 'university_id' => $universityId]);

        return $statement->rowCount() > 0;
    }

    public function addComparison(int $userId, int $universityId): string
    {
        if (!$this->isPublicUniversity($universityId)) {
            return 'not_found';
        }
        $this->database->beginTransaction();
        $lock = $this->database->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $userId]);
        $list = $this->database->prepare(
            'SELECT id FROM student_comparison_lists WHERE user_id = :user_id ORDER BY id ASC LIMIT 1'
        );
        $list->execute(['user_id' => $userId]);
        $comparisonId = $list->fetchColumn();
        if ($comparisonId === false) {
            $createList = $this->database->prepare(
                'INSERT INTO student_comparison_lists (user_id, name) VALUES (:user_id, :name)'
            );
            $createList->execute(['user_id' => $userId, 'name' => 'My comparison']);
            $comparisonId = (int) $this->database->lastInsertId();
        } else {
            $comparisonId = (int) $comparisonId;
        }

        $existing = $this->database->prepare(
            'SELECT 1 FROM student_comparison_items
             WHERE comparison_id = :comparison_id AND university_id = :university_id'
        );
        $existing->execute(['comparison_id' => $comparisonId, 'university_id' => $universityId]);
        if ($existing->fetchColumn() !== false) {
            $this->database->commit();
            return 'already_added';
        }
        $count = $this->database->prepare(
            'SELECT COUNT(*) FROM student_comparison_items WHERE comparison_id = :comparison_id'
        );
        $count->execute(['comparison_id' => $comparisonId]);
        if ((int) $count->fetchColumn() >= 4) {
            $this->database->rollBack();
            return 'limit_reached';
        }
        $insert = $this->database->prepare(
            'INSERT INTO student_comparison_items (comparison_id, university_id)
             VALUES (:comparison_id, :university_id)'
        );
        $insert->execute(['comparison_id' => $comparisonId, 'university_id' => $universityId]);
        $this->database->commit();

        return 'added';
    }

    public function removeComparison(int $userId, int $universityId): bool
    {
        $statement = $this->database->prepare(
            'DELETE items FROM student_comparison_items items
             INNER JOIN student_comparison_lists lists ON lists.id = items.comparison_id
             WHERE lists.user_id = :user_id AND items.university_id = :university_id'
        );
        $statement->execute(['user_id' => $userId, 'university_id' => $universityId]);

        return $statement->rowCount() > 0;
    }

    private function isPublicUniversity(int $universityId): bool
    {
        $statement = $this->database->prepare(
            "SELECT 1 FROM universities u
             INNER JOIN sources s ON s.id = u.source_id AND s.verification_status = 'verified'
             WHERE u.id = :id AND u.status = 'published' AND u.verification_status = 'verified'
             LIMIT 1"
        );
        $statement->execute(['id' => $universityId]);

        return $statement->fetchColumn() !== false;
    }
}
