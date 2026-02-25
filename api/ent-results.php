<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

require_once __DIR__ . '/../app/election_service.php';

user_require_role(['VOTER', 'ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_error('Méthode non autorisée', 405);
}

$electionId = (int)($_GET['election_id'] ?? 0);
if ($electionId <= 0) json_error('election_id manquant', 422);

$stmt = $pdo->prepare('SELECT * FROM elections WHERE id=?');
$stmt->execute([$electionId]);
$election = $stmt->fetch();
if (!$election) json_error('Élection introuvable', 404);

$isAdminViewer = user_has_role('ADMIN') || user_has_role('SCRUTATEUR') || user_has_role('SUPERADMIN');
$viewerUserId = user_current_id();
$status = (string)($election['status'] ?? 'DRAFT');
$type = (string)($election['type'] ?? 'SINGLE');
$audienceMode = strtoupper((string)($election['audience_mode'] ?? 'INTERNAL'));
$resultsVisibility = strtoupper((string)($election['results_visibility'] ?? 'AFTER_CLOSE'));

if (!in_array($audienceMode, ['INTERNAL', 'HYBRID', 'EXTERNAL'], true)) {
    $audienceMode = 'INTERNAL';
}
if ($resultsVisibility !== 'AFTER_CLOSE') {
    $resultsVisibility = 'AFTER_CLOSE';
}

function ent_results_audience_sql(string $audienceMode, string $alias = 'u'): string
{
    if ($audienceMode === 'EXTERNAL') {
        return "{$alias}.user_type='EXTERNAL'";
    }
    if ($audienceMode === 'INTERNAL') {
        return "{$alias}.user_type='INTERNAL'";
    }
    return "{$alias}.user_type IN ('INTERNAL','EXTERNAL')";
}

function ent_results_has_snapshot(PDO $pdo, int $electionId): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM voter_roll WHERE election_id=?');
    $st->execute([$electionId]);
    return (int)$st->fetchColumn() > 0;
}

function ent_results_has_groups(PDO $pdo, int $electionId): bool
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM election_groups WHERE election_id=?');
    $st->execute([$electionId]);
    return (int)$st->fetchColumn() > 0;
}

