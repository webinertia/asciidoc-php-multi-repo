<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Cli\Version;

use function preg_match;
use function sprintf;

#[CoversClass(Version::class)]
final class VersionTest extends TestCase
{
    public function testCurrentReturnsAReleaseShapedString(): void
    {
        $version = Version::current();

        self::assertNotSame('', $version);
        self::assertSame(
            1,
            preg_match('/^[0-9]+\.[0-9]+\.[0-9]+/', $version),
            sprintf('Version "%s" must start with a three part release number.', $version),
        );
    }

    public function testFallbackMatchesTheReportSchemaPattern(): void
    {
        self::assertSame(1, preg_match('/^[0-9]+\.[0-9]+\.[0-9]+/', Version::FALLBACK));
    }

    public function testPackageNameMatchesComposer(): void
    {
        self::assertSame('webware/asciidoc-php-multi-repo', Version::PACKAGE);
    }
}
