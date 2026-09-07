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
    protected static function getPdo(string|null $configFilePath = null): PDO
    {
        if (self::$pdo === null) {
            if (empty($configFilePath)) {
                // __DIR__ is assumed to be /path/to/<project>/<domain>/vendor/ghbarratt/sitesketchphp/src/Traits
                $configFilePath = __DIR__ . '/../../../../../../shared/config/database.php';
            }

            if (!file_exists($configFilePath)) {
                throw new RuntimeException("Database configuration file not found at: {$configFilePath}");
            }
            $config = require $configFilePath;

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
