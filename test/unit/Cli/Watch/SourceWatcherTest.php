<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli\Watch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Watch\SourceWatcher;
use Webware\AsciidocPhp\MultiRepo\Cli\Watch\WatchedSource;
use Webware\AsciidocPhp\MultiRepo\Support\Filesystem;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function array_keys;
use function dirname;
use function hash;
use function sleep;

#[CoversClass(SourceWatcher::class)]
#[CoversMethod(SourceWatcher::class, 'snapshot')]
#[CoversMethod(SourceWatcher::class, 'sources')]
final class SourceWatcherTest extends TestCase
{
    use TempDirectoryTrait;

    private string $root = '';

    private Filesystem $filesystem;

    #[Test]
    public function aDirectorySourceContributesEveryFileBelowIt(): void
    {
        $this->write('org/a/docs/v1/index.adoc', 'one');
        $this->write('org/a/docs/v1/guide.adoc', 'two');

        $snapshot = $this->watcher([WatchedSource::forProject('a', $this->path('org/a'))])->snapshot();

        static::assertCount(2, $snapshot->signatures());
    }

    #[Test]
    public function aGlobalSourceTagsItsFilesAsShared(): void
    {
        $this->write('site.json', '{}');

        $watcher = $this->watcher([WatchedSource::global($this->path('site.json'))]);
        $before  = $watcher->snapshot();

        $this->write('site.json', '{ }');

        static::assertTrue($before->changeAgainst($watcher->snapshot())->everything());
    }

    #[Test]
    public function aPathThatDoesNotExistContributesNothing(): void
    {
        $snapshot = $this->watcher([WatchedSource::global($this->path('absent'))])->snapshot();

        static::assertSame([], $snapshot->signatures());
    }

    #[Test]
    public function aProjectSourceTagsItsFilesWithTheSlug(): void
    {
        $this->write('org/a/docs/v1/index.adoc', 'one');

        $watcher = $this->watcher([WatchedSource::forProject('a', $this->path('org/a'))]);
        $before  = $watcher->snapshot();

        $this->write('org/a/docs/v1/index.adoc', 'changed');

        $change = $before->changeAgainst($watcher->snapshot());

        static::assertSame(['a'], $change->projects());
        static::assertFalse($change->everything());
    }

    #[Test]
    public function aSameLengthEditChangesTheSignature(): void
    {
        $this->write('org/a/page.adoc', 'aaaa');
        $watcher = $this->watcher([WatchedSource::forProject('a', $this->path('org/a'))]);

        $before = $watcher->snapshot();
        $this->write('org/a/page.adoc', 'bbbb');
        $after = $watcher->snapshot();

        static::assertNotSame($before->signatures(), $after->signatures());
    }

    #[Test]
    public function aSingleFileSourceContributesThatFile(): void
    {
        $this->write('site.json', '{}');

        $snapshot = $this->watcher([WatchedSource::global($this->path('site.json'))])->snapshot();

        static::assertSame([$this->path('site.json')], array_keys($snapshot->signatures()));
    }

    #[Test]
    public function aTouchWithIdenticalContentStillChangesTheSignature(): void
    {
        $this->write('org/a/page.adoc', 'aaaa');
        $watcher = $this->watcher([WatchedSource::forProject('a', $this->path('org/a'))]);

        $before = $watcher->snapshot();
        sleep(1);
        $this->write('org/a/page.adoc', 'aaaa');
        $after = $watcher->snapshot();

        $path = $this->path('org/a/page.adoc');

        static::assertNotSame($before->signatures()[$path], $after->signatures()[$path]);
        static::assertStringEndsWith(
            ':'
                . hash(
                    algo: 'sha256',
                    data: 'aaaa',
                ),
            $after->signatures()[$path],
        );
    }

    #[Test]
    public function theSignatureRecordsSizeAndHash(): void
    {
        $this->write('org/a/page.adoc', 'abcd');

        $signature = $this
            ->watcher([WatchedSource::forProject('a', $this->path('org/a'))])
            ->snapshot()
            ->signatures()[$this->path('org/a/page.adoc')];

        static::assertStringEndsWith(
            ':4:'
                . hash(
                    algo: 'sha256',
                    data: 'abcd',
                ),
            $signature,
        );
    }

    #[Test]
    public function theSourcesAreExposed(): void
    {
        $sources = [WatchedSource::global($this->path('site.json'))];

        static::assertCount(1, $this->watcher($sources)->sources());
    }

    #[Test]
    public function twoSourcesDoNotDuplicateASharedFile(): void
    {
        $this->write('org/a/page.adoc', 'one');

        $snapshot = $this->watcher([
            WatchedSource::forProject('a', $this->path('org/a')),
            WatchedSource::forProject('b', $this->path('org/a')),
        ])->snapshot();

        static::assertCount(1, $snapshot->signatures());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->root       = $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();

        parent::tearDown();
    }

    private function path(string $relative): string
    {
        return Path::join($this->root, $relative);
    }

    /**
     * @param list<WatchedSource> $sources
     */
    private function watcher(array $sources): SourceWatcher
    {
        return new SourceWatcher($sources);
    }

    private function write(string $relative, string $contents): void
    {
        $path = $this->path($relative);

        $this->filesystem->ensureDirectory(dirname($path));
        $this->filesystem->writeAtomic($path, $contents);
    }
}
