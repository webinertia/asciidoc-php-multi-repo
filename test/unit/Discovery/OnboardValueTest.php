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
        static::assertTrue(OnboardValue::matches(true, 'true'));
        static::assertFalse(OnboardValue::matches(true, 'yes'));
    }

    #[Test]
    public function aNonScalarValueNeverMatches(): void
    {
        static::assertFalse(OnboardValue::matches(null, 'yes'));
        static::assertFalse(OnboardValue::matches(['yes'], 'yes'));
    }

    #[Test]
    public function aValueIsComparedCaseInsensitivelyAndTrimmed(): void
    {
        static::assertTrue(OnboardValue::matches('  Yes  ', 'yes'));
    }

    #[Test]
    public function theConfiguredValueIsTrimmedAndLowercased(): void
    {
        static::assertTrue(OnboardValue::matches('yes', ' YES '));
    }
}
