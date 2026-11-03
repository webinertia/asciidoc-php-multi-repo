<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Report\ReasonCode;
use Webware\AsciidocPhp\MultiRepo\Source\DocPage;
use Webware\AsciidocPhp\MultiRepo\Source\SourceException;
use Webware\AsciidocPhp\MultiRepo\Source\SourceFormat;
use Webware\AsciidocPhp\MultiRepo\Source\SourcePreparer;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

/**
 * Source preparation: format detection, encoding validation, byte order mark removal, line-ending
 * normalisation, and the content hash.
 */
#[CoversClass(SourcePreparer::class)]
#[CoversMethod(SourcePreparer::class, 'prepare')]
final class SourcePreparerTest extends TestCase
{
    use TempDirectoryTrait;

    private Filesystem $filesystem;

    #[Test]
    public function aLeadingByteOrderMarkIsRemoved(): void
    {
        $source = new SourcePreparer()->prepare($this->page('docs/bom.adoc', "\xEF\xBB\xBF= Title\n"));

        self::assertSame("= Title\n", $source->text());
    }

    #[Test]
    public function aLoneCarriageReturnIsNormalisedToLf(): void
    {
        $source = new SourcePreparer()->prepare($this->page('docs/old.adoc', "= Title\rBody.\r"));

        self::assertSame("= Title\nBody.\n", $source->text());
    }

    #[Test]
    public function aMarkdownPageIsRejectedBeforeItIsRead(): void
    {
        $page = new DocPage('v1', 'docs/v1/guide.md', Path::join($this->tempDirectory, 'absent.md'));

        try {
            new SourcePreparer()->prepare($page);

            self::fail('Expected a SourceException.');
        } catch (SourceException $exception) {
            $error = $exception->error();

            self::assertSame(ReasonCode::NoAsciidocSources, $error->reasonCode());
            self::assertSame('docs/v1/guide.md', $error->path());
            self::assertSame('v1', $error->version());
            self::assertStringContainsString('markdown', $error->message());
            self::assertNotNull($error->correctiveAction());
        }
    }

    #[Test]
    public function anAsciidocExtensionInUpperCaseIsAccepted(): void
    {
        $source = new SourcePreparer()->prepare($this->page('docs/INDEX.ADOC', "= Title\n"));

        self::assertSame(SourceFormat::AsciiDoc, $source->format());
    }

    #[Test]
    public function anUnknownExtensionIsRejected(): void
    {
        $page = new DocPage('v1', 'docs/v1/notes.txt', Path::join($this->tempDirectory, 'absent.txt'));

        $this->expectException(SourceException::class);

        new SourcePreparer()->prepare($page);
    }

    #[Test]
    public function aWellFormedPageIsReturnedUnchanged(): void
    {
        $page   = $this->page('docs/index.adoc', "= Title\n\nBody.\n");
        $source = new SourcePreparer()->prepare($page);

        self::assertSame("= Title\n\nBody.\n", $source->text());
        self::assertSame(SourceFormat::AsciiDoc, $source->format());
        self::assertSame($page, $source->page());
        self::assertSame($this->filesystem->hashContents("= Title\n\nBody.\n"), $source->hash());
    }

    #[Test]
    public function crlfIsNormalisedToLf(): void
    {
        $crlf = $this->page('docs/crlf.adoc', "= Title\r\n\r\nBody.\r\n");
        $lf   = $this->page('docs/lf.adoc', "= Title\n\nBody.\n");

        $preparer = new SourcePreparer();

        self::assertSame($preparer->prepare($lf)->text(), $preparer->prepare($crlf)->text());
        self::assertSame($preparer->prepare($lf)->hash(), $preparer->prepare($crlf)->hash());
    }

    #[Test]
    public function invalidUtf8IsRejectedNamingThePath(): void
    {
        $page = $this->page('docs/v1/latin1.adoc', "= Caf\xE9\n");

        try {
            new SourcePreparer()->prepare($page);

            self::fail('Expected a SourceException.');
        } catch (SourceException $exception) {
            $error = $exception->error();

            self::assertSame(ReasonCode::InvalidEncoding, $error->reasonCode());
            self::assertSame('docs/v1/latin1.adoc', $error->path());
            self::assertSame('v1', $error->version());
            self::assertStringContainsString('docs/v1/latin1.adoc', $error->message());
        }
    }

    #[Test]
    public function theHashCoversTheNormalisedText(): void
    {
        $source = new SourcePreparer()->prepare($this->page('docs/crlf.adoc', "= Title\r\n"));

        self::assertSame($this->filesystem->hashContents("= Title\n"), $source->hash());
        self::assertNotSame($this->filesystem->hashContents("= Title\r\n"), $source->hash());
    }

    #[Test]
    public function thePreparedSourceKeepsThePageItCameFrom(): void
    {
        $page   = $this->page('docs/index.adoc', "= Title\n");
        $source = new SourcePreparer()->prepare($page);

        self::assertSame('docs/index.adoc', $source->page()->relativePath());
        self::assertSame('v1', $source->page()->version());
    }

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    private function page(string $relativePath, string $contents): DocPage
    {
        $absolute = Path::join($this->tempDirectory, $relativePath);

        $this->filesystem->ensureDirectory(Path::join($this->tempDirectory, 'docs', 'v1'));
        $this->filesystem->writeAtomic($absolute, $contents);

        return new DocPage('v1', $relativePath, $absolute);
    }
}
