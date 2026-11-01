<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Source\FetchedRepository;
use Webware\AsciidocPhpMultiRepo\Support\Path;

#[CoversClass(FetchedRepository::class)]
#[CoversMethod(FetchedRepository::class, 'attempts')]
#[CoversMethod(FetchedRepository::class, 'checkedOut')]
#[CoversMethod(FetchedRepository::class, 'docPath')]
#[CoversMethod(FetchedRepository::class, 'documentationDirectory')]
#[CoversMethod(FetchedRepository::class, 'files')]
#[CoversMethod(FetchedRepository::class, 'hasDocumentation')]
#[CoversMethod(FetchedRepository::class, 'repositoryName')]
#[CoversMethod(FetchedRepository::class, 'revision')]
#[CoversMethod(FetchedRepository::class, 'rootPath')]
final class FetchedRepositoryTest extends TestCase
{
    public function testADirectoryPrefixMatchIsNotEnough(): void
    {
        $fetched = new FetchedRepository('versioned', 'abc123', '/r', 'docs', ['docs-extra/index.adoc']);

        self::assertFalse($fetched->hasDocumentation());
    }

    public function testAnEmptyFileListHasNoDocumentation(): void
    {
        self::assertFalse(new FetchedRepository('versioned', 'abc123', '/r', 'docs', [])->hasDocumentation());
    }

    public function testDocumentationIsAbsentWhenNothingSitsBelowTheDirectory(): void
    {
        $fetched = new FetchedRepository('versioned', 'abc123', '/r', 'docs', ['README.md', 'src/Docs.php']);

        self::assertFalse($fetched->hasDocumentation());
    }

    public function testDocumentationIsPresentWhenAFileSitsBelowTheDirectory(): void
    {
        $fetched = new FetchedRepository('versioned', 'abc123', '/r', 'docs', ['README.md', 'docs/index.adoc']);

        self::assertTrue($fetched->hasDocumentation());
    }

    public function testOneAttemptAndAnUpdatedWorktreeAreTheDefaults(): void
    {
        $fetched = new FetchedRepository('versioned', 'abc123', '/cache/versioned', 'docs', []);

        self::assertSame(1, $fetched->attempts());
        self::assertTrue($fetched->checkedOut());
    }

    public function testTheDocumentationPathIsJoinedOntoTheRoot(): void
    {
        $fetched = new FetchedRepository('versioned', 'abc123', '/cache/versioned', 'docs', []);

        self::assertSame(Path::join('/cache/versioned', 'docs'), $fetched->docPath());
    }

    public function testTheRecordedFieldsAreReturned(): void
    {
        $fetched = new FetchedRepository(
            'versioned',
            'abc123',
            '/cache/versioned',
            'docs',
            ['docs/index.adoc'],
            2,
            false,
        );

        self::assertSame('versioned', $fetched->repositoryName());
        self::assertSame('abc123', $fetched->revision());
        self::assertSame('/cache/versioned', $fetched->rootPath());
        self::assertSame('docs', $fetched->documentationDirectory());
        self::assertSame(['docs/index.adoc'], $fetched->files());
        self::assertSame(2, $fetched->attempts());
        self::assertFalse($fetched->checkedOut());
    }
}
