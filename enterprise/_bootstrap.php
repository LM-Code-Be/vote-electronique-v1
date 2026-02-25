<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../app/db.php';

require_once __DIR__ . '/../app/user_auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/audit.php';
require_once __DIR__ . '/../app/election_auto_close.php';

user_enforce_session($pdo, false);

try {
    // Meme logique que l API: on evite qu un scrutin reste publie apres sa fin.
    election_auto_close_expired($pdo);
} catch (Throwable $e) {
    // Non bloquant pour le portail.
}
