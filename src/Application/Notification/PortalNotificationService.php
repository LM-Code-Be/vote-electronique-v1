<?php
declare(strict_types=1);

namespace Vote\Application\Notification;

use PDO;
use Throwable;

final class PortalNotificationService
{
    public function isMaintenance(): bool
    {
        try {
            if (function_exists('env_get_bool')) {
                return env_get_bool('MAINTENANCE', false);
            }
        } catch (Throwable $e) {
            // Fall through to default false.
        }

        return false;
    }

    public function active(PDO $pdo, int $limit = 3): array
    {
        try {
            $stmt = $pdo->prepare('
                SELECT title, body, level
                FROM notifications
                WHERE is_active=1
                  AND (starts_at IS NULL OR starts_at <= NOW())
                  AND (ends_at IS NULL OR ends_at >= NOW())
                ORDER BY created_at DESC
                LIMIT ?
            ');
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
