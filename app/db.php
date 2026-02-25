<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Vote\Infrastructure\Persistence\PdoConnectionFactory;

static $pdo;
if ($pdo instanceof PDO) {
    return $pdo;
}

$pdo = (new PdoConnectionFactory())->create();

return $pdo;
