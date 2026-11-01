<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Discovery\OnboardValue;

#[CoversClass(OnboardValue::class)]
#[CoversMethod(OnboardValue::class, 'matches')]
final class OnboardValueTest extends TestCase
{
    public function testABooleanTrueMatchesTheConfiguredString(): void
    {
        self::assertTrue(OnboardValue::matches(true, 'true'));
        self::assertFalse(OnboardValue::matches(true, 'yes'));
    }

    public function testANonScalarValueNeverMatches(): void
    {
        self::assertFalse(OnboardValue::matches(null, 'yes'));
        self::assertFalse(OnboardValue::matches(['yes'], 'yes'));
    }

    public function testAValueIsComparedCaseInsensitivelyAndTrimmed(): void
    {
        self::assertTrue(OnboardValue::matches('  Yes  ', 'yes'));
    }

    public function testTheConfiguredValueIsTrimmedAndLowercased(): void
    {
        self::assertTrue(OnboardValue::matches('yes', ' YES '));
    }
}
