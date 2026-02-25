<?php
declare(strict_types=1);

namespace Vote\Application\Notification;

use PDO;
use Throwable;

final class UserPushService
{
    private const LEVELS = ['INFO', 'SUCCESS', 'WARNING', 'ERROR'];
    private const SCOPES = ['ALL', 'VOTER', 'ADMIN', 'SCRUTATEUR', 'SUPERADMIN'];
    private const PORTAL_ROLES = ['VOTER', 'ADMIN', 'SCRUTATEUR', 'SUPERADMIN'];

    public function isReady(PDO $pdo): bool
    {
        try {
            $st = $pdo->query("SHOW TABLES LIKE 'user_notifications'");
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function recipientsForScope(PDO $pdo, string $scope = 'ALL'): array
    {
        $scope = strtoupper(trim($scope));
        if (!in_array($scope, self::SCOPES, true)) {
            $scope = 'ALL';
        }

        $roles = $scope === 'ALL' ? self::PORTAL_ROLES : [$scope];
        $in = implode(',', array_fill(0, count($roles), '?'));
        $sql = "
            SELECT DISTINCT u.id
            FROM users u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id
            WHERE u.status='ACTIVE'
              AND r.code IN ($in)
            ORDER BY u.id
        ";
        $st = $pdo->prepare($sql);
        $st->execute($roles);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function recipientsForElectionAudience(PDO $pdo, int $electionId, string $scope = 'ALL'): array
    {
        $scope = strtoupper(trim($scope));
        if (!in_array($scope, self::SCOPES, true)) {
            $scope = 'ALL';
        }

        $roles = $scope === 'ALL' ? self::PORTAL_ROLES : [$scope];
        $in = implode(',', array_fill(0, count($roles), '?'));

        $audSt = $pdo->prepare('SELECT audience_mode FROM elections WHERE id=? LIMIT 1');
        $audSt->execute([$electionId]);
        $aud = strtoupper((string)($audSt->fetchColumn() ?: 'INTERNAL'));
        if (!in_array($aud, ['INTERNAL', 'HYBRID', 'EXTERNAL'], true)) {
            $aud = 'INTERNAL';
        }

        $audSql = "u.user_type IN ('INTERNAL','EXTERNAL')";
        if ($aud === 'INTERNAL') {
            $audSql = "u.user_type='INTERNAL'";
        } elseif ($aud === 'EXTERNAL') {
            $audSql = "u.user_type='EXTERNAL'";
        }

        $sql = "
            SELECT DISTINCT u.id
            FROM users u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id
            WHERE u.status='ACTIVE'
              AND r.code IN ($in)
              AND $audSql
            ORDER BY u.id
        ";

        $st = $pdo->prepare($sql);
        $st->execute($roles);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function pushToUsers(
        PDO $pdo,
        array $userIds,
        string $title,
        string $body = '',
        string $level = 'INFO',
        ?string $targetUrl = null,
        ?string $eventType = null,
        ?int $electionId = null,
        ?int $sourceNotificationId = null
    ): int {
        if (!$this->isReady($pdo)) {
            return 0;
        }

        $title = trim($title);
        if ($title === '') {
            return 0;
        }

        $level = strtoupper(trim($level));
        if (!in_array($level, self::LEVELS, true)) {
            $level = 'INFO';
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $v): bool => $v > 0)));
        if (!$ids) {
            return 0;
        }

        $targetUrl = $targetUrl !== null ? trim($targetUrl) : null;
        if ($targetUrl === '') {
            $targetUrl = null;
        }
        if ($targetUrl !== null) {
            $targetUrl = substr($targetUrl, 0, 255);
        }

        $ins = $pdo->prepare('
            INSERT INTO user_notifications(
                user_id, source_notification_id, election_id, event_type, title, body, level, target_url
            ) VALUES(?,?,?,?,?,?,?,?)
        ');

        $count = 0;
        try {
            foreach ($ids as $uid) {
                $ins->execute([
                    $uid,
                    $sourceNotificationId,
                    $electionId,
                    $eventType,
                    $title,
                    $body !== '' ? $body : null,
                    $level,
                    $targetUrl,
                ]);
                $count++;
            }
        } catch (Throwable $e) {
            return 0;
        }
        return $count;
    }

    public function pushByScope(
        PDO $pdo,
        string $scope,
        string $title,
        string $body = '',
        string $level = 'INFO',
        ?string $targetUrl = null,
        ?string $eventType = null,
        ?int $electionId = null,
        ?int $sourceNotificationId = null
    ): int {
        try {
            $ids = $this->recipientsForScope($pdo, $scope);
            return $this->pushToUsers($pdo, $ids, $title, $body, $level, $targetUrl, $eventType, $electionId, $sourceNotificationId);
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function pushElectionPublished(PDO $pdo, int $electionId, string $electionTitle): int
    {
        $title = 'Nouveau scrutin publie';
        $body = 'Le scrutin "' . trim($electionTitle) . '" est ouvert.';
        $target = function_exists('app_url')
            ? app_url('/enterprise/vote.php?id=' . urlencode((string)$electionId))
            : '/enterprise/vote.php?id=' . urlencode((string)$electionId);
        try {
            $ids = $this->recipientsForElectionAudience($pdo, $electionId, 'ALL');
            return $this->pushToUsers($pdo, $ids, $title, $body, 'INFO', $target, 'ELECTION_PUBLISHED', $electionId, null);
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function pushElectionClosed(PDO $pdo, int $electionId, string $electionTitle): int
    {
        $title = 'Scrutin cloture';
        $body = 'Le scrutin "' . trim($electionTitle) . '" est termine.';
        $target = function_exists('app_url')
            ? app_url('/enterprise/results.php?id=' . urlencode((string)$electionId))
            : '/enterprise/results.php?id=' . urlencode((string)$electionId);
        try {
            $ids = $this->recipientsForElectionAudience($pdo, $electionId, 'ALL');
            return $this->pushToUsers($pdo, $ids, $title, $body, 'SUCCESS', $target, 'ELECTION_CLOSED', $electionId, null);
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function pushVoteCastToUser(PDO $pdo, int $userId, int $electionId, string $electionTitle): int
    {
        $title = 'Vote enregistre';
        $body = 'Ton vote pour "' . trim($electionTitle) . '" a bien ete pris en compte.';
        $target = function_exists('app_url')
            ? app_url('/enterprise/vote.php?id=' . urlencode((string)$electionId))
            : '/enterprise/vote.php?id=' . urlencode((string)$electionId);
        try {
            return $this->pushToUsers($pdo, [$userId], $title, $body, 'SUCCESS', $target, 'VOTE_CAST', $electionId, null);
        } catch (Throwable $e) {
            return 0;
        }
    }
}
