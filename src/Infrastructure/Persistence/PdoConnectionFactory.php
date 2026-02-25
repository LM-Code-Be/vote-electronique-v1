<?php
declare(strict_types=1);

namespace Vote\Infrastructure\Persistence;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class PdoConnectionFactory
{
    public function create(): PDO
    {
        $dbHost = $this->env('DB_HOST', 'localhost');
        $dbName = $this->env('DB_NAME', 'vote_electronique');
        $dbUser = $this->env('DB_USER', 'root');
        $dbPass = $this->env('DB_PASS', '');
        $dbChar = $this->env('DB_CHAR', 'utf8mb4');

        $dbCollation = strcasecmp($dbChar, 'utf8mb4') === 0 ? 'utf8mb4_unicode_ci' : null;
        $dbInitSql = $dbCollation ? "SET NAMES {$dbChar} COLLATE {$dbCollation}" : "SET NAMES {$dbChar}";
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbChar}";

        try {
            return new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => $dbInitSql,
            ]);
        } catch (PDOException $e) {
            $debug = $this->env('APP_ENV', 'local') !== 'production';
            throw new RuntimeException(
                $debug ? ('Connexion DB impossible : ' . $e->getMessage()) : 'Connexion DB impossible'
            );
        }
    }

    private function env(string $key, string $default): string
    {
        try {
            if (function_exists('env_get')) {
                return (string)(env_get($key, $default) ?? $default);
            }
        } catch (Throwable $e) {
            // Ignore and use default.
        }

        return $default;
    }
}
