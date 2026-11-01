<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Config;

use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Config\BuildConfig;
use Webware\AsciidocPhpMultiRepo\Config\SiteConfig;
use Webware\AsciidocPhpMultiRepo\Support\Path;

use function sys_get_temp_dir;

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

    public function testFromSiteConfigCopiesResolvedValues(): void
    {
        $site  = self::siteConfig();
        $build = BuildConfig::fromSiteConfig($site);

        self::assertSame($site->output()->directory(), $build->outputDirectory());
        self::assertSame($site->output()->cacheDirectory(), $build->cacheDirectory());
        self::assertSame($site->output()->reportPath(), $build->reportPath());
        self::assertSame(4, $build->concurrency());
        self::assertSame('/', $build->basePath());
        self::assertFalse($build->full());
    }

    public function testWithOverridesAppliesEveryOverride(): void
    {
        $working = Path::join(sys_get_temp_dir(), 'work');

        $build = BuildConfig::fromSiteConfig(self::siteConfig())->withOverrides(
            outputDirectory: 'out',
            cacheDirectory: 'cache2',
            reportPath: 'report2.json',
            concurrency: 8,
            basePath: '/manual',
            full: true,
            workingDirectory: $working,
        );

        self::assertSame(Path::join($working, 'out'), $build->outputDirectory());
        self::assertSame(Path::join($working, 'cache2'), $build->cacheDirectory());
        self::assertSame(Path::join($working, 'report2.json'), $build->reportPath());
        self::assertSame(8, $build->concurrency());
        self::assertSame('/manual/', $build->basePath());
        self::assertTrue($build->full());
    }

    public function testWithOverridesKeepsValuesWhenNotSupplied(): void
    {
        $original = BuildConfig::fromSiteConfig(self::siteConfig());
        $updated  = $original->withOverrides(workingDirectory: sys_get_temp_dir());

        self::assertSame($original->outputDirectory(), $updated->outputDirectory());
        self::assertSame($original->cacheDirectory(), $updated->cacheDirectory());
        self::assertSame($original->reportPath(), $updated->reportPath());
        self::assertSame($original->concurrency(), $updated->concurrency());
        self::assertSame($original->basePath(), $updated->basePath());
        self::assertSame($original->full(), $updated->full());
    }

    public function testAbsoluteOverridePathsAreKept(): void
    {
        $absolute = Path::join(sys_get_temp_dir(), 'absolute', 'site');

        $build = BuildConfig::fromSiteConfig(self::siteConfig())
            ->withOverrides(outputDirectory: $absolute, workingDirectory: sys_get_temp_dir());

        self::assertSame(Path::normalise($absolute), $build->outputDirectory());
    }
}
