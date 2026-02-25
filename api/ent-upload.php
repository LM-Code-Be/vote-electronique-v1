<?php
declare(strict_types=1);

/** @var PDO $pdo */
$pdo = require __DIR__ . '/ent_bootstrap.php';

user_require_role(['ADMIN', 'SUPERADMIN'], true);
csrf_require();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_error('Méthode interdite', 405);
}

if (empty($_FILES['file']['tmp_name'])) {
    json_error('Fichier manquant', 422);
}

$maxBytes = (int)(env_get('UPLOAD_MAX_BYTES', '5242880') ?? 5242880); // 5MB
if (!empty($_FILES['file']['size']) && (int)$_FILES['file']['size'] > $maxBytes) {
    json_error('Fichier trop volumineux', 413);
}

$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$ext = strtolower(pathinfo((string)$_FILES['file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed, true)) {
    json_error('Extension non autorisée (jpg, jpeg, png, gif, webp)', 415);
}

$targetDir = __DIR__ . '/../uploads';
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
    json_error('Impossible de créer le dossier uploads', 500);
}

$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$destPath = $targetDir . '/' . $filename;
if (!move_uploaded_file((string)$_FILES['file']['tmp_name'], $destPath)) {
    json_error('Erreur de déplacement du fichier', 500);
}

$publicPath = 'uploads/' . $filename;
audit_event($pdo, 'UPLOAD', null, null, ['path' => $publicPath]);
json_success(['path' => $publicPath, 'message' => 'Upload réussi']);

