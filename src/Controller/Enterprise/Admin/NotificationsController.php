<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise\Admin;

final class NotificationsController extends BaseAdminController
{
    public function __invoke(): string
    {
        user_require_role(['ADMIN', 'SUPERADMIN'], false);
        return $this->view->render('enterprise/admin/notifications', $this->adminContext() + [
            'pageTitle' => 'Notifications',
            'includeDataTables' => true,
        ]);
    }
}

