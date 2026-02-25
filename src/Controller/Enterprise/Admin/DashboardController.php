<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise\Admin;

final class DashboardController extends BaseAdminController
{
    public function __invoke(): string
    {
        user_require_role(['ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], false);
        return $this->view->render('enterprise/admin/dashboard', $this->adminContext() + [
            'pageTitle' => 'Dashboard',
            'includeChart' => true,
        ]);
    }
}

