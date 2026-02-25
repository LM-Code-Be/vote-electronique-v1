<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        user_require_role(['ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], true);

        $scope = (string)($_GET['scope'] ?? '');
        $isScrutOnly = user_has_role('SCRUTATEUR') && !user_has_role('ADMIN') && !user_has_role('SUPERADMIN');
        if ($isScrutOnly && !in_array($scope, ['organizers', 'candidates'], true)) {
            json_error('Acces refuse', 403);
        }
        if ($scope === 'organizers') {
            $rows = $pdo->query("
                SELECT
                    u.id,
                    u.username,
                    u.full_name,
                    u.user_type,
                    u.status,
                    GROUP_CONCAT(DISTINCT r.code ORDER BY r.code SEPARATOR ',') AS role_codes
                FROM users u
                JOIN user_roles ur ON ur.user_id = u.id
                JOIN roles r ON r.id = ur.role_id
                WHERE UPPER(TRIM(COALESCE(u.status, '')))='ACTIVE'
                  AND r.code IN ('SUPERADMIN','ADMIN','SCRUTATEUR')
                GROUP BY u.id, u.username, u.full_name, u.user_type, u.status
                ORDER BY
                    CASE WHEN u.full_name IS NULL OR u.full_name='' THEN u.username ELSE u.full_name END ASC
            ")->fetchAll() ?: [];

            foreach ($rows as &$u) {
                $label = trim((string)($u['full_name'] ?? ''));
                if ($label === '') {
                    $label = (string)($u['username'] ?? '');
                }
                $u['label'] = $label;
            }
            unset($u);

            json_success($rows);
        }

        if ($scope === 'candidates') {
            $rows = $pdo->query("
                SELECT id, username, full_name, user_type, status
                FROM users
                WHERE UPPER(TRIM(COALESCE(status, '')))='ACTIVE'
                ORDER BY
                    CASE WHEN full_name IS NULL OR full_name='' THEN username ELSE full_name END ASC
            ")->fetchAll() ?: [];

            foreach ($rows as &$u) {
                $label = trim((string)($u['full_name'] ?? ''));
                if ($label === '') {
                    $label = (string)($u['username'] ?? '');
                }
                $u['label'] = $label;
            }
            unset($u);

            json_success($rows);
        }

        $rows = $pdo->query('SELECT id,username,email,full_name,phone,service AS departement,service,employee_id,user_type,status,must_reset_password,created_at,last_login_at FROM users ORDER BY username')->fetchAll() ?: [];

        $roleRows = $pdo->query("
            SELECT ur.user_id, r.code
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
        ")->fetchAll() ?: [];
        $rolesByUser = [];
        foreach ($roleRows as $rr) {
            $uid = (int)$rr['user_id'];
            $rolesByUser[$uid] ??= [];
            $rolesByUser[$uid][] = (string)$rr['code'];
        }

        $groupRows = $pdo->query("
            SELECT ug.user_id, g.id AS group_id, g.name, g.type
            FROM user_groups ug
            JOIN `groups` g ON g.id = ug.group_id
        ")->fetchAll() ?: [];
        $groupsByUser = [];
        foreach ($groupRows as $gr) {
            $uid = (int)$gr['user_id'];
            $groupsByUser[$uid] ??= [];
            $groupsByUser[$uid][] = [
                'id' => (int)$gr['group_id'],
                'name' => (string)$gr['name'],
                'type' => (string)$gr['type'],
            ];
        }

        foreach ($rows as &$u) {
            $id = (int)$u['id'];
            $u['departement'] = (string)($u['departement'] ?? $u['service'] ?? '');
            $u['roles'] = $rolesByUser[$id] ?? [];
            $u['groups'] = $groupsByUser[$id] ?? [];
        }
        unset($u);

        $format = (string)($_GET['format'] ?? '');
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="users_' . date('Ymd_His') . '.csv"');
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['username', 'full_name', 'email', 'phone', 'departement', 'employee_id', 'user_type', 'status', 'roles', 'groups', 'must_reset_password', 'created_at', 'last_login_at']);
            foreach ($rows as $u) {
                $roles = isset($u['roles']) && is_array($u['roles']) ? implode('|', array_map('strval', $u['roles'])) : '';
                $groups = isset($u['groups']) && is_array($u['groups']) ? implode('|', array_map(fn($g) => ($g['type'] ?? '') . ':' . ($g['name'] ?? ''), $u['groups'])) : '';
                fputcsv($out, [
                    (string)($u['username'] ?? ''),
                    (string)($u['full_name'] ?? ''),
                    (string)($u['email'] ?? ''),
                    (string)($u['phone'] ?? ''),
                    (string)($u['departement'] ?? $u['service'] ?? ''),
                    (string)($u['employee_id'] ?? ''),
                    (string)($u['user_type'] ?? 'INTERNAL'),
                    (string)($u['status'] ?? ''),
                    $roles,
                    $groups,
                    !empty($u['must_reset_password']) ? '1' : '0',
                    (string)($u['created_at'] ?? ''),
                    (string)($u['last_login_at'] ?? ''),
                ]);
            }
            fclose($out);
            exit;
        }

        json_success($rows);
    }

    // Operations (admin only): reset password, revoke sessions
    if ($method === 'POST' && isset($_GET['op'])) {
        user_require_role(['ADMIN', 'SUPERADMIN'], true);
        csrf_require();

        $op = (string)$_GET['op'];
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_error('id manquant', 422);

        if ($op === 'reset_password') {
            $stmt = $pdo->prepare('SELECT id, username, status FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $u = $stmt->fetch();
            if (!$u) json_error('Utilisateur introuvable', 404);
            if (($u['status'] ?? 'ACTIVE') !== 'ACTIVE') json_error('Compte inactif', 409);

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = (new DateTimeImmutable('now'))->modify('+2 hours')->format('Y-m-d H:i:s');

            $pdo->prepare('INSERT INTO password_resets(user_id, token_hash, expires_at) VALUES(?,?,?)')
                ->execute([(int)$u['id'], $tokenHash, $expiresAt]);
            $pdo->prepare('UPDATE users SET must_reset_password=1, updated_at=NOW() WHERE id=?')->execute([(int)$u['id']]);

            audit_event($pdo, 'USER_RESET_PASSWORD', 'USER', (int)$u['id'], ['username' => (string)$u['username']]);

            $resetUrl = app_url('/enterprise/reset.php?token=' . urlencode($token));
            json_success(['reset_url' => $resetUrl, 'expires_at' => $expiresAt]);
        }

        if ($op === 'revoke_sessions') {
            $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL')->execute([$id]);
            $count = (int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn();
            audit_event($pdo, 'USER_REVOKE_SESSIONS', 'USER', $id, ['revoked' => $count]);
            json_success(['revoked' => $count]);
        }

        json_error('Opération inconnue', 422);
    }

    // Import via CSV multipart
    if ($method === 'POST' && !empty($_FILES['file']['tmp_name'])) {
        user_require_role(['ADMIN', 'SUPERADMIN'], true);
        csrf_require();

        $handle = fopen((string)$_FILES['file']['tmp_name'], 'rb');
        if ($handle === false) json_error('Impossible de lire le fichier', 500);

        $header = null;
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        $roleIdVoter = (int)$pdo->query("SELECT id FROM roles WHERE code='VOTER'")->fetchColumn();
        if ($roleIdVoter <= 0) json_error('Rôle VOTER manquant', 500);

        $pdo->beginTransaction();
        try {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if (!$row) continue;

                if ($header === null) {
                    $lower = array_map(fn($v) => strtolower(trim((string)$v)), $row);
                    if (in_array('email', $lower, true) || in_array('username', $lower, true)) {
                        $header = $lower;
                        continue;
                    }
                    $header = ['username', 'full_name', 'email', 'phone', 'departement', 'employee_id', 'user_type', 'status', 'group', 'password'];
                }

                $map = [];
                foreach ($header as $i => $key) {
                    $map[$key] = isset($row[$i]) ? trim((string)$row[$i]) : '';
                }

                $username = $map['username'] ?? '';
                $email = $map['email'] ?? '';
                $fullName = $map['full_name'] ?? '';
                $employeeId = $map['employee_id'] ?? '';
                $departement = trim((string)($map['departement'] ?? ($map['service'] ?? '')));
                $phone = $map['phone'] ?? '';
                $userType = strtoupper(trim((string)($map['user_type'] ?? 'INTERNAL')));
                $status = strtoupper($map['status'] ?? 'ACTIVE');
                $groupName = $map['group'] ?? '';
                $plainPassword = (string)($map['password'] ?? '');

                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    continue;
                }
                if ($fullName === '') $fullName = $username;
                if ($username === '' && $email !== '') $username = explode('@', $email)[0];
                if ($username === '' || !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
                    $skipped++;
                    continue;
                }
                if (!in_array($userType, ['INTERNAL', 'EXTERNAL'], true)) $userType = 'INTERNAL';
                if (!in_array($status, ['ACTIVE','SUSPENDED','DELETED'], true)) $status = 'ACTIVE';

                // Upsert by employee_id > email > username
                $userId = null;
                if ($employeeId !== '') {
                    $st = $pdo->prepare('SELECT id FROM users WHERE employee_id=? LIMIT 1');
                    $st->execute([$employeeId]);
                    $col = $st->fetchColumn();
                    $userId = $col ? (int)$col : null;
                }
                if ($userId === null && $email !== '') {
                    $st = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
                    $st->execute([mb_strtolower($email)]);
                    $col = $st->fetchColumn();
                    $userId = $col ? (int)$col : null;
                }
                if ($userId === null) {
                    $st = $pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
                    $st->execute([$username]);
                    $col = $st->fetchColumn();
                    $userId = $col ? (int)$col : null;
                }

                if ($userId === null) {
                    // Default random password; admin can force reset.
                    $mustReset = 1;
                    if ($plainPassword !== '' && strlen($plainPassword) >= 8) {
                        $pwdHash = password_hash($plainPassword, PASSWORD_DEFAULT);
                        $mustReset = 0;
                    } else {
                        $tmpPwd = bin2hex(random_bytes(6));
                        $pwdHash = password_hash($tmpPwd, PASSWORD_DEFAULT);
                        $mustReset = 1;
                    }
                    $ins = $pdo->prepare('INSERT INTO users(username,email,password_hash,full_name,phone,service,employee_id,user_type,status,must_reset_password) VALUES(?,?,?,?,?,?,?,?,?,?)');
                    $ins->execute([
                        $username,
                        $email !== '' ? mb_strtolower($email) : null,
                        $pwdHash ?: '',
                        $fullName,
                        $phone !== '' ? $phone : null,
                        $departement !== '' ? $departement : null,
                        $employeeId !== '' ? $employeeId : null,
                        $userType,
                        $status,
                        $mustReset,
                    ]);
                    $userId = (int)$pdo->lastInsertId();

                    $pdo->prepare('INSERT IGNORE INTO user_roles(user_id,role_id) VALUES(?,?)')->execute([$userId, $roleIdVoter]);
                    $imported++;
                } else {
                    if ($plainPassword !== '' && strlen($plainPassword) >= 8) {
                        $pwdHash = password_hash($plainPassword, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare('UPDATE users SET username=?, email=?, password_hash=?, must_reset_password=0, full_name=?, phone=?, service=?, employee_id=?, user_type=?, status=?, updated_at=NOW() WHERE id=?');
                        $upd->execute([
                            $username,
                            $email !== '' ? mb_strtolower($email) : null,
                            $pwdHash ?: '',
                            $fullName,
                            $phone !== '' ? $phone : null,
                            $departement !== '' ? $departement : null,
                            $employeeId !== '' ? $employeeId : null,
                            $userType,
                            $status,
                            $userId,
                        ]);
                    } else {
                        $upd = $pdo->prepare('UPDATE users SET username=?, email=?, full_name=?, phone=?, service=?, employee_id=?, user_type=?, status=?, updated_at=NOW() WHERE id=?');
                        $upd->execute([
                            $username,
                            $email !== '' ? mb_strtolower($email) : null,
                            $fullName,
                            $phone !== '' ? $phone : null,
                            $departement !== '' ? $departement : null,
                            $employeeId !== '' ? $employeeId : null,
                            $userType,
                            $status,
                            $userId,
                        ]);
                    }
                    $pdo->prepare('INSERT IGNORE INTO user_roles(user_id,role_id) VALUES(?,?)')->execute([$userId, $roleIdVoter]);
                    $updated++;
                }

                if ($groupName !== '') {
                    $st = $pdo->prepare("SELECT id FROM `groups` WHERE name=? AND type='DEPT' LIMIT 1");
                    $st->execute([$groupName]);
                    $gid = (int)($st->fetchColumn() ?: 0);
                    if ($gid <= 0) {
                        $pdo->prepare("INSERT INTO `groups`(name,type) VALUES(?, 'DEPT')")->execute([$groupName]);
                        $gid = (int)$pdo->lastInsertId();
                    }
                    $pdo->prepare('INSERT IGNORE INTO user_groups(user_id,group_id) VALUES(?,?)')->execute([$userId, $gid]);
                }
            }
            fclose($handle);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        audit_event($pdo, 'USERS_IMPORT', null, null, compact('imported','updated','skipped'));
        json_success(['imported' => $imported, 'updated' => $updated, 'skipped' => $skipped]);
    }

    // JSON CRUD
    user_require_role(['ADMIN', 'SUPERADMIN'], true);
    csrf_require();

    if ($method === 'POST') {
        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) json_error('JSON invalide', 422);

        $username = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $fullName = trim((string)($data['full_name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $departement = trim((string)($data['departement'] ?? ($data['service'] ?? '')));
        $employeeId = trim((string)($data['employee_id'] ?? ''));
        $userType = strtoupper(trim((string)($data['user_type'] ?? 'INTERNAL')));
        $status = strtoupper(trim((string)($data['status'] ?? 'ACTIVE')));
        $roles = $data['roles'] ?? [];
        $groups = $data['groups'] ?? [];
        $canManageRoles = user_has_role('SUPERADMIN');

        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) json_error('Username invalide', 422);
        if ($fullName === '') json_error('Nom requis', 422);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Email invalide', 422);
        if (strlen($password) < 8) json_error('Mot de passe trop court (min 8)', 422);
        if (!in_array($userType, ['INTERNAL', 'EXTERNAL'], true)) $userType = 'INTERNAL';
        if (!in_array($status, ['ACTIVE','SUSPENDED','DELETED'], true)) $status = 'ACTIVE';

        $pwdHash = password_hash($password, PASSWORD_DEFAULT);
        if ($pwdHash === false) json_error('Erreur de hash', 500);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO users(username,email,password_hash,full_name,phone,service,employee_id,user_type,status,must_reset_password) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $username,
                $email !== '' ? mb_strtolower($email) : null,
                $pwdHash,
                $fullName,
                $phone !== '' ? $phone : null,
                $departement !== '' ? $departement : null,
                $employeeId !== '' ? $employeeId : null,
                $userType,
                $status,
                0,
            ]);
            $userId = (int)$pdo->lastInsertId();

            // roles
            $roleCodes = array_values(array_unique(array_map('strtoupper', is_array($roles) ? $roles : [])));
            if (!$roleCodes) $roleCodes = ['VOTER'];
            if (!$canManageRoles) {
                $roleCodes = ['VOTER'];
            }
            if (in_array('SUPERADMIN', $roleCodes, true) && !$canManageRoles) {
                json_error('Seul un SUPERADMIN peut attribuer le rôle SUPERADMIN', 403);
            }
            $in = implode(',', array_fill(0, count($roleCodes), '?'));
            $st = $pdo->prepare("SELECT id,code FROM roles WHERE code IN ($in)");
            $st->execute($roleCodes);
            $rows = $st->fetchAll() ?: [];
            $ins = $pdo->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)');
            foreach ($rows as $r) {
                $ins->execute([$userId, (int)$r['id']]);
            }

            // groups
            if (is_array($groups) && $groups) {
                $insG = $pdo->prepare('INSERT IGNORE INTO user_groups(user_id,group_id) VALUES(?,?)');
                foreach ($groups as $g) {
                    $gid = (int)$g;
                    if ($gid > 0) $insG->execute([$userId, $gid]);
                }
            }

            $pdo->commit();
            audit_event($pdo, 'USER_CREATE', 'USER', $userId, ['username' => $username, 'roles' => $roleCodes]);
            json_success(['message' => 'Créé', 'id' => $userId], 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_error('ID manquant', 422);

        $data = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($data)) json_error('JSON invalide', 422);

        $username = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $password = isset($data['password']) ? (string)$data['password'] : null;
        $fullName = trim((string)($data['full_name'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $departement = trim((string)($data['departement'] ?? ($data['service'] ?? '')));
        $employeeId = trim((string)($data['employee_id'] ?? ''));
        $userType = strtoupper(trim((string)($data['user_type'] ?? 'INTERNAL')));
        $status = strtoupper(trim((string)($data['status'] ?? 'ACTIVE')));
        $roles = $data['roles'] ?? null;
        $groups = $data['groups'] ?? null;
        $canManageRoles = user_has_role('SUPERADMIN');
        if (!$canManageRoles) {
            $roles = null;
        }

        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) json_error('Username invalide', 422);
        if ($fullName === '') json_error('Nom requis', 422);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Email invalide', 422);
        if ($password !== null && $password !== '' && strlen($password) < 8) json_error('Mot de passe trop court (min 8)', 422);
        if (!in_array($userType, ['INTERNAL', 'EXTERNAL'], true)) $userType = 'INTERNAL';
        if (!in_array($status, ['ACTIVE','SUSPENDED','DELETED'], true)) $status = 'ACTIVE';

        $me = user_current_id();
        if ($me !== null && $id === $me && $status !== 'ACTIVE') {
            json_error('Impossible de suspendre ton propre compte', 409);
        }

        // Ensure at least one active superadmin remains
        if (is_array($roles)) {
            $newRoles = array_values(array_unique(array_map('strtoupper', $roles)));
            if (in_array('SUPERADMIN', $newRoles, true) && !$canManageRoles) {
                json_error('Seul un SUPERADMIN peut attribuer le rôle SUPERADMIN', 403);
            }
            if (!$canManageRoles) {
                $st = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM user_roles ur
                    JOIN roles r ON r.id=ur.role_id
                    WHERE ur.user_id=? AND r.code='SUPERADMIN'
                ");
                $st->execute([$id]);
                if ((int)$st->fetchColumn() > 0) {
                    json_error('Seul un SUPERADMIN peut modifier les rôles d’un SUPERADMIN', 403);
                }
            }
            if ($me !== null && $id === $me && !in_array('SUPERADMIN', $newRoles, true)) {
                json_error('Impossible de retirer SUPERADMIN à ton propre compte', 409);
            }
            if (!in_array('SUPERADMIN', $newRoles, true)) {
                $count = (int)$pdo->query("
                    SELECT COUNT(DISTINCT u.id)
                    FROM users u
                    JOIN user_roles ur ON ur.user_id=u.id
                    JOIN roles r ON r.id=ur.role_id AND r.code='SUPERADMIN'
                    WHERE u.status='ACTIVE'
                ")->fetchColumn();
                if ($count <= 1) {
                    // check if this user is a superadmin
                    $st = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM user_roles ur JOIN roles r ON r.id=ur.role_id
                        WHERE ur.user_id=? AND r.code='SUPERADMIN'
                    ");
                    $st->execute([$id]);
                    if ((int)$st->fetchColumn() > 0) {
                        json_error('Au moins 1 superadmin actif requis', 409);
                    }
                }
            }
        }

        $pdo->beginTransaction();
        try {
            $fields = [
                'username' => $username,
                'email' => $email !== '' ? mb_strtolower($email) : null,
                'full_name' => $fullName,
                'phone' => $phone !== '' ? $phone : null,
                'service' => $departement !== '' ? $departement : null,
                'employee_id' => $employeeId !== '' ? $employeeId : null,
                'user_type' => $userType,
                'status' => $status,
            ];
            if ($password !== null && $password !== '') {
                $pwdHash = password_hash($password, PASSWORD_DEFAULT);
                if ($pwdHash === false) json_error('Erreur de hash', 500);
                $fields['password_hash'] = $pwdHash;
                $fields['must_reset_password'] = 0;
            }

            $set = [];
            $params = [];
            foreach ($fields as $k => $v) {
                $set[] = "$k=?";
                $params[] = $v;
            }
            $params[] = $id;
            $pdo->prepare('UPDATE users SET ' . implode(',', $set) . ', updated_at=NOW() WHERE id=?')->execute($params);

            if ($password !== null && $password !== '') {
                // revoke other sessions (best-effort)
                try {
                    $sid = session_id();
                    $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL AND session_id<>?')
                        ->execute([$id, $sid]);
                } catch (Throwable $e) {
                    // ignore
                }
            }

            if (is_array($roles)) {
                $pdo->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$id]);
                $roleCodes = array_values(array_unique(array_map('strtoupper', $roles)));
                if (!$roleCodes) $roleCodes = ['VOTER'];
                $in = implode(',', array_fill(0, count($roleCodes), '?'));
                $st = $pdo->prepare("SELECT id FROM roles WHERE code IN ($in)");
                $st->execute($roleCodes);
                $ids = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $ins = $pdo->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)');
                foreach ($ids as $rid) $ins->execute([$id, (int)$rid]);
            }

            if (is_array($groups)) {
                $pdo->prepare('DELETE FROM user_groups WHERE user_id=?')->execute([$id]);
                $insG = $pdo->prepare('INSERT IGNORE INTO user_groups(user_id,group_id) VALUES(?,?)');
                foreach ($groups as $g) {
                    $gid = (int)$g;
                    if ($gid > 0) $insG->execute([$id, $gid]);
                }
            }

            // if suspended/deleted: revoke sessions
            if ($status !== 'ACTIVE') {
                $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL')->execute([$id]);
            }

            $pdo->commit();
            audit_event($pdo, 'USER_UPDATE', 'USER', $id, ['status' => $status]);
            json_success(['message' => 'Mis à jour']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) json_error('ID manquant', 422);
        $me = user_current_id();
        if ($me !== null && $id === $me) json_error('Impossible de supprimer ton propre compte', 409);

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET status='DELETED', updated_at=NOW() WHERE id=?")->execute([$id]);
            $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL')->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        audit_event($pdo, 'USER_DELETE', 'USER', $id, []);
        json_success(['message' => 'Supprimé']);
    }

    json_error('Méthode non autorisée', 405);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? null) === 1062) {
        json_error('Contrainte unique violée (username/email/matricule)', 409);
    }
    $debug = env_get('APP_ENV', 'local') !== 'production';
    json_error($debug ? $e->getMessage() : 'Erreur serveur', 500);
} catch (Throwable $e) {
    $debug = env_get('APP_ENV', 'local') !== 'production';
    json_error($debug ? $e->getMessage() : 'Erreur serveur', 500);
}
