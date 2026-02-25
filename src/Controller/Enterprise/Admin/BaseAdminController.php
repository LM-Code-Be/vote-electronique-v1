<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise\Admin;

use Vote\Controller\AbstractController;

abstract class BaseAdminController extends AbstractController
{
    protected function adminContext(): array
    {
        return [];
    }
}

