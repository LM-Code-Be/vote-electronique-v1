<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

user_require_role(['ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], true);

try {
    $stats = [];
    $stats['elections'] = (int)$pdo->query('SELECT COUNT(*) FROM elections')->fetchColumn();
    $stats['open'] = (int)$pdo->query("SELECT COUNT(*) FROM elections WHERE status='PUBLISHED' AND start_at<=NOW() AND end_at>=NOW()")->fetchColumn();

    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id)
        FROM users u
        JOIN user_roles ur ON ur.user_id = u.id
        JOIN roles r ON r.id = ur.role_id AND r.code='VOTER'
        WHERE u.status='ACTIVE'
    ");
    $stats['voters'] = (int)$stmt->fetchColumn();

    $stats['votesToday'] = (int)$pdo->query("SELECT COUNT(*) FROM participations WHERE DATE(voted_at)=CURDATE()")->fetchColumn();

    // Votes series (last 14 days) for chart
    $series = [];
    $stmt = $pdo->query("
        SELECT DATE(voted_at) AS d, COUNT(*) AS c
        FROM participations
        WHERE voted_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
        GROUP BY DATE(voted_at)
        ORDER BY d ASC
    ");
    $raw = $stmt->fetchAll() ?: [];
    $byDay = [];
    foreach ($raw as $r) {
        $byDay[(string)$r['d']] = (int)$r['c'];
    }
    $start = new DateTimeImmutable('today');
    $start = $start->modify('-13 days');
    for ($i = 0; $i < 14; $i++) {
        $d = $start->modify('+' . $i . ' days')->format('Y-m-d');
        $series[] = ['date' => $d, 'count' => (int)($byDay[$d] ?? 0)];
    }

    $recentElections = $pdo->query("SELECT id,title,status,start_at,end_at,type,is_anonymous FROM elections ORDER BY created_at DESC LIMIT 8")->fetchAll() ?: [];

    $recentAudit = [];
    if (user_has_role('SUPERADMIN')) {
        $recentAudit = $pdo->query("
            SELECT a.created_at, a.action, a.entity_type, a.entity_id, u.username AS actor
            FROM audit_logs a
            LEFT JOIN users u ON u.id = a.actor_user_id
            ORDER BY a.created_at DESC
            LIMIT 12
        ")->fetchAll() ?: [];
    }

    json_success([
        'stats' => $stats,
        'votesSeries' => $series,
        'recentElections' => $recentElections,
        'recentAudit' => $recentAudit,
    ]);
} catch (Throwable $e) {
    $debug = env_get('APP_ENV', 'local') !== 'production';
    json_error($debug ? $e->getMessage() : 'Erreur serveur', 500);
}
