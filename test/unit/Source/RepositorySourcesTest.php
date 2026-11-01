<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
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
    public function testAFailureCarriesTheReasonCodeAndIsNotSuccessful(): void
    {
        $error   = new SourceError(ReasonCode::FetchFailed, 'message-bus: fetch failed.');
        $sources = RepositorySources::failed(SourceFixture::repository(), null, $error);

        self::assertFalse($sources->isSuccessful());
        self::assertSame([$error], $sources->errors());
        self::assertNull($sources->fetched());
        self::assertNull($sources->revision());
        self::assertNull($sources->documentationPath());
    }

    public function testAFailureKeepsTheFetchItGotThatFarWith(): void
    {
        $fetched = SourceFixture::fetched('message-bus', ['README.md'], revision: 'deadbeef');
        $sources = RepositorySources::failed(
            SourceFixture::repository(),
            $fetched,
            new SourceError(ReasonCode::NoAsciidocSources, 'message-bus: no AsciiDoc.'),
        );

        self::assertSame($fetched, $sources->fetched());
        self::assertSame('deadbeef', $sources->revision());
        self::assertNull($sources->documentationPath());
    }

    public function testAnEmptyResultIsSuccessful(): void
    {
        $sources = new RepositorySources(SourceFixture::repository());

        self::assertTrue($sources->isSuccessful());
        self::assertSame([], $sources->errors());
        self::assertSame([], $sources->versions());
        self::assertSame([], $sources->pages());
        self::assertSame([], $sources->sources());
        self::assertSame([], $sources->warnings());
    }

    public function testAWarningAloneDoesNotMakeTheResultUnsuccessful(): void
    {
        $sources = new RepositorySources(
            SourceFixture::repository(),
            new FetchedRepository('versioned', 'abc', '/repo', 'docs', ['docs/index.adoc']),
            warnings: ['versioned: docs/loose.adoc sits outside a version directory'],
        );

        self::assertTrue($sources->isSuccessful());
        self::assertCount(1, $sources->warnings());
    }

    public function testEveryStageOutputIsKept(): void
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

        self::assertSame([$version], $sources->versions());
        self::assertCount(1, $sources->pages());
        self::assertSame([$prepared], $sources->sources());
        self::assertSame(['versioned: a warning'], $sources->warnings());
        self::assertSame('abc', $sources->revision());
        self::assertTrue($sources->isSuccessful());
    }

    public function testTheDocumentationPathIsNullWhenTheDirectoryIsAbsent(): void
    {
        $sources = new RepositorySources(
            SourceFixture::repository(),
            SourceFixture::fetched('versioned', ['README.md']),
        );

        self::assertNull($sources->documentationPath());
    }

    public function testTheDocumentationPathIsReportedWhenTheDirectoryExists(): void
    {
        $sources = new RepositorySources(
            SourceFixture::repository(),
            SourceFixture::fetched('versioned', ['docs/index.adoc']),
        );

        self::assertSame('docs', $sources->documentationPath());
    }

    public function testTheRepositoryIsAlwaysAvailable(): void
    {
        $repository = SourceFixture::repository(
            name: 'message-bus',
            slug: 'message-bus',
        );

        self::assertSame($repository, new RepositorySources($repository)->repository());
    }
}
