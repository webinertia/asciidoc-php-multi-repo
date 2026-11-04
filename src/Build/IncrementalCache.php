<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Build;

use Throwable;
use Webware\AsciidocPhp\MultiRepo\Render\PageChrome;
use Webware\AsciidocPhp\MultiRepo\Render\PageContext;
use Webware\AsciidocPhp\MultiRepo\Render\RenderedPage;
use Webware\AsciidocPhp\MultiRepo\Render\TocEntry;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Json;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function array_is_list;
use function array_values;
use function hash;
use function is_array;
use function is_file;
use function is_int;
use function is_string;
use function ksort;

/**
 * Reuses the conversion of a page whose inputs have not changed.
 *
 * The key covers everything that can change a page's bytes: the configuration slice that reaches the
 * converter or the shell, the repository name and revision, the page's path and content, the theme
 * assets the shell links, and the format version of this package's output. A page is reused only on
 * an exact key match, so a stale entry can never be served.
 *
 * Only the conversion is reused. The shell is rendered again from the cached inputs, because the
 * site chrome that surrounds a page is not known until every project has rendered.
 *
 * @api
 */
final class IncrementalCache
{
    /**
     * Bumped whenever the cached payload changes shape, which invalidates every existing entry.
     */
    public const int FORMAT_VERSION = 2;

    private const string DIRECTORY = 'pages';

    private const string EXTENSION = '.json';

    private readonly Json $json;

    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        $this->json = new Json();
    }

    /**
     * The cache key for one page.
     *
     * Every part is encoded in a canonical order, so two runs that describe the same inputs produce
     * the same key on any platform.
     *
     * @param array<string, mixed> $parts
     */
    public function key(array $parts): string
    {
        $canonical = $this->canonical(['formatVersion' => self::FORMAT_VERSION] + $parts);

        return hash('sha256', $this->json->encodeCompact($canonical));
    }

    public function load(string $cacheDirectory, string $key): ?RenderedPage
    {
        $path = $this->path($cacheDirectory, $key);

        if (! is_file($path)) {
            return null;
        }

        try {
            $decoded = $this->json->decodeObject($this->filesystem->read($path), $path);
        } catch (Throwable) {
            // A corrupt entry is a miss, not a build failure: the page is rendered again.
            return null;
        }

        return $this->restore($decoded);
    }

    public function store(string $cacheDirectory, string $key, RenderedPage $page): void
    {
        $payload = $this->encode($page);

        if (null === $payload) {
            return;
        }

        $this->filesystem->writeAtomic($this->path($cacheDirectory, $key), $this->json->encode($payload));
    }

    /**
     * Sorts every associative array by key so the encoding of a given value never varies.
     */
    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $canonical = [];
        foreach ($value as $key => $item) {
            $canonical[$key] = $this->canonical($item);
        }

        if (! array_is_list($canonical)) {
            ksort($canonical, SORT_STRING);
        }

        return $canonical;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function encode(RenderedPage $page): ?array
    {
        $context = $page->context();

        if (null === $context) {
            return null;
        }

        $toc = [];
        foreach ($page->toc() as $entry) {
            $toc[] = $entry->toArray();
        }

        return [
            'html'       => $page->html(),
            'outputPath' => $page->outputPath(),
            'urlPath'    => $page->urlPath(),
            'title'      => $page->title(),
            'toc'        => $toc,
            'headings'   => $page->headings(),
            'searchText' => $page->searchText(),
            'context'    => [
                'siteName'         => $context->siteName(),
                'projectTitle'     => $context->projectTitle(),
                'pageTitle'        => $context->pageTitle(),
                'versionLabel'     => $context->versionLabel(),
                'canonicalUrl'     => $context->canonicalUrl(),
                'body'             => $context->body(),
                'stylesheets'      => $context->stylesheets(),
                'scripts'          => $context->scripts(),
                'sourceUrl'        => $context->sourceUrl(),
                'colorMode'        => $context->colorMode(),
                'defaultColorMode' => $context->defaultColorMode(),
                'iconFontCdn'      => $context->iconFontCdn(),
                'logoUrl'          => $context->logoUrl(),
            ],
        ];
    }

    private function path(string $cacheDirectory, string $key): string
    {
        return Path::join($cacheDirectory, self::DIRECTORY, $key . self::EXTENSION);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function restore(array $data): ?RenderedPage
    {
        $context = $data['context'] ?? null;
        $toc     = $data['toc'] ?? null;

        if (! is_array($context) || ! is_array($toc)) {
            return null;
        }

        /** @var array<string, mixed> $context */
        $entries = [];
        foreach ($toc as $entry) {
            if (! is_array($entry)) {
                return null;
            }

            $level = $entry['level'] ?? null;
            $id    = $entry['id'] ?? null;
            $title = $entry['title'] ?? null;

            if (! is_int($level) || ! is_string($id) || ! is_string($title)) {
                return null;
            }

            $entries[] = new TocEntry($level, $id, $title);
        }

        $html       = $this->string($data, 'html');
        $outputPath = $this->string($data, 'outputPath');
        $urlPath    = $this->string($data, 'urlPath');
        $title      = $this->string($data, 'title');
        $searchText = $this->string($data, 'searchText');

        if (null === $html || null === $outputPath || null === $urlPath || null === $title || null === $searchText) {
            return null;
        }

        $pageContext = new PageContext(
            siteName        : $this->string($context, 'siteName') ?? '',
            projectTitle    : $this->string($context, 'projectTitle') ?? '',
            pageTitle       : $this->string($context, 'pageTitle') ?? '',
            versionLabel    : $this->string($context, 'versionLabel') ?? '',
            canonicalUrl    : $this->string($context, 'canonicalUrl') ?? '',
            body            : $this->string($context, 'body') ?? '',
            toc             : $entries,
            stylesheets     : $this->strings($context, 'stylesheets'),
            scripts         : $this->strings($context, 'scripts'),
            sourceUrl       : $this->string($context, 'sourceUrl'),
            colorMode       : $this->string($context, 'colorMode') ?? 'system',
            defaultColorMode: $this->string($context, 'defaultColorMode') ?? 'dark',
            iconFontCdn     : $this->string($context, 'iconFontCdn') ?? '',
            logoUrl         : $this->string($context, 'logoUrl') ?? '',
            chrome          : new PageChrome(),
        );

        return new RenderedPage(
            html      : $html,
            outputPath: $outputPath,
            urlPath   : $urlPath,
            title     : $title,
            toc       : $entries,
            headings  : $this->strings($data, 'headings'),
            searchText: $searchText,
            context   : $pageContext,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function strings(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $strings = [];
        foreach (array_values($value) as $item) {
            if (! is_string($item)) {
                continue;
            }

            $strings[] = $item;
        }

        return $strings;
    }
}
