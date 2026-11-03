<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\DocPage;
use Webware\AsciidocPhp\MultiRepo\Source\DocVersion;
use Webware\AsciidocPhp\MultiRepo\Source\PageEnumerator;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;

use function array_map;

/**
 * Page enumeration: extension filtering, partial and exclude rules, nested directories, and the
 * two failures a version can produce.
 */
#[CoversClass(PageEnumerator::class)]
#[CoversMethod(PageEnumerator::class, 'enumerate')]
final class PageEnumeratorTest extends TestCase
{
    #[Test]
    public function aMissingEntryNamesTheCandidates(): void
    {
        try {
            new PageEnumerator()->enumerate(
                SourceFixture::fetched('missing-entry', ['docs/v1/other.adoc']),
                new DocVersion('v1', 'docs/v1', 1),
                SourceFixture::config(),
            );

            self::fail('Expected a SourceException.');
        } catch (SourceException $exception) {
            $error = $exception->error();

            static::assertSame(ReasonCode::MissingEntry, $error->reasonCode());
            static::assertStringContainsString('index.adoc', $error->message());
            static::assertStringContainsString('README.adoc', $error->message());
            static::assertStringContainsString('missing-entry', $error->message());
            static::assertNotNull($error->correctiveAction());
        }
    }

    #[Test]
    public function aNestedPartialIsDroppedEvenWithNoExcludePatterns(): void
    {
        $config = SourceFixture::config([
            'source' => [
                'directory'       => 'docs',
                'excludePatterns' => [],
            ],
        ]);

        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', [
                'docs/index.adoc',
                'docs/reference/_nested-partial.adoc',
            ]),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            $config,
        );

        static::assertSame(
            ['docs/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    #[Test]
    public function anExcludePatternIsMatchedInsideTheVersionOnly(): void
    {
        $config = SourceFixture::config([
            'source' => [
                'directory'       => 'docs',
                'excludePatterns' => ['guide.adoc'],
            ],
        ]);

        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/v1/guide.adoc', 'docs/v1/index.adoc']),
            new DocVersion('v1', 'docs/v1', 1),
            $config,
        );

        static::assertSame(
            ['docs/v1/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    #[Test]
    public function aNonAsciiDocFileDoesNotStopTheScan(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/notes.txt', 'docs/index.adoc']),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            SourceFixture::config(),
        );

        static::assertSame(
            ['docs/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    #[Test]
    public function aPartialDoesNotStopTheScan(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/_partial.adoc', 'docs/index.adoc']),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            SourceFixture::config(),
        );

        static::assertSame(
            ['docs/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    #[Test]
    public function aSingleStarDoesNotCrossDirectories(): void
    {
        $config = SourceFixture::config([
            'source' => [
                'directory'       => 'docs',
                'excludePatterns' => ['*deep.adoc'],
            ],
        ]);

        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/index.adoc', 'docs/reference/deep.adoc']),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            $config,
        );

        static::assertSame(
            ['docs/index.adoc', 'docs/reference/deep.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    #[Test]
    public function aVersionWithoutAsciiDocFailsWithNoAsciidocSources(): void
    {
        try {
            new PageEnumerator()->enumerate(
                SourceFixture::fetched('markdown-only', ['docs/v1/index.md', 'docs/v1/guide.md']),
                new DocVersion('v1', 'docs/v1', 1),
                SourceFixture::config(),
            );

            self::fail('Expected a SourceException.');
        } catch (SourceException $exception) {
            $error = $exception->error();

            static::assertSame(ReasonCode::NoAsciidocSources, $error->reasonCode());
            static::assertStringContainsString('.adoc', $error->message());
            static::assertStringContainsString('markdown-only', $error->message());
        }
    }

    #[Test]
    public function bothAsciiDocExtensionsAreAccepted(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/index.adoc', 'docs/guide.asciidoc']),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            SourceFixture::config(),
        );

        static::assertSame(
            ['docs/index.adoc', 'docs/guide.asciidoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    #[Test]
    public function pagesOfAnotherVersionAreExcluded(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('versioned', ['docs/v1/index.adoc', 'docs/v2/index.adoc']),
            new DocVersion('v2', 'docs/v2', 2),
            SourceFixture::config(),
        );

        static::assertSame(
            ['docs/v2/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    #[Test]
    public function theAbsolutePathIsAnchoredAtTheRepositoryRoot(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/index.adoc'], rootPath: '/cache/example'),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            SourceFixture::config(),
        );
        $page = $set->entry();

        static::assertSame('docs/index.adoc', $page->relativePath());
        static::assertStringContainsString('example', $page->absolutePath());
        static::assertStringEndsWith('index.adoc', $page->absolutePath());
    }

    #[Test]
    public function theConfiguredExcludePatternsApply(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', [
                'docs/index.adoc',
                'docs/draft.adoc',
                'docs/reference/draft.adoc',
            ]),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            SourceFixture::config([
                'source' => [
                    'directory'       => 'docs',
                    'excludePatterns' => ['**/draft.adoc'],
                ],
            ]),
        );

        static::assertSame(
            ['docs/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    #[Test]
    public function theEntryPageIsTheFirstConfiguredCandidateThatExists(): void
    {
        $config = SourceFixture::config();

        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/v1/README.adoc', 'docs/v1/guide.adoc']),
            new DocVersion('v1', 'docs/v1', 1),
            $config,
        );

        static::assertSame('docs/v1/README.adoc', $set->entry()->relativePath());
    }

    #[Test]
    public function theEntryPageMustBeAtTheRootOfTheVersion(): void
    {
        try {
            new PageEnumerator()->enumerate(
                SourceFixture::fetched('example', ['docs/v1/reference/index.adoc']),
                new DocVersion('v1', 'docs/v1', 1),
                SourceFixture::config(),
            );
        } catch (SourceException $exception) {
            static::assertSame(ReasonCode::MissingEntry, $exception->error()->reasonCode());
            static::assertSame('v1', $exception->error()->version());
            static::assertSame('docs/v1', $exception->error()->path());

            return;
        }

        self::fail('Expected a SourceException.');
    }

    #[Test]
    public function theVersionedFixtureEnumeratesItsExactPages(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('versioned', [
                'docs/v1/guide.adoc',
                'docs/v1/index.adoc',
                'docs/v1/reference/nested.adoc',
                'docs/v2/index.adoc',
            ]),
            new DocVersion('v1', 'docs/v1', 1),
            SourceFixture::config(),
        );

        static::assertSame(
            ['docs/v1/guide.adoc', 'docs/v1/index.adoc', 'docs/v1/reference/nested.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
        static::assertSame('docs/v1/index.adoc', $set->entry()->relativePath());
        static::assertSame('v1', $set->entry()->version());
        static::assertSame('v1', $set->version()->label());
    }

    #[Test]
    public function underscorePrefixedFilesAreDropped(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', [
                'docs/index.adoc',
                'docs/_partial.adoc',
                'docs/reference/_nested-partial.adoc',
            ]),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            SourceFixture::config(),
        );

        static::assertSame(
            ['docs/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }
}
