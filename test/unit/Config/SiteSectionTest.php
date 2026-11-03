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

        static::assertSame('docs.example.test', $section->effectiveHostname());
    }

    #[Test]
    public function effectiveHostnamePrefersConfiguredHostname(): void
    {
        $section = SiteSection::fromArray([
            'name'     => 'Docs',
            'baseUrl'  => 'https://example.test',
            'hostname' => 'docs.example.test',
        ]);

        static::assertSame('docs.example.test', $section->effectiveHostname());
    }

    #[Test]
    public function fromArrayAppliesDefaults(): void
    {
        $section = SiteSection::fromArray([
            'name'    => 'Docs',
            'baseUrl' => 'https://example.test',
        ]);

        static::assertSame('/', $section->basePath());
        static::assertNull($section->hostname());
        static::assertSame('latest', $section->defaultVersionLabel());
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

        static::assertSame('Webware Documentation', $section->name());
        static::assertSame('https://docs.webinertia.net', $section->baseUrl());
        static::assertSame('/docs/', $section->basePath());
        static::assertSame('docs.webinertia.net', $section->hostname());
        static::assertSame('stable', $section->defaultVersionLabel());
    }

    #[Test]
    public function isRootBasePath(): void
    {
        static::assertTrue(SiteSection::fromArray(['name' => 'D', 'baseUrl' => 'https://e.test'])->isRootBasePath());
        static::assertFalse(
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
        static::assertSame('/docs/', SiteSection::normaliseBasePath('docs'));
        static::assertSame('/docs/', SiteSection::normaliseBasePath('/docs'));
        static::assertSame('/docs/', SiteSection::normaliseBasePath('docs/'));
        static::assertSame('/docs/', SiteSection::normaliseBasePath('/docs/'));
    }

    #[Test]
    public function normaliseBasePathKeepsRoot(): void
    {
        static::assertSame('/', SiteSection::normaliseBasePath('/'));
        static::assertSame('/', SiteSection::normaliseBasePath(''));
    }

    #[Test]
    public function withBasePathReturnsANewInstance(): void
    {
        $section = SiteSection::fromArray(['name' => 'Docs', 'baseUrl' => 'https://example.test']);
        $updated = $section->withBasePath('/manual');

        static::assertNotSame($section, $updated);
        static::assertSame('/manual/', $updated->basePath());
        static::assertSame('/', $section->basePath());
    }
}
