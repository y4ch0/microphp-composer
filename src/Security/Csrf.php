<?php

declare(strict_types=1);

namespace MicroPHP\Security;

final class Csrf
{
    public function __construct(private readonly string $sessionKey = '_microphp_csrf_token') {}

    public function token(): string
    {
        $this->startSession();
        $token = $_SESSION[$this->sessionKey] ?? null;
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[$this->sessionKey] = $token;
        }
        return $token;
    }

    public function validate(?string $submitted): bool
    {
        $this->startSession();
        $expected = $_SESSION[$this->sessionKey] ?? null;
        return is_string($expected) && $expected !== ''
            && is_string($submitted) && $submitted !== ''
            && hash_equals($expected, $submitted);
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }
}
