<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Config;

use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Config\SourceSection;

final class SourceSectionTest extends TestCase
{
    public function testFromArrayReadsEveryField(): void
    {
        $section = SourceSection::fromArray([
            'directory'         => 'documentation',
            'defaultBranchOnly' => false,
            'entryCandidates'   => ['README.adoc'],
            'extensions'        => ['.adoc'],
            'versionPattern'    => '^release-[0-9]+$',
            'excludePatterns'   => ['drafts/**'],
        ]);

        self::assertSame('documentation', $section->directory());
        self::assertFalse($section->defaultBranchOnly());
        self::assertSame(['README.adoc'], $section->entryCandidates());
        self::assertSame(['.adoc'], $section->extensions());
        self::assertSame('^release-[0-9]+$', $section->versionPattern());
        self::assertSame(['drafts/**'], $section->excludePatterns());
    }

    public function testFromArrayAppliesDefaults(): void
    {
        $section = SourceSection::fromArray([]);

        self::assertSame('docs', $section->directory());
        self::assertTrue($section->defaultBranchOnly());
        self::assertSame(['index.adoc', 'README.adoc'], $section->entryCandidates());
        self::assertSame(['.adoc', '.asciidoc'], $section->extensions());
        self::assertSame('^v[1-9][0-9]*$', $section->versionPattern());
        self::assertSame(['**/_*.adoc', '**/_*.asciidoc'], $section->excludePatterns());
    }
}
