<?php
declare(strict_types=1);

namespace Vote\Controller;

use PDO;
use Vote\Application\Auth\UserAuthService;
use Vote\Application\Election\ElectionService;
use Vote\Application\Notification\PortalNotificationService;
use Vote\Application\Security\CsrfService;
use Vote\Infrastructure\Composition\AppServices;
use Vote\View\Renderer;

abstract class AbstractController
{
    public function __construct(
        protected readonly PDO $pdo,
        protected readonly Renderer $view
    ) {
    }

    protected function auth(): UserAuthService
    {
        return AppServices::auth();
    }

    protected function elections(): ElectionService
    {
        return AppServices::election();
    }

    protected function csrf(): CsrfService
    {
        return AppServices::csrf();
    }

    protected function notifications(): PortalNotificationService
    {
        return AppServices::notifications();
    }
}
