<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$renderer = new Vote\View\Renderer(APP_ROOT . '/views');
$controller = new Vote\Controller\Enterprise\Admin\CandidatesController($pdo, $renderer);
echo $controller();

