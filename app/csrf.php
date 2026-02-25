<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/response.php';

use Vote\Infrastructure\Composition\AppServices;

function csrf_token(): string
{
    return AppServices::csrf()->token();
}

function csrf_require(): void
{
    $got = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!AppServices::csrf()->isValidHeader(is_string($got) ? $got : null)) {
        json_error('CSRF invalide', 403);
    }
}

function csrf_field(): string
{
    return AppServices::csrf()->fieldHtml();
}

function csrf_require_post(bool $asJson = false): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    $got = $_POST['csrf_token'] ?? null;
    if (!AppServices::csrf()->isValidPostToken(is_string($got) ? $got : null)) {
        if ($asJson) json_error('CSRF invalide', 403);
        http_response_code(403);
        echo 'CSRF invalide';
        exit;
    }
}
