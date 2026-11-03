<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Build;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\IncrementalCache;
use Webware\AsciidocPhp\MultiRepo\Render\PageContext;
use Webware\AsciidocPhp\MultiRepo\Render\RenderedPage;
use Webware\AsciidocPhp\MultiRepo\Render\TocEntry;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_array;

/**
 * The cache is only safe if it loses nothing: a reused page must be byte-identical to the page that
 * was stored, and a key must not depend on the order the caller happened to build its parts in.
 */
#[CoversClass(IncrementalCache::class)]
#[CoversMethod(IncrementalCache::class, 'key')]
#[CoversMethod(IncrementalCache::class, 'load')]
#[CoversMethod(IncrementalCache::class, 'store')]
final class IncrementalCacheTest extends TestCase
{
    use TempDirectoryTrait;

    #[Test]
    public function aCorruptEntryIsAMissRatherThanAFailure(): void
    {
        $directory = $this->createTempDirectory();
        $path      = Path::join($directory, 'pages', 'broken.json');
        new Filesystem()->ensureDirectory(dirname($path));
        file_put_contents($path, data: 'not json at all');

        static::assertNull(new IncrementalCache()->load($directory, 'broken'));
    }

    #[Test]
    public function aMissingEntryIsAMiss(): void
    {
        $directory = $this->createTempDirectory();

        static::assertNull(new IncrementalCache()->load($directory, 'absent'));
    }

    #[Test]
    public function anEntryWithAMalformedTableOfContentsIsAMiss(): void
    {
        $directory      = $this->createTempDirectory();
        $cache          = new IncrementalCache();
        $payload        = $this->payloadAfter($cache, $directory, 'badtoc');
        $payload['toc'] = [['level' => 'two', 'id' => 'intro', 'title' => 'Intro']];
        $this->writePayload($directory, 'badtoc', $payload);

        static::assertNull($cache->load($directory, 'badtoc'));
    }

    #[Test]
    public function anEntryWithoutATableOfContentsIsAMiss(): void
    {
        $directory = $this->createTempDirectory();
        $cache     = new IncrementalCache();
        $payload   = $this->payloadAfter($cache, $directory, 'notoc');
        unset($payload['toc']);
        $this->writePayload($directory, 'notoc', $payload);

        static::assertNull($cache->load($directory, 'notoc'));
    }

    #[Test]
    public function anEntryWithoutItsRequiredFieldsIsAMiss(): void
    {
        $directory = $this->createTempDirectory();
        $cache     = new IncrementalCache();
        $payload   = $this->payloadAfter($cache, $directory, 'nofields');
        unset($payload['urlPath']);
        $this->writePayload($directory, 'nofields', $payload);

        static::assertNull($cache->load($directory, 'nofields'));
    }

    #[Test]
    public function anEntryWithoutTheColorModeFieldsUsesTheDefaults(): void
    {
        $directory = $this->createTempDirectory();
        $cache     = new IncrementalCache();
        $payload   = $this->payloadAfter($cache, $directory, 'legacy');
        $context   = $payload['context'];
        $context   = is_array($context) ? $context : [];
        unset($context['colorMode'], $context['defaultColorMode'], $context['iconFontCdn'], $context['sourceUrl']);
        $payload['context'] = $context;
        $this->writePayload($directory, 'legacy', $payload);

        $restored = $cache->load($directory, 'legacy');

        static::assertNotNull($restored);
        $loaded = $restored->context();
        static::assertNotNull($loaded);
        static::assertSame('system', $loaded->colorMode());
        static::assertSame('dark', $loaded->defaultColorMode());
        static::assertSame('', $loaded->iconFontCdn());
        static::assertNull($loaded->sourceUrl());
    }

    #[Test]
    public function aNonObjectEntryIsAMiss(): void
    {
        $directory = $this->createTempDirectory();
        $path      = Path::join($directory, 'pages', 'scalar.json');
        new Filesystem()->ensureDirectory(dirname($path));
        file_put_contents($path, data: '"a string"');

        static::assertNull(new IncrementalCache()->load($directory, 'scalar'));
    }

