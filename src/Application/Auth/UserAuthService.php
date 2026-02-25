<?php
declare(strict_types=1);

namespace Vote\Application\Auth;

use DateTimeImmutable;
use PDO;
use Throwable;
use Vote\Infrastructure\Http\RequestContext;

final class UserAuthService
{
    public function __construct(
        private readonly RequestContext $requestContext
    ) {
    }

    public function currentId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        if ($id === null) {
            return null;
        }

        $id = (int)$id;
        return $id > 0 ? $id : null;
    }

    public function currentUsername(): ?string
    {
        $username = $_SESSION['user_username'] ?? null;
        if (!is_string($username) || $username === '') {
            return null;
        }

        return $username;
    }

    public function isLoggedIn(): bool
    {
        return $this->currentId() !== null;
    }

    public function roles(): array
    {
        $roles = $_SESSION['user_roles'] ?? [];
        if (!is_array($roles)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', $roles)));
    }

    public function hasRole(string $code): bool
    {
        return in_array(strtoupper($code), array_map('strtoupper', $this->roles()), true);
    }

    public function sessionIdleMinutes(): int
    {
        return (int)$this->env('SESSION_IDLE_MINUTES', '30');
    }

    public function sessionAbsoluteHours(): int
    {
        return (int)$this->env('SESSION_ABSOLUTE_HOURS', '12');
    }

    public function rateLimitWindowSeconds(): int
    {
        return (int)$this->env('LOGIN_RATE_WINDOW_SECONDS', '900');
    }

    public function rateLimitMaxFailures(): int
    {
        return (int)$this->env('LOGIN_RATE_MAX_FAILURES', '10');
    }

    public function loginAllowed(PDO $pdo, string $usernameOrEmail, ?string $ip): bool
    {
        $window = $this->rateLimitWindowSeconds();
        $max = $this->rateLimitMaxFailures();
        $since = (new DateTimeImmutable('now'))->modify("-{$window} seconds")->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare('
            SELECT COUNT(*)
            FROM login_attempts
            WHERE success=0
              AND created_at >= ?
              AND (
                (ip IS NOT NULL AND ip = ?)
                OR (username_or_email IS NOT NULL AND username_or_email = ?)
              )
        ');
        $stmt->execute([$since, $ip, $this->lower($usernameOrEmail)]);

        return ((int)$stmt->fetchColumn()) < $max;
    }

    public function loadByLogin(PDO $pdo, string $usernameOrEmail): ?array
    {
        $login = trim($usernameOrEmail);
        if ($login === '') {
            return null;
        }

        $stmt = $pdo->prepare('
            SELECT *
            FROM users
            WHERE username = ?
               OR email = ?
            LIMIT 1
        ');
        $stmt->execute([$login, $this->lower($login)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function loadRoles(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare('
            SELECT r.code
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = ?
            ORDER BY r.code
        ');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_values(array_unique(array_map('strval', $rows)));
    }

    public function login(PDO $pdo, array $user, array $roles): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_roles'] = $roles;
        $_SESSION['user_username'] = (string)($user['username'] ?? '');
        $_SESSION['user_login_at'] = time();
        $_SESSION['user_last_activity'] = time();

        $sessionId = session_id();
        $now = new DateTimeImmutable('now');
        $expiresAt = $now->modify('+' . $this->sessionAbsoluteHours() . ' hours')->format('Y-m-d H:i:s');
        $ip = $this->requestContext->ip();
        $ua = $this->requestContext->userAgent();

        try {
            $stmt = $pdo->prepare('
                INSERT INTO user_sessions(user_id, session_id, expires_at, ip, user_agent)
                VALUES(?,?,?,?,?)
            ');
            $stmt->execute([(int)$user['id'], $sessionId, $expiresAt, $ip, $ua]);
        } catch (Throwable $e) {
            // Best effort only.
        }

        try {
            $pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([(int)$user['id']]);
        } catch (Throwable $e) {
            // Ignore.
        }
    }

    public function logout(PDO $pdo): void
    {
        $sessionId = session_id();
        try {
            $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE session_id=? AND revoked_at IS NULL')->execute([$sessionId]);
        } catch (Throwable $e) {
            // Ignore.
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function logoutAll(PDO $pdo, int $userId): void
    {
        $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL')->execute([$userId]);
    }

    public function enforceSession(PDO $pdo): ?string
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $userId = $this->currentId();
        if ($userId === null) {
            return null;
        }

        $sessionId = session_id();
        $row = null;
        try {
            $stmt = $pdo->prepare('SELECT revoked_at, expires_at, last_seen_at FROM user_sessions WHERE session_id=? LIMIT 1');
            $stmt->execute([$sessionId]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            $row = null;
        }

        $now = new DateTimeImmutable('now');
        $idleLimit = $now->modify('-' . $this->sessionIdleMinutes() . ' minutes');

        if ($row) {
            if (!empty($row['revoked_at'])) {
                return 'revoked';
            }

            if (!empty($row['expires_at']) && $now > new DateTimeImmutable((string)$row['expires_at'])) {
                $this->logout($pdo);
                return 'expired';
            }

            if (!empty($row['last_seen_at']) && new DateTimeImmutable((string)$row['last_seen_at']) < $idleLimit) {
                $this->logout($pdo);
                return 'idle';
            }

            try {
                $pdo->prepare('UPDATE user_sessions SET last_seen_at=NOW() WHERE session_id=?')->execute([$sessionId]);
            } catch (Throwable $e) {
                // Ignore.
            }
        }

        $_SESSION['user_last_activity'] = time();
        return null;
    }

    private function env(string $key, string $default): string
    {
        try {
            if (function_exists('env_get')) {
                return (string)(env_get($key, $default) ?? $default);
            }
        } catch (Throwable $e) {
            // Ignore and use default.
        }

        return $default;
    }

    private function lower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value);
        }

        return strtolower($value);
    }
}
