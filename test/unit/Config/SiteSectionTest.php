<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\SiteSection;

#[CoversClass(SiteSection::class)]
final class SiteSectionTest extends TestCase
{
    #[Test]
    public function effectiveHostnameFallsBackToBaseUrlHost(): void
    {
        $section = SiteSection::fromArray([
            'name'    => 'Docs',
            'baseUrl' => 'https://docs.example.test',
        ]);

        self::assertSame('docs.example.test', $section->effectiveHostname());
    }

    #[Test]
    public function effectiveHostnamePrefersConfiguredHostname(): void
    {
        $section = SiteSection::fromArray([
            'name'     => 'Docs',
            'baseUrl'  => 'https://example.test',
            'hostname' => 'docs.example.test',
        ]);

        self::assertSame('docs.example.test', $section->effectiveHostname());
    }

    #[Test]
    public function fromArrayAppliesDefaults(): void
    {
        $section = SiteSection::fromArray([
            'name'    => 'Docs',
            'baseUrl' => 'https://example.test',
        ]);

        self::assertSame('/', $section->basePath());
        self::assertNull($section->hostname());
        self::assertSame('latest', $section->defaultVersionLabel());
    }

    #[Test]
    public function fromArrayReadsEveryField(): void
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

    #[Test]
    public function isRootBasePath(): void
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

    #[Test]
    public function normaliseBasePathAddsBothSlashes(): void
    {
        self::assertSame('/docs/', SiteSection::normaliseBasePath('docs'));
        self::assertSame('/docs/', SiteSection::normaliseBasePath('/docs'));
        self::assertSame('/docs/', SiteSection::normaliseBasePath('docs/'));
        self::assertSame('/docs/', SiteSection::normaliseBasePath('/docs/'));
    }

    #[Test]
    public function normaliseBasePathKeepsRoot(): void
    {
        self::assertSame('/', SiteSection::normaliseBasePath('/'));
        self::assertSame('/', SiteSection::normaliseBasePath(''));
    }

    #[Test]
    public function withBasePathReturnsANewInstance(): void
    {
        $section = SiteSection::fromArray(['name' => 'Docs', 'baseUrl' => 'https://example.test']);
        $updated = $section->withBasePath('/manual');

        self::assertNotSame($section, $updated);
        self::assertSame('/manual/', $updated->basePath());
        self::assertSame('/', $section->basePath());
    }
}
