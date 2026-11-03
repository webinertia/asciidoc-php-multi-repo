<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Config\ProjectOverride;

#[CoversClass(ProjectOverride::class)]
final class ProjectOverrideTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function singleFields(): array
    {
        return [
            'accent'      => [['accent' => '#05a578']],
            'description' => [['description' => 'Message bus abstractions.']],
            'order'       => [['order' => 10]],
            'slug'        => [['slug' => 'message-bus']],
            'title'       => [['title' => 'Message Bus']],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('singleFields')]
    public function aSingleFieldMakesTheOverrideNonEmpty(array $data): void
    {
        static::assertFalse(ProjectOverride::fromArray($data)->isEmpty());
    }

    #[Test]
    public function emptyIsEmpty(): void
    {
        static::assertTrue(ProjectOverride::empty()->isEmpty());
    }

    #[Test]
    public function fromArrayReadsEveryField(): void
    {
        $override = ProjectOverride::fromArray([
            'slug'        => 'message-bus',
            'title'       => 'Message Bus',
            'description' => 'Message bus abstractions.',
            'accent'      => '#05a578',
            'order'       => 10,
        ]);

        static::assertSame('message-bus', $override->slug());
        static::assertSame('Message Bus', $override->title());
        static::assertSame('Message bus abstractions.', $override->description());
        static::assertSame('#05a578', $override->accent());
        static::assertSame(10, $override->order());
        static::assertFalse($override->isEmpty());
    }

    #[Test]
    public function fromArrayWithoutFieldsIsEmpty(): void
    {
        $override = ProjectOverride::fromArray([]);

        static::assertNull($override->slug());
        static::assertNull($override->title());
        static::assertNull($override->description());
        static::assertNull($override->accent());
        static::assertNull($override->order());
        static::assertTrue($override->isEmpty());
    }
}
