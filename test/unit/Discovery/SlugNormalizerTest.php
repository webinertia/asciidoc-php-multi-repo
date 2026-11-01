<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Discovery\SlugException;
use Webware\AsciidocPhpMultiRepo\Discovery\SlugNormalizer;

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

    public function testANameWithNoUsableCharacterIsRejected(): void
    {
        try {
            new SlugNormalizer()->normalise('___');

            self::fail('Expected a SlugException.');
        } catch (SlugException $exception) {
            self::assertStringContainsString('___', $exception->getMessage());
            self::assertStringContainsString('slug', $exception->getMessage());
        }
    }

    public function testAnEmptyNameIsRejected(): void
    {
        $this->expectException(SlugException::class);

        new SlugNormalizer()->normalise('');
    }

    public function testANonAsciiNameCollapsesToSeparators(): void
    {
        self::assertSame('na-ve', new SlugNormalizer()->normalise('naïve'));
    }

    public function testPunctuationVariantsCannotMoveTheUrl(): void
    {
        $normalizer = new SlugNormalizer();
        $slugs      = [
            $normalizer->normalise('Message_Bus'),
            $normalizer->normalise('message.bus'),
            $normalizer->normalise('message--bus'),
            $normalizer->normalise('MESSAGE BUS'),
        ];

        self::assertCount(1, array_unique($slugs));
    }

    #[DataProvider('equivalentNames')]
    public function testTheNameIsReducedToALowercaseHyphenatedSlug(string $name, string $expected): void
    {
        self::assertSame($expected, new SlugNormalizer()->normalise($name));
    }

    public function testTheResultContainsOnlyLowercaseAsciiAndHyphens(): void
    {
        self::assertMatchesRegularExpression(
            '/^[a-z0-9]+(-[a-z0-9]+)*$/',
            new SlugNormalizer()->normalise('Webware.Asciidoc PHP_Multi Repo'),
        );
    }

    public function testTheResultNeverStartsWithOrEndsWithAHyphen(): void
    {
        $slug = new SlugNormalizer()->normalise('__Message_Bus__');

        self::assertSame('message-bus', $slug);
        self::assertStringStartsNotWith('-', $slug);
        self::assertStringEndsNotWith('-', $slug);
    }
}
