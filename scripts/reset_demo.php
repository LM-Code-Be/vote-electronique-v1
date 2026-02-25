<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

function err(string $msg): void
{
    fwrite(STDERR, $msg . PHP_EOL);
}

$opt = getopt('', ['yes', 'keep-uploads']);
$confirmed = array_key_exists('yes', $opt);
$keepUploads = array_key_exists('keep-uploads', $opt);

if (!$confirmed) {
    err('Usage: php scripts/reset_demo.php --yes [--keep-uploads]');
    err('Attention: supprime les donnees metier (elections, votes, users, audit...).');
    exit(2);
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../app/db.php';

$tables = [
    'ballot_items',
    'ballots',
    'participations',
    'voter_roll',
    'election_groups',
    'candidates',
    'elections',
    'user_groups',
    'user_roles',
    'password_resets',
    'login_attempts',
    'user_sessions',
    'audit_logs',
    'notifications',
    'users',
    'groups',
    'voters',
    'votes',
    'logs',
    'admin_users',
];

$existsStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = ?
");

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $table) {
        $existsStmt->execute([$table]);
        $exists = (int)$existsStmt->fetchColumn() > 0;
        if (!$exists) {
            continue;
        }
        $pdo->exec('TRUNCATE TABLE `' . str_replace('`', '``', $table) . '`');
        out('TRUNCATE OK: ' . $table);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
} catch (Throwable $e) {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $ignored) {
        // ignore
    }
    err('Erreur reset: ' . $e->getMessage());
    exit(1);
}

if (!$keepUploads) {
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if (is_string($uploadsDir) && is_dir($uploadsDir)) {
        $items = scandir($uploadsDir);
        if (is_array($items)) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item === '.htaccess' || $item === '.gitkeep') {
                    continue;
                }
                $path = $uploadsDir . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
        out('Uploads nettoyes (hors .htaccess/.gitkeep)');
    }
}

out('Reset termine.');
out('Etapes suivantes:');
out('1) php scripts/migrate.php status');
out('2) recreer les comptes demo via scripts/user_create.php');

