<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Source\SourceTree;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function dirname;

/**
 * The file list a source tree reports: git metadata and the paths this build writes are not sources.
 */
#[CoversClass(SourceTree::class)]
final class SourceTreeTest extends TestCase
{
    use TempDirectoryTrait;

    private Filesystem $filesystem;

    #[Test]
    public function anExcludedPathIsNotASource(): void
    {
        $root = $this->tempDirectory;

        $this->write('docs/index.adoc', "= Index\n");
        $this->write('build/report.json', '{"ok":1}');

        static::assertSame(
            ['docs/index.adoc'],
            new SourceTree()->files($root, [Path::join($root, 'build')]),
        );
    }

    #[Test]
    public function aRootOnlyExclusionExcludesNothing(): void
    {
        $root = $this->tempDirectory;

        $this->write('docs/index.adoc', "= Index\n");

        static::assertSame(['docs/index.adoc'], new SourceTree()->files($root, ['/']));
        static::assertSame(['docs/index.adoc'], new SourceTree()->files($root, ['']));
    }

    #[Test]
    public function gitMetadataIsNotASource(): void
    {
        $this->write('docs/index.adoc', "= Index\n");
        $this->write('.git/HEAD', "ref: refs/heads/main\n");

        static::assertSame(['docs/index.adoc'], new SourceTree()->files($this->tempDirectory));
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

    private function write(string $relative, string $contents): void
    {
        $absolute = Path::join($this->tempDirectory, $relative);

        $this->filesystem->ensureDirectory(dirname($absolute));
        $this->filesystem->writeAtomic($absolute, $contents);
    }
}
