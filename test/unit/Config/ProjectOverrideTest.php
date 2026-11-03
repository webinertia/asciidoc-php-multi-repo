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
        self::assertFalse(ProjectOverride::fromArray($data)->isEmpty());
    }

    #[Test]
    public function emptyIsEmpty(): void
    {
        self::assertTrue(ProjectOverride::empty()->isEmpty());
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

        self::assertSame('message-bus', $override->slug());
        self::assertSame('Message Bus', $override->title());
        self::assertSame('Message bus abstractions.', $override->description());
        self::assertSame('#05a578', $override->accent());
        self::assertSame(10, $override->order());
        self::assertFalse($override->isEmpty());
    }

    #[Test]
    public function fromArrayWithoutFieldsIsEmpty(): void
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
