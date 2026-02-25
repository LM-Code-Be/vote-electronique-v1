<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise\Admin;

final class AuditController extends BaseAdminController
{
    public function __invoke(): string
    {
        user_require_role(['SUPERADMIN'], false);
        return $this->view->render('enterprise/admin/audit', $this->adminContext() + [
            'pageTitle' => 'Audit',
            'includeDataTables' => true,
        ]);
    }
}
