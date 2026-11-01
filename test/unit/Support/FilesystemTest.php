<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webware\AsciidocPhpMultiRepo\Support\Filesystem;
use Webware\AsciidocPhpMultiRepo\Support\Path;

use function bin2hex;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function glob;
use function hash;
use function is_dir;
use function is_file;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function touch;
use function unlink;

#[CoversClass(Filesystem::class)]
final class FilesystemTest extends TestCase
{
    private string $directory;

    private Filesystem $filesystem;

    public function testEnsureDirectoryIsIdempotent(): void
    {
        $directory = Path::join($this->directory, 'nested', 'deeper');

        $this->filesystem->ensureDirectory($directory);
        $this->filesystem->ensureDirectory($directory);

        self::assertTrue(is_dir($directory));
    }

    public function testExistsAndRead(): void
    {
        $target = Path::join($this->directory, 'index.adoc');

        self::assertFalse($this->filesystem->exists($target));

        $this->filesystem->writeAtomic($target, 'contents');

        self::assertTrue($this->filesystem->exists($target));
        self::assertSame('contents', $this->filesystem->read($target));
    }

    public function testFailedWriteLeavesNoTemporaryFile(): void
    {
        $blocker = Path::join($this->directory, 'blocker');
        file_put_contents($blocker, 'x');

        $target = Path::join($blocker, 'nested', 'index.adoc');

        try {
            $this->filesystem->writeAtomic($target, 'content');
            self::fail('Writing below a file should fail.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('blocker', $exception->getMessage());
        }

        self::assertSame([], glob(Path::join($this->directory, '.tmp-*')) ?: []);
        self::assertFalse(is_file($target));
    }

    public function testHashContentsMatchesSha256(): void
    {
        self::assertSame(hash('sha256', 'contents'), $this->filesystem->hashContents('contents'));
    }

    public function testHashFailsForMissingFile(): void
    {
        $this->expectException(RuntimeException::class);

        $this->filesystem->hash(Path::join($this->directory, 'missing.adoc'));
    }

    public function testHashMatchesSha256OfFileContents(): void
    {
        $target = Path::join($this->directory, 'index.adoc');
        $this->filesystem->writeAtomic($target, 'contents');

        self::assertSame(hash('sha256', 'contents'), $this->filesystem->hash($target));
    }

    public function testReadFailsForMissingFile(): void
    {
        $this->expectException(RuntimeException::class);

        $this->filesystem->read(Path::join($this->directory, 'missing.adoc'));
    }

    public function testScanIgnoresDirectories(): void
    {
        $this->filesystem->writeAtomic(Path::join($this->directory, 'a', 'b.adoc'), 'b');

        self::assertSame(['a/b.adoc'], $this->filesystem->scan($this->directory));
    }

    public function testScanReturnsEmptyListForMissingDirectory(): void
    {
        self::assertSame([], $this->filesystem->scan(Path::join($this->directory, 'missing')));
    }

    public function testScanReturnsSortedRelativePathsWithForwardSlashes(): void
    {
        $this->filesystem->writeAtomic(Path::join($this->directory, 'b.adoc'), 'b');
        $this->filesystem->writeAtomic(Path::join($this->directory, 'a', 'c.adoc'), 'c');
        $this->filesystem->writeAtomic(Path::join($this->directory, 'a', 'b.adoc'), 'b');

        self::assertSame(
            ['a/b.adoc', 'a/c.adoc', 'b.adoc'],
            $this->filesystem->scan($this->directory),
        );
    }

    public function testWriteAtomicCreatesMissingDirectories(): void
    {
        $target = Path::join($this->directory, 'build', 'html', 'index.html');

        $this->filesystem->writeAtomic($target, '<html></html>');

        self::assertSame('<html></html>', file_get_contents($target));
    }

    public function testWriteIfChangedLeavesNoTemporaryFile(): void
    {
        $target = Path::join($this->directory, 'index.adoc');

        $this->filesystem->writeIfChanged($target, 'first');
        $this->filesystem->writeIfChanged($target, 'second');

        self::assertSame([], glob(Path::join($this->directory, '.tmp-*')) ?: []);
    }

    public function testWriteIfChangedReplacesDifferentBytes(): void
    {
        $target = Path::join($this->directory, 'index.adoc');

        $this->filesystem->writeIfChanged($target, 'first');

        self::assertTrue($this->filesystem->writeIfChanged($target, 'second'));
        self::assertSame('second', file_get_contents($target));
    }

    public function testWriteIfChangedSkipsIdenticalBytesAndPreservesMtime(): void
    {
        $target = Path::join($this->directory, 'index.adoc');

        self::assertTrue($this->filesystem->writeIfChanged($target, 'first'));

        $timestamp = 1_600_000_000;
        touch($target, $timestamp);
        self::assertSame($timestamp, filemtime($target));

        self::assertFalse($this->filesystem->writeIfChanged($target, 'first'));
        self::assertSame($timestamp, filemtime($target));
        self::assertSame('first', file_get_contents($target));
    }

    public function testWriteIfChangedWritesNewFile(): void
    {
        $target = Path::join($this->directory, 'index.adoc');

        self::assertTrue($this->filesystem->writeIfChanged($target, 'first'));
        self::assertSame('first', file_get_contents($target));
    }

    protected function setUp(): void
    {
        $this->directory  = Path::join(sys_get_temp_dir(), 'asciidoc-fs-' . bin2hex(random_bytes(6)));
        $this->filesystem = new Filesystem();

        self::assertTrue(mkdir($this->directory, 0o777, true));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob(Path::join($directory, '*')) ?: [] as $entry) {
            if (is_dir($entry)) {
                $this->removeDirectory($entry);

                continue;
            }

            unlink($entry);
        }

        rmdir($directory);
    }
}
