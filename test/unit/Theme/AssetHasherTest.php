<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
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

    public function testAShortHashLengthIsHonoured(): void
    {
        $hasher = new AssetHasher(1);

        self::assertSame(substr(hash('sha256', self::CONTENTS), 0, 1), $hasher->hash(self::CONTENTS));
        self::assertSame(1, strlen($hasher->hash(self::CONTENTS)));
    }

    public function testDifferentContentsProduceDifferentHashes(): void
    {
        $hasher = new AssetHasher();

        self::assertSame($hasher->hash('a'), $hasher->hash('a'));
        self::assertNotSame($hasher->hash('a'), $hasher->hash('b'));
    }

    public function testTheHashIsSixCharactersByDefault(): void
    {
        self::assertSame(6, strlen(new AssetHasher()->hash(self::CONTENTS)));
    }

    public function testTheHashIsTheLeadingCharactersOfTheContentDigest(): void
    {
        self::assertSame(
            substr(hash('sha256', self::CONTENTS), 0, 6),
            new AssetHasher()->hash(self::CONTENTS),
        );
    }

    public function testThePublishedNameCarriesTheBaseNameTheHashAndTheExtension(): void
    {
        $hasher = new AssetHasher();

        self::assertSame(
            "site.{$hasher->hash(self::CONTENTS)}.css",
            $hasher->hashedName('site', 'css', self::CONTENTS),
        );
    }
}
