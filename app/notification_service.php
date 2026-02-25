<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Vote\Infrastructure\Composition\AppServices;

function app_is_maintenance(): bool
{
    return AppServices::notifications()->isMaintenance();
}

function notifications_active(PDO $pdo, int $limit = 3): array
{
    return AppServices::notifications()->active($pdo, $limit);
}
