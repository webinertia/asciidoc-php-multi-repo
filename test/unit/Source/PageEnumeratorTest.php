<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Report\ReasonCode;
use Webware\AsciidocPhpMultiRepo\Source\DocPage;
use Webware\AsciidocPhpMultiRepo\Source\DocVersion;
use Webware\AsciidocPhpMultiRepo\Source\PageEnumerator;
use Webware\AsciidocPhpMultiRepo\Source\SourceException;
use WebwareTest\AsciidocPhpMultiRepo\Support\SourceFixture;

use function array_map;

/**
 * Page enumeration: extension filtering, partial and exclude rules, nested directories, and the
 * two failures a version can produce.
 */
#[CoversClass(PageEnumerator::class)]
#[CoversMethod(PageEnumerator::class, 'enumerate')]
final class PageEnumeratorTest extends TestCase
{
    public function testAMissingEntryNamesTheCandidates(): void
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

            self::assertSame(ReasonCode::MissingEntry, $error->reasonCode());
            self::assertStringContainsString('index.adoc', $error->message());
            self::assertStringContainsString('README.adoc', $error->message());
            self::assertStringContainsString('missing-entry', $error->message());
            self::assertNotNull($error->correctiveAction());
        }
    }

    public function testANestedPartialIsDroppedEvenWithNoExcludePatterns(): void
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

        self::assertSame(
            ['docs/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    public function testAnExcludePatternIsMatchedInsideTheVersionOnly(): void
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

        self::assertSame(
            ['docs/v1/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    public function testANonAsciiDocFileDoesNotStopTheScan(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/notes.txt', 'docs/index.adoc']),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            SourceFixture::config(),
        );

        self::assertSame(
            ['docs/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    public function testAPartialDoesNotStopTheScan(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/_partial.adoc', 'docs/index.adoc']),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            SourceFixture::config(),
        );

        self::assertSame(
            ['docs/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    public function testASingleStarDoesNotCrossDirectories(): void
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

        self::assertSame(
            ['docs/index.adoc', 'docs/reference/deep.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    public function testAVersionWithoutAsciiDocFailsWithNoAsciidocSources(): void
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

            self::assertSame(ReasonCode::NoAsciidocSources, $error->reasonCode());
            self::assertStringContainsString('.adoc', $error->message());
            self::assertStringContainsString('markdown-only', $error->message());
        }
    }

    public function testBothAsciiDocExtensionsAreAccepted(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/index.adoc', 'docs/guide.asciidoc']),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            SourceFixture::config(),
        );

        self::assertSame(
            ['docs/index.adoc', 'docs/guide.asciidoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    public function testPagesOfAnotherVersionAreExcluded(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('versioned', ['docs/v1/index.adoc', 'docs/v2/index.adoc']),
            new DocVersion('v2', 'docs/v2', 2),
            SourceFixture::config(),
        );

        self::assertSame(
            ['docs/v2/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    public function testTheAbsolutePathIsAnchoredAtTheRepositoryRoot(): void
    {
        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/index.adoc'], rootPath: '/cache/example'),
            new DocVersion(DocVersion::LATEST, 'docs', 0),
            SourceFixture::config(),
        );
        $page = $set->entry();

        self::assertSame('docs/index.adoc', $page->relativePath());
        self::assertStringContainsString('example', $page->absolutePath());
        self::assertStringEndsWith('index.adoc', $page->absolutePath());
    }

    public function testTheConfiguredExcludePatternsApply(): void
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

        self::assertSame(
            ['docs/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }

    public function testTheEntryPageIsTheFirstConfiguredCandidateThatExists(): void
    {
        $config = SourceFixture::config();

        $set = new PageEnumerator()->enumerate(
            SourceFixture::fetched('example', ['docs/v1/README.adoc', 'docs/v1/guide.adoc']),
            new DocVersion('v1', 'docs/v1', 1),
            $config,
        );

        self::assertSame('docs/v1/README.adoc', $set->entry()->relativePath());
    }

    public function testTheEntryPageMustBeAtTheRootOfTheVersion(): void
    {
        try {
            new PageEnumerator()->enumerate(
                SourceFixture::fetched('example', ['docs/v1/reference/index.adoc']),
                new DocVersion('v1', 'docs/v1', 1),
                SourceFixture::config(),
            );
        } catch (SourceException $exception) {
            self::assertSame(ReasonCode::MissingEntry, $exception->error()->reasonCode());
            self::assertSame('v1', $exception->error()->version());
            self::assertSame('docs/v1', $exception->error()->path());

            return;
        }

        self::fail('Expected a SourceException.');
    }

    public function testTheVersionedFixtureEnumeratesItsExactPages(): void
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

        self::assertSame(
            ['docs/v1/guide.adoc', 'docs/v1/index.adoc', 'docs/v1/reference/nested.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
        self::assertSame('docs/v1/index.adoc', $set->entry()->relativePath());
        self::assertSame('v1', $set->entry()->version());
        self::assertSame('v1', $set->version()->label());
    }

    public function testUnderscorePrefixedFilesAreDropped(): void
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

        self::assertSame(
            ['docs/index.adoc'],
            array_map(static fn(DocPage $page): string => $page->relativePath(), $set->pages()),
        );
    }
}
