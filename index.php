<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/user_auth.php';

try {
    /** @var PDO $pdo */
    $pdo = require __DIR__ . '/app/db.php';
    user_enforce_session($pdo, false);
} catch (Throwable $e) {
    // If DB is unavailable, keep redirect behavior to login page.
}

if (user_is_logged_in()) {
    if (user_has_role('ADMIN') || user_has_role('SUPERADMIN') || user_has_role('SCRUTATEUR')) {
        header('Location: ' . app_url('/enterprise/admin/dashboard.php'));
        exit;
    }
    header('Location: ' . app_url('/enterprise/elections.php'));
    exit;
}

header('Location: ' . app_url('/enterprise/login.php'));
exit;
// Gestion single-tenant: point d'entree unique vers le portail enterprise.
