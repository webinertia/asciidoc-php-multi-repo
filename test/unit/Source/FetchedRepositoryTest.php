<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Source\FetchedRepository;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

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
    #[Test]
    public function aDirectoryPrefixMatchIsNotEnough(): void
    {
        $fetched = new FetchedRepository('versioned', 'abc123', '/r', 'docs', ['docs-extra/index.adoc']);

        static::assertFalse($fetched->hasDocumentation());
    }

    #[Test]
    public function anEmptyFileListHasNoDocumentation(): void
    {
        static::assertFalse(new FetchedRepository('versioned', 'abc123', '/r', 'docs', [])->hasDocumentation());
    }

    #[Test]
    public function documentationIsAbsentWhenNothingSitsBelowTheDirectory(): void
    {
        $fetched = new FetchedRepository('versioned', 'abc123', '/r', 'docs', ['README.md', 'src/Docs.php']);

        static::assertFalse($fetched->hasDocumentation());
    }

    #[Test]
    public function documentationIsPresentWhenAFileSitsBelowTheDirectory(): void
    {
        $fetched = new FetchedRepository('versioned', 'abc123', '/r', 'docs', ['README.md', 'docs/index.adoc']);

        static::assertTrue($fetched->hasDocumentation());
    }

    #[Test]
    public function oneAttemptAndAnUpdatedWorktreeAreTheDefaults(): void
    {
        $fetched = new FetchedRepository('versioned', 'abc123', '/cache/versioned', 'docs', []);

        static::assertSame(1, $fetched->attempts());
        static::assertTrue($fetched->checkedOut());
    }

    #[Test]
    public function theDocumentationPathIsJoinedOntoTheRoot(): void
    {
        $fetched = new FetchedRepository('versioned', 'abc123', '/cache/versioned', 'docs', []);

        static::assertSame(Path::join('/cache/versioned', 'docs'), $fetched->docPath());
    }

    #[Test]
    public function theRecordedFieldsAreReturned(): void
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

        static::assertSame('versioned', $fetched->repositoryName());
        static::assertSame('abc123', $fetched->revision());
        static::assertSame('/cache/versioned', $fetched->rootPath());
        static::assertSame('docs', $fetched->documentationDirectory());
        static::assertSame(['docs/index.adoc'], $fetched->files());
        static::assertSame(2, $fetched->attempts());
        static::assertFalse($fetched->checkedOut());
    }
}
