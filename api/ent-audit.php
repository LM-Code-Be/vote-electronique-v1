<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

user_require_role(['SUPERADMIN'], true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_error('Méthode non autorisée', 405);
}

$action = trim((string)($_GET['action'] ?? ''));
$limit = (int)($_GET['limit'] ?? 500);
$limit = max(1, min(2000, $limit));

$where = [];
$params = [];
if ($action !== '') {
    $where[] = 'a.action = ?';
    $params[] = $action;
}

$sql = "
    SELECT
        a.created_at,
        u.username AS actor,
        a.action,
        a.entity_type,
        a.entity_id,
        a.metadata_json
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.actor_user_id
";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY a.created_at DESC LIMIT ' . $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];
json_success($rows);
