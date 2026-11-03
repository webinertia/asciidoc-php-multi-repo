<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Theme\AssetHasher;

use function hash;
use function strlen;
use function substr;

/**
 * T036: the content-addressed asset name.
 *
 * The name is the leading characters of the content digest, so the assertion recomputes the digest
 * here rather than pinning a literal that a stylesheet edit would break.
 */
#[CoversClass(AssetHasher::class)]
#[CoversMethod(AssetHasher::class, 'hash')]
#[CoversMethod(AssetHasher::class, 'hashedName')]
final class AssetHasherTest extends TestCase
{
    private const string CONTENTS = 'body { color: #7e5ae0; }';

    #[Test]
    public function aShortHashLengthIsHonoured(): void
    {
        $hasher = new AssetHasher(1);

        static::assertSame(substr(hash('sha256', self::CONTENTS), offset: 0, length: 1), $hasher->hash(self::CONTENTS));
        static::assertSame(1, strlen($hasher->hash(self::CONTENTS)));
    }

    #[Test]
    public function differentContentsProduceDifferentHashes(): void
    {
        $hasher = new AssetHasher();

        static::assertSame($hasher->hash('a'), $hasher->hash('a'));
        static::assertNotSame($hasher->hash('a'), $hasher->hash('b'));
    }

    #[Test]
    public function theHashIsSixCharactersByDefault(): void
    {
        static::assertSame(6, strlen(new AssetHasher()->hash(self::CONTENTS)));
    }

    #[Test]
    public function theHashIsTheLeadingCharactersOfTheContentDigest(): void
    {
        static::assertSame(
            substr(hash('sha256', self::CONTENTS), offset: 0, length: 6),
            new AssetHasher()->hash(self::CONTENTS),
        );
    }

    #[Test]
    public function thePublishedNameCarriesTheBaseNameTheHashAndTheExtension(): void
    {
        $hasher = new AssetHasher();

        static::assertSame(
            "site.{$hasher->hash(self::CONTENTS)}.css",
            $hasher->hashedName('site', 'css', self::CONTENTS),
        );
    }
}
