<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Build;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Build\DeterminismGuard;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function chdir;
use function dirname;
use function explode;
use function file_put_contents;
use function getcwd;
use function gethostname;
use function is_string;
use function strlen;

/**
 * The guard is the only thing standing between a template that leaks the clock or the machine and
 * a published site that changes bytes between two identical builds. Each pattern is asserted
 * against a file written for that pattern alone, so a pattern that stops matching is a failure
 * rather than something another pattern hides.
 */
#[CoversClass(DeterminismGuard::class)]
#[CoversMethod(DeterminismGuard::class, 'inspect')]
final class DeterminismGuardTest extends TestCase
{
    use TempDirectoryTrait;

    public function testAFileWithoutAnExtensionIsNotScanned(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'LICENSE', "2026-11-02T18:19:02Z\n");

        self::assertSame([], new DeterminismGuard()->inspect($directory));
    }

    public function testAGeneratedAtStampIsReported(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'page.html', "<p>Generated at 2026-11-02 by the build</p>\n");

        self::assertSame(
            ['page.html line 1: a generated-at stamp'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testAGeneratedOnStampIsReported(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'page.html', "<p>generated on 2026-11-02</p>\n");

        self::assertSame(
            ['page.html line 1: a generated-at stamp'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testALeftoverTemporaryFileNameIsReported(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'page.html', "served from .tmp-a1b2c3\n");

        self::assertSame(
            ['page.html line 1: a leftover temporary file name'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testALongerExtensionlessNameIsNotScannedEither(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'xhtml', "2026-11-02T18:19:02Z\n");

        self::assertSame([], new DeterminismGuard()->inspect($directory));
    }

    public function testAnAbsolutePosixHomePathIsReported(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'page.html', '/home/runner/site/page.html');

        self::assertSame(
            ['page.html line 1: an absolute POSIX home path'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testAnAbsoluteWindowsPathIsReported(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'page.html', 'C:/build/site/page.html');

        self::assertSame(
            ['page.html line 1: an absolute Windows path'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testANonTextNameDoesNotHideALaterFile(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'a/logo.png', "2026-11-02T18:19:02Z\n");
        $this->write($directory, 'b.html', "2026-11-02T18:19:02Z\n");

        self::assertSame(
            ['b.html line 1: a build timestamp (an ISO 8601 instant)'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testAnUnlistedExtensionIsNotScanned(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'logo.png', "2026-11-02T18:19:02Z\n");

        self::assertSame([], new DeterminismGuard()->inspect($directory));
    }

    public function testARelativeOutputDirectoryContributesNoPattern(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'site/page.html', 'built in site order');

        $previous = getcwd();
        chdir($directory);

        try {
            self::assertSame([], new DeterminismGuard()->inspect('site'));
        } finally {
            chdir($previous);
        }
    }

    public function testATextExtensionIsScannedWhateverItsCase(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'PAGE.HTML', "2026-11-02T18:19:02Z\n");

        self::assertSame(
            ['PAGE.HTML line 1: a build timestamp (an ISO 8601 instant)'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testATimestampIsReportedOnTheLineItOccurs(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'page.html', "first\nsecond\n2026-11-02T18:19:02Z\n");

        self::assertSame(
            ['page.html line 3: a build timestamp (an ISO 8601 instant)'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testCleanOutputReportsNothing(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'index.html', "<p>stable</p>\n");

        self::assertSame([], new DeterminismGuard()->inspect($directory));
    }

    public function testEveryTextFileIsInspectedInAPathOrderedSequence(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'b.html', "2026-11-02T18:19:02Z\n");
        $this->write($directory, 'a/x.css', "2026-11-02T18:19:02Z\n");

        self::assertSame(
            [
                'a/x.css line 1: a build timestamp (an ISO 8601 instant)',
                'b.html line 1: a build timestamp (an ISO 8601 instant)',
            ],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testTheCnameFileIsScannedWhateverItsCase(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'CNAME', "2026-11-02T18:19:02Z\n");

        self::assertSame(
            ['CNAME line 1: a build timestamp (an ISO 8601 instant)'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testTheHostnameIsReported(): void
    {
        $hostname = gethostname();

        if (! is_string($hostname) || strlen($hostname) <= 2) {
            self::markTestSkipped('this machine has no hostname to detect');
        }

        $directory = $this->createTempDirectory();
        $this->write($directory, 'page.html', "served by {$hostname}\n");

        self::assertSame(
            ['page.html line 1: this machine\'s name'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testTheLineNumberCountsEveryNewlineBeforeTheMatch(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'page.html', "\n<p>Generated at 2026-11-02</p>\n");

        self::assertSame(
            ['page.html line 2: a generated-at stamp'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testTheNoJekyllMarkerIsScannedEvenWithoutAnExtension(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, '.nojekyll', "2026-11-02T18:19:02Z\n");

        self::assertSame(
            ['.nojekyll line 1: a build timestamp (an ISO 8601 instant)'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    public function testTheOutputDirectoryPathIsReported(): void
    {
        $directory = $this->createTempDirectory();
        $this->write($directory, 'page.html', "checkout at {$directory}/page.html\n");

        self::assertSame(
            ['page.html line 1: the output directory path'],
            new DeterminismGuard()->inspect($directory),
        );
    }

    private function write(string $directory, string $relativePath, string $contents): void
    {
        $path = Path::join($directory, ...explode('/', $relativePath));

        new Filesystem()->ensureDirectory(dirname($path));
        file_put_contents($path, $contents);
    }
}
