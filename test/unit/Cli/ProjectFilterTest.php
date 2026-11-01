<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\ProjectFilter;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;

#[CoversClass(ProjectFilter::class)]
#[CoversMethod(ProjectFilter::class, 'apply')]
final class ProjectFilterTest extends TestCase
{
    public function testAnUnrelatedProjectIsDropped(): void
    {
        $repositories = [SourceFixture::repository('message-bus', 'bus')];

        self::assertSame([], ProjectFilter::apply($repositories, new Options(projects: ['other'])));
    }

    public function testAProjectIsMatchedByItsName(): void
    {
        $repositories = [SourceFixture::repository('message-bus', 'bus')];

        $filtered = ProjectFilter::apply($repositories, new Options(projects: ['message-bus']));

        self::assertCount(1, $filtered);
        self::assertSame('bus', $filtered[0]->slug());
    }

    public function testAProjectIsMatchedByItsSlug(): void
    {
        $repositories = [SourceFixture::repository('message-bus', 'bus')];

        $filtered = ProjectFilter::apply($repositories, new Options(projects: ['bus']));

        self::assertCount(1, $filtered);
        self::assertSame('message-bus', $filtered[0]->name());
    }

    public function testWithoutAFilterEveryProjectIsKept(): void
    {
        $repositories = [
            SourceFixture::repository('message-bus', 'bus'),
            SourceFixture::repository('versioned', 'versioned'),
        ];

        self::assertCount(2, ProjectFilter::apply($repositories, new Options()));
    }
}
