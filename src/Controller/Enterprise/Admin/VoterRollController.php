<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise\Admin;

final class VoterRollController extends BaseAdminController
{
    public function __invoke(): string
    {
        user_require_role(['ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], false);
        $canEdit = user_has_role('ADMIN') || user_has_role('SUPERADMIN');
        return $this->view->render('enterprise/admin/voter-roll', $this->adminContext() + [
            'pageTitle' => 'Émargement',
            'includeDataTables' => true,
            'canEdit' => $canEdit,
        ]);
    }
}
