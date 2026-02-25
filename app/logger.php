<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Vote\Infrastructure\Composition\AppServices;

function request_ip(): ?string
{
    return AppServices::requestContext()->ip();
}

function request_user_agent(): ?string
{
    return AppServices::requestContext()->userAgent();
}

function request_actor_user_id(): ?int
{
    $userId = AppServices::auth()->currentId();
    if ($userId !== null) return $userId;

    $legacyAdminId = $_SESSION['admin_user_id'] ?? null;
    if ($legacyAdminId !== null) {
        $v = (int)$legacyAdminId;
        if ($v > 0) {
            return $v;
        }
    }
    return null;
}

function log_action(PDO $pdo, string $actionType, ?string $email = null, ?string $details = null): void
{
    AppServices::actionLogger()->log($pdo, $actionType, $email, $details, request_actor_user_id());
}
