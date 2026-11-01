<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\ThemeConfig;

#[CoversClass(ThemeConfig::class)]
final class ThemeConfigTest extends TestCase
{
    public function testDefaultsMatchesFromArrayOnEmptyInput(): void
    {
        $defaults  = ThemeConfig::defaults();
        $fromArray = ThemeConfig::fromArray([]);

        self::assertSame($defaults->name(), $fromArray->name());
        self::assertSame($defaults->defaultAccent(), $fromArray->defaultAccent());
        self::assertSame($defaults->colorMode(), $fromArray->colorMode());
        self::assertSame($defaults->defaultColorMode(), $fromArray->defaultColorMode());
    }

    public function testFromArrayAppliesDefaults(): void
    {
        $theme = ThemeConfig::fromArray([]);

        self::assertSame('webware', $theme->name());
        self::assertSame('#7e5ae0', $theme->defaultAccent());
        self::assertSame('system', $theme->colorMode());
        self::assertSame('dark', $theme->defaultColorMode());
    }

    public function testFromArrayReadsEveryField(): void
    {
        $theme = ThemeConfig::fromArray([
            'name'             => 'webware',
            'defaultAccent'    => '#05a578',
            'colorMode'        => 'light',
            'defaultColorMode' => 'light',
        ]);

        self::assertSame('webware', $theme->name());
        self::assertSame('#05a578', $theme->defaultAccent());
        self::assertSame('light', $theme->colorMode());
        self::assertSame('light', $theme->defaultColorMode());
    }

    public function testIsBuiltIn(): void
    {
        self::assertTrue(ThemeConfig::defaults()->isBuiltIn());
        self::assertFalse(ThemeConfig::fromArray(['name' => 'custom'])->isBuiltIn());
    }
}
