<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise\Admin;

final class CandidatesController extends BaseAdminController
{
    public function __invoke(): string
    {
        user_require_role(['ADMIN', 'SUPERADMIN'], false);
        return $this->view->render('enterprise/admin/candidates', $this->adminContext() + [
            'pageTitle' => 'Candidats',
            'includeDataTables' => true,
        ]);
    }
}

