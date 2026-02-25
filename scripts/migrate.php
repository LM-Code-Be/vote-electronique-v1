<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../app/db.php';

function stdout(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function stderr(string $msg): void
{
    fwrite(STDERR, $msg . PHP_EOL);
}

function split_sql_statements(string $sql): array
{
    // Remove UTF-8 BOM if present
    if (str_starts_with($sql, "\xEF\xBB\xBF")) {
        $sql = substr($sql, 3);
    }

    $statements = [];
    $buffer = '';

    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($ch === "\n") {
                $inLineComment = false;
            }
            $buffer .= $ch;
            continue;
        }

        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') {
                $inBlockComment = false;
                $buffer .= '*/';
                $i++;
                continue;
            }
            $buffer .= $ch;
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick) {
            // Start of line comment: -- or #
            if ($ch === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
                $inLineComment = true;
                $buffer .= $ch;
                continue;
            }
            if ($ch === '#') {
                $inLineComment = true;
                $buffer .= $ch;
                continue;
            }
            // Start of block comment: /*
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $buffer .= '/*';
                $i++;
                continue;
            }
        }

        if ($ch === "'" && !$inDouble && !$inBacktick) {
            // Handle escaped single quote ''
            if ($inSingle && $next === "'") {
                $buffer .= "''";
                $i++;
                continue;
            }
            $inSingle = !$inSingle;
            $buffer .= $ch;
            continue;
        }

        if ($ch === '"' && !$inSingle && !$inBacktick) {
            $inDouble = !$inDouble;
            $buffer .= $ch;
            continue;
        }

        if ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
            $buffer .= $ch;
            continue;
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function ensure_migrations_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
          id INT AUTO_INCREMENT PRIMARY KEY,
          filename VARCHAR(255) NOT NULL UNIQUE,
          applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function load_applied(PDO $pdo): array
{
    try {
        $rows = $pdo->query('SELECT filename FROM schema_migrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_map('strval', $rows ?: []));
    } catch (Throwable $e) {
        return [];
    }
}

function list_migration_files(string $dir): array
{
    $files = glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    sort($files, SORT_STRING);
    return $files;
}

function apply_migration(PDO $pdo, string $filePath): void
{
    $sql = file_get_contents($filePath);
    if ($sql === false) {
        throw new RuntimeException("Impossible de lire $filePath");
    }

    $statements = split_sql_statements($sql);
    if (!$statements) {
        return;
    }

    // Note: MySQL DDL auto-commits; we execute statements sequentially.
    foreach ($statements as $stmt) {
        // Some statements (e.g. EXECUTE of a prepared SELECT) can return result sets.
        // If not fully consumed, MySQL can throw "2014 Cannot execute queries while other unbuffered queries are active".
        if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN|EXECUTE)\b/i', $stmt)) {
            $q = $pdo->query($stmt);
            if ($q instanceof PDOStatement) {
                $q->fetchAll();
                $q->closeCursor();
            }
        } else {
            $pdo->exec($stmt);
        }
    }
    $ins = $pdo->prepare('INSERT INTO schema_migrations(filename) VALUES(?)');
    $ins->execute([basename($filePath)]);
}

$cmd = $argv[1] ?? 'up';
$migrationsDir = __DIR__ . '/../migrations';

ensure_migrations_table($pdo);

if ($cmd === 'status') {
    $applied = load_applied($pdo);
    $files = list_migration_files($migrationsDir);
    stdout('Migrations:');
    foreach ($files as $f) {
        $name = basename($f);
        $mark = in_array($name, $applied, true) ? '[x]' : '[ ]';
        stdout("  $mark $name");
    }
    exit(0);
}

if ($cmd !== 'up') {
    stderr("Usage: php scripts/migrate.php [up|status]");
    exit(2);
}

$applied = load_applied($pdo);
$files = list_migration_files($migrationsDir);

$pending = array_values(array_filter($files, fn ($f) => !in_array(basename($f), $applied, true)));
if (!$pending) {
    stdout('Aucune migration à appliquer.');
    exit(0);
}

foreach ($pending as $file) {
    $name = basename($file);
    stdout("Applying $name ...");
    apply_migration($pdo, $file);
    stdout("OK: $name");
}

stdout('Terminé.');
