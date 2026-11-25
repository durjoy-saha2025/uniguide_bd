<?php
declare(strict_types=1);

namespace UniGuide\App\Core;

use PDO;

final class AuthRateLimiter
{
    private const MAX_FAILURES = 5;
    private const WINDOW_MINUTES = 15;
    private const BLOCK_MINUTES = 15;

    public function __construct(private PDO $database)
    {
    }

    public function isBlocked(string $email, string $ipAddress): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM auth_login_attempts
             WHERE identifier_hash = :identifier AND blocked_until > CURRENT_TIMESTAMP LIMIT 1'
        );
        $statement->execute(['identifier' => $this->identifier($email, $ipAddress)]);

        return $statement->fetchColumn() !== false;
    }

    public function recordFailure(string $email, string $ipAddress): void
    {
        $identifier = $this->identifier($email, $ipAddress);
        $this->database->beginTransaction();
        $ensure = $this->database->prepare(
            'INSERT IGNORE INTO auth_login_attempts (identifier_hash, failures, window_started_at)
             VALUES (:identifier, 0, CURRENT_TIMESTAMP)'
        );
        $ensure->execute(['identifier' => $identifier]);
        $select = $this->database->prepare(
            'SELECT failures, window_started_at FROM auth_login_attempts
             WHERE identifier_hash = :identifier FOR UPDATE'
        );
        $select->execute(['identifier' => $identifier]);
        $current = $select->fetch();
        if ($current === false) {
            $this->database->rollBack();
            throw new \RuntimeException('The login attempt record could not be locked.');
        }

        $windowExpired = strtotime($current['window_started_at']) < time() - self::WINDOW_MINUTES * 60;
        $failures = $windowExpired ? 1 : (int) $current['failures'] + 1;
        $blocked = $failures >= self::MAX_FAILURES;
        $update = $this->database->prepare(
            'UPDATE auth_login_attempts
             SET failures = :failures,
                 window_started_at = IF(:reset_window = 1, CURRENT_TIMESTAMP, window_started_at),
                 blocked_until = IF(:blocked = 1, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL '
                . self::BLOCK_MINUTES . ' MINUTE), NULL)
             WHERE identifier_hash = :identifier'
        );
        $update->execute([
            'failures' => $failures,
            'reset_window' => $windowExpired ? 1 : 0,
            'blocked' => $blocked ? 1 : 0,
            'identifier' => $identifier,
        ]);
        $this->database->commit();

        if (random_int(1, 50) === 1) {
            $this->database->exec(
                'DELETE FROM auth_login_attempts
                 WHERE updated_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)
                   AND (blocked_until IS NULL OR blocked_until < CURRENT_TIMESTAMP)'
            );
        }
    }

    public function clear(string $email, string $ipAddress): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM auth_login_attempts WHERE identifier_hash = :identifier'
        );
        $statement->execute(['identifier' => $this->identifier($email, $ipAddress)]);
    }

    private function identifier(string $email, string $ipAddress): string
    {
        $pepper = getenv('DB_PASSWORD');
        if (!is_string($pepper) || $pepper === '') {
            throw new \RuntimeException('A non-empty database password is required for login throttling.');
        }

        return hash_hmac('sha256', strtolower(trim($email)) . "\0" . $ipAddress, $pepper);
    }
}
