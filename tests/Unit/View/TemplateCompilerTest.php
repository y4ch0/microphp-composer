<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use MicroPHP\View;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TemplateCompilerTest extends TestCase
{
    public function testEscapedAndRawOutput(): void
    {
        self::assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', \render_compiled('{{ $value }}', ['value' => '<script>alert(1)</script>']));
        self::assertSame('<strong>raw</strong>', \render_compiled('{!! $value !!}', ['value' => '<strong>raw</strong>']));
        self::assertStringContainsString("\u{FFFD}", \render_compiled('{{ $value }}', ['value' => "bad\xFF"]));
    }

    public function testStyleClassAndValueEscapeQuotes(): void
    {
        $style = \render_compiled('<i @style(["color" => $value])></i>', ['value' => 'red" onmouseover="x']);
        $class = \render_compiled('<i @class([$value])></i>', ['value' => 'x" onclick="y']);
        $value = \render_compiled('<input @value($value)>', ['value' => 'x" autofocus']);
        self::assertStringContainsString('&quot;', $style);
        self::assertStringNotContainsString(' onmouseover="x"', $style);
        self::assertStringContainsString('&quot;', $class);
        self::assertStringContainsString('value="x&quot; autofocus"', $value);
    }

    public function testBalancedExpressionsConditionsAndLoops(): void
    {
        $template = <<<'TPL'
@if (
    in_array(strtoupper($needle), ['A)', 'B]'], true)
)
@foreach (array_filter($items, fn ($item) => strlen($item) > 0) as $item)
{{ sprintf('[%s]', $item) }}
@endforeach
@else
no
@endif
TPL;
        $output = \render_compiled($template, ['needle' => 'a)', 'items' => ['', 'x)']]);
        self::assertStringContainsString('[x)]', $output);
        self::assertStringNotContainsString('no', $output);
    }

    public function testIncludesAcceptNestedArrays(): void
    {
        $root = \test_temp_dir('views');
        $cache = $root . '/cache';
        mkdir($root . '/pages', 0777, true);
        mkdir($root . '/components', 0777, true);
        file_put_contents($root . '/pages/child.micro.php', '{{ $data["nested"][0] }}');
        file_put_contents($root . '/pages/parent.micro.php', '@include("child", ["data" => ["nested" => [strtoupper("ok")]]])');
        View::configurePaths($root . '/pages', $cache, $root . '/components');
        try {
            self::assertSame('OK', View::render('parent'));
            $compiled = View::compile('@component("box", ["data" => [fn () => [")", "]"]]])');
            self::assertStringContainsString('renderComponent', $compiled);
        } finally {
            View::configurePaths();
            \test_remove_tree($root);
        }
    }

    public function testMalformedDirectiveThrows(): void
    {
        $this->expectException(RuntimeException::class);
        View::compile('@if (foo([1, 2])');
    }

    public function testUnclosedBlockThrows(): void
    {
        $this->expectException(RuntimeException::class);
        View::compile('@foreach ([1] as $item){{ $item }}');
    }

    public function testRenderFileIsConfinedToConfiguredRoots(): void
    {
        $outside = tempnam(sys_get_temp_dir(), 'microphp-outside-');
        file_put_contents($outside, '{{ "secret" }}');
        try {
            $this->expectException(RuntimeException::class);
            View::renderFile($outside);
        } finally {
            @unlink($outside);
        }
    }
}
