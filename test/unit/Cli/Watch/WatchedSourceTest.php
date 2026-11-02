<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli\Watch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Watch\WatchedSource;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

#[CoversClass(WatchedSource::class)]
#[CoversMethod(WatchedSource::class, 'forProject')]
#[CoversMethod(WatchedSource::class, 'global')]
#[CoversMethod(WatchedSource::class, 'path')]
#[CoversMethod(WatchedSource::class, 'project')]
#[CoversMethod(WatchedSource::class, 'rebuildsEverything')]
final class WatchedSourceTest extends TestCase
{
    #[Test]
    public function aGlobalSourceRebuildsEverything(): void
    {
        $source = WatchedSource::global('/site/resources');

        static::assertNull($source->project());
        static::assertTrue($source->rebuildsEverything());
    }

    #[Test]
    public function aProjectSourceOwnsOneProject(): void
    {
        $source = WatchedSource::forProject('versioned', '/site/org/versioned');

        static::assertSame('versioned', $source->project());
        static::assertFalse($source->rebuildsEverything());
    }

    #[Test]
    public function aTrailingSeparatorIsTrimmed(): void
    {
        static::assertSame(
            Path::normalise('/site/org/versioned'),
            WatchedSource::forProject('versioned', '/site/org/versioned/')->path(),
        );
        static::assertSame(
            Path::normalise('/site/resources'),
            WatchedSource::global('/site/resources/')->path(),
        );
    }

    #[Test]
    public function theAccessorDoesNotShadowTheFactory(): void
    {
        $source = WatchedSource::forProject('mixed', '/site/org/mixed');

        static::assertSame('mixed', $source->project());
        static::assertSame(Path::normalise('/site/org/mixed'), $source->path());
    }

    #[Test]
    public function thePathIsNormalisedToThePlatformSeparator(): void
    {
        static::assertSame(
            Path::normalise('C:/site/org/versioned'),
            WatchedSource::forProject('versioned', 'C:\\site\\org\\versioned')->path(),
        );
    }
}
