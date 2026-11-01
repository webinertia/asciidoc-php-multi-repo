<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Config\ProjectOverride;

#[CoversClass(ProjectOverride::class)]
final class ProjectOverrideTest extends TestCase
{
    public function testEmptyIsEmpty(): void
    {
        self::assertTrue(ProjectOverride::empty()->isEmpty());
    }

    public function testFromArrayReadsEveryField(): void
    {
        $override = ProjectOverride::fromArray([
            'slug'        => 'message-bus',
            'title'       => 'Message Bus',
            'description' => 'Message bus abstractions.',
            'accent'      => '#05a578',
            'order'       => 10,
        ]);

        self::assertSame('message-bus', $override->slug());
        self::assertSame('Message Bus', $override->title());
        self::assertSame('Message bus abstractions.', $override->description());
        self::assertSame('#05a578', $override->accent());
        self::assertSame(10, $override->order());
        self::assertFalse($override->isEmpty());
    }

    public function testFromArrayWithoutFieldsIsEmpty(): void
    {
        $override = ProjectOverride::fromArray([]);

        self::assertNull($override->slug());
        self::assertNull($override->title());
        self::assertNull($override->description());
        self::assertNull($override->accent());
        self::assertNull($override->order());
        self::assertTrue($override->isEmpty());
    }
}
