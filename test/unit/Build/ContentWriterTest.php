<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Build;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\ContentWriter;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function file_get_contents;
use function is_file;

/**
 * T040: the writer puts a rendered page at its published path.
 *
 * Write-only-if-changed and stale removal belong to the build determinism work, so what is proven
 * here is the plain contract: the parent directories are created, the bytes land, the path is
 * returned, and a second write replaces the contents.
 */
#[CoversClass(ContentWriter::class)]
#[CoversMethod(ContentWriter::class, 'write')]
final class ContentWriterTest extends TestCase
{
    use TempDirectoryTrait;

    #[Test]
    public function aNestedPathIsCreatedAndTheFileIsWritten(): void
    {
        $directory = $this->createTempDirectory();
        $path      = new ContentWriter()->write($directory, 'project/v1/guide/index.html', '<p>Hi</p>');

        self::assertSame(Path::join($directory, 'project', 'v1', 'guide', 'index.html'), $path);
        self::assertTrue(is_file($path));
        self::assertSame('<p>Hi</p>', file_get_contents($path));
    }

    #[Test]
    public function aSecondWriteReplacesTheContents(): void
    {
        $directory = $this->createTempDirectory();
        $writer    = new ContentWriter();

        $writer->write($directory, 'index.html', 'first');
        $path = $writer->write($directory, 'index.html', 'second');

        self::assertSame('second', file_get_contents($path));
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }
}
