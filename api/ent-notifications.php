<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

// Gestion admin des notifications globales et push in-app.
user_require_role(['ADMIN', 'SUPERADMIN'], true);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function normalize_dt(?string $v): ?string
{
    if ($v === null) return null;
    $v = trim($v);
    if ($v === '') return null;
    return str_replace('T', ' ', $v);
}

function normalize_level(string $v): string
{
    $v = strtoupper(trim($v));
    return in_array($v, ['INFO', 'SUCCESS', 'WARNING', 'ERROR'], true) ? $v : 'INFO';
}

function normalize_scope(string $v): string
{
    $v = strtoupper(trim($v));
    return in_array($v, ['ALL', 'VOTER', 'ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], true) ? $v : 'ALL';
}

function normalize_target_url(?string $v): ?string
{
    if ($v === null) return null;
    $v = trim($v);
    if ($v === '') return null;
    return substr($v, 0, 255);
}

function notif_push_schema_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        // Verifie que la migration 011 est bien presente avant d utiliser les colonnes push.
        $target = (bool)$pdo->query("SHOW COLUMNS FROM notifications LIKE 'target_url'")->fetchColumn();
        $scope = (bool)$pdo->query("SHOW COLUMNS FROM notifications LIKE 'audience_scope'")->fetchColumn();
        $isPush = (bool)$pdo->query("SHOW COLUMNS FROM notifications LIKE 'is_push'")->fetchColumn();
        $count = (bool)$pdo->query("SHOW COLUMNS FROM notifications LIKE 'sent_count'")->fetchColumn();
        $sentAt = (bool)$pdo->query("SHOW COLUMNS FROM notifications LIKE 'sent_at'")->fetchColumn();
        $ready = $target && $scope && $isPush && $count && $sentAt;
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

function notif_dispatch_push(
    PDO $pdo,
    int $notificationId,
    string $title,
    string $body,
    string $level,
    string $scope,
    ?string $targetUrl,
    bool $sendNow
): int {
    if (!$sendNow) return 0;
    if (!notif_push_schema_ready($pdo)) return 0;

    try {
        // Fanout vers inbox utilisateur en respectant l audience choisie.
        $count = \Vote\Infrastructure\Composition\AppServices::userPush()->pushByScope(
            $pdo,
            $scope,
            $title,
            $body,
            $level,
            $targetUrl,
            'ADMIN_NOTIFICATION',
            null,
            $notificationId
        );
        $pdo->prepare('UPDATE notifications SET sent_count=?, sent_at=NOW() WHERE id=?')->execute([$count, $notificationId]);
        return $count;
    } catch (Throwable $e) {
        return 0;
    }
}

try {
    if ($method === 'GET') {
        if (notif_push_schema_ready($pdo)) {
            $rows = $pdo->query('
                SELECT id,title,body,level,is_active,target_url,audience_scope,is_push,sent_count,sent_at,starts_at,ends_at,created_at
                FROM notifications
                ORDER BY created_at DESC
            ')->fetchAll() ?: [];
        } else {
            // Compatibilite si migration push absente: on expose des valeurs par defaut.
            $rows = $pdo->query('
                SELECT id,title,body,level,is_active,starts_at,ends_at,created_at
                FROM notifications
                ORDER BY created_at DESC
            ')->fetchAll() ?: [];
            foreach ($rows as &$row) {
                $row['target_url'] = null;
                $row['audience_scope'] = 'ALL';
                $row['is_push'] = 0;
                $row['sent_count'] = 0;
                $row['sent_at'] = null;
            }
            unset($row);
        }
        json_success($rows);
    }

    csrf_require();

    if ($method === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) json_error('JSON invalide', 422);

        $title = trim((string)($data['title'] ?? ''));
        $body = (string)($data['body'] ?? '');
        $level = normalize_level((string)($data['level'] ?? 'INFO'));
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $starts = normalize_dt($data['starts_at'] ?? null);
        $ends = normalize_dt($data['ends_at'] ?? null);
        $targetUrl = normalize_target_url($data['target_url'] ?? null);
        $scope = normalize_scope((string)($data['audience_scope'] ?? 'ALL'));
        $isPush = !empty($data['is_push']) ? 1 : 0;
        $sendPushNow = $isPush && !empty($data['send_push_now']);

        if ($title === '') json_error('Titre requis', 422);

        if (notif_push_schema_ready($pdo)) {
            $stmt = $pdo->prepare('
                INSERT INTO notifications(title,body,target_url,audience_scope,level,is_active,is_push,sent_count,sent_at,starts_at,ends_at,created_by)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?)
            ');
            $stmt->execute([$title, $body, $targetUrl, $scope, $level, $isActive, $isPush, 0, null, $starts, $ends, user_current_id()]);
        } else {
            // Degrade proprement si la base est plus ancienne.
            $stmt = $pdo->prepare('
                INSERT INTO notifications(title,body,level,is_active,starts_at,ends_at,created_by)
                VALUES(?,?,?,?,?,?,?)
            ');
            $stmt->execute([$title, $body, $level, $isActive, $starts, $ends, user_current_id()]);
        }

        $id = (int)$pdo->lastInsertId();
        // Envoi immediat optionnel (sinon notification seulement stockee).
        $pushCount = notif_dispatch_push($pdo, $id, $title, $body, $level, $scope, $targetUrl, $sendPushNow);
        audit_event($pdo, 'NOTIF_CREATE', 'NOTIFICATION', $id, [
            'title' => $title,
            'scope' => $scope,
            'is_push' => $isPush,
            'push_count' => $pushCount,
        ]);
        json_success(['message' => 'Creee', 'id' => $id, 'push_count' => $pushCount], 201);
    }

    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_error('ID manquant', 422);

        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) json_error('JSON invalide', 422);

        $title = trim((string)($data['title'] ?? ''));
        $body = (string)($data['body'] ?? '');
        $level = normalize_level((string)($data['level'] ?? 'INFO'));
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $starts = normalize_dt($data['starts_at'] ?? null);
        $ends = normalize_dt($data['ends_at'] ?? null);
        $targetUrl = normalize_target_url($data['target_url'] ?? null);
        $scope = normalize_scope((string)($data['audience_scope'] ?? 'ALL'));
        $isPush = !empty($data['is_push']) ? 1 : 0;
        $sendPushNow = $isPush && !empty($data['send_push_now']);

        if ($title === '') json_error('Titre requis', 422);

        if (notif_push_schema_ready($pdo)) {
            $stmt = $pdo->prepare('
                UPDATE notifications
                SET title=?, body=?, target_url=?, audience_scope=?, level=?, is_active=?, is_push=?, starts_at=?, ends_at=?
                WHERE id=?
            ');
            $stmt->execute([$title, $body, $targetUrl, $scope, $level, $isActive, $isPush, $starts, $ends, $id]);
        } else {
            $stmt = $pdo->prepare('
                UPDATE notifications
                SET title=?, body=?, level=?, is_active=?, starts_at=?, ends_at=?
                WHERE id=?
            ');
            $stmt->execute([$title, $body, $level, $isActive, $starts, $ends, $id]);
        }

        $pushCount = notif_dispatch_push($pdo, $id, $title, $body, $level, $scope, $targetUrl, $sendPushNow);
        audit_event($pdo, 'NOTIF_UPDATE', 'NOTIFICATION', $id, [
            'title' => $title,
            'scope' => $scope,
            'is_push' => $isPush,
            'push_count' => $pushCount,
        ]);
        json_success(['message' => 'Mise a jour', 'push_count' => $pushCount]);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_error('ID manquant', 422);
        $pdo->prepare('DELETE FROM notifications WHERE id=?')->execute([$id]);
        audit_event($pdo, 'NOTIF_DELETE', 'NOTIFICATION', $id, []);
        json_success(['message' => 'Supprimee']);
    }

    json_error('Methode non autorisee', 405);
} catch (Throwable $e) {
    $debug = env_get('APP_ENV', 'local') !== 'production';
    json_error($debug ? $e->getMessage() : 'Erreur serveur', 500);
}
