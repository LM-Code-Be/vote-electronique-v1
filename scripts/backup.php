<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$dbHost = env_get('DB_HOST', 'localhost');
$dbName = env_get('DB_NAME', 'vote_electronique');
$dbUser = env_get('DB_USER', 'root');
$dbPass = env_get('DB_PASS', '');
$mysqldump = env_get('MYSQLDUMP_BIN', 'mysqldump');

$ts = (new DateTimeImmutable('now'))->format('Ymd_His');
$outDir = __DIR__ . '/../backups';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
$outFile = $outDir . "/backup_{$dbName}_{$ts}.sql";

$cmd = sprintf(
    '%s --host=%s --user=%s %s %s > %s',
    escapeshellcmd($mysqldump),
    escapeshellarg((string)$dbHost),
    escapeshellarg((string)$dbUser),
    $dbPass !== '' ? ('--password=' . escapeshellarg((string)$dbPass)) : '',
    escapeshellarg((string)$dbName),
    escapeshellarg((string)$outFile)
);

fwrite(STDOUT, "Running: $mysqldump ...\n");
@exec($cmd, $output, $code);
if ($code !== 0 || !is_file($outFile) || filesize($outFile) === 0) {
    $safeCmd = $cmd;
    if ($dbPass !== '') {
        $safeCmd = preg_replace('/--password=\\S+/', '--password=***', $safeCmd) ?? $safeCmd;
    }
    fwrite(STDERR, "Echec. Essaie un export via phpMyAdmin (onglet Exporter), ou mets mysqldump dans le PATH.\n");
    fwrite(STDERR, "Commande: $safeCmd\n");
    exit(1);
}

fwrite(STDOUT, "OK: $outFile\n");
