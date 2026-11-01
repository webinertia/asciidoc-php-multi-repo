<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Config\SiteSection;

#[CoversClass(SiteSection::class)]
final class SiteSectionTest extends TestCase
{
    public function testEffectiveHostnameFallsBackToBaseUrlHost(): void
    {
        $section = SiteSection::fromArray([
            'name'    => 'Docs',
            'baseUrl' => 'https://docs.example.test',
        ]);

        self::assertSame('docs.example.test', $section->effectiveHostname());
    }

    public function testEffectiveHostnamePrefersConfiguredHostname(): void
    {
        $section = SiteSection::fromArray([
            'name'     => 'Docs',
            'baseUrl'  => 'https://example.test',
            'hostname' => 'docs.example.test',
        ]);

        self::assertSame('docs.example.test', $section->effectiveHostname());
    }

    public function testFromArrayAppliesDefaults(): void
    {
        $section = SiteSection::fromArray([
            'name'    => 'Docs',
            'baseUrl' => 'https://example.test',
        ]);

        self::assertSame('/', $section->basePath());
        self::assertNull($section->hostname());
        self::assertSame('latest', $section->defaultVersionLabel());
    }

    public function testFromArrayReadsEveryField(): void
    {
        $section = SiteSection::fromArray([
            'name'                => 'Webware Documentation',
            'baseUrl'             => 'https://docs.webinertia.net/',
            'basePath'            => '/docs/',
            'hostname'            => 'docs.webinertia.net',
            'defaultVersionLabel' => 'stable',
        ]);

        self::assertSame('Webware Documentation', $section->name());
        self::assertSame('https://docs.webinertia.net', $section->baseUrl());
        self::assertSame('/docs/', $section->basePath());
        self::assertSame('docs.webinertia.net', $section->hostname());
        self::assertSame('stable', $section->defaultVersionLabel());
    }

    public function testIsRootBasePath(): void
    {
        self::assertTrue(SiteSection::fromArray(['name' => 'D', 'baseUrl' => 'https://e.test'])->isRootBasePath());
        self::assertFalse(
            SiteSection::fromArray([
                'name'     => 'D',
                'baseUrl'  => 'https://e.test',
                'basePath' => '/docs/',
            ])->isRootBasePath(),
        );
    }

    public function testNormaliseBasePathAddsBothSlashes(): void
    {
        self::assertSame('/docs/', SiteSection::normaliseBasePath('docs'));
        self::assertSame('/docs/', SiteSection::normaliseBasePath('/docs'));
        self::assertSame('/docs/', SiteSection::normaliseBasePath('docs/'));
        self::assertSame('/docs/', SiteSection::normaliseBasePath('/docs/'));
    }

    public function testNormaliseBasePathKeepsRoot(): void
    {
        self::assertSame('/', SiteSection::normaliseBasePath('/'));
        self::assertSame('/', SiteSection::normaliseBasePath(''));
    }

    public function testWithBasePathReturnsANewInstance(): void
    {
        $section = SiteSection::fromArray(['name' => 'Docs', 'baseUrl' => 'https://example.test']);
        $updated = $section->withBasePath('/manual');

        self::assertNotSame($section, $updated);
        self::assertSame('/manual/', $updated->basePath());
        self::assertSame('/', $section->basePath());
    }
}
