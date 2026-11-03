<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

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

    #[Test]
    public function ensureDirectoryIsIdempotent(): void
    {
        $directory = Path::join($this->directory, 'nested', 'deeper');

        $this->filesystem->ensureDirectory($directory);
        $this->filesystem->ensureDirectory($directory);

        static::assertTrue(is_dir($directory));
    }

    #[Test]
    public function existsAndRead(): void
    {
        $target = Path::join($this->directory, 'index.adoc');

        static::assertFalse($this->filesystem->exists($target));

        $this->filesystem->writeAtomic($target, 'contents');

        static::assertTrue($this->filesystem->exists($target));
        static::assertSame('contents', $this->filesystem->read($target));
    }

    #[Test]
    public function failedWriteLeavesNoTemporaryFile(): void
    {
        $blocker = Path::join($this->directory, 'blocker');
        file_put_contents($blocker, 'x');

        $target = Path::join($blocker, 'nested', 'index.adoc');

        try {
            $this->filesystem->writeAtomic($target, 'content');
            self::fail('Writing below a file should fail.');
        } catch (RuntimeException $exception) {
            static::assertStringContainsString('blocker', $exception->getMessage());
        }

        static::assertSame([], glob(Path::join($this->directory, '.tmp-*')) ?: []);
        static::assertFalse(is_file($target));
    }

    #[Test]
    public function hashContentsMatchesSha256(): void
    {
        static::assertSame(hash('sha256', 'contents'), $this->filesystem->hashContents('contents'));
    }

    #[Test]
    public function hashFailsForMissingFile(): void
    {
        $this->expectException(RuntimeException::class);

        $this->filesystem->hash(Path::join($this->directory, 'missing.adoc'));
    }

    #[Test]
    public function hashMatchesSha256OfFileContents(): void
    {
        $target = Path::join($this->directory, 'index.adoc');
        $this->filesystem->writeAtomic($target, 'contents');

        static::assertSame(hash('sha256', 'contents'), $this->filesystem->hash($target));
    }

    #[Test]
    public function readFailsForMissingFile(): void
    {
        $this->expectException(RuntimeException::class);

        $this->filesystem->read(Path::join($this->directory, 'missing.adoc'));
    }

    #[Test]
    public function removeDeletesADirectoryTree(): void
    {
        $tree = Path::join($this->directory, 'build');

        $this->filesystem->ensureDirectory(Path::join($tree, 'deep/nested'));

        static::assertNotFalse(file_put_contents(Path::join($tree, 'index.html'), data: '<html></html>'));
        static::assertNotFalse(file_put_contents(Path::join($tree, 'deep/nested/page.html'), data: '<html></html>'));

        static::assertTrue($this->filesystem->remove($tree));
        static::assertFalse(is_dir($tree));
    }

    #[Test]
    public function removeDeletesASingleFile(): void
    {
        $file = Path::join($this->directory, 'report.json');

        static::assertNotFalse(file_put_contents($file, data: '{}'));

        static::assertTrue($this->filesystem->remove($file));
        static::assertFalse(is_file($file));
    }

    #[Test]
    public function removeTreatsAMissingPathAsAlreadyRemoved(): void
    {
        static::assertTrue($this->filesystem->remove(Path::join($this->directory, 'absent')));
    }

    #[Test]
    public function scanIgnoresDirectories(): void
    {
        $this->filesystem->writeAtomic(Path::join($this->directory, 'a', 'b.adoc'), 'b');

        static::assertSame(['a/b.adoc'], $this->filesystem->scan($this->directory));
    }

    #[Test]
    public function scanReturnsEmptyListForMissingDirectory(): void
    {
        static::assertSame([], $this->filesystem->scan(Path::join($this->directory, 'missing')));
    }

    #[Test]
    public function scanReturnsSortedRelativePathsWithForwardSlashes(): void
    {
        $this->filesystem->writeAtomic(Path::join($this->directory, 'b.adoc'), 'b');
        $this->filesystem->writeAtomic(Path::join($this->directory, 'a', 'c.adoc'), 'c');
        $this->filesystem->writeAtomic(Path::join($this->directory, 'a', 'b.adoc'), 'b');

        static::assertSame(
            ['a/b.adoc', 'a/c.adoc', 'b.adoc'],
            $this->filesystem->scan($this->directory),
        );
    }

    #[Test]
    public function writeAtomicCreatesMissingDirectories(): void
    {
        $target = Path::join($this->directory, 'build', 'html', 'index.html');

        $this->filesystem->writeAtomic($target, '<html></html>');

        static::assertSame('<html></html>', file_get_contents($target));
    }

    #[Test]
    public function writeIfChangedLeavesNoTemporaryFile(): void
    {
        $target = Path::join($this->directory, 'index.adoc');

        $this->filesystem->writeIfChanged($target, 'first');
        $this->filesystem->writeIfChanged($target, 'second');

        static::assertSame([], glob(Path::join($this->directory, '.tmp-*')) ?: []);
    }

    #[Test]
    public function writeIfChangedReplacesDifferentBytes(): void
    {
        $target = Path::join($this->directory, 'index.adoc');

        $this->filesystem->writeIfChanged($target, 'first');

        static::assertTrue($this->filesystem->writeIfChanged($target, 'second'));
        static::assertSame('second', file_get_contents($target));
    }

    #[Test]
    public function writeIfChangedSkipsIdenticalBytesAndPreservesMtime(): void
    {
        $target = Path::join($this->directory, 'index.adoc');

        static::assertTrue($this->filesystem->writeIfChanged($target, 'first'));

        $timestamp = 1_600_000_000;
        touch($target, $timestamp);
        static::assertSame($timestamp, filemtime($target));

        static::assertFalse($this->filesystem->writeIfChanged($target, 'first'));
        static::assertSame($timestamp, filemtime($target));
        static::assertSame('first', file_get_contents($target));
    }

    #[Test]
    public function writeIfChangedWritesNewFile(): void
    {
        $target = Path::join($this->directory, 'index.adoc');

        static::assertTrue($this->filesystem->writeIfChanged($target, 'first'));
        static::assertSame('first', file_get_contents($target));
    }

    protected function setUp(): void
    {
        $this->directory  = Path::join(sys_get_temp_dir(), 'asciidoc-fs-' . bin2hex(random_bytes(6)));
        $this->filesystem = new Filesystem();

        static::assertTrue(mkdir($this->directory, 0o777, true));
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
