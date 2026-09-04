<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

final class HelpersSecurityTest extends TestCase
{
    public function testAuthorizationArrayComparisonsAreStrict(): void
    {
        $_SESSION['security_test'] = ['role' => 'admin'];

        self::assertNotNull(\microphp_access_failure([
            ['session_key' => 'security_test.role', 'check' => [true]],
        ]));
        self::assertNull(\microphp_access_failure([
            ['session_key' => 'security_test.role', 'check' => ['admin']],
        ]));
    }

    public function testCurrentUrlUsesConfiguredOriginInsteadOfHostHeader(): void
    {
        $previousUri = $_SERVER['REQUEST_URI'] ?? null;
        $previousHost = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['REQUEST_URI'] = '/reset?token=secret';
        $_SERVER['HTTP_HOST'] = 'attacker.example';

        try {
            self::assertSame(APP_URL . '/reset?token=secret', \current_url());
            self::assertStringNotContainsString('attacker.example', \current_url());
        } finally {
            if ($previousUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previousUri;
            }
            if ($previousHost === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $previousHost;
            }
        }
    }

    public function testSessionDefaultsAreHardenedBeforeSessionStart(): void
    {
        self::assertSame('1', ini_get('session.use_strict_mode'));
        self::assertSame('1', ini_get('session.use_only_cookies'));
        $params = session_get_cookie_params();
        self::assertTrue($params['httponly']);
        self::assertSame(SESSION_COOKIE_SAMESITE, $params['samesite']);
    }
}
