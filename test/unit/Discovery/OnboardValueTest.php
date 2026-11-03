<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Discovery\OnboardValue;

#[CoversClass(OnboardValue::class)]
#[CoversMethod(OnboardValue::class, 'matches')]
final class OnboardValueTest extends TestCase
{
    #[Test]
    public function aBooleanTrueMatchesTheConfiguredString(): void
    {
        self::assertTrue(OnboardValue::matches(true, 'true'));
        self::assertFalse(OnboardValue::matches(true, 'yes'));
    }

    #[Test]
    public function aNonScalarValueNeverMatches(): void
    {
        self::assertFalse(OnboardValue::matches(null, 'yes'));
        self::assertFalse(OnboardValue::matches(['yes'], 'yes'));
    }

    #[Test]
    public function aValueIsComparedCaseInsensitivelyAndTrimmed(): void
    {
        self::assertTrue(OnboardValue::matches('  Yes  ', 'yes'));
    }

    #[Test]
    public function theConfiguredValueIsTrimmedAndLowercased(): void
    {
        self::assertTrue(OnboardValue::matches('yes', ' YES '));
    }
}
