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
use Webware\AsciidocPhp\MultiRepo\Source\FetchedRepository;
use Webware\AsciidocPhp\MultiRepo\Source\PageSet;
use Webware\AsciidocPhp\MultiRepo\Source\PreparedSource;
use Webware\AsciidocPhp\MultiRepo\Source\RepositorySources;
use Webware\AsciidocPhp\MultiRepo\Source\SourceError;
use Webware\AsciidocPhp\MultiRepo\Source\SourceFormat;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;

#[CoversClass(RepositorySources::class)]
#[CoversMethod(RepositorySources::class, 'documentationPath')]
#[CoversMethod(RepositorySources::class, 'errors')]
#[CoversMethod(RepositorySources::class, 'failed')]
#[CoversMethod(RepositorySources::class, 'fetched')]
#[CoversMethod(RepositorySources::class, 'isSuccessful')]
#[CoversMethod(RepositorySources::class, 'pages')]
#[CoversMethod(RepositorySources::class, 'repository')]
#[CoversMethod(RepositorySources::class, 'revision')]
#[CoversMethod(RepositorySources::class, 'sources')]
#[CoversMethod(RepositorySources::class, 'versions')]
#[CoversMethod(RepositorySources::class, 'warnings')]
final class RepositorySourcesTest extends TestCase
{
    #[Test]
    public function aFailureCarriesTheReasonCodeAndIsNotSuccessful(): void
    {
        $error   = new SourceError(ReasonCode::FetchFailed, 'message-bus: fetch failed.');
        $sources = RepositorySources::failed(SourceFixture::repository(), null, $error);

        static::assertFalse($sources->isSuccessful());
        static::assertSame([$error], $sources->errors());
        static::assertNull($sources->fetched());
        static::assertNull($sources->revision());
        static::assertNull($sources->documentationPath());
    }

    #[Test]
    public function aFailureKeepsTheFetchItGotThatFarWith(): void
    {
        $fetched = SourceFixture::fetched('message-bus', ['README.md'], revision: 'deadbeef');
        $sources = RepositorySources::failed(
            SourceFixture::repository(),
            $fetched,
            new SourceError(ReasonCode::NoAsciidocSources, 'message-bus: no AsciiDoc.'),
        );

        static::assertSame($fetched, $sources->fetched());
        static::assertSame('deadbeef', $sources->revision());
        static::assertNull($sources->documentationPath());
    }

    #[Test]
    public function anEmptyResultIsSuccessful(): void
    {
        $sources = new RepositorySources(SourceFixture::repository());

        static::assertTrue($sources->isSuccessful());
        static::assertSame([], $sources->errors());
        static::assertSame([], $sources->versions());
        static::assertSame([], $sources->pages());
        static::assertSame([], $sources->sources());
        static::assertSame([], $sources->warnings());
    }

    #[Test]
    public function aWarningAloneDoesNotMakeTheResultUnsuccessful(): void
    {
        $sources = new RepositorySources(
            SourceFixture::repository(),
            new FetchedRepository('versioned', 'abc', '/repo', 'docs', ['docs/index.adoc']),
            warnings: ['versioned: docs/loose.adoc sits outside a version directory'],
        );

        static::assertTrue($sources->isSuccessful());
        static::assertCount(1, $sources->warnings());
    }

    #[Test]
    public function everyStageOutputIsKept(): void
    {
        $repository = SourceFixture::repository();
        $fetched    = SourceFixture::fetched('versioned', ['docs/index.adoc'], revision: 'abc');
        $version    = new DocVersion(DocVersion::LATEST, 'docs', 0);
        $page       = new DocPage('latest', 'docs/index.adoc', '/repo/docs/index.adoc');
        $prepared   = new PreparedSource($page, SourceFormat::AsciiDoc, "= Title\n", 'hash');

        $sources = new RepositorySources(
            $repository,
            $fetched,
            [$version],
            [new PageSet($version, $page, [$page])],
            [$prepared],
            ['versioned: a warning'],
        );

        static::assertSame([$version], $sources->versions());
        static::assertCount(1, $sources->pages());
        static::assertSame([$prepared], $sources->sources());
        static::assertSame(['versioned: a warning'], $sources->warnings());
        static::assertSame('abc', $sources->revision());
        static::assertTrue($sources->isSuccessful());
    }

    #[Test]
    public function theDocumentationPathIsNullWhenTheDirectoryIsAbsent(): void
    {
        $sources = new RepositorySources(
            SourceFixture::repository(),
            SourceFixture::fetched('versioned', ['README.md']),
        );

        static::assertNull($sources->documentationPath());
    }

    #[Test]
    public function theDocumentationPathIsReportedWhenTheDirectoryExists(): void
    {
        $sources = new RepositorySources(
            SourceFixture::repository(),
            SourceFixture::fetched('versioned', ['docs/index.adoc']),
        );

        static::assertSame('docs', $sources->documentationPath());
    }

    #[Test]
    public function theRepositoryIsAlwaysAvailable(): void
    {
        $repository = SourceFixture::repository(
            name: 'message-bus',
            slug: 'message-bus',
        );

        static::assertSame($repository, new RepositorySources($repository)->repository());
    }
}
