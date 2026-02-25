<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Vote';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars($pageTitle)?> • Vote</title>

<link rel="icon" type="image/png" href="<?=htmlspecialchars(app_url('/assets/brand/lm-code/logo-square.png'))?>">
<link rel="shortcut icon" type="image/png" href="<?=htmlspecialchars(app_url('/assets/brand/lm-code/logo-square.png'))?>">
<link rel="apple-touch-icon" href="<?=htmlspecialchars(app_url('/assets/brand/lm-code/logo-square.png'))?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc3/dist/css/adminlte.min.css" rel="stylesheet">
<link href="<?=htmlspecialchars(app_url('/assets/css/adminlte-vote.css'))?>" rel="stylesheet">
<link href="<?=htmlspecialchars(app_url('/assets/css/ui-enterprise.css'))?>" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
