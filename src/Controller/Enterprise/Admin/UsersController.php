<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise\Admin;

final class UsersController extends BaseAdminController
{
    public function __invoke(): string
    {
        user_require_role(['ADMIN', 'SUPERADMIN'], false);
        return $this->view->render('enterprise/admin/users', $this->adminContext() + [
            'pageTitle' => 'Utilisateurs',
            'includeDataTables' => true,
        ]);
    }
}

