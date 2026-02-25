<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/response.php';
require_once __DIR__ . '/../app/user_auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/audit.php';
require_once __DIR__ . '/../app/logger.php';
require_once __DIR__ . '/../app/election_auto_close.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    json_no_content();
}

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/../app/db.php';
} catch (Throwable $e) {
    $debug = env_get('APP_ENV', 'local') !== 'production';
    json_error($debug ? $e->getMessage() : 'Erreur serveur', 500);
}

try {
    // Cloture preventive des scrutins expires a chaque hit API.
    election_auto_close_expired($pdo);
} catch (Throwable $e) {
    // Non bloquant pour les endpoints.
}

user_enforce_session($pdo, true);

return $pdo;
