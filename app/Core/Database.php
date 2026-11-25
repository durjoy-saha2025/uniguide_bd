<?php
declare(strict_types=1);

namespace UniGuide\App\Core;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $loadConfiguration = require dirname(__DIR__, 2) . '/config/database.php';
        $configuration = $loadConfiguration();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $configuration['host'],
            $configuration['port'],
            $configuration['database']
        );

        self::$connection = new PDO(
            $dsn,
            $configuration['username'],
            $configuration['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );

        return self::$connection;
    }
}
