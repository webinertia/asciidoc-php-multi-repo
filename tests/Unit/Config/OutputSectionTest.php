<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Config\OutputSection;

use function sys_get_temp_dir;

#[CoversClass(OutputSection::class)]
final class OutputSectionTest extends TestCase
{
    public function testFromArrayAppliesDefaults(): void
    {
        $section = OutputSection::fromArray([]);

        self::assertSame('build/site', $section->directory());
        self::assertSame('.cache/multi-repo', $section->cacheDirectory());
        self::assertSame('build/report.json', $section->reportPath());
    }

    public function testFromArrayReadsEveryField(): void
    {
        $section = OutputSection::fromArray([
            'directory'      => 'public',
            'cacheDirectory' => 'cache',
            'reportPath'     => 'reports/report.json',
        ]);

        self::assertSame('public', $section->directory());
        self::assertSame('cache', $section->cacheDirectory());
        self::assertSame('reports/report.json', $section->reportPath());
    }

    public function testWithPathsReturnsANewInstance(): void
    {
        $section = OutputSection::fromArray([]);
        $updated = $section->withPaths(
            sys_get_temp_dir() . '/site',
            sys_get_temp_dir() . '/cache',
            sys_get_temp_dir() . '/report.json',
        );

        self::assertNotSame($section, $updated);
        self::assertSame(sys_get_temp_dir() . '/site', $updated->directory());
        self::assertSame(sys_get_temp_dir() . '/cache', $updated->cacheDirectory());
        self::assertSame(sys_get_temp_dir() . '/report.json', $updated->reportPath());
    }
}
