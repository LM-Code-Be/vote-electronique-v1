<?php
declare(strict_types=1);

require_once __DIR__ . '/user_auth.php';

use Vote\Infrastructure\Composition\AppServices;

function audit_event(PDO $pdo, string $action, ?string $entityType = null, ?int $entityId = null, array $meta = []): void
{
    AppServices::auditTrail()->record($pdo, $action, $entityType, $entityId, $meta, user_current_id());
}
