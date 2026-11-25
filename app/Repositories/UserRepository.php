<?php
declare(strict_types=1);

namespace UniGuide\App\Repositories;

use PDO;
use PDOException;

final class UserRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, name, email, password_hash, role, account_status
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function findPublicById(int $userId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, name, email, role
             FROM users
             WHERE id = :id AND account_status = :account_status
             LIMIT 1'
        );
        $statement->execute([
            'id' => $userId,
            'account_status' => 'active',
        ]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function findForAuthenticationById(int $userId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, name, email, password_hash, role, account_status
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function createStudent(string $name, string $email, string $passwordHash): ?int
    {
        try {
            $statement = $this->database->prepare(
                'INSERT INTO users (name, email, password_hash, role)
                 VALUES (:name, :email, :password_hash, :role)'
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => $passwordHash,
                'role' => 'student',
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return null;
            }

            throw $exception;
        }

        return (int) $this->database->lastInsertId();
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $statement = $this->database->prepare(
            'UPDATE users
             SET password_hash = :password_hash
             WHERE id = :id AND account_status = :account_status'
        );
        $statement->execute([
            'password_hash' => $passwordHash,
            'id' => $userId,
            'account_status' => 'active',
        ]);
    }

    public function deleteAccount(int $userId): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM users WHERE id = :id AND role = :role'
        );
        $statement->execute([
            'id' => $userId,
            'role' => 'student',
        ]);
    }
}
