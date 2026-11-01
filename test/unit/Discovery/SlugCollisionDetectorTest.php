<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhpMultiRepo\Discovery\SlugCollisionDetector;
use Webware\AsciidocPhpMultiRepo\Discovery\SlugException;

#[CoversClass(SlugCollisionDetector::class)]
#[CoversMethod(SlugCollisionDetector::class, 'detect')]
final class SlugCollisionDetectorTest extends TestCase
{
    public function testACollisionNamesBothRepositoriesAndTheSlug(): void
    {
        try {
            new SlugCollisionDetector()->detect([
                new DiscoveredRepository('message-bus', 'message-bus'),
                new DiscoveredRepository('message.bus', 'message-bus'),
            ]);

            self::fail('Expected a SlugException.');
        } catch (SlugException $exception) {
            self::assertStringContainsString('message-bus', $exception->getMessage());
            self::assertStringContainsString('message.bus', $exception->getMessage());
            self::assertStringContainsString('"message-bus"', $exception->getMessage());
        }
    }

    public function testAnEmptySetPasses(): void
    {
        $this->expectNotToPerformAssertions();

        new SlugCollisionDetector()->detect([]);
    }

    public function testDistinctSlugsPass(): void
    {
        $this->expectNotToPerformAssertions();

        new SlugCollisionDetector()->detect([
            new DiscoveredRepository('message-bus', 'message-bus'),
            new DiscoveredRepository('job-queue', 'job-queue'),
        ]);
    }

    public function testTheFirstDuplicateIsReported(): void
    {
        try {
            new SlugCollisionDetector()->detect([
                new DiscoveredRepository('alpha', 'shared'),
                new DiscoveredRepository('beta', 'shared'),
                new DiscoveredRepository('gamma', 'other'),
            ]);

            self::fail('Expected a SlugException.');
        } catch (SlugException $exception) {
            self::assertStringContainsString('alpha', $exception->getMessage());
            self::assertStringContainsString('beta', $exception->getMessage());
            self::assertStringNotContainsString('gamma', $exception->getMessage());
        }
    }

    public function testTheMessageNamesTheRemedy(): void
    {
        try {
            new SlugCollisionDetector()->detect([
                new DiscoveredRepository('message-bus', 'message-bus'),
                new DiscoveredRepository('message.bus', 'message-bus'),
            ]);

            self::fail('Expected a SlugException.');
        } catch (SlugException $exception) {
            self::assertStringContainsString('projects.message.bus.slug', $exception->getMessage());
        }
    }

    public function testThreeRepositoriesSharingASlugStillRaise(): void
    {
        $this->expectException(SlugException::class);

        new SlugCollisionDetector()->detect([
            new DiscoveredRepository('alpha', 'shared'),
            new DiscoveredRepository('beta', 'shared'),
            new DiscoveredRepository('gamma', 'shared'),
        ]);
    }
}
