<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Discovery\SlugException;
use Webware\AsciidocPhp\MultiRepo\Discovery\SlugNormalizer;

use function array_unique;

#[CoversClass(SlugNormalizer::class)]
#[CoversMethod(SlugNormalizer::class, 'normalise')]
final class SlugNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function equivalentNames(): iterable
    {
        yield 'plain' => ['message-bus', 'message-bus'];
        yield 'underscore' => ['Message_Bus', 'message-bus'];
        yield 'dot' => ['message.bus', 'message-bus'];
        yield 'double hyphen' => ['message--bus', 'message-bus'];
        yield 'camel case' => ['MessageBus', 'messagebus'];
        yield 'space' => ['Message Bus', 'message-bus'];
        yield 'surrounding' => ['-message-bus-', 'message-bus'];
        yield 'mixed junk' => ['Message_Bus.v2', 'message-bus-v2'];
        yield 'digits' => ['V2Docs', 'v2docs'];
        yield 'already a slug' => ['asciidoc-php', 'asciidoc-php'];
    }

    #[Test]
    public function aNameWithNoUsableCharacterIsRejected(): void
    {
        try {
            new SlugNormalizer()->normalise('___');

            self::fail('Expected a SlugException.');
        } catch (SlugException $exception) {
            static::assertStringContainsString('___', $exception->getMessage());
            static::assertStringContainsString('slug', $exception->getMessage());
        }
    }

    #[Test]
    public function anEmptyNameIsRejected(): void
    {
        $this->expectException(SlugException::class);

        new SlugNormalizer()->normalise('');
    }

    #[Test]
    public function aNonAsciiNameCollapsesToSeparators(): void
    {
        static::assertSame('na-ve', new SlugNormalizer()->normalise('naïve'));
    }

    #[Test]
    public function punctuationVariantsCannotMoveTheUrl(): void
    {
        $normalizer = new SlugNormalizer();
        $slugs      = [
            $normalizer->normalise('Message_Bus'),
            $normalizer->normalise('message.bus'),
            $normalizer->normalise('message--bus'),
            $normalizer->normalise('MESSAGE BUS'),
        ];

        static::assertCount(1, array_unique($slugs));
    }

    #[Test]
    #[DataProvider('equivalentNames')]
    public function theNameIsReducedToALowercaseHyphenatedSlug(string $name, string $expected): void
    {
        static::assertSame($expected, new SlugNormalizer()->normalise($name));
    }

    #[Test]
    public function theResultContainsOnlyLowercaseAsciiAndHyphens(): void
    {
        static::assertMatchesRegularExpression(
            '/^[a-z0-9]+(-[a-z0-9]+)*$/',
            new SlugNormalizer()->normalise('Webware.Asciidoc PHP_Multi Repo'),
        );
    }

    #[Test]
    public function theResultNeverStartsWithOrEndsWithAHyphen(): void
    {
        $slug = new SlugNormalizer()->normalise('__Message_Bus__');

        static::assertSame('message-bus', $slug);
        static::assertStringStartsNotWith('-', $slug);
        static::assertStringEndsNotWith('-', $slug);
    }
}
