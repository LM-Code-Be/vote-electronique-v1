<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise\Admin;

final class ElectionsController extends BaseAdminController
{
    public function __invoke(): string
    {
        user_require_role(['ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], false);
        return $this->view->render('enterprise/admin/elections', $this->adminContext() + [
            'pageTitle' => 'Élections',
            'includeDataTables' => true,
            'canManageElections' => user_has_role('ADMIN') || user_has_role('SUPERADMIN'),
        ]);
    }
}
