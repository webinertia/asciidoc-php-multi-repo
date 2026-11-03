<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\SourceSection;

#[CoversClass(SourceSection::class)]
final class SourceSectionTest extends TestCase
{
    #[Test]
    public function fromArrayAppliesDefaults(): void
    {
        $section = SourceSection::fromArray([]);

        static::assertSame('docs', $section->directory());
        static::assertTrue($section->defaultBranchOnly());
        static::assertSame(['index.adoc', 'README.adoc'], $section->entryCandidates());
        static::assertSame(['.adoc', '.asciidoc'], $section->extensions());
        static::assertSame('^v[1-9][0-9]*$', $section->versionPattern());
        static::assertSame(['**/_*.adoc', '**/_*.asciidoc'], $section->excludePatterns());
    }

    #[Test]
    public function fromArrayReadsEveryField(): void
    {
        $section = SourceSection::fromArray([
            'directory'         => 'documentation',
            'defaultBranchOnly' => false,
            'entryCandidates'   => ['README.adoc'],
            'extensions'        => ['.adoc'],
            'versionPattern'    => '^release-[0-9]+$',
            'excludePatterns'   => ['drafts/**'],
        ]);

        static::assertSame('documentation', $section->directory());
        static::assertFalse($section->defaultBranchOnly());
        static::assertSame(['README.adoc'], $section->entryCandidates());
        static::assertSame(['.adoc'], $section->extensions());
        static::assertSame('^release-[0-9]+$', $section->versionPattern());
        static::assertSame(['drafts/**'], $section->excludePatterns());
    }
}
