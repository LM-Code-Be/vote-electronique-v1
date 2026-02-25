<?php
declare(strict_types=1);

namespace Vote\Application\Audit;

use PDO;
use Throwable;
use Vote\Infrastructure\Http\RequestContext;

final class AuditTrail
{
    public function __construct(
        private readonly RequestContext $requestContext
    ) {
    }

    public function record(PDO $pdo, string $action, ?string $entityType = null, ?int $entityId = null, array $meta = [], ?int $actorUserId = null): void
    {
        $ip = $this->requestContext->ip();
        $ua = $this->requestContext->userAgent();

        try {
            $json = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $stmt = $pdo->prepare('INSERT INTO audit_logs(actor_user_id,action,entity_type,entity_id,metadata_json,ip,user_agent) VALUES(?,?,?,?,?,?,?)');
            $stmt->execute([$actorUserId, $action, $entityType, $entityId, $json, $ip, $ua]);
        } catch (Throwable $e) {
            // Best-effort audit: never break the main flow.
        }
    }
}
