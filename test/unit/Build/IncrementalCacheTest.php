<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Build;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
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

    public function testACorruptEntryIsAMissRatherThanAFailure(): void
    {
        $directory = $this->createTempDirectory();
        $path      = Path::join($directory, 'pages', 'broken.json');
        new Filesystem()->ensureDirectory(dirname($path));
        file_put_contents($path, 'not json at all');

        self::assertNull(new IncrementalCache()->load($directory, 'broken'));
    }

    public function testAMissingEntryIsAMiss(): void
    {
        $directory = $this->createTempDirectory();

        self::assertNull(new IncrementalCache()->load($directory, 'absent'));
    }

    public function testAnEntryWithAMalformedTableOfContentsIsAMiss(): void
    {
        $directory      = $this->createTempDirectory();
        $cache          = new IncrementalCache();
        $payload        = $this->payloadAfter($cache, $directory, 'badtoc');
        $payload['toc'] = [['level' => 'two', 'id' => 'intro', 'title' => 'Intro']];
        $this->writePayload($directory, 'badtoc', $payload);

        self::assertNull($cache->load($directory, 'badtoc'));
    }

    public function testAnEntryWithoutATableOfContentsIsAMiss(): void
    {
        $directory = $this->createTempDirectory();
        $cache     = new IncrementalCache();
        $payload   = $this->payloadAfter($cache, $directory, 'notoc');
        unset($payload['toc']);
        $this->writePayload($directory, 'notoc', $payload);

        self::assertNull($cache->load($directory, 'notoc'));
    }

    public function testAnEntryWithoutItsRequiredFieldsIsAMiss(): void
    {
        $directory = $this->createTempDirectory();
        $cache     = new IncrementalCache();
        $payload   = $this->payloadAfter($cache, $directory, 'nofields');
        unset($payload['urlPath']);
        $this->writePayload($directory, 'nofields', $payload);

        self::assertNull($cache->load($directory, 'nofields'));
    }

    public function testAnEntryWithoutTheColorModeFieldsUsesTheDefaults(): void
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

        self::assertNotNull($restored);
        $loaded = $restored->context();
        self::assertNotNull($loaded);
        self::assertSame('system', $loaded->colorMode());
        self::assertSame('dark', $loaded->defaultColorMode());
        self::assertSame('', $loaded->iconFontCdn());
        self::assertNull($loaded->sourceUrl());
    }

    public function testANonObjectEntryIsAMiss(): void
    {
        $directory = $this->createTempDirectory();
        $path      = Path::join($directory, 'pages', 'scalar.json');
        new Filesystem()->ensureDirectory(dirname($path));
        file_put_contents($path, '"a string"');

        self::assertNull(new IncrementalCache()->load($directory, 'scalar'));
    }

    public function testAPageSurvivesARoundTripIntact(): void
    {
        $directory = $this->createTempDirectory();
        $cache     = new IncrementalCache();
        $page      = $this->page();

        $cache->store($directory, 'entry', $page);
        $restored = $cache->load($directory, 'entry');

        self::assertNotNull($restored);
        self::assertSame($page->html(), $restored->html());
        self::assertSame($page->outputPath(), $restored->outputPath());
        self::assertSame($page->urlPath(), $restored->urlPath());
        self::assertSame($page->title(), $restored->title());
        self::assertSame($page->headings(), $restored->headings());
        self::assertSame($page->searchText(), $restored->searchText());

        $toc = $restored->toc();
        self::assertCount(2, $toc);
        self::assertSame(2, $toc[0]->level());
        self::assertSame('intro', $toc[0]->id());
        self::assertSame('Intro', $toc[0]->title());
        self::assertSame(3, $toc[1]->level());

        $context = $restored->context();
        self::assertNotNull($context);
        self::assertSame('Docs', $context->siteName());
        self::assertSame('Project', $context->projectTitle());
        self::assertSame('Guide', $context->pageTitle());
        self::assertSame('v1', $context->versionLabel());
        self::assertSame('https://example.test/project/v1/guide/', $context->canonicalUrl());
        self::assertSame('<p>body</p>', $context->body());
        self::assertSame(['https://example.test/style.css'], $context->stylesheets());
        self::assertSame('https://example.test/source/guide.adoc', $context->sourceUrl());
        self::assertSame('light', $context->colorMode());
        self::assertSame('light', $context->defaultColorMode());
        self::assertSame('https://example.test/icons.css', $context->iconFontCdn());
        self::assertSame([], $context->scripts());
    }

    public function testAPageWithoutAShellContextIsNotStored(): void
    {
        $directory = $this->createTempDirectory();
        $cache     = new IncrementalCache();

        $cache->store($directory, 'bare', new RenderedPage('html', 'index.html', '/', 'Home'));

        self::assertFileDoesNotExist(Path::join($directory, 'pages', 'bare.json'));
        self::assertNull($cache->load($directory, 'bare'));
    }

    public function testTheKeyChangesWhenAnInputChanges(): void
    {
        $cache = new IncrementalCache();

        self::assertNotSame($cache->key(['content' => 'one']), $cache->key(['content' => 'two']));
    }

    public function testTheKeyDoesNotDependOnTheOrderOfNestedParts(): void
    {
        $cache = new IncrementalCache();

        self::assertSame(
            $cache->key(['options' => ['safe' => 'unsafe', 'header_footer' => true]]),
            $cache->key(['options' => ['header_footer' => true, 'safe' => 'unsafe']]),
        );
    }

    public function testTheKeyDoesNotDependOnTheOrderOfTheParts(): void
    {
        $cache = new IncrementalCache();

        self::assertSame(
            $cache->key(['revision' => 'abc', 'path' => 'guide.adoc']),
            $cache->key(['path' => 'guide.adoc', 'revision' => 'abc']),
        );
    }

    public function testTheKeyKeepsTheListOrderOfAList(): void
    {
        $cache = new IncrementalCache();

        self::assertNotSame(
            $cache->key(['stylesheets' => ['a.css', 'b.css']]),
            $cache->key(['stylesheets' => ['b.css', 'a.css']]),
        );
    }

    public function testTheKeyOwnsTheFormatVersion(): void
    {
        $cache = new IncrementalCache();

        // A caller that hands in its own formatVersion cannot collide with, or override, the
        // constant that invalidates every entry when the payload changes shape.
        self::assertSame($cache->key([]), $cache->key(['formatVersion' => 99]));
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
        $path = Path::join($directory, 'pages', $key . '.json');

        return new Json()->decodeObject((string) file_get_contents($path), $path);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writePayload(string $directory, string $key, array $payload): void
    {
        file_put_contents(Path::join($directory, 'pages', $key . '.json'), new Json()->encode($payload));
    }
}
