<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

user_require_login(false);
$got = (string)($_GET['csrf'] ?? '');
if ($got === '' || !hash_equals(csrf_token(), $got)) {
    http_response_code(403);
    echo 'CSRF invalide';
    exit;
}

user_logout($pdo);
header('Location: ' . app_url('/enterprise/login.php'));
exit;
