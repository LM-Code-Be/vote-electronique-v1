<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/response.php';

use Vote\Infrastructure\Composition\AppServices;

function user_current_id(): ?int
{
    return AppServices::auth()->currentId();
}

function user_current_username(): ?string
{
    return AppServices::auth()->currentUsername();
}

function user_is_logged_in(): bool
{
    return AppServices::auth()->isLoggedIn();
}

function user_roles(): array
{
    return AppServices::auth()->roles();
}

function user_has_role(string $code): bool
{
    return AppServices::auth()->hasRole($code);
}

function user_require_login(bool $asJson = false): void
{
    if (user_is_logged_in()) return;

    if ($asJson) json_error('Non authentifie', 401);
    header('Location: ' . app_url('/enterprise/login.php'));
    exit;
}

function user_require_role(array $codes, bool $asJson = false): void
{
    user_require_login($asJson);
    foreach ($codes as $code) {
        if (user_has_role((string)$code)) {
            return;
        }
    }

    if ($asJson) json_error('Acces refuse', 403);
    http_response_code(403);
    echo 'Acces refuse';
    exit;
}

function user_session_idle_minutes(): int
{
    return AppServices::auth()->sessionIdleMinutes();
}

function user_session_absolute_hours(): int
{
    return AppServices::auth()->sessionAbsoluteHours();
}

function user_rate_limit_window_seconds(): int
{
    return AppServices::auth()->rateLimitWindowSeconds();
}

function user_rate_limit_max_failures(): int
{
    return AppServices::auth()->rateLimitMaxFailures();
}

function user_login_allowed(PDO $pdo, string $usernameOrEmail, ?string $ip): bool
{
    return AppServices::auth()->loginAllowed($pdo, $usernameOrEmail, $ip);
}

function user_load_by_login(PDO $pdo, string $usernameOrEmail): ?array
{
    return AppServices::auth()->loadByLogin($pdo, $usernameOrEmail);
}

function user_load_roles(PDO $pdo, int $userId): array
{
    return AppServices::auth()->loadRoles($pdo, $userId);
}

function user_login(PDO $pdo, array $user, array $roles): void
{
    AppServices::auth()->login($pdo, $user, $roles);
}

function user_logout(PDO $pdo): void
{
    AppServices::auth()->logout($pdo);
}

function user_logout_all(PDO $pdo, int $userId): void
{
    AppServices::auth()->logoutAll($pdo, $userId);
}

function user_enforce_session(PDO $pdo, bool $asJson = false): void
{
    $status = AppServices::auth()->enforceSession($pdo);
    if ($status === null) return;

    $message = match ($status) {
        'revoked' => 'Session revoquee',
        'expired' => 'Session expiree',
        'idle' => 'Inactivite',
        default => 'Session invalide',
    };

    if ($asJson) json_error($message, 401);
    header('Location: ' . app_url('/enterprise/login.php'));
    exit;
}
