<?php

declare(strict_types=1);

namespace Sitesketch\Traits;

use PDO;
use RuntimeException;

trait DatabaseHandlerTrait
{
    private static ?PDO $pdo = null;
    private static string $driver = 'pgsql'; // PostgreSQL is the default but can be set

    // Get the shared PDO instance, establishing the connection just in time if needed
    protected static function getPdo(string|null $credentialsFilepath = null): PDO
    {
        if (self::$pdo === null) {
            $sourcesToTry = [
                $credentialsFilepath,
                __DIR__ . '/../../../../../' . $credentialsFilepath,
                __DIR__ . '/../../../../../src/database_credentials.php',
                __DIR__ . '/../../../../../database_credentials.php',
                __DIR__ . '/../../../../../.env',
                'env',
            ];
            $keysToConvert = [
                'username' => 'user',
                'db_user' => 'user',
                'db_username' => 'user',
                'pass' => 'password',
                'db_password' => 'password',
                'db_pass' => 'password',
                'hostname' => 'host',
                'db_host' => 'host',
                'db_hostname' => 'host',
                'db_port' => 'port',
                'database' => 'dbname',
                'db_database' => 'dbname',
                'name' => 'dbname',
                'db_name' => 'dbname',
                'db_dbname' => 'dbname',
                'db_sslmode' => 'sslmode',
            ];
            foreach ($sourcesToTry as $stt) {
                if ($stt == 'env') {
                    if (!empty($_ENV['DB_PASSWORD'])) {
                        $potentials = $_ENV;
                    } elseif (!empty($_SERVER['DB_PASSWORD'])) {
                        $potentials = $_SERVER;
                    } elseif (!empty(getenv('DB_PASSWORD'))) {
                        foreach ($toPull as $tpk => $tpv) {
                            if (!empty(getenv(strtoupper($tpk)))) {
                                $potentials[$tpv] = getenv(strtoupper($tpk));
                            }
                        }
                    }
                } elseif (!empty($stt) && is_file($stt)) {
                    // Assume a filename with a PHP extension can be included/required
                    if (str_ends_with($stt, '.php')) {
                        $potentials = require $stt;
                    } else {
                        // Otherwise, assume a text-based file with up to one setting/definition per line
                        $pattern = '/^([a-zA-Z_\-]+)\s*(=|:|(?<=\s))\s*(.*)$/';
                        $lines = file($stt, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        $potentials = [];
                        foreach ($lines as $l) {
                            $l = trim($l);
                            if ($l === '' || str_starts_with($l, '#') || str_starts_with($l, '//')) {
                                continue;
                            }

                            if (preg_match($pattern, $l, $matches)) {
                                $key = strtolower($matches[1]);
                                $rawValue = trim($matches[3]);

                                $value = '';
                                $inQuote = null; // Tracks if we are inside ' or "
                                $len = strlen($rawValue);

                                for ($i = 0; $i < $len; $i++) {
                                    $char = $rawValue[$i];
                                    $nextChar = $rawValue[$i + 1] ?? '';

                                    if ($char === '\\' && $inQuote !== null) {
                                        $value .= $char . $nextChar;
                                        $i++; // Skip the escaped character
                                        continue;
                                    }

                                    if (($char === '"' || $char === "'") && ($inQuote === null || $inQuote === $char)) {
                                        $inQuote = ($inQuote === null) ? $char : null;
                                    }

                                    if ($inQuote === null) {
                                        if ($char === '#' || ($char === '/' && $nextChar === '/')) {
                                            break; // Stop parsing right here; the rest is a comment
                                        }
                                    }

                                    $value .= $char;
                                }
                                $value = trim($value);

                                // Evaluate the quoting state of the cleaned value
                                $isDoubleQuoted = str_starts_with($value, '"') && str_ends_with($value, '"');
                                $isSingleQuoted = str_starts_with($value, "'") && str_ends_with($value, "'");

                                if ($isDoubleQuoted || $isSingleQuoted) {
                                    $value = substr($value, 1, -1);
                                    if ($isDoubleQuoted) {
                                        $value = stripcslashes($value);
                                    }
                                }

                                $potentials[$key] = $value;
                            }
                        }
                    }
                }

                if (!empty($potentials)) {
                    array_change_key_case($potentials, CASE_LOWER);
                    foreach ($potentials as $pk => $pv) {
                        if (array_key_exists($pk, $keysToConvert)) {
                            $potentials[$keysToConvert[$pk]] = $pv;
                            unset($potentials[$pk]);
                        }
                    }

                    if (
                        is_array($potentials)
                        && array_key_exists('user', $potentials)
                        && array_key_exists('password', $potentials)
                    ) {
                        if (empty($potentials['host'])) {
                            $potentials['host'] = 'localhost';
                        }
                        $dsn = self::$driver . ':host=' . $potentials['host'];
                        $dsnElementNames = ['port', 'dbname', 'sslmode'];
                        foreach ($dsnElementNames as $den) {
                            if (!empty($potentials[$den])) {
                                $dsn .= ';' . $den . '=' . $potentials[$den];
                            }
                        }
                        $username = $potentials['user'];
                        $password = $potentials['password'];
                        break;
                    }
                }
            }

            if (empty($dsn)) {
                throw new RuntimeException('Unable to find database credentials');
            }

            try {
                self::$pdo = new PDO(
                    $dsn,
                    $username,
                    $password,
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
