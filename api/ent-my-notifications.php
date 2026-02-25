<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

// Endpoint personnel: toute session authentifiee peut lire sa propre inbox.
user_require_login(true);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = (int)(user_current_id() ?? 0);
if ($userId <= 0) {
    json_error('Non authentifie', 401);
}

function user_notifs_ready(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        // Verifie la presence de la table inbox introduite par la migration 011.
        $st = $pdo->query("SHOW TABLES LIKE 'user_notifications'");
        $ready = (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

try {
    if ($method === 'GET') {
        if (!user_notifs_ready($pdo)) {
            // Compatibilite: si la table n existe pas, on renvoie une inbox vide.
            json_success([
                'unread_count' => 0,
                'latest_id' => 0,
                'items' => [],
            ]);
        }

        $limit = (int)($_GET['limit'] ?? 20);
        if ($limit < 1) $limit = 20;
        if ($limit > 50) $limit = 50;

        $sinceId = (int)($_GET['since_id'] ?? 0);
        $onlyUnread = !empty($_GET['unread_only']);

        $where = 'WHERE user_id=?';
        $params = [$userId];
        if ($sinceId > 0) {
            $where .= ' AND id > ?';
            $params[] = $sinceId;
        }
        if ($onlyUnread) {
            $where .= ' AND is_read=0';
        }

        $sql = "
            SELECT id, source_notification_id, election_id, event_type, title, body, level, target_url, is_read, delivered_at, read_at
            FROM user_notifications
            $where
            ORDER BY id DESC
            LIMIT $limit
        ";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $items = $st->fetchAll() ?: [];

        $cnt = $pdo->prepare('SELECT COUNT(*) FROM user_notifications WHERE user_id=? AND is_read=0');
        $cnt->execute([$userId]);
        $unreadCount = (int)$cnt->fetchColumn();

        $latest = $pdo->prepare('SELECT COALESCE(MAX(id),0) FROM user_notifications WHERE user_id=?');
        $latest->execute([$userId]);
        $latestId = (int)$latest->fetchColumn();

        json_success([
            'unread_count' => $unreadCount,
            'latest_id' => $latestId,
            'items' => $items,
        ]);
    }

    csrf_require();
    if (!user_notifs_ready($pdo)) {
        json_success(['message' => 'Inbox indisponible']);
    }

    if ($method === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) $data = [];
        $action = strtoupper(trim((string)($data['action'] ?? 'READ')));

        if ($action === 'READ') {
            $id = (int)($data['id'] ?? 0);
            if ($id <= 0) json_error('ID manquant', 422);

            $st = $pdo->prepare('
                UPDATE user_notifications
                SET is_read=1, read_at=COALESCE(read_at, NOW())
                WHERE id=? AND user_id=?
            ');
            // Filtre user_id obligatoire pour eviter de marquer la notif d un autre compte.
            $st->execute([$id, $userId]);
            json_success(['message' => 'Notification lue']);
        }

        if ($action === 'READ_ALL') {
            $st = $pdo->prepare('
                UPDATE user_notifications
                SET is_read=1, read_at=COALESCE(read_at, NOW())
                WHERE user_id=? AND is_read=0
            ');
            $st->execute([$userId]);
            json_success(['message' => 'Toutes les notifications sont lues']);
        }

        json_error('Action inconnue', 422);
    }

    json_error('Methode non autorisee', 405);
} catch (Throwable $e) {
    $debug = env_get('APP_ENV', 'local') !== 'production';
    json_error($debug ? $e->getMessage() : 'Erreur serveur', 500);
}
