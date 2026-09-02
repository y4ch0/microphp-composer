<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use MicroPHP\Routing\RouteResolver;
use MicroPHP\Routing\RoutingConfigurationException;
use PHPUnit\Framework\TestCase;

final class RouteResolverTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \test_temp_dir('routes');
        mkdir($this->root . '/home');
        mkdir($this->root . '/users/new', 0777, true);
        mkdir($this->root . '/users/[id]/posts/[postId]', 0777, true);
    }

    protected function tearDown(): void { \test_remove_tree($this->root); }

    public function testStaticDynamicNestedMissingAndDefaultRoutes(): void
    {
        $resolver = new RouteResolver();
        self::assertSame('home', basename($resolver->resolve($this->root, '/', ['home'])->directory));
        self::assertSame([], $resolver->resolve($this->root, '/users/new')->params);
        self::assertSame(['id' => '42', 'postId' => '9'], $resolver->resolve($this->root, '/users/42/posts/9')->params);
        self::assertNull($resolver->resolve($this->root, '/missing'));
    }

    public function testAllTraversalFormsAreRejected(): void
    {
        $resolver = new RouteResolver();
        foreach (['../x', '%2e%2e/x', '%252e%252e/x', '..\\x', '%5c..%5cx', 'a%2fb', 'a%255cb', "a\0b"] as $path) {
            self::assertNull($resolver->resolve($this->root, '/' . $path), $path);
        }
    }

    public function testVersionResolutionIsStaticOnly(): void
    {
        mkdir($this->root . '/[version]');
        $resolver = new RouteResolver();
        self::assertNull($resolver->resolveStaticChild($this->root, 'v1'));
        mkdir($this->root . '/v1');
        self::assertSame('v1', basename($resolver->resolveStaticChild($this->root, 'v1')->directory));
    }

    public function testAmbiguousDynamicDirectoriesAreConfigurationError(): void
    {
        mkdir($this->root . '/ambiguous/[one]', 0777, true);
        mkdir($this->root . '/ambiguous/[two]', 0777, true);
        $this->expectException(RoutingConfigurationException::class);
        (new RouteResolver())->resolve($this->root, '/ambiguous/value');
    }

    public function testAmbiguityIsRejectedEvenWhenAStaticSiblingMatches(): void
    {
        mkdir($this->root . '/mixed/static', 0777, true);
        mkdir($this->root . '/mixed/[one]', 0777, true);
        mkdir($this->root . '/mixed/[two]', 0777, true);
        $this->expectException(RoutingConfigurationException::class);
        (new RouteResolver())->resolve($this->root, '/mixed/static');
    }

    public function testSymlinkOutsideRootIsNeverResolved(): void
    {
        $outside = \test_temp_dir('outside');
        mkdir($outside . '/secret');
        if (!@symlink($outside . '/secret', $this->root . '/linked')) {
            \test_remove_tree($outside);
            self::markTestSkipped('Symlinks are unavailable.');
        }
        try { self::assertNull((new RouteResolver())->resolve($this->root, '/linked')); }
        finally { \test_remove_tree($outside); }
    }
}