function ent_results_eligible_count(PDO $pdo, int $electionId, string $audienceMode): int
{
    $audSql = ent_results_audience_sql($audienceMode, 'u');
    $hasRoll = ent_results_has_snapshot($pdo, $electionId);
    if ($hasRoll) {
        $st = $pdo->prepare("
            SELECT COUNT(*)
            FROM voter_roll vr
            JOIN users u ON u.id = vr.user_id
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id AND r.code='VOTER'
            WHERE vr.election_id=?
              AND vr.eligible=1
              AND u.status='ACTIVE'
              AND $audSql
        ");
        $st->execute([$electionId]);
        return (int)$st->fetchColumn();
    }

    $hasGroups = ent_results_has_groups($pdo, $electionId);
    if (!$hasGroups) {
        return (int)$pdo->query("
            SELECT COUNT(DISTINCT u.id)
            FROM users u
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
            WHERE u.status='ACTIVE'
              AND $audSql
        ")->fetchColumn();
    }

    $st = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id)
        FROM users u
        JOIN user_roles ur ON ur.user_id=u.id
        JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
        JOIN user_groups ug ON ug.user_id=u.id
        JOIN election_groups eg ON eg.group_id=ug.group_id AND eg.election_id=?
        WHERE u.status='ACTIVE'
          AND $audSql
    ");
    $st->execute([$electionId]);
    return (int)$st->fetchColumn();
}

function ent_results_voted_count(PDO $pdo, int $electionId, string $audienceMode): int
{
    $audSql = ent_results_audience_sql($audienceMode, 'u');
    $hasRoll = ent_results_has_snapshot($pdo, $electionId);
    if ($hasRoll) {
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
        return (int)$st->fetchColumn();
    }

    $hasGroups = ent_results_has_groups($pdo, $electionId);
    if (!$hasGroups) {
        $st = $pdo->prepare("
            SELECT COUNT(*)
            FROM participations p
            JOIN users u ON u.id=p.user_id
            JOIN user_roles ur ON ur.user_id=u.id
            JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
            WHERE p.election_id=?
              AND u.status='ACTIVE'
              AND $audSql
        ");
        $st->execute([$electionId]);
        return (int)$st->fetchColumn();
    }

    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM participations p
        JOIN users u ON u.id=p.user_id
        JOIN user_roles ur ON ur.user_id=u.id
        JOIN roles r ON r.id=ur.role_id AND r.code='VOTER'
        JOIN user_groups ug ON ug.user_id=u.id
        JOIN election_groups eg ON eg.group_id=ug.group_id AND eg.election_id=?
        WHERE p.election_id=?
          AND u.status='ACTIVE'
          AND $audSql
    ");
    $st->execute([$electionId, $electionId]);
    return (int)$st->fetchColumn();
}

$resultsAvailable = true;
$resultsMessage = null;
$resultsCode = null;

if (!$isAdminViewer) {
    if ($viewerUserId === null || !election_is_user_eligible($pdo, $electionId, $viewerUserId)) {
        $resultsAvailable = false;
        $resultsCode = 'NOT_ELIGIBLE';
        $resultsMessage = 'Tu n’es pas éligible à ce scrutin.';
    } elseif ($resultsVisibility === 'AFTER_CLOSE' && !in_array($status, ['CLOSED', 'ARCHIVED'], true)) {
        $resultsAvailable = false;
        $resultsCode = 'AFTER_CLOSE_ONLY';
        $resultsMessage = 'Les résultats seront visibles après la clôture du scrutin.';
    }
}

$eligible = ent_results_eligible_count($pdo, $electionId, $audienceMode);
$voted = ent_results_voted_count($pdo, $electionId, $audienceMode);

$results = [];

if ($resultsAvailable) {
    if ($type === 'YESNO') {
        $st = $pdo->prepare("
            SELECT value_yesno AS label, COUNT(*) AS count
            FROM ballots b
            JOIN ballot_items bi ON bi.ballot_id = b.id
            WHERE b.election_id=?
            GROUP BY value_yesno
        ");
        $st->execute([$electionId]);
        $results = $st->fetchAll() ?: [];
    } elseif ($type === 'RANKED') {
        // Simple: counts of rank=1
        $st = $pdo->prepare("
            SELECT c.full_name AS label, COUNT(*) AS count
            FROM ballot_items bi
            JOIN ballots b ON b.id = bi.ballot_id
            JOIN candidates c ON c.id = bi.candidate_id
            WHERE b.election_id=? AND bi.rank_pos=1
            GROUP BY c.id
            ORDER BY count DESC, label ASC
        ");
        $st->execute([$electionId]);
        $results = $st->fetchAll() ?: [];
    } else {
        $st = $pdo->prepare("
            SELECT c.full_name AS label, COUNT(*) AS count
            FROM ballot_items bi
            JOIN ballots b ON b.id = bi.ballot_id
            JOIN candidates c ON c.id = bi.candidate_id
            WHERE b.election_id=?
            GROUP BY c.id
            ORDER BY count DESC, label ASC
        ");
        $st->execute([$electionId]);
        $results = $st->fetchAll() ?: [];
    }
}

$format = (string)($_GET['format'] ?? '');
if ($format === 'csv') {
    if (!$resultsAvailable) {
        json_error($resultsMessage ?? 'Résultats indisponibles', 403);
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="results_election_' . $electionId . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['label', 'count']);
    foreach ($results as $r) fputcsv($out, [$r['label'], $r['count']]);
    fclose($out);
    exit;
}

json_success([
    'election' => [
        'id' => (int)$election['id'],
        'title' => (string)$election['title'],
        'status' => (string)$election['status'],
        'type' => $type,
        'is_anonymous' => (int)$election['is_anonymous'],
        'start_at' => (string)$election['start_at'],
        'end_at' => (string)$election['end_at'],
        'audience_mode' => $audienceMode,
        'results_visibility' => $resultsVisibility,
    ],
    'results_available' => $resultsAvailable,
    'results_access_code' => $resultsCode,
    'results_message' => $resultsMessage,
    'eligible' => $eligible,
    'voted' => $voted,
    'rate' => $eligible > 0 ? round($voted / $eligible * 100, 1) : 0,
    'results' => $results,
]);
