<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise\Admin;

final class GroupsController extends BaseAdminController
{
    public function __invoke(): string
    {
        user_require_role(['ADMIN', 'SUPERADMIN'], false);
        return $this->view->render('enterprise/admin/groups', $this->adminContext() + [
            'pageTitle' => 'Groupes',
            'includeDataTables' => true,
        ]);
    }
}

