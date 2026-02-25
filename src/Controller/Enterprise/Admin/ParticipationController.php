<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise\Admin;

final class ParticipationController extends BaseAdminController
{
    public function __invoke(): string
    {
        user_require_role(['ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], false);
        return $this->view->render('enterprise/admin/participation', $this->adminContext() + [
            'pageTitle' => 'Participation',
            'includeDataTables' => true,
        ]);
    }
}

