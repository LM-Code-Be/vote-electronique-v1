<?php
declare(strict_types=1);

namespace Vote\Infrastructure\Composition;

use Closure;
use Vote\Application\Audit\AuditTrail;
use Vote\Application\Auth\UserAuthService;
use Vote\Application\Election\ElectionService;
use Vote\Application\Logging\ActionLogger;
use Vote\Application\Notification\PortalNotificationService;
use Vote\Application\Notification\UserPushService;
use Vote\Application\Security\CsrfService;
use Vote\Domain\Election\ElectionRules;
use Vote\Infrastructure\Http\RequestContext;

final class AppServices
{
    private static ?RequestContext $requestContext = null;
    private static ?ActionLogger $actionLogger = null;
    private static ?AuditTrail $auditTrail = null;
    private static ?CsrfService $csrfService = null;
    private static ?UserAuthService $authService = null;
    private static ?PortalNotificationService $notificationService = null;
    private static ?UserPushService $userPushService = null;
    private static ?ElectionService $electionService = null;
    private static ?ElectionRules $electionRules = null;

    public static function requestContext(): RequestContext
    {
        return self::$requestContext ??= new RequestContext();
    }

    public static function actionLogger(): ActionLogger
    {
        return self::$actionLogger ??= new ActionLogger(self::requestContext());
    }

    public static function auditTrail(): AuditTrail
    {
        return self::$auditTrail ??= new AuditTrail(self::requestContext());
    }

    public static function csrf(): CsrfService
    {
        return self::$csrfService ??= new CsrfService();
    }

    public static function auth(): UserAuthService
    {
        return self::$authService ??= new UserAuthService(self::requestContext());
    }

    public static function notifications(): PortalNotificationService
    {
        return self::$notificationService ??= new PortalNotificationService();
    }

    public static function userPush(): UserPushService
    {
        return self::$userPushService ??= new UserPushService();
    }

    public static function electionRules(): ElectionRules
    {
        return self::$electionRules ??= new ElectionRules();
    }

    public static function election(): ElectionService
    {
        return self::$electionService ??= new ElectionService(
            self::electionRules(),
            self::actionLogger(),
            self::auditTrail(),
            self::maintenanceChecker(),
            self::userPush()
        );
    }

    private static function maintenanceChecker(): Closure
    {
        return static function (): bool {
            try {
                if (function_exists('env_get_bool')) {
                    return env_get_bool('MAINTENANCE', false);
                }
            } catch (\Throwable $e) {
                // Fall through to default false.
            }

            return false;
        };
    }
}
