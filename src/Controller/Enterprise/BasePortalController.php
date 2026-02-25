<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise;

use Vote\Controller\AbstractController;

abstract class BasePortalController extends AbstractController
{
    protected function portalContext(): array
    {
        return [
            'maintenance' => $this->notifications()->isMaintenance(),
            'notifications' => $this->notifications()->active($this->pdo, 3),
        ];
    }
}
