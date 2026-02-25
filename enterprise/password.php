<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$renderer = new Vote\View\Renderer(APP_ROOT . '/views');
$controller = new Vote\Controller\Enterprise\PasswordController($pdo, $renderer);
echo $controller();

