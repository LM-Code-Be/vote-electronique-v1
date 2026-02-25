<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/response.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    json_no_content();
}

json_error('Mode enterprise uniquement. Utilise les endpoints /api/ent-*.', 410);

