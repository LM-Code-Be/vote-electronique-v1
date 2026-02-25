<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

function out(string $msg): void { fwrite(STDOUT, $msg . PHP_EOL); }
function err(string $msg): void { fwrite(STDERR, $msg . PHP_EOL); }

$opt = getopt('', ['username:', 'password:', 'email::', 'full_name::', 'roles::', 'status::', 'user_type::', 'update::']);
$username = isset($opt['username']) ? trim((string)$opt['username']) : '';
$password = isset($opt['password']) ? (string)$opt['password'] : '';
$email = isset($opt['email']) ? trim((string)$opt['email']) : '';
$fullName = isset($opt['full_name']) ? trim((string)$opt['full_name']) : '';
$rolesRaw = isset($opt['roles']) ? trim((string)$opt['roles']) : '';
$status = isset($opt['status']) ? strtoupper(trim((string)$opt['status'])) : 'ACTIVE';
$userType = isset($opt['user_type']) ? strtoupper(trim((string)$opt['user_type'])) : 'INTERNAL';
$update = array_key_exists('update', $opt);

if ($username === '' || $password === '') {
    err('Usage: php scripts/user_create.php --username=admin --password=DemoPass123! [--email=...] [--full_name="..."] [--roles=SUPERADMIN,ADMIN,VOTER] [--user_type=INTERNAL|EXTERNAL] [--update]');
    exit(2);
}
if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
    err('Erreur: username invalide (3-50, a-z0-9._-)');
    exit(2);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    err('Erreur: email invalide');
    exit(2);
}
if (strlen($password) < 8) {
    err('Erreur: mot de passe trop court (min 8).');
    exit(2);
}
if ($fullName === '') $fullName = $username;
if (!in_array($status, ['ACTIVE','SUSPENDED','DELETED'], true)) $status = 'ACTIVE';
if (!in_array($userType, ['INTERNAL','EXTERNAL'], true)) $userType = 'INTERNAL';

/** @var PDO $pdo */
$pdo = require __DIR__ . '/../app/db.php';

// Ensure enterprise schema exists
try {
    $pdo->query('SELECT 1 FROM users LIMIT 1');
    $pdo->query('SELECT 1 FROM roles LIMIT 1');
} catch (Throwable $e) {
    err("Erreur: schéma enterprise absent. Lance d'abord: php scripts/migrate.php up");
    exit(1);
}

$roles = $rolesRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', strtoupper($rolesRaw))))) : ['SUPERADMIN','ADMIN'];
if (!$roles) $roles = ['VOTER'];

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    err('Erreur: impossible de hasher le mot de passe.');
    exit(1);
}

try {
    $pdo->beginTransaction();

    $userId = null;
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
    $stmt->execute([$username]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        $userId = (int)$existing;
        if (!$update) {
            err('Erreur: username déjà existant. Relance avec --update.');
            $pdo->rollBack();
            exit(1);
        }
        $pdo->prepare('UPDATE users SET email=?, full_name=?, password_hash=?, user_type=?, status=?, updated_at=NOW() WHERE id=?')
            ->execute([$email !== '' ? mb_strtolower($email) : null, $fullName, $hash, $userType, $status, $userId]);
        $pdo->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$userId]);
    } else {
        $pdo->prepare('INSERT INTO users(username,email,password_hash,full_name,user_type,status) VALUES(?,?,?,?,?,?)')
            ->execute([$username, $email !== '' ? mb_strtolower($email) : null, $hash, $fullName, $userType, $status]);
        $userId = (int)$pdo->lastInsertId();
    }

    $in = implode(',', array_fill(0, count($roles), '?'));
    // Build a code => role_id map for fast/clear assignment.
    $st = $pdo->prepare("SELECT code,id FROM roles WHERE code IN ($in)");
    $st->execute($roles);
    $roleIds = $st->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    $ins = $pdo->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)');
    foreach ($roles as $code) {
        $rid = (int)($roleIds[$code] ?? 0);
        if ($rid > 0) $ins->execute([$userId, $rid]);
    }

    $pdo->commit();
    out(($existing ? 'User mis à jour: ' : 'User créé: ') . $username . ' (id=' . $userId . ') type=' . $userType . ' roles=' . implode(',', $roles));
    exit(0);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (($e->errorInfo[1] ?? null) === 1062) {
        err('Erreur: contrainte unique violée (email/matricule).');
        exit(1);
    }
    throw $e;
}
