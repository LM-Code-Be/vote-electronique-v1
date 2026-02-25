<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

user_require_role(['ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_error('Méthode non autorisée', 405);
}

$electionId = (int)($_GET['election_id'] ?? 0);
if ($electionId <= 0) json_error('election_id manquant', 422);

$stmt = $pdo->prepare('SELECT id,title,status,audience_mode FROM elections WHERE id=?');
$stmt->execute([$electionId]);
$election = $stmt->fetch();
if (!$election) json_error('Élection introuvable', 404);

$audienceMode = strtoupper((string)($election['audience_mode'] ?? 'INTERNAL'));
if (!in_array($audienceMode, ['INTERNAL', 'HYBRID', 'EXTERNAL'], true)) {
    $audienceMode = 'INTERNAL';
}

function ent_part_audience_sql(string $audienceMode, string $alias = 'u'): string
{
    if ($audienceMode === 'EXTERNAL') {
        return "{$alias}.user_type='EXTERNAL'";
    }
    if ($audienceMode === 'INTERNAL') {
        return "{$alias}.user_type='INTERNAL'";
    }
    return "{$alias}.user_type IN ('INTERNAL','EXTERNAL')";
}

$audSql = ent_part_audience_sql($audienceMode, 'u');

$onlyMissing = !empty($_GET['only_missing']);
$summaryOnly = !empty($_GET['summary']);

$st = $pdo->prepare('SELECT COUNT(*) FROM voter_roll WHERE election_id=?');
$st->execute([$electionId]);
$hasRoll = (int)$st->fetchColumn() > 0;

$rows = [];
$eligibleCount = 0;
$votedCount = 0;

if ($hasRoll) {
    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM voter_roll vr
        JOIN users u ON u.id=vr.user_id
        JOIN user_roles ur ON ur.user_id=u.id
        JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
        WHERE vr.election_id=?
          AND vr.eligible=1
          AND u.status='ACTIVE'
          AND $audSql
    ");
    $st->execute([$electionId]);
    $eligibleCount = (int)$st->fetchColumn();

    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM participations p
        JOIN voter_roll vr ON vr.election_id=p.election_id AND vr.user_id=p.user_id AND vr.eligible=1
        JOIN users u ON u.id = p.user_id
        JOIN user_roles ur ON ur.user_id=u.id
        JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
        WHERE p.election_id=?
          AND u.status='ACTIVE'
          AND $audSql
    ");
    $st->execute([$electionId]);
    $votedCount = (int)$st->fetchColumn();

    $sql = "
        SELECT
            u.id, u.username, u.full_name, u.email, u.service AS departement, u.employee_id, u.status, u.user_type,
            vr.eligible,
            p.voted_at,
            GROUP_CONCAT(DISTINCT CONCAT(g.type,':',g.name) ORDER BY g.type,g.name SEPARATOR ' | ') AS groups
        FROM voter_roll vr
        JOIN users u ON u.id = vr.user_id
        JOIN user_roles ur ON ur.user_id=u.id
        JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
        LEFT JOIN participations p ON p.election_id = vr.election_id AND p.user_id = u.id
        LEFT JOIN user_groups ug ON ug.user_id = u.id
        LEFT JOIN `groups` g ON g.id = ug.group_id
        WHERE vr.election_id = ?
          AND vr.eligible = 1
          AND u.status='ACTIVE'
          AND $audSql
    ";
    if ($onlyMissing) $sql .= " AND p.id IS NULL ";
    $sql .= "
        GROUP BY u.id, u.username, u.full_name, u.email, u.service, u.employee_id, u.status, u.user_type, vr.eligible, p.voted_at
        ORDER BY u.username
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$electionId]);
    $rows = $stmt->fetchAll() ?: [];
} else {
    $st = $pdo->prepare('SELECT COUNT(*) FROM election_groups WHERE election_id=?');
    $st->execute([$electionId]);
    $hasGroups = (int)$st->fetchColumn() > 0;

    if (!$hasGroups) {
        $eligibleCount = (int)$pdo->query("
            SELECT COUNT(DISTINCT u.id)
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
            WHERE u.status='ACTIVE'
              AND $audSql
        ")->fetchColumn();

        $st = $pdo->prepare("
            SELECT COUNT(*)
            FROM participations p
            JOIN users u ON u.id=p.user_id AND u.status='ACTIVE'
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
            WHERE p.election_id=?
              AND $audSql
        ");
        $st->execute([$electionId]);
        $votedCount = (int)$st->fetchColumn();

        $sql = "
            SELECT
                u.id, u.username, u.full_name, u.email, u.service AS departement, u.employee_id, u.status, u.user_type,
                1 AS eligible,
                p.voted_at,
                GROUP_CONCAT(DISTINCT CONCAT(g.type,':',g.name) ORDER BY g.type,g.name SEPARATOR ' | ') AS groups
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
            LEFT JOIN participations p ON p.election_id = ? AND p.user_id = u.id
            LEFT JOIN user_groups ug ON ug.user_id = u.id
            LEFT JOIN `groups` g ON g.id = ug.group_id
            WHERE u.status='ACTIVE'
              AND $audSql
        ";
        if ($onlyMissing) $sql .= " AND p.id IS NULL ";
        $sql .= "
            GROUP BY u.id, u.username, u.full_name, u.email, u.service, u.employee_id, u.status, u.user_type, p.voted_at
            ORDER BY u.username
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$electionId]);
        $rows = $stmt->fetchAll() ?: [];
    } else {
        $st = $pdo->prepare("
            SELECT COUNT(DISTINCT u.id)
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
            JOIN user_groups ugElig ON ugElig.user_id=u.id
            JOIN election_groups eg ON eg.group_id=ugElig.group_id AND eg.election_id=?
            WHERE u.status='ACTIVE'
              AND $audSql
        ");
        $st->execute([$electionId]);
        $eligibleCount = (int)$st->fetchColumn();

        $st = $pdo->prepare("
            SELECT COUNT(*)
            FROM participations p
            JOIN users u ON u.id=p.user_id AND u.status='ACTIVE'
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
            JOIN user_groups ugElig ON ugElig.user_id=u.id
            JOIN election_groups eg ON eg.group_id=ugElig.group_id AND eg.election_id=?
            WHERE p.election_id=?
              AND $audSql
        ");
        $st->execute([$electionId, $electionId]);
        $votedCount = (int)$st->fetchColumn();

        $sql = "
            SELECT
                u.id, u.username, u.full_name, u.email, u.service AS departement, u.employee_id, u.status, u.user_type,
                1 AS eligible,
                p.voted_at,
                GROUP_CONCAT(DISTINCT CONCAT(g.type,':',g.name) ORDER BY g.type,g.name SEPARATOR ' | ') AS groups
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
            JOIN user_groups ugElig ON ugElig.user_id=u.id
            JOIN election_groups eg ON eg.group_id=ugElig.group_id AND eg.election_id=?
            LEFT JOIN participations p ON p.election_id = ? AND p.user_id = u.id
            LEFT JOIN user_groups ug ON ug.user_id = u.id
            LEFT JOIN `groups` g ON g.id = ug.group_id
            WHERE u.status='ACTIVE'
              AND $audSql
        ";
        if ($onlyMissing) $sql .= " AND p.id IS NULL ";
        $sql .= "
            GROUP BY u.id, u.username, u.full_name, u.email, u.service, u.employee_id, u.status, u.user_type, p.voted_at
            ORDER BY u.username
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$electionId, $electionId]);
        $rows = $stmt->fetchAll() ?: [];
    }
}

