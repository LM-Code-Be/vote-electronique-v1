<?php
declare(strict_types=1);

namespace Vote\Application\Logging;

use PDO;
use Throwable;
use Vote\Infrastructure\Http\RequestContext;

final class ActionLogger
{
    public function __construct(
        private readonly RequestContext $requestContext
    ) {
    }

    public function log(PDO $pdo, string $actionType, ?string $email = null, ?string $details = null, ?int $actorUserId = null): void
    {
        $ip = $this->requestContext->ip();
        $ua = $this->requestContext->userAgent();

        try {
            $stmt = $pdo->prepare('INSERT INTO logs(action_type,email,admin_user_id,ip,user_agent,details) VALUES(?,?,?,?,?,?)');
            $stmt->execute([$actionType, $email, $actorUserId, $ip, $ua, $details]);
            return;
        } catch (Throwable $e) {
            // Fall back to legacy schema.
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO logs(action_type,email,details) VALUES(?,?,?)');
            $stmt->execute([$actionType, $email, $details]);
        } catch (Throwable $e) {
            // Best-effort logging: never break the main flow.
        }
    }
}
