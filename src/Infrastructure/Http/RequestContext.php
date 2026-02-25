<?php
declare(strict_types=1);

namespace Vote\Infrastructure\Http;

final class RequestContext
{
    public function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!is_string($ip) || $ip === '') {
            return null;
        }

        return substr($ip, 0, 45);
    }

    public function userAgent(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if (!is_string($ua) || $ua === '') {
            return null;
        }

        return substr($ua, 0, 255);
    }
}
