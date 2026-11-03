<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\ProjectFilter;
use WebwareTest\AsciidocPhp\MultiRepo\Support\SourceFixture;

#[CoversClass(ProjectFilter::class)]
#[CoversMethod(ProjectFilter::class, 'apply')]
final class ProjectFilterTest extends TestCase
{
    #[Test]
    public function anUnrelatedProjectIsDropped(): void
    {
        $repositories = [SourceFixture::repository('message-bus', 'bus')];

        static::assertSame([], ProjectFilter::apply($repositories, new Options(projects: ['other'])));
    }

    #[Test]
    public function aProjectIsMatchedByItsName(): void
    {
        $repositories = [SourceFixture::repository('message-bus', 'bus')];

        $filtered = ProjectFilter::apply($repositories, new Options(projects: ['message-bus']));

        static::assertCount(1, $filtered);
        static::assertSame('bus', $filtered[0]->slug());
    }

    #[Test]
    public function aProjectIsMatchedByItsSlug(): void
    {
        $repositories = [SourceFixture::repository('message-bus', 'bus')];

        $filtered = ProjectFilter::apply($repositories, new Options(projects: ['bus']));

        static::assertCount(1, $filtered);
        static::assertSame('message-bus', $filtered[0]->name());
    }

    #[Test]
    public function withoutAFilterEveryProjectIsKept(): void
    {
        $repositories = [
            SourceFixture::repository('message-bus', 'bus'),
            SourceFixture::repository('versioned', 'versioned'),
        ];

        static::assertCount(2, ProjectFilter::apply($repositories, new Options()));
    }
}
