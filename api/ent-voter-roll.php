<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

user_require_role(['ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], true);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$electionId = (int)($_GET['election_id'] ?? 0);
if ($electionId <= 0) json_error('election_id manquant', 422);

$stmt = $pdo->prepare('SELECT id,title,status,audience_mode,created_by FROM elections WHERE id=?');
$stmt->execute([$electionId]);
$election = $stmt->fetch();
if (!$election) json_error('Élection introuvable', 404);

$audienceMode = strtoupper((string)($election['audience_mode'] ?? 'INTERNAL'));
if (!in_array($audienceMode, ['INTERNAL', 'HYBRID', 'EXTERNAL'], true)) {
    $audienceMode = 'INTERNAL';
}

function ent_vr_audience_sql(string $audienceMode, string $alias = 'u'): string
{
    if ($audienceMode === 'EXTERNAL') {
        return "{$alias}.user_type='EXTERNAL'";
    }
    if ($audienceMode === 'INTERNAL') {
        return "{$alias}.user_type='INTERNAL'";
    }
    return "{$alias}.user_type IN ('INTERNAL','EXTERNAL')";
}

function ent_vr_has_snapshot(PDO $pdo, int $electionId): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM voter_roll WHERE election_id=?');
    $st->execute([$electionId]);
    return (int)$st->fetchColumn() > 0;
}

function user_can_manage_election(array $election): bool
{
    if (user_has_role('SUPERADMIN')) return true;
    if (!user_has_role('ADMIN')) return false;
    $me = user_current_id();
    if ($me === null) return false;
    return (int)($election['created_by'] ?? 0) === $me;
}

function require_manage_election(array $election): void
{
    if (user_can_manage_election($election)) return;
    json_error('Seul le createur ou un SUPERADMIN peut modifier le snapshot', 403);
}

function ent_vr_compute_base(PDO $pdo, int $electionId): array
{
    global $audienceMode;
    $audSql = ent_vr_audience_sql($audienceMode, 'u');

    $st = $pdo->prepare('SELECT COUNT(*) FROM election_groups WHERE election_id=?');
    $st->execute([$electionId]);
    $hasGroups = (int)$st->fetchColumn() > 0;

    if (!$hasGroups) {
        $rows = $pdo->query("
            SELECT u.id, u.username, u.full_name, u.email, u.service AS departement, u.employee_id, u.status,
                   (u.status='ACTIVE') AS eligible
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
            WHERE $audSql
            ORDER BY u.username
        ")->fetchAll() ?: [];
        return $rows;
    }

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.full_name, u.email, u.service AS departement, u.employee_id, u.status,
               CASE WHEN u.status='ACTIVE' AND COALESCE(g.cnt,0) > 0 THEN 1 ELSE 0 END AS eligible
        FROM users u
        JOIN user_roles ur ON ur.user_id=u.id
        JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
        LEFT JOIN (
            SELECT ug.user_id, COUNT(*) AS cnt
            FROM user_groups ug
            JOIN election_groups eg ON eg.group_id=ug.group_id AND eg.election_id=?
            GROUP BY ug.user_id
        ) g ON g.user_id=u.id
        WHERE $audSql
        ORDER BY u.username
    ");
    $stmt->execute([$electionId]);
    return $stmt->fetchAll() ?: [];
}

if ($method === 'GET') {
    $hasSnapshot = ent_vr_has_snapshot($pdo, $electionId);

    if ($hasSnapshot) {
        $audSql = ent_vr_audience_sql($audienceMode, 'u');
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.full_name, u.email, u.service AS departement, u.employee_id, u.status,
                   vr.eligible
            FROM voter_roll vr
            JOIN users u ON u.id = vr.user_id
            WHERE vr.election_id=?
              AND $audSql
            ORDER BY u.username
        ");
        $stmt->execute([$electionId]);
        $rows = $stmt->fetchAll() ?: [];
    } else {
        $rows = ent_vr_compute_base($pdo, $electionId);
    }

    $format = (string)($_GET['format'] ?? '');
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="voter_roll_' . $electionId . '_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['username', 'full_name', 'email', 'departement', 'employee_id', 'status', 'eligible']);
        foreach ($rows as $r) {
            fputcsv($out, [
                (string)($r['username'] ?? ''),
                (string)($r['full_name'] ?? ''),
                (string)($r['email'] ?? ''),
                (string)($r['departement'] ?? ''),
                (string)($r['employee_id'] ?? ''),
                (string)($r['status'] ?? ''),
                !empty($r['eligible']) ? '1' : '0',
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
        'has_snapshot' => $hasSnapshot ? 1 : 0,
        'rows' => $rows,
    ]);
}

user_require_role(['ADMIN', 'SUPERADMIN'], true);
csrf_require();
require_manage_election($election);

if ($method === 'POST') {
    $op = (string)($_GET['op'] ?? '');

    if ($op === 'clear') {
        $st = $pdo->prepare('DELETE FROM voter_roll WHERE election_id=?');
        $st->execute([$electionId]);
        $deleted = (int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn();
        audit_event($pdo, 'VOTER_ROLL_CLEAR', 'ELECTION', $electionId, ['deleted' => $deleted]);
        json_success(['deleted' => $deleted]);
    }

    if ($op === 'generate') {
        $overwrite = !empty($_GET['overwrite']) ? 1 : 0;
        $base = ent_vr_compute_base($pdo, $electionId);

        $pdo->beginTransaction();
        try {
            if ($overwrite) {
                $pdo->prepare('DELETE FROM voter_roll WHERE election_id=?')->execute([$electionId]);
            }
            $ins = $pdo->prepare('INSERT IGNORE INTO voter_roll(election_id,user_id,eligible) VALUES(?,?,?)');
            $count = 0;
            foreach ($base as $u) {
                $uid = (int)$u['id'];
                if ($uid <= 0) continue;
                $ins->execute([$electionId, $uid, !empty($u['eligible']) ? 1 : 0]);
                $count += (int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn();
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        audit_event($pdo, 'VOTER_ROLL_GENERATE', 'ELECTION', $electionId, ['inserted' => $count, 'overwrite' => $overwrite]);
        json_success(['inserted' => $count, 'overwrite' => $overwrite]);
    }

    json_error('op manquant', 422);
}

if ($method === 'PUT') {
    $userId = (int)($_GET['user_id'] ?? 0);
    if ($userId <= 0) json_error('user_id manquant', 422);
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) json_error('JSON invalide', 422);
    $eligible = !empty($data['eligible']) ? 1 : 0;

    $pdo->prepare('INSERT INTO voter_roll(election_id,user_id,eligible) VALUES(?,?,?) ON DUPLICATE KEY UPDATE eligible=VALUES(eligible)')
        ->execute([$electionId, $userId, $eligible]);

    audit_event($pdo, 'VOTER_ROLL_UPDATE', 'ELECTION', $electionId, ['user_id' => $userId, 'eligible' => $eligible]);
    json_success(['ok' => 1]);
}

json_error('Méthode non autorisée', 405);
