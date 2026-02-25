<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

user_require_role(['ADMIN', 'SUPERADMIN'], true);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function require_election(PDO $pdo, int $id): array
{
    $st = $pdo->prepare('SELECT id,type,status,audience_mode,created_by FROM elections WHERE id=?');
    $st->execute([$id]);
    $e = $st->fetch();
    if (!$e) {
        json_error('Election introuvable', 404);
    }
    return $e;
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
    json_error('Seul le createur ou un SUPERADMIN peut modifier les candidats', 403);
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

function load_candidate_user(PDO $pdo, int $userId): ?array
{
    $st = $pdo->prepare('SELECT id, username, full_name, user_type, status FROM users WHERE id=? LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) {
        return null;
    }
    if ((string)($row['status'] ?? '') !== 'ACTIVE') {
        return null;
    }
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

try {
    if ($method === 'GET') {
        $electionId = (int)($_GET['election_id'] ?? 0);
        if ($electionId <= 0) json_error('election_id manquant', 422);
        require_election($pdo, $electionId);

        $stmt = $pdo->prepare('
            SELECT
                c.id,
                c.user_id,
                c.full_name,
                c.biography,
                c.photo_path,
                c.category,
                c.display_order,
                c.is_validated,
                c.is_active,
                c.created_at,
                u.username AS linked_username,
                u.full_name AS linked_full_name,
                u.user_type AS linked_user_type
            FROM candidates c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.election_id = ?
            ORDER BY c.display_order ASC, c.full_name ASC
        ');
        $stmt->execute([$electionId]);
        json_success($stmt->fetchAll() ?: []);
    }

    csrf_require();

    // Import CSV
    if ($method === 'POST' && !empty($_FILES['file']['tmp_name'])) {
        $electionId = (int)($_GET['election_id'] ?? 0);
        if ($electionId <= 0) json_error('election_id manquant', 422);
        $election = require_election($pdo, $electionId);
        require_manage_election($election);
        $audienceMode = strtoupper((string)($election['audience_mode'] ?? 'INTERNAL'));

        $handle = fopen((string)$_FILES['file']['tmp_name'], 'rb');
        if ($handle === false) json_error('Impossible de lire le fichier', 500);

        $header = null;
        $imported = 0;
        $skipped = 0;

        $pdo->beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if (!$row) continue;
                if ($header === null) {
                    $lower = array_map(fn($v) => strtolower(trim((string)$v)), $row);
                    if (in_array('full_name', $lower, true) || in_array('name', $lower, true)) {
                        $header = $lower;
                        continue;
                    }
                    $header = ['full_name', 'category', 'biography', 'display_order', 'is_validated', 'is_active'];
                }

                $map = [];
                foreach ($header as $i => $key) $map[$key] = isset($row[$i]) ? trim((string)$row[$i]) : '';
                $name = $map['full_name'] ?: ($map['name'] ?? '');
                $name = trim((string)$name);
                if ($name === '') { $skipped++; continue; }

                $cat = strtoupper(trim((string)($map['category'] ?? '')));
                if ($cat !== '' && !audience_allows_user_type($audienceMode, $cat)) {
                    $skipped++;
                    continue;
                }
                $bio = (string)($map['biography'] ?? '');
                $order = (int)($map['display_order'] ?? 0);
                $val = (string)($map['is_validated'] ?? '1');
                $act = (string)($map['is_active'] ?? '1');
                $isValidated = in_array(strtolower($val), ['1','true','yes','oui','y'], true) ? 1 : 0;
                $isActive = in_array(strtolower($act), ['1','true','yes','oui','y'], true) ? 1 : 0;

                $stmt = $pdo->prepare('
                    INSERT INTO candidates(election_id, full_name, biography, category, display_order, is_validated, is_active)
                    VALUES(?,?,?,?,?,?,?)
                ');
                $stmt->execute([$electionId, $name, $bio, $cat !== '' ? $cat : null, $order, $isValidated, $isActive]);
                $imported++;
            }
            fclose($handle);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        audit_event($pdo, 'CANDIDATES_IMPORT', 'ELECTION', $electionId, compact('imported','skipped'));
        json_success(['imported' => $imported, 'skipped' => $skipped]);
    }

    $action = (string)($_GET['action'] ?? '');
    if ($method === 'POST' && $action === 'randomize') {
        $electionId = (int)($_GET['election_id'] ?? 0);
        if ($electionId <= 0) json_error('election_id manquant', 422);
        $election = require_election($pdo, $electionId);
        require_manage_election($election);

        $rows = $pdo->prepare('SELECT id FROM candidates WHERE election_id=?');
        $rows->execute([$electionId]);
        $ids = $rows->fetchAll(PDO::FETCH_COLUMN) ?: [];
        shuffle($ids);
        $upd = $pdo->prepare('UPDATE candidates SET display_order=? WHERE id=?');
        $i = 1;
        foreach ($ids as $cid) {
            $upd->execute([$i++, (int)$cid]);
        }
        audit_event($pdo, 'CANDIDATES_RANDOMIZE', 'ELECTION', $electionId, []);
        json_success(['message' => 'OK']);
    }

    if ($method === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) json_error('JSON invalide', 422);

        $electionId = (int)($data['election_id'] ?? 0);
        if ($electionId <= 0) json_error('election_id manquant', 422);
        $election = require_election($pdo, $electionId);
        require_manage_election($election);
        $audienceMode = strtoupper((string)($election['audience_mode'] ?? 'INTERNAL'));

        $userId = (int)($data['user_id'] ?? 0);
        $linkedUser = $userId > 0 ? load_candidate_user($pdo, $userId) : null;
        if ($userId > 0 && !$linkedUser) {
            json_error('Utilisateur candidat introuvable ou inactif', 422);
        }
        if ($linkedUser && !audience_allows_user_type($audienceMode, (string)($linkedUser['user_type'] ?? 'INTERNAL'))) {
            json_error('Type utilisateur candidat incompatible avec l audience du scrutin', 422);
        }

        $name = trim((string)($data['full_name'] ?? ''));
        if ($name === '' && $linkedUser) {
            $name = trim((string)($linkedUser['full_name'] ?? ''));
            if ($name === '') {
                $name = (string)($linkedUser['username'] ?? '');
            }
        }
        if ($name === '') json_error('Nom requis', 422);

        $category = ($data['category'] ?? null);
        $category = is_string($category) ? trim($category) : '';
        if ($category === '' && $linkedUser) {
            $category = strtoupper((string)($linkedUser['user_type'] ?? ''));
        }
        if ($category !== '') $category = strtoupper($category);
        if ($category !== '' && in_array(strtoupper($category), ['INTERNAL', 'EXTERNAL'], true) && !audience_allows_user_type($audienceMode, $category)) {
            json_error('Categorie candidat incompatible avec l audience du scrutin', 422);
        }
        if ($userId > 0) {
            ensure_user_has_voter_role($pdo, $userId);
        }

        $stmt = $pdo->prepare('
            INSERT INTO candidates(election_id, user_id, full_name, biography, photo_path, category, display_order, is_validated, is_active)
            VALUES(?,?,?,?,?,?,?,?,?)
        ');
        $stmt->execute([
            $electionId,
            $userId > 0 ? $userId : null,
            $name,
            (string)($data['biography'] ?? ''),
            ($data['photo_path'] ?? null) ?: null,
            $category !== '' ? $category : null,
            (int)($data['display_order'] ?? 0),
            !empty($data['is_validated']) ? 1 : 0,
            !empty($data['is_active']) ? 1 : 0,
        ]);
        $id = (int)$pdo->lastInsertId();
        audit_event($pdo, 'CANDIDATE_CREATE', 'CANDIDATE', $id, ['election_id' => $electionId, 'user_id' => $userId > 0 ? $userId : null]);
        json_success(['message' => 'Cree', 'id' => $id], 201);
    }

    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_error('ID manquant', 422);

        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) json_error('JSON invalide', 422);

        $stCand = $pdo->prepare('
            SELECT c.id, c.election_id, e.audience_mode, e.created_by
            FROM candidates c
            JOIN elections e ON e.id = c.election_id
            WHERE c.id=?
            LIMIT 1
        ');
        $stCand->execute([$id]);
        $candidateRow = $stCand->fetch();
        if (!$candidateRow) {
            json_error('Candidat introuvable', 404);
        }
        require_manage_election($candidateRow);
        $audienceMode = strtoupper((string)($candidateRow['audience_mode'] ?? 'INTERNAL'));

        $userId = (int)($data['user_id'] ?? 0);
        $linkedUser = $userId > 0 ? load_candidate_user($pdo, $userId) : null;
        if ($userId > 0 && !$linkedUser) {
            json_error('Utilisateur candidat introuvable ou inactif', 422);
        }
        if ($linkedUser && !audience_allows_user_type($audienceMode, (string)($linkedUser['user_type'] ?? 'INTERNAL'))) {
            json_error('Type utilisateur candidat incompatible avec l audience du scrutin', 422);
        }

        $name = trim((string)($data['full_name'] ?? ''));
        if ($name === '' && $linkedUser) {
            $name = trim((string)($linkedUser['full_name'] ?? ''));
            if ($name === '') {
                $name = (string)($linkedUser['username'] ?? '');
            }
        }
        if ($name === '') json_error('Nom requis', 422);

        $category = ($data['category'] ?? null);
        $category = is_string($category) ? trim($category) : '';
        if ($category === '' && $linkedUser) {
            $category = strtoupper((string)($linkedUser['user_type'] ?? ''));
        }
        if ($category !== '') $category = strtoupper($category);
        if ($category !== '' && in_array(strtoupper($category), ['INTERNAL', 'EXTERNAL'], true) && !audience_allows_user_type($audienceMode, $category)) {
            json_error('Categorie candidat incompatible avec l audience du scrutin', 422);
        }
        if ($userId > 0) {
            ensure_user_has_voter_role($pdo, $userId);
        }

        $stmt = $pdo->prepare('
            UPDATE candidates
            SET user_id=?, full_name=?, biography=?, photo_path=?, category=?, display_order=?, is_validated=?, is_active=?
            WHERE id=?
        ');
        $stmt->execute([
            $userId > 0 ? $userId : null,
            $name,
            (string)($data['biography'] ?? ''),
            ($data['photo_path'] ?? null) ?: null,
            $category !== '' ? $category : null,
            (int)($data['display_order'] ?? 0),
            !empty($data['is_validated']) ? 1 : 0,
            !empty($data['is_active']) ? 1 : 0,
            $id,
        ]);
        audit_event($pdo, 'CANDIDATE_UPDATE', 'CANDIDATE', $id, ['user_id' => $userId > 0 ? $userId : null]);
        json_success(['message' => 'Mis a jour']);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_error('ID manquant', 422);
        $stCand = $pdo->prepare('
            SELECT c.id, e.created_by
            FROM candidates c
            JOIN elections e ON e.id = c.election_id
            WHERE c.id=?
            LIMIT 1
        ');
        $stCand->execute([$id]);
        $candidateRow = $stCand->fetch();
        if (!$candidateRow) json_error('Candidat introuvable', 404);
        require_manage_election($candidateRow);
        $pdo->prepare('DELETE FROM candidates WHERE id=?')->execute([$id]);
        audit_event($pdo, 'CANDIDATE_DELETE', 'CANDIDATE', $id, []);
        json_success(['message' => 'Supprime']);
    }

    json_error('Methode non autorisee', 405);
} catch (PDOException $e) {
    $debug = env_get('APP_ENV', 'local') !== 'production';
    json_error($debug ? $e->getMessage() : 'Erreur serveur', 500);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $debug = env_get('APP_ENV', 'local') !== 'production';
    json_error($debug ? $e->getMessage() : 'Erreur serveur', 500);
}
