<?php
declare(strict_types=1);

namespace Vote\Application\Http;

final class JsonResponder
{
    public static function success(array $data = [], int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $code = 400, array $extra = []): never
    {
        self::success(['error' => $message] + $extra, $code);
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }
}
