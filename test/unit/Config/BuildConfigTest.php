<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\BuildConfig;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function sys_get_temp_dir;

#[CoversClass(BuildConfig::class)]
final class BuildConfigTest extends TestCase
{
    private static function siteConfig(): SiteConfig
    {
        return SiteConfig::fromArray(
            [
                'schemaVersion' => 1,
                'site'          => ['name' => 'Docs', 'baseUrl' => 'https://example.test'],
                'organization'  => ['name' => 'webinertia', 'propertyName' => 'doc-target'],
                'render'        => ['concurrency' => 4],
            ],
            Path::join(sys_get_temp_dir(), 'site-fixture', 'site.json'),
        );
    }

    #[Test]
    public function absoluteOverridePathsAreKept(): void
    {
        $absolute = Path::join(sys_get_temp_dir(), 'absolute', 'site');

        $build = BuildConfig::fromSiteConfig(self::siteConfig())
            ->withOverrides(
                outputDirectory : $absolute,
                workingDirectory: sys_get_temp_dir(),
            );

        static::assertSame(Path::normalise($absolute), $build->outputDirectory());
    }

    #[Test]
    public function fromSiteConfigCopiesResolvedValues(): void
    {
        $site  = self::siteConfig();
        $build = BuildConfig::fromSiteConfig($site);

        static::assertSame($site->output()->directory(), $build->outputDirectory());
        static::assertSame($site->output()->cacheDirectory(), $build->cacheDirectory());
        static::assertSame($site->output()->reportPath(), $build->reportPath());
        static::assertSame(4, $build->concurrency());
        static::assertSame('/', $build->basePath());
        static::assertFalse($build->full());
    }

    #[Test]
    public function withOverridesAppliesEveryOverride(): void
    {
        $working = Path::join(sys_get_temp_dir(), 'work');

        $build = BuildConfig::fromSiteConfig(self::siteConfig())->withOverrides(
            outputDirectory : 'out',
            cacheDirectory  : 'cache2',
            reportPath      : 'report2.json',
            concurrency     : 8,
            basePath        : '/manual',
            full            : true,
            workingDirectory: $working,
        );

        static::assertSame(Path::join($working, 'out'), $build->outputDirectory());
        static::assertSame(Path::join($working, 'cache2'), $build->cacheDirectory());
        static::assertSame(Path::join($working, 'report2.json'), $build->reportPath());
        static::assertSame(8, $build->concurrency());
        static::assertSame('/manual/', $build->basePath());
        static::assertTrue($build->full());
    }

    #[Test]
    public function withOverridesKeepsValuesWhenNotSupplied(): void
    {
        $original = BuildConfig::fromSiteConfig(self::siteConfig());
        $updated  = $original->withOverrides(workingDirectory: sys_get_temp_dir());

        static::assertSame($original->outputDirectory(), $updated->outputDirectory());
        static::assertSame($original->cacheDirectory(), $updated->cacheDirectory());
        static::assertSame($original->reportPath(), $updated->reportPath());
        static::assertSame($original->concurrency(), $updated->concurrency());
        static::assertSame($original->basePath(), $updated->basePath());
        static::assertSame($original->full(), $updated->full());
    }
}
