<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    user_require_role(['ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], true);
} else {
    user_require_role(['ADMIN', 'SUPERADMIN'], true);
}

try {
    if ($method === 'GET') {
        $rows = $pdo->query("SELECT id,name,'DEPT' AS type,created_at FROM `groups` ORDER BY name")->fetchAll() ?: [];
        json_success($rows);
    }

    csrf_require();

    if ($method === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) json_error('JSON invalide', 422);
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') json_error('Nom requis', 422);

        $stmt = $pdo->prepare("INSERT INTO `groups`(name,type) VALUES(?, 'DEPT')");
        $stmt->execute([$name]);
        $id = (int)$pdo->lastInsertId();
        audit_event($pdo, 'GROUP_CREATE', 'GROUP', $id, ['name' => $name, 'type' => 'DEPT']);
        json_success(['message' => 'Cree', 'id' => $id], 201);
    }

    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_error('ID manquant', 422);
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) json_error('JSON invalide', 422);
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') json_error('Nom requis', 422);

        $stmt = $pdo->prepare("UPDATE `groups` SET name=?, type='DEPT' WHERE id=?");
        $stmt->execute([$name, $id]);
        audit_event($pdo, 'GROUP_UPDATE', 'GROUP', $id, ['name' => $name, 'type' => 'DEPT']);
        json_success(['message' => 'Mis a jour']);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_error('ID manquant', 422);
        $pdo->prepare('DELETE FROM `groups` WHERE id=?')->execute([$id]);
        audit_event($pdo, 'GROUP_DELETE', 'GROUP', $id, []);
        json_success(['message' => 'Supprime']);
    }

    json_error('Methode non autorisee', 405);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? null) === 1062) {
        json_error('Groupe deja existant', 409);
    }
    $debug = env_get('APP_ENV', 'local') !== 'production';
    json_error($debug ? $e->getMessage() : 'Erreur serveur', 500);
} catch (Throwable $e) {
    $debug = env_get('APP_ENV', 'local') !== 'production';
    json_error($debug ? $e->getMessage() : 'Erreur serveur', 500);
}
