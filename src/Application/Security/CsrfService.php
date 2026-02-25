<?php
declare(strict_types=1);

namespace Vote\Application\Security;

final class CsrfService
{
    public function token(): string
    {
        $token = $_SESSION['csrf_token'] ?? null;
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $token;
        }

        return $token;
    }

    public function isValidHeader(?string $headerToken): bool
    {
        if (!is_string($headerToken) || $headerToken === '') {
            return false;
        }

        return hash_equals($this->token(), $headerToken);
    }

    public function isValidPostToken(?string $postToken): bool
    {
        if (!is_string($postToken) || $postToken === '') {
            return false;
        }

        return hash_equals($this->token(), $postToken);
    }

    public function fieldHtml(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->token(), ENT_QUOTES) . '">';
    }
}
