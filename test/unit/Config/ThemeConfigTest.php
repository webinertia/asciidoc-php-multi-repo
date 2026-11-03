<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\ThemeConfig;

#[CoversClass(ThemeConfig::class)]
final class ThemeConfigTest extends TestCase
{
    #[Test]
    public function defaultsMatchesFromArrayOnEmptyInput(): void
    {
        $defaults  = ThemeConfig::defaults();
        $fromArray = ThemeConfig::fromArray([]);

        static::assertSame($defaults->name(), $fromArray->name());
        static::assertSame($defaults->defaultAccent(), $fromArray->defaultAccent());
        static::assertSame($defaults->colorMode(), $fromArray->colorMode());
        static::assertSame($defaults->defaultColorMode(), $fromArray->defaultColorMode());
    }

    #[Test]
    public function fromArrayAppliesDefaults(): void
    {
        $theme = ThemeConfig::fromArray([]);

        static::assertSame('webware', $theme->name());
        static::assertSame('#7e5ae0', $theme->defaultAccent());
        static::assertSame('system', $theme->colorMode());
        static::assertSame('dark', $theme->defaultColorMode());
    }

    #[Test]
    public function fromArrayReadsEveryField(): void
    {
        $theme = ThemeConfig::fromArray([
            'name'             => 'webware',
            'defaultAccent'    => '#05a578',
            'colorMode'        => 'light',
            'defaultColorMode' => 'light',
        ]);

        static::assertSame('webware', $theme->name());
        static::assertSame('#05a578', $theme->defaultAccent());
        static::assertSame('light', $theme->colorMode());
        static::assertSame('light', $theme->defaultColorMode());
    }

    #[Test]
    public function isBuiltIn(): void
    {
        static::assertTrue(ThemeConfig::defaults()->isBuiltIn());
        static::assertFalse(ThemeConfig::fromArray(['name' => 'custom'])->isBuiltIn());
    }
}
