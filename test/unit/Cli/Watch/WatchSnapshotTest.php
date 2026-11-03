<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli\Watch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Watch\WatchChange;
use Webware\AsciidocPhp\MultiRepo\Cli\Watch\WatchSnapshot;

#[CoversClass(WatchSnapshot::class)]
#[CoversMethod(WatchSnapshot::class, 'changeAgainst')]
#[CoversMethod(WatchSnapshot::class, 'isEmpty')]
#[CoversMethod(WatchSnapshot::class, 'signatures')]
#[CoversClass(WatchChange::class)]
#[CoversMethod(WatchChange::class, 'describe')]
#[CoversMethod(WatchChange::class, 'everything')]
#[CoversMethod(WatchChange::class, 'isEmpty')]
#[CoversMethod(WatchChange::class, 'merge')]
#[CoversMethod(WatchChange::class, 'none')]
#[CoversMethod(WatchChange::class, 'paths')]
#[CoversMethod(WatchChange::class, 'projects')]
final class WatchSnapshotTest extends TestCase
{
    #[Test]
    public function aChangedGlobalFileInvalidatesEverything(): void
    {
        $before = $this->snapshot(['/org/a/page.adoc' => '1', '/site.json' => '1'], ['/org/a/page.adoc' => 'a']);
        $after  = $this->snapshot(['/org/a/page.adoc' => '1', '/site.json' => '2'], ['/org/a/page.adoc' => 'a']);

        $change = $before->changeAgainst($after);

        static::assertTrue($change->everything());
        static::assertSame(['/site.json'], $change->paths());
        static::assertSame([], $change->projects());
    }

    #[Test]
    public function aChangedThemeAssetInvalidatesEverything(): void
    {
        $before = $this->snapshot(['/resources/css/site.css' => '1'], []);
        $after  = $this->snapshot(['/resources/css/site.css' => '2'], []);

        static::assertTrue($before->changeAgainst($after)->everything());
    }

    #[Test]
    public function aDeletedFileIsDetected(): void
    {
        $before = $this->snapshot(['/org/a/gone.adoc' => '1'], ['/org/a/gone.adoc' => 'a']);
        $after  = $this->snapshot([], []);

        $change = $before->changeAgainst($after);

        static::assertSame(['/org/a/gone.adoc'], $change->paths());
        static::assertSame(['a'], $change->projects());
    }

    #[Test]
    public function aDeletedGlobalFileInvalidatesEverything(): void
    {
        $before = $this->snapshot(['/site.json' => '1'], []);
        $after  = $this->snapshot([], []);

        static::assertTrue($before->changeAgainst($after)->everything());
    }

    #[Test]
    public function aModifiedFileNamesItsOwningProject(): void
    {
        $before = $this->snapshot(['/org/a/page.adoc' => '1'], ['/org/a/page.adoc' => 'a']);
        $after  = $this->snapshot(['/org/a/page.adoc' => '2'], ['/org/a/page.adoc' => 'a']);

        $change = $before->changeAgainst($after);

        static::assertSame(['/org/a/page.adoc'], $change->paths());
        static::assertSame(['a'], $change->projects());
        static::assertFalse($change->everything());
    }

    #[Test]
    public function anAddedFileIsDetected(): void
    {
        $before = $this->snapshot([], []);
        $after  = $this->snapshot(['/org/a/new.adoc' => '1'], ['/org/a/new.adoc' => 'a']);

        $change = $before->changeAgainst($after);

        static::assertSame(['/org/a/new.adoc'], $change->paths());
        static::assertSame(['a'], $change->projects());
    }

    #[Test]
    public function anEmptySnapshotReportsItselfEmpty(): void
    {
        static::assertTrue($this->snapshot([], [])->isEmpty());
        static::assertFalse($this->snapshot(['/a' => '1'], [])->isEmpty());
    }

    #[Test]
    public function anIdenticalSnapshotReportsNoChange(): void
    {
        $snapshot = $this->snapshot(['/org/a/page.adoc' => '1', '/site.json' => '1'], ['/org/a/page.adoc' => 'a']);

        $change = $snapshot->changeAgainst($snapshot);

        static::assertTrue($change->isEmpty());
        static::assertSame([], $change->paths());
        static::assertSame([], $change->projects());
        static::assertFalse($change->everything());
    }

    #[Test]
    public function describeNamesTheProjects(): void
    {
        static::assertSame('1 file(s) changed, rebuilding a', new WatchChange(['/org/a/p.adoc'], ['a'])->describe());
        static::assertSame('0 file(s) changed, rebuilding no project', WatchChange::none()->describe());
        static::assertSame(
            '2 file(s) changed, rebuilding every project',
            new WatchChange(['/a', '/b'], [], true)->describe(),
        );
    }

    #[Test]
    public function mergingAnEverythingChangeStaysEverything(): void
    {
        $merged = new WatchChange(['/a'], ['a'])->merge(new WatchChange(['/site.json'], [], true));

        static::assertTrue($merged->everything());
    }

    #[Test]
    public function mergingKeepsEveryPathAndProjectOnce(): void
    {
        $first  = new WatchChange(['/org/a/one.adoc'], ['a']);
        $second = new WatchChange(['/org/a/one.adoc', '/org/b/two.adoc'], ['a', 'b']);

        $merged = $first->merge($second);

        static::assertSame(['/org/a/one.adoc', '/org/b/two.adoc'], $merged->paths());
        static::assertSame(['a', 'b'], $merged->projects());
        static::assertFalse($merged->everything());
    }

    #[Test]
    public function noneIsEmpty(): void
    {
        static::assertTrue(WatchChange::none()->isEmpty());
    }

    #[Test]
    public function severalProjectsAreReportedOnceEachAndSorted(): void
    {
        $before = $this->snapshot(['/org/b/p.adoc' => '1', '/org/a/p.adoc' => '1'], [
            '/org/b/p.adoc' => 'b',
            '/org/a/p.adoc' => 'a',
        ]);
        $after = $this->snapshot(['/org/b/p.adoc' => '2', '/org/a/p.adoc' => '2'], [
            '/org/b/p.adoc' => 'b',
            '/org/a/p.adoc' => 'a',
        ]);

        $change = $before->changeAgainst($after);

        static::assertSame(['a', 'b'], $change->projects());
        static::assertSame(['/org/a/p.adoc', '/org/b/p.adoc'], $change->paths());
    }

    #[Test]
    public function theSignaturesAreExposed(): void
    {
        static::assertSame(['/a' => '1'], $this->snapshot(['/a' => '1'], [])->signatures());
    }

    /**
     * @param array<string, string> $signatures
     * @param array<string, string> $owners
     */
    private function snapshot(array $signatures, array $owners): WatchSnapshot
    {
        return new WatchSnapshot($signatures, $owners);
    }
}
