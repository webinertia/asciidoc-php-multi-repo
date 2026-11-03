<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Version;

use function preg_match;
use function sprintf;

#[CoversClass(Version::class)]
final class VersionTest extends TestCase
{
    #[Test]
    public function currentReturnsAReleaseShapedString(): void
    {
        $version = Version::current();

        static::assertNotSame('', $version);
        static::assertSame(
            1,
            preg_match('/^[0-9]+\.[0-9]+\.[0-9]+/', $version),
            sprintf('Version "%s" must start with a three part release number.', $version),
        );
    }

    #[Test]
    public function fallbackMatchesTheReportSchemaPattern(): void
    {
        static::assertSame(1, preg_match('/^[0-9]+\.[0-9]+\.[0-9]+/', Version::FALLBACK));
    }

    #[Test]
    public function packageNameMatchesComposer(): void
    {
        static::assertSame('webware/asciidoc-php-multi-repo', Version::PACKAGE);
    }
}
