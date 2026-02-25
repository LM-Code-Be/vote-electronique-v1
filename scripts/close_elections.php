<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/audit.php';
require_once __DIR__ . '/../app/election_auto_close.php';

function out(string $msg): void
{
    fwrite(STDOUT, $msg . PHP_EOL);
}

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../app/db.php';

// Le script CLI reutilise strictement la meme logique que le portail/API.
$closed = election_auto_close_expired(
    $pdo,
    static function (int $electionId, string $title): void {
        out("Cloture auto: #{$electionId} {$title}");
    }
);

out("OK: elections closed={$closed}");
exit(0);