$format = (string)($_GET['format'] ?? '');
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="participation_' . $electionId . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['username', 'full_name', 'email', 'departement', 'employee_id', 'groups', 'voted_at']);
    foreach ($rows as $r) {
        fputcsv($out, [
            (string)($r['username'] ?? ''),
            (string)($r['full_name'] ?? ''),
            (string)($r['email'] ?? ''),
            (string)($r['departement'] ?? ''),
            (string)($r['employee_id'] ?? ''),
            (string)($r['groups'] ?? ''),
            (string)($r['voted_at'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
}

json_success([
    'election' => [
        'id' => (int)$election['id'],
        'title' => (string)$election['title'],
        'status' => (string)$election['status'],
        'audience_mode' => $audienceMode,
    ],
    'eligible' => $eligibleCount,
    'voted' => $votedCount,
    'rate' => $eligibleCount > 0 ? round($votedCount / $eligibleCount * 100, 1) : 0,
    'rows' => $summaryOnly ? [] : $rows,
    'recent_votes' => (static function (array $rows): array {
        $voted = array_values(array_filter($rows, static fn(array $row): bool => !empty($row['voted_at'])));
        usort($voted, static function (array $a, array $b): int {
            return strcmp((string)($b['voted_at'] ?? ''), (string)($a['voted_at'] ?? ''));
        });
        $voted = array_slice($voted, 0, 10);
        return array_map(static function (array $r): array {
            return [
                'user_id' => (int)($r['id'] ?? 0),
                'username' => (string)($r['username'] ?? ''),
                'full_name' => (string)($r['full_name'] ?? ''),
                'departement' => (string)($r['departement'] ?? ''),
                'voted_at' => (string)($r['voted_at'] ?? ''),
            ];
        }, $voted);
    })($rows),
    'generated_at' => date('c'),
]);