    #[Test]
    public function aPageSurvivesARoundTripIntact(): void
    {
        $directory = $this->createTempDirectory();
        $cache     = new IncrementalCache();
        $page      = $this->page();

        $cache->store($directory, 'entry', $page);
        $restored = $cache->load($directory, 'entry');

        static::assertNotNull($restored);
        static::assertSame($page->html(), $restored->html());
        static::assertSame($page->outputPath(), $restored->outputPath());
        static::assertSame($page->urlPath(), $restored->urlPath());
        static::assertSame($page->title(), $restored->title());
        static::assertSame($page->headings(), $restored->headings());
        static::assertSame($page->searchText(), $restored->searchText());

        $toc = $restored->toc();
        static::assertCount(2, $toc);
        static::assertSame(2, $toc[0]->level());
        static::assertSame('intro', $toc[0]->id());
        static::assertSame('Intro', $toc[0]->title());
        static::assertSame(3, $toc[1]->level());

        $context = $restored->context();
        static::assertNotNull($context);
        static::assertSame('Docs', $context->siteName());
        static::assertSame('Project', $context->projectTitle());
        static::assertSame('Guide', $context->pageTitle());
        static::assertSame('v1', $context->versionLabel());
        static::assertSame('https://example.test/project/v1/guide/', $context->canonicalUrl());
        static::assertSame('<p>body</p>', $context->body());
        static::assertSame(['https://example.test/style.css'], $context->stylesheets());
        static::assertSame('https://example.test/source/guide.adoc', $context->sourceUrl());
        static::assertSame('light', $context->colorMode());
        static::assertSame('light', $context->defaultColorMode());
        static::assertSame('https://example.test/icons.css', $context->iconFontCdn());
        static::assertSame([], $context->scripts());
    }

    #[Test]
    public function aPageWithoutAShellContextIsNotStored(): void
    {
        $directory = $this->createTempDirectory();
        $cache     = new IncrementalCache();

        $cache->store($directory, 'bare', new RenderedPage('html', 'index.html', '/', 'Home'));

        static::assertFileDoesNotExist(Path::join($directory, 'pages', 'bare.json'));
        static::assertNull($cache->load($directory, 'bare'));
    }

    #[Test]
    public function theKeyChangesWhenAnInputChanges(): void
    {
        $cache = new IncrementalCache();

        static::assertNotSame($cache->key(['content' => 'one']), $cache->key(['content' => 'two']));
    }

    #[Test]
    public function theKeyDoesNotDependOnTheOrderOfNestedParts(): void
    {
        $cache = new IncrementalCache();

        static::assertSame(
            $cache->key(['options' => ['safe' => 'unsafe', 'header_footer' => true]]),
            $cache->key(['options' => ['header_footer' => true, 'safe' => 'unsafe']]),
        );
    }

    #[Test]
    public function theKeyDoesNotDependOnTheOrderOfTheParts(): void
    {
        $cache = new IncrementalCache();

        static::assertSame(
            $cache->key(['revision' => 'abc', 'path' => 'guide.adoc']),
            $cache->key(['path' => 'guide.adoc', 'revision' => 'abc']),
        );
    }

    #[Test]
    public function theKeyKeepsTheListOrderOfAList(): void
    {
        $cache = new IncrementalCache();

        static::assertNotSame(
            $cache->key(['stylesheets' => ['a.css', 'b.css']]),
            $cache->key(['stylesheets' => ['b.css', 'a.css']]),
        );
    }

    #[Test]
    public function theKeyOwnsTheFormatVersion(): void
    {
        $cache = new IncrementalCache();

        // A caller that hands in its own formatVersion cannot collide with, or override, the
        // constant that invalidates every entry when the payload changes shape.
        static::assertSame($cache->key([]), $cache->key(['formatVersion' => 99]));
    }

    private function page(): RenderedPage
    {
        $toc = [new TocEntry(2, 'intro', 'Intro'), new TocEntry(3, 'detail', 'Detail')];

        return new RenderedPage(
            html      : '<p>body</p>',
            outputPath: 'project/v1/guide/index.html',
            urlPath   : '/project/v1/guide/',
            title     : 'Guide',
            toc       : $toc,
            headings  : ['Intro', 'Detail'],
            searchText: 'guide text',
            context   : new PageContext(
                siteName        : 'Docs',
                projectTitle    : 'Project',
                pageTitle       : 'Guide',
                versionLabel    : 'v1',
                canonicalUrl    : 'https://example.test/project/v1/guide/',
                body            : '<p>body</p>',
                toc             : $toc,
                stylesheets     : ['https://example.test/style.css'],
                sourceUrl       : 'https://example.test/source/guide.adoc',
                colorMode       : 'light',
                defaultColorMode: 'light',
                iconFontCdn     : 'https://example.test/icons.css',
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadAfter(IncrementalCache $cache, string $directory, string $key): array
    {
        $cache->store($directory, $key, $this->page());
        $path = Path::join($directory, 'pages', "{$key}.json");

        return new Json()->decodeObject((string) file_get_contents($path), $path);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writePayload(string $directory, string $key, array $payload): void
    {
        file_put_contents(Path::join($directory, 'pages', "{$key}.json"), new Json()->encode($payload));
    }
}
