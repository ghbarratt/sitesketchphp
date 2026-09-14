<?php

declare(strict_types=1);

namespace Sitesketch\Traits;

use PDO;
use RuntimeException;

trait DatabaseHandlerTrait
{
    private static ?PDO $pdo = null;

    /**
     * Get the shared PDO instance, establishing the connection JIT if needed.
     */
    protected static function getPdo(string|null $credentialsFilepath = null): PDO
    {
        if (self::$pdo === null) {
            if (empty($credentialsFilepath)) {
                $credentialsFilepath = __DIR__ . '/../../../../../src/database_credentials.php';
            }

            if (!file_exists($credentialsFilepath)) {
                throw new RuntimeException('Database credentials file not found at: ' . $credentialsFilepath);
            }
            $config = require $credentialsFilepath;

            try {
                self::$pdo = new PDO(
                    $config['dsn'],
                    $config['user'],
                    $config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            } catch (\PDOException $e) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
            }
        }

        return self::$pdo;
    }

    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}
