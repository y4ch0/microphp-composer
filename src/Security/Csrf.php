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

    /** Generate a fresh token after authentication or another privilege change. */
    public function rotate(): string
    {
        $this->startSession();
        unset($_SESSION[$this->sessionKey]);

        return $this->token();
    }

    private function startSession(): void
    {
        // CLI requests have no browser cookie to persist. Keeping the token in
        // the process-local session array also avoids "headers already sent"
        // failures in console commands and smoke tests.
        if (PHP_SAPI === 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!session_start()) {
                throw new \RuntimeException('Unable to start the session required for CSRF protection.');
            }
        }
    }
}
