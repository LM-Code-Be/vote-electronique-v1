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
    json_error('Seul le createur ou un SUPERADMIN peut modifier ce scrutin', 403);
}

function load_election_acl(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT id, title, created_by, status, type, audience_mode, start_at, end_at FROM elections WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function ensure_user_has_voter_role(PDO $pdo, int $userId): void
{
    if ($userId <= 0) return;
    static $roleId = null;
    if ($roleId === null) {
        $roleId = (int)$pdo->query("SELECT id FROM roles WHERE code='VOTER'")->fetchColumn();
    }
    if ($roleId <= 0) return;
    $pdo->prepare('INSERT IGNORE INTO user_roles(user_id, role_id) VALUES(?, ?)')->execute([$userId, $roleId]);
}

function normalize_dt_local(?string $v): ?string
{
    if ($v === null) return null;
    $v = trim($v);
    if ($v === '') return null;
    return str_replace('T', ' ', $v);
}

function normalize_candidate_name(?string $name): string
{
    $name = trim((string)$name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim((string)$name);
}

function load_active_user_for_candidate(PDO $pdo, int $userId): ?array
{
    $st = $pdo->prepare('SELECT id, username, full_name, user_type, status FROM users WHERE id=? LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) return null;
    if ((string)($row['status'] ?? '') !== 'ACTIVE') return null;
    return $row;
}

function audience_allows_user_type(string $audienceMode, string $userType): bool
{
    $audienceMode = strtoupper(trim($audienceMode));
    $userType = strtoupper(trim($userType));
    if ($audienceMode === 'INTERNAL') return $userType === 'INTERNAL';
    if ($audienceMode === 'EXTERNAL') return $userType === 'EXTERNAL';
    return in_array($userType, ['INTERNAL', 'EXTERNAL'], true);
}

function normalize_candidates_payload(PDO $pdo, mixed $raw, int $typeNeedsCandidates, string $audienceMode): array
{
    if (!is_array($raw)) return [];

    $out = [];
    $seenUsers = [];
    $order = 1;

    foreach ($raw as $item) {
        $userId = 0;
        $fullName = '';
        $category = null;
        $bio = '';
        $isValidated = 1;
        $isActive = 1;
        $displayOrder = $order;

        if (is_string($item)) {
            $fullName = normalize_candidate_name($item);
        } elseif (is_array($item)) {
            $userId = (int)($item['user_id'] ?? 0);
            $fullName = normalize_candidate_name((string)($item['full_name'] ?? ''));
            $category = isset($item['category']) ? trim((string)$item['category']) : null;
            $bio = (string)($item['biography'] ?? '');
            $isValidated = !empty($item['is_validated']) ? 1 : 0;
            $isActive = !empty($item['is_active']) ? 1 : 0;
            $displayOrder = max(1, (int)($item['display_order'] ?? $order));
        } else {
            continue;
        }

        if ($userId > 0) {
            if (isset($seenUsers[$userId])) {
                continue;
            }
            $user = load_active_user_for_candidate($pdo, $userId);
            if (!$user) {
                throw new RuntimeException('Utilisateur candidat introuvable ou inactif');
            }
            $linkedType = strtoupper((string)($user['user_type'] ?? 'INTERNAL'));
            if (!audience_allows_user_type($audienceMode, $linkedType)) {
                throw new RuntimeException('Audience du scrutin incompatible avec un candidat selectionne');
            }
            if ($fullName === '') {
                $fullName = normalize_candidate_name((string)($user['full_name'] ?? ''));
                if ($fullName === '') {
                    $fullName = normalize_candidate_name((string)($user['username'] ?? ''));
                }
            }
            if ($category === null || $category === '') {
                $category = strtoupper((string)($user['user_type'] ?? ''));
            }
            $seenUsers[$userId] = true;
        }

        if ($fullName === '') {
            continue;
        }

        $candidateCategory = $category !== null && $category !== '' ? strtoupper((string)$category) : null;
        if ($candidateCategory !== null && in_array($candidateCategory, ['INTERNAL', 'EXTERNAL'], true)) {
            if (!audience_allows_user_type($audienceMode, $candidateCategory)) {
                throw new RuntimeException('Audience du scrutin incompatible avec la categorie candidat');
            }
        }

        $out[] = [
            'user_id' => $userId > 0 ? $userId : null,
            'full_name' => $fullName,
            'biography' => $bio,
            'category' => $candidateCategory !== null ? $candidateCategory : null,
            'display_order' => $displayOrder,
            'is_validated' => $isValidated,
            'is_active' => $isActive,
        ];

        $order++;
    }

    if ($typeNeedsCandidates > 0 && !$out) {
        return [];
    }

    usort($out, static fn(array $a, array $b): int => ((int)$a['display_order']) <=> ((int)$b['display_order']));
    return $out;
}

try {
    if ($method === 'GET') {
        $rows = $pdo->query('SELECT * FROM elections ORDER BY created_at DESC')->fetchAll() ?: [];
        $grp = $pdo->query('SELECT election_id, group_id FROM election_groups')->fetchAll() ?: [];
        $cand = $pdo->query('SELECT election_id, user_id FROM candidates WHERE user_id IS NOT NULL')->fetchAll() ?: [];
        $by = [];
        $byCand = [];
        foreach ($grp as $g) {
            $eid = (int)$g['election_id'];
            $by[$eid] ??= [];
            $by[$eid][] = (int)$g['group_id'];
        }
        foreach ($cand as $c) {
            $eid = (int)$c['election_id'];
            $uid = (int)$c['user_id'];
            if ($eid <= 0 || $uid <= 0) continue;
            $byCand[$eid] ??= [];
            $byCand[$eid][] = $uid;
        }
        foreach ($rows as &$r) {
            $r['group_ids'] = $by[(int)$r['id']] ?? [];
            $r['candidate_user_ids'] = array_values(array_unique($byCand[(int)$r['id']] ?? []));
            $status = strtoupper((string)($r['status'] ?? 'DRAFT'));
            $canManage = user_can_manage_election($r);
            $r['can_edit'] = $canManage ? 1 : 0;
            $r['can_manage_candidates'] = $canManage ? 1 : 0;
            $r['can_publish'] = ($canManage && $status === 'DRAFT') ? 1 : 0;
            $r['can_close'] = ($canManage && $status === 'PUBLISHED') ? 1 : 0;
            $r['can_delete'] = $canManage ? 1 : 0;
        }
        unset($r);
        json_success($rows);
    }

    csrf_require();

    $action = (string)($_GET['action'] ?? '');
    if ($method === 'POST' && $action === '') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) json_error('JSON invalide', 422);

        $title = trim((string)($data['title'] ?? ''));
        $description = (string)($data['description'] ?? '');
        $organizer = trim((string)($data['organizer'] ?? ''));
        $type = strtoupper(trim((string)($data['type'] ?? 'SINGLE')));
        $timezone = trim((string)($data['timezone'] ?? 'Europe/Paris'));
        $audienceMode = strtoupper(trim((string)($data['audience_mode'] ?? 'INTERNAL')));
        $resultsVisibility = strtoupper(trim((string)($data['results_visibility'] ?? 'AFTER_CLOSE')));
        $start = normalize_dt_local($data['start_at'] ?? null) ?? '';
        $end = normalize_dt_local($data['end_at'] ?? null) ?? '';
        $isAnon = !empty($data['is_anonymous']) ? 1 : 0;
        $isMandatory = !empty($data['is_mandatory']) ? 1 : 0;
        $allowChange = !empty($data['allow_vote_change']) ? 1 : 0;
        $maxChoices = $data['max_choices'] ?? null;
        $maxChoices = ($maxChoices === '' || $maxChoices === null) ? null : max(1, (int)$maxChoices);
        $displayOrderMode = !empty($data['display_order_mode']) ? (string)$data['display_order_mode'] : 'MANUAL';
        $groupIds = $data['group_ids'] ?? [];
        $rawCandidates = $data['candidates'] ?? [];

        if ((!is_array($rawCandidates) || !$rawCandidates) && is_array($data['candidate_user_ids'] ?? null)) {
            $rawCandidates = array_map(static fn($uid) => ['user_id' => (int)$uid], $data['candidate_user_ids']);
        }

        $needsCandidates = in_array($type, ['SINGLE','MULTI','RANKED'], true) ? 1 : 0;
        $candidates = normalize_candidates_payload($pdo, $rawCandidates, $needsCandidates, $audienceMode);

        if ($title === '' || $start === '' || $end === '') json_error('Champs requis', 422);
        if (!in_array($type, ['SINGLE','MULTI','YESNO','RANKED'], true)) json_error('Type invalide', 422);
        if (!in_array($audienceMode, ['INTERNAL', 'HYBRID', 'EXTERNAL'], true)) json_error('Mode audience invalide', 422);
        if (!in_array($resultsVisibility, ['AFTER_CLOSE'], true)) $resultsVisibility = 'AFTER_CLOSE';
        if (!in_array($displayOrderMode, ['MANUAL','RANDOM'], true)) $displayOrderMode = 'MANUAL';
        if ($isAnon && $allowChange) $allowChange = 0;
        if ($type !== 'MULTI') $maxChoices = null;
        if ($needsCandidates && $audienceMode !== 'EXTERNAL' && !$candidates) {
            json_error('Ajoute au moins un candidat (utilisateur actif) a la creation', 422);
        }
        if (strtotime($start) === false || strtotime($end) === false || strtotime($start) >= strtotime($end)) {
            json_error('Dates invalides', 422);
        }

        $createdBy = user_current_id();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO elections(title,description,organizer,type,is_anonymous,is_mandatory,max_choices,allow_vote_change,start_at,end_at,timezone,audience_mode,results_visibility,status,display_order_mode,created_by)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?, 'DRAFT', ?, ?)
            ");
            $stmt->execute([
                $title,
                $description,
                $organizer !== '' ? $organizer : null,
                $type,
                $isAnon,
                $isMandatory,
                $maxChoices,
                $allowChange,
                $start,
                $end,
                $timezone,
                $audienceMode,
                $resultsVisibility,
                $displayOrderMode,
                $createdBy,
            ]);
            $eid = (int)$pdo->lastInsertId();

            if (is_array($groupIds) && $groupIds) {
                $ins = $pdo->prepare('INSERT IGNORE INTO election_groups(election_id,group_id) VALUES(?,?)');
                foreach ($groupIds as $gid) {
                    $gid = (int)$gid;
                    if ($gid > 0) $ins->execute([$eid, $gid]);
                }
            }

            if ($candidates) {
                $insCandidate = $pdo->prepare("
                    INSERT INTO candidates(election_id,user_id,full_name,biography,category,display_order,is_validated,is_active)
                    VALUES(?,?,?,?,?,?,?,?)
                ");
                foreach ($candidates as $candidate) {
                    $candidateUserId = (int)($candidate['user_id'] ?? 0);
                    if ($candidateUserId > 0) {
                        ensure_user_has_voter_role($pdo, $candidateUserId);
                    }
                    $insCandidate->execute([
                        $eid,
                        $candidate['user_id'],
                        (string)$candidate['full_name'],
                        (string)($candidate['biography'] ?? ''),
                        $candidate['category'],
                        (int)$candidate['display_order'],
                        !empty($candidate['is_validated']) ? 1 : 0,
                        !empty($candidate['is_active']) ? 1 : 0,
                    ]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        audit_event($pdo, 'ELECTION_CREATE', 'ELECTION', $eid, ['title' => $title, 'candidate_count' => count($candidates)]);
        json_success(['message' => 'Cree', 'id' => $eid, 'candidate_count' => count($candidates)], 201);
    }

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_error('ID manquant', 422);

    if ($method === 'POST' && $action !== '') {
        $managedElection = load_election_acl($pdo, $id);
        if (!$managedElection) json_error('Introuvable', 404);
        require_manage_election($managedElection);

        if ($action === 'sync_candidates') {
            $election = $managedElection;

            $data = json_decode((string)file_get_contents('php://input'), true);
            if (!is_array($data)) $data = [];

            $ids = $data['candidate_user_ids'] ?? [];
            if (!is_array($ids)) $ids = [];
            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0)));

            $audienceMode = strtoupper((string)($election['audience_mode'] ?? 'INTERNAL'));
            $activeUsers = [];
            $incompatible = [];
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $st = $pdo->prepare("SELECT id, username, full_name, user_type, status FROM users WHERE id IN ($in)");
                $st->execute($ids);
                $rows = $st->fetchAll() ?: [];
                foreach ($rows as $u) {
                    if ((string)($u['status'] ?? '') !== 'ACTIVE') continue;
                    $uid = (int)$u['id'];
                    $name = trim((string)($u['full_name'] ?? ''));
                    if ($name === '') $name = trim((string)($u['username'] ?? ''));
                    if ($name === '') continue;
                    $userType = strtoupper((string)($u['user_type'] ?? 'INTERNAL'));
                    if (!audience_allows_user_type($audienceMode, $userType)) {
                        $incompatible[] = $name;
                        continue;
                    }
                    $activeUsers[$uid] = [
                        'name' => $name,
                        'category' => $userType,
                    ];
                }
                $ids = array_values(array_keys($activeUsers));
            }

            if ($incompatible) {
                $list = implode(', ', array_slice($incompatible, 0, 5));
                json_error('Candidats incompatibles avec l audience du scrutin: ' . $list, 422);
            }

            $needsCandidates = in_array((string)($election['type'] ?? ''), ['SINGLE', 'MULTI', 'RANKED'], true);

            if ($needsCandidates && $audienceMode !== 'EXTERNAL' && !$ids) {
                $st = $pdo->prepare('SELECT COUNT(*) FROM candidates WHERE election_id=? AND user_id IS NULL');
                $st->execute([$id]);
                $manualCount = (int)$st->fetchColumn();
                if ($manualCount <= 0) {
                    json_error('Ajoute au moins un candidat utilisateur ou manuel', 422);
                }
            }

            $pdo->beginTransaction();
            try {
                $existingSt = $pdo->prepare('SELECT user_id FROM candidates WHERE election_id=? AND user_id IS NOT NULL');
                $existingSt->execute([$id]);
                $existingUserIds = array_map('intval', $existingSt->fetchAll(PDO::FETCH_COLUMN) ?: []);
                $existingUserIds = array_values(array_unique(array_filter($existingUserIds, static fn(int $v): bool => $v > 0)));

                $toInsert = array_values(array_diff($ids, $existingUserIds));
                if ($toInsert) {
                    $maxSt = $pdo->prepare('SELECT COALESCE(MAX(display_order),0) FROM candidates WHERE election_id=?');
                    $maxSt->execute([$id]);
                    $display = (int)$maxSt->fetchColumn();

                    $ins = $pdo->prepare('
                        INSERT INTO candidates(election_id,user_id,full_name,biography,category,display_order,is_validated,is_active)
                        VALUES(?,?,?,?,?,?,1,1)
                    ');
                    foreach ($toInsert as $uid) {
                        $display++;
                        $u = $activeUsers[$uid] ?? null;
                        if (!$u) continue;
                        ensure_user_has_voter_role($pdo, (int)$uid);
                        $ins->execute([$id, $uid, $u['name'], '', $u['category'], $display]);
                    }
                }

                if ($ids) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $del = $pdo->prepare("DELETE FROM candidates WHERE election_id=? AND user_id IS NOT NULL AND user_id NOT IN ($in)");
                    $del->execute(array_merge([$id], $ids));
                } else {
                    $del = $pdo->prepare('DELETE FROM candidates WHERE election_id=? AND user_id IS NOT NULL');
                    $del->execute([$id]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            audit_event($pdo, 'ELECTION_SYNC_CANDIDATES', 'ELECTION', $id, ['count' => count($ids)]);
            json_success(['message' => 'Candidats synchronises', 'count' => count($ids)]);
        }
        if ($action === 'publish') {
            $alreadyPublished = strtoupper((string)($managedElection['status'] ?? 'DRAFT')) === 'PUBLISHED';
            $pdo->prepare("UPDATE elections SET status='PUBLISHED', published_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$id]);
            audit_event($pdo, 'ELECTION_PUBLISH', 'ELECTION', $id, []);

            $pushCount = 0;
            if (!$alreadyPublished) {
                try {
                    $pushCount = \Vote\Infrastructure\Composition\AppServices::userPush()
                        ->pushElectionPublished($pdo, $id, (string)($managedElection['title'] ?? ('Scrutin #' . $id)));
                } catch (Throwable $e) {
                    $pushCount = 0;
                }
            }
            json_success(['message' => 'Publiee', 'push_count' => $pushCount]);
        }
        if ($action === 'unpublish') {
            $pdo->prepare("UPDATE elections SET status='DRAFT', updated_at=NOW() WHERE id=?")->execute([$id]);
            audit_event($pdo, 'ELECTION_UNPUBLISH', 'ELECTION', $id, []);
            json_success(['message' => 'Dépubliée']);
        }
        if ($action === 'close') {
            $alreadyClosed = strtoupper((string)($managedElection['status'] ?? 'DRAFT')) === 'CLOSED';
            $pdo->prepare("UPDATE elections SET status='CLOSED', closed_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$id]);
            audit_event($pdo, 'ELECTION_CLOSE', 'ELECTION', $id, []);

            $pushCount = 0;
            if (!$alreadyClosed) {
                try {
                    $pushCount = \Vote\Infrastructure\Composition\AppServices::userPush()
                        ->pushElectionClosed($pdo, $id, (string)($managedElection['title'] ?? ('Scrutin #' . $id)));
                } catch (Throwable $e) {
                    $pushCount = 0;
                }
            }
            json_success(['message' => 'Cloturee', 'push_count' => $pushCount]);
        }
        if ($action === 'archive') {
            $pdo->prepare("UPDATE elections SET status='ARCHIVED', archived_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$id]);
            audit_event($pdo, 'ELECTION_ARCHIVE', 'ELECTION', $id, []);
            json_success(['message' => 'Archivée']);
        }
        if ($action === 'duplicate') {
            // Duplicate election row (as draft) + groups
            $row = $pdo->prepare('SELECT * FROM elections WHERE id=?');
            $row->execute([$id]);
            $e = $row->fetch();
            if (!$e) json_error('Introuvable', 404);

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO elections(title,description,organizer,type,is_anonymous,is_mandatory,max_choices,allow_vote_change,start_at,end_at,timezone,audience_mode,results_visibility,status,display_order_mode,created_by)
                    VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?, 'DRAFT', ?, ?)
                ");
                $stmt->execute([
                    ((string)$e['title']) . ' (copie)',
                    (string)($e['description'] ?? ''),
                    $e['organizer'],
                    $e['type'],
                    (int)$e['is_anonymous'],
                    (int)$e['is_mandatory'],
                    $e['max_choices'],
                    (int)$e['allow_vote_change'],
                    (string)$e['start_at'],
                    (string)$e['end_at'],
                    (string)$e['timezone'],
                    (string)($e['audience_mode'] ?? 'INTERNAL'),
                    (string)($e['results_visibility'] ?? 'AFTER_CLOSE'),
                    (string)$e['display_order_mode'],
                    user_current_id(),
                ]);
                $newId = (int)$pdo->lastInsertId();
                $g = $pdo->prepare('INSERT IGNORE INTO election_groups(election_id,group_id) SELECT ?, group_id FROM election_groups WHERE election_id=?');
                $g->execute([$newId, $id]);
                $c = $pdo->prepare("
                    INSERT INTO candidates(election_id,user_id,full_name,biography,photo_path,category,display_order,is_validated,is_active)
                    SELECT ?, user_id, full_name, biography, photo_path, category, display_order, is_validated, is_active
                    FROM candidates
                    WHERE election_id=?
                ");
                $c->execute([$newId, $id]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            audit_event($pdo, 'ELECTION_DUPLICATE', 'ELECTION', $newId, ['from' => $id]);
            json_success(['message' => 'Dupliquée', 'id' => $newId], 201);
        }

        json_error('Action inconnue', 422);
    }

    if ($method === 'PUT') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) json_error('JSON invalide', 422);

        $st = $pdo->prepare('SELECT * FROM elections WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $current = $st->fetch();
        if (!$current) json_error('Élection introuvable', 404);
        require_manage_election($current);
        $currentStatus = (string)($current['status'] ?? 'DRAFT');
        $st = $pdo->prepare('SELECT group_id FROM election_groups WHERE election_id=? ORDER BY group_id');
        $st->execute([$id]);
        $currentGroups = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $title = trim((string)($data['title'] ?? ''));
        $description = (string)($data['description'] ?? '');
        $organizer = trim((string)($data['organizer'] ?? ''));
        $type = strtoupper(trim((string)($data['type'] ?? 'SINGLE')));
        $timezone = trim((string)($data['timezone'] ?? 'Europe/Paris'));
        $audienceMode = strtoupper(trim((string)($data['audience_mode'] ?? 'INTERNAL')));
        $resultsVisibility = strtoupper(trim((string)($data['results_visibility'] ?? 'AFTER_CLOSE')));
        $start = normalize_dt_local($data['start_at'] ?? null) ?? '';
        $end = normalize_dt_local($data['end_at'] ?? null) ?? '';
        $isAnon = !empty($data['is_anonymous']) ? 1 : 0;
        $isMandatory = !empty($data['is_mandatory']) ? 1 : 0;
        $allowChange = !empty($data['allow_vote_change']) ? 1 : 0;
        $maxChoices = $data['max_choices'] ?? null;
        $maxChoices = ($maxChoices === '' || $maxChoices === null) ? null : max(1, (int)$maxChoices);
        $displayOrderMode = !empty($data['display_order_mode']) ? (string)$data['display_order_mode'] : 'MANUAL';
        $groupIds = $data['group_ids'] ?? [];

        // Lock sensitive parameters after publication
        $curMaxChoices = $current['max_choices'] === null ? null : (int)$current['max_choices'];
        $groupIdsNorm = array_values(array_unique(array_map('intval', is_array($groupIds) ? $groupIds : [])));
        sort($groupIdsNorm);
        $cg = $currentGroups;
        sort($cg);

        if ($currentStatus !== 'DRAFT') {
            $lockedDiff =
                $type !== (string)$current['type'] ||
                $timezone !== (string)$current['timezone'] ||
                $start !== (string)$current['start_at'] ||
                $isAnon !== (int)$current['is_anonymous'] ||
                $isMandatory !== (int)$current['is_mandatory'] ||
                ($curMaxChoices !== ($maxChoices === null ? null : (int)$maxChoices)) ||
                $allowChange !== (int)$current['allow_vote_change'] ||
                $displayOrderMode !== (string)$current['display_order_mode'] ||
                $audienceMode !== (string)($current['audience_mode'] ?? 'INTERNAL') ||
                $resultsVisibility !== (string)($current['results_visibility'] ?? 'AFTER_CLOSE') ||
                $cg !== $groupIdsNorm;

            if ($lockedDiff) {
                json_error('Paramètres verrouillés après publication. Duplique le scrutin si tu dois modifier ses règles.', 409);
            }

            if ($currentStatus !== 'PUBLISHED' && $end !== (string)$current['end_at']) {
                json_error('Ce scrutin n’est plus modifiable (clôturé/archivé).', 409);
            }
        }

        if ($title === '' || $start === '' || $end === '') json_error('Champs requis', 422);
        if (!in_array($type, ['SINGLE','MULTI','YESNO','RANKED'], true)) json_error('Type invalide', 422);
        if (!in_array($audienceMode, ['INTERNAL', 'HYBRID', 'EXTERNAL'], true)) json_error('Mode audience invalide', 422);
        if (!in_array($resultsVisibility, ['AFTER_CLOSE'], true)) $resultsVisibility = 'AFTER_CLOSE';
        if (!in_array($displayOrderMode, ['MANUAL','RANDOM'], true)) $displayOrderMode = 'MANUAL';
        if ($isAnon && $allowChange) $allowChange = 0;
        if ($type !== 'MULTI') $maxChoices = null;
        if (strtotime($start) === false || strtotime($end) === false || strtotime($start) >= strtotime($end)) {
            json_error('Dates invalides', 422);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE elections
                SET title=?, description=?, organizer=?, type=?, is_anonymous=?, is_mandatory=?, max_choices=?, allow_vote_change=?, start_at=?, end_at=?, timezone=?, audience_mode=?, results_visibility=?, display_order_mode=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([
                $title,
                $description,
                $organizer !== '' ? $organizer : null,
                $type,
                $isAnon,
                $isMandatory,
                $maxChoices,
                $allowChange,
                $start,
                $end,
                $timezone,
                $audienceMode,
                $resultsVisibility,
                $displayOrderMode,
                $id,
            ]);

            $pdo->prepare('DELETE FROM election_groups WHERE election_id=?')->execute([$id]);
            if (is_array($groupIds) && $groupIds) {
                $ins = $pdo->prepare('INSERT IGNORE INTO election_groups(election_id,group_id) VALUES(?,?)');
                foreach ($groupIds as $gid) {
                    $gid = (int)$gid;
                    if ($gid > 0) $ins->execute([$id, $gid]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        audit_event($pdo, 'ELECTION_UPDATE', 'ELECTION', $id, ['title' => $title]);
        json_success(['message' => 'Mis à jour']);
    }

    if ($method === 'DELETE') {
        $managedElection = load_election_acl($pdo, $id);
        if (!$managedElection) json_error('Introuvable', 404);
        require_manage_election($managedElection);
        // soft-delete: archive
        $pdo->prepare("UPDATE elections SET status='ARCHIVED', archived_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$id]);
        audit_event($pdo, 'ELECTION_DELETE', 'ELECTION', $id, []);
        json_success(['message' => 'Archivée']);
    }

    json_error('Méthode non autorisée', 405);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $debug = env_get('APP_ENV', 'local') !== 'production';
    json_error($debug ? $e->getMessage() : 'Erreur serveur', 500);
}


