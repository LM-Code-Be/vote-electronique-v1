<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

user_require_role(['SUPERADMIN'], true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_error('Méthode non autorisée', 405);
}

$rows = $pdo->query('SELECT code,label,created_at FROM roles ORDER BY code')->fetchAll() ?: [];
json_success($rows);
