<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhp\MultiRepo\Discovery\SlugCollisionDetector;
use Webware\AsciidocPhp\MultiRepo\Discovery\SlugException;

#[CoversClass(SlugCollisionDetector::class)]
#[CoversMethod(SlugCollisionDetector::class, 'detect')]
final class SlugCollisionDetectorTest extends TestCase
{
    #[Test]
    public function aCollisionNamesBothRepositoriesAndTheSlug(): void
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

    #[Test]
    public function anEmptySetPasses(): void
    {
        $this->expectNotToPerformAssertions();

        new SlugCollisionDetector()->detect([]);
    }

    #[Test]
    public function distinctSlugsPass(): void
    {
        $this->expectNotToPerformAssertions();

        new SlugCollisionDetector()->detect([
            new DiscoveredRepository('message-bus', 'message-bus'),
            new DiscoveredRepository('job-queue', 'job-queue'),
        ]);
    }

    #[Test]
    public function theFirstDuplicateIsReported(): void
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

    #[Test]
    public function theMessageNamesTheRemedy(): void
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

    #[Test]
    public function threeRepositoriesSharingASlugStillRaise(): void
    {
        $this->expectException(SlugException::class);

        new SlugCollisionDetector()->detect([
            new DiscoveredRepository('alpha', 'shared'),
            new DiscoveredRepository('beta', 'shared'),
            new DiscoveredRepository('gamma', 'shared'),
        ]);
    }
}
