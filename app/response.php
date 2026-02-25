<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Vote\Application\Http\JsonResponder;

function json_success(array $data = [], int $code = 200): void
{
    JsonResponder::success($data, $code);
}

function json_error(string $msg, int $code = 400, array $extra = []): void
{
    JsonResponder::error($msg, $code, $extra);
}

function json_no_content(): void
{
    JsonResponder::noContent();
}
