<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise\Admin;

final class RolesController extends BaseAdminController
{
    public function __invoke(): string
    {
        user_require_role(['SUPERADMIN'], false);
        return $this->view->render('enterprise/admin/roles', $this->adminContext() + [
            'pageTitle' => 'Rôles',
            'includeDataTables' => true,
        ]);
    }
}

