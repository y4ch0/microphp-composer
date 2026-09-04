<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use MicroPHP\AssetManager;
use MicroPHP\Component;
use PHPUnit\Framework\TestCase;

final class AssetManagerTest extends TestCase
{
    public function testDeduplicationOrderPathControlAndRequestIsolation(): void
    {
        $root = \test_temp_dir('assets');
        mkdir($root . '/a'); mkdir($root . '/b');
        file_put_contents($root . '/a/style.css', 'a{}');
        file_put_contents($root . '/b/style.css', 'b{}');
        $a = new AssetManager([$root => '/controlled']);
        $a->registerStyleFile($root . '/a/style.css');
        $a->registerStyleFile($root . '/a/style.css');
        $a->registerStyleFile('/etc/passwd');
        self::assertSame(['/controlled/a/style.css'], $a->styles());

        $b = new AssetManager([$root => '/controlled']);
        $b->registerStyleFile($root . '/b/style.css');
        self::assertSame(['/controlled/b/style.css'], $b->styles());
        self::assertStringNotContainsString('/a/style.css', $b->stylesHtml());
        \test_remove_tree($root);
    }

    public function testRenderedComponentsAreDeduplicatedAndIsolatedAcrossRequests(): void
    {
        $aDir = COMPONENTS_PATH . '/__phpunit-asset-a';
        $bDir = COMPONENTS_PATH . '/__phpunit-asset-b';
        mkdir($aDir, 0777, true); mkdir($bDir, 0777, true);
        file_put_contents($aDir . '/style.css', 'a{}');
        file_put_contents($bDir . '/style.css', 'b{}');
        try {
            $requestA = new AssetManager();
            TestAssetComponentA::renderComponent([], $requestA);
            TestAssetComponentA::renderComponent([], $requestA);
            self::assertCount(1, $requestA->styles());

            $requestB = new AssetManager();
            TestAssetComponentB::renderComponent([], $requestB);
            self::assertCount(1, $requestB->styles());
            self::assertStringContainsString('__phpunit-asset-b/style.css', $requestB->styles()[0]);
            self::assertStringNotContainsString('__phpunit-asset-a', $requestB->stylesHtml());
        } finally {
            \test_remove_tree($aDir); \test_remove_tree($bDir);
        }
    }

    public function testAssetsAreOrderedByScopeInsteadOfRenderTiming(): void
    {
        $root = \test_temp_dir('asset-order');
        mkdir($root . '/global');
        mkdir($root . '/page');
        mkdir($root . '/component');
        foreach (['global', 'page', 'component'] as $scope) {
            file_put_contents($root . "/{$scope}/style.css", ".{$scope} {}");
            file_put_contents($root . "/{$scope}/script.js", "window.{$scope} = true;");
        }

        try {
            $assets = new AssetManager([$root => '/controlled']);

            // Components can render while page content is being captured,
            // before the router has registered global and page assets.
            $assets->registerStyleFile($root . '/component/style.css', AssetManager::PRIORITY_COMPONENT);
            $assets->registerScriptFile($root . '/component/script.js', AssetManager::PRIORITY_COMPONENT);
            $assets->registerStyleFile($root . '/global/style.css');
            $assets->registerScriptFile($root . '/global/script.js');
            $assets->registerStyleFile($root . '/page/style.css', AssetManager::PRIORITY_PAGE);
            $assets->registerScriptFile($root . '/page/script.js', AssetManager::PRIORITY_PAGE);

            self::assertSame([
                '/controlled/global/style.css',
                '/controlled/page/style.css',
                '/controlled/component/style.css',
            ], $assets->styles());
            self::assertSame([
                '/controlled/global/script.js',
                '/controlled/page/script.js',
                '/controlled/component/script.js',
            ], $assets->scripts());
        } finally {
            \test_remove_tree($root);
        }
    }
}

final class TestAssetComponentA extends Component
{
    protected static ?string $assetName = '__phpunit-asset-a';
    public function render(): string { return '<p>A</p>'; }
}

final class TestAssetComponentB extends Component
{
    protected static ?string $assetName = '__phpunit-asset-b';
    public function render(): string { return '<p>B</p>'; }
}
