<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\SchemaValidator;
use Webware\AsciidocPhp\MultiRepo\Render\RenderedPage;
use Webware\AsciidocPhp\MultiRepo\Render\TocEntry;
use Webware\AsciidocPhp\MultiRepo\Search\SearchIndexBuilder;
use Webware\AsciidocPhp\MultiRepo\Search\SiteIndexBuilder;
use Webware\AsciidocPhp\MultiRepo\Site\ProjectSummary;
use Webware\AsciidocPhp\MultiRepo\Site\UrlBuilder;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function dirname;
use function file_get_contents;
use function is_array;

/**
 * T048 to T050: the search index the builder writes, and the contract it has to satisfy.
 *
 * The index is the only part of the output the browser reads as data, so the assertions are about
 * the shape a client can rely on: every page carries a url, a title, its headings, and its text, the
 * versions are ordered newest first, the newest version is flagged as latest, and the whole document
 * validates against the published schema.
 */
#[CoversClass(SearchIndexBuilder::class)]
#[CoversClass(SiteIndexBuilder::class)]
#[CoversMethod(SearchIndexBuilder::class, 'build')]
#[CoversMethod(SearchIndexBuilder::class, 'toJson')]
#[CoversMethod(SiteIndexBuilder::class, 'build')]
#[CoversMethod(SiteIndexBuilder::class, 'toJson')]
final class SearchIndexSchemaTest extends TestCase
{
    private Json $json;

    public function testEveryPageCarriesItsUrlTitleHeadingsAndText(): void
    {
        $index = new SearchIndexBuilder()->build('message-bus', 'Message Bus', $this->pages(), 'v2');

        $page = $index['versions'][2]['pages'][1];

        self::assertSame('/message-bus/v2/index.html', $page['url']);
        self::assertSame('Message Bus', $page['title']);
        self::assertSame('The message bus routes a message.', $page['text']);
        self::assertSame([['id' => 'overview', 'title' => 'Overview', 'level' => 2]], $page['headings']);
    }

    public function testPagesWithinAVersionAreOrderedByUrl(): void
    {
        $index = new SearchIndexBuilder()->build('message-bus', 'Message Bus', $this->pages(), 'v2');

        $urls = array_column($index['versions'][2]['pages'], 'url');

        self::assertSame(['/message-bus/v2/guide.html', '/message-bus/v2/index.html'], $urls);
    }

    public function testTheProjectIndexValidatesAgainstTheContract(): void
    {
        $index = new SearchIndexBuilder()->build('message-bus', 'Message Bus', $this->pages(), 'v2');

        $result = new SchemaValidator()->validate($index, $this->schema('search-index'));

        self::assertTrue($result->isValid(), $result->describe());
    }

    public function testTheSerialisedIndexIsTheSameDocument(): void
    {
        $builder = new SearchIndexBuilder();
        $pages   = $this->pages();

        self::assertSame(
            $this->json->encode($builder->build('message-bus', 'Message Bus', $pages, 'v2')),
            $builder->toJson('message-bus', 'Message Bus', $pages, 'v2'),
        );
    }

    public function testTheSiteIndexIsSerialisedAsJson(): void
    {
        $builder = new SiteIndexBuilder();
        $index   = $builder->build([], new UrlBuilder());

        self::assertSame($this->json->encode($index), $builder->toJson([], new UrlBuilder()));
        self::assertSame([], $index['projects']);
    }

    public function testTheSiteIndexListsEveryProjectOnce(): void
    {
        $summaries = [
            new ProjectSummary(
                slug        : 'message-bus',
                title       : 'Message Bus',
                versionLabel: 'v2',
                url         : '/message-bus/',
                order       : 1,
                description : 'Routes messages.',
            ),
            new ProjectSummary(
                slug        : 'job-queue',
                title       : 'Job Queue',
                versionLabel: 'latest',
                url         : '/job-queue/',
                order       : 2,
                description : '',
            ),
        ];

        $index = new SiteIndexBuilder()->build($summaries, new UrlBuilder());

        self::assertSame(1, $index['schemaVersion']);
        self::assertSame(
            [
                ['slug' => 'message-bus', 'title' => 'Message Bus', 'index' => '/message-bus/search-index.json'],
                ['slug' => 'job-queue', 'title' => 'Job Queue', 'index' => '/job-queue/search-index.json'],
            ],
            $index['projects'],
        );
    }

    public function testVersionsAreOrderedNewestFirstAndOnlyOneIsLatest(): void
    {
        $index = new SearchIndexBuilder()->build('message-bus', 'Message Bus', $this->pages(), 'v2');

        self::assertSame(['v10', 'v9', 'v2', 'v1'], array_column($index['versions'], 'label'));
        self::assertSame([false, false, true, false], array_column($index['versions'], 'latest'));
        self::assertSame('/message-bus/v2/', $index['versions'][2]['url']);
    }

    protected function setUp(): void
    {
        $this->json = new Json();
    }

    private function page(string $urlPath, string $title): RenderedPage
    {
        return new RenderedPage(
            html      : '<article class="doc"></article>',
            outputPath: ltrim($urlPath, '/'),
            urlPath   : $urlPath,
            title     : $title,
            toc       : [new TocEntry(2, 'overview', 'Overview')],
            headings  : ['Overview'],
            searchText: 'The message bus routes a message.',
        );
    }

    /**
     * @return array<string, list<RenderedPage>>
     */
    private function pages(): array
    {
        return [
            'v10' => [$this->page('/message-bus/v10/index.html', 'Message Bus')],
            'v9'  => [$this->page('/message-bus/v9/index.html', 'Message Bus')],
            'v2'  => [
                $this->page('/message-bus/v2/index.html', 'Message Bus'),
                $this->page('/message-bus/v2/guide.html', 'Guide'),
            ],
            'v1'  => [$this->page('/message-bus/v1/index.html', 'Message Bus')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(string $name): array
    {
        $path = Path::join(dirname(__DIR__, 3), 'contracts', "{$name}.schema.json");

        $schema = $this->json->decodeObject((string) file_get_contents($path), $path);

        self::assertTrue(is_array($schema));

        return $schema;
    }
}
