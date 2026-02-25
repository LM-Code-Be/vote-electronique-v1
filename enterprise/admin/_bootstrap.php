<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

require_once __DIR__ . '/../../app/election_service.php';

user_require_role(['ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], false);

