<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Theme\ThemeResolver;

use function hexdec;
use function max;
use function min;
use function pow;
use function preg_match;
use function sprintf;
use function substr;

/**
 * T036: the light-mode link and muted-text tokens clear 4.5:1 against their backgrounds.
 *
 * The colours are read from the stylesheet through `ThemeResolver`, so the assertion covers the
 * bytes that ship rather than a copy of the palette kept in the test. The ratio is computed with the
 * WCAG 2.x relative luminance formula.
 */
#[CoversClass(ThemeResolver::class)]
#[CoversMethod(ThemeResolver::class, 'lightColors')]
final class ContrastTest extends TestCase
{
    private const float MINIMUM_RATIO = 4.5;

    #[Test]
    public function theBodyTextTokenClearsTheContrastMinimum(): void
    {
        $tokens = new ThemeResolver()->lightColors();

        self::assertGreaterThanOrEqual(
            self::MINIMUM_RATIO,
            $this->ratio($tokens['--text-body'], $tokens['--surface-page']),
            $this->describe('--text-body', $tokens['--text-body'], '--surface-page', $tokens['--surface-page']),
        );
    }

    #[Test]
    public function theLinkTokenClearsTheContrastMinimumOnBothSurfaces(): void
    {
        $tokens = new ThemeResolver()->lightColors();

        self::assertGreaterThanOrEqual(
            self::MINIMUM_RATIO,
            $this->ratio($tokens['--color-link'], $tokens['--surface-page']),
            $this->describe('--color-link', $tokens['--color-link'], '--surface-page', $tokens['--surface-page']),
        );

        self::assertGreaterThanOrEqual(
            self::MINIMUM_RATIO,
            $this->ratio($tokens['--color-link'], $tokens['--surface-raised']),
            $this->describe('--color-link', $tokens['--color-link'], '--surface-raised', $tokens['--surface-raised']),
        );
    }

    #[Test]
    public function theMutedTextTokenClearsTheContrastMinimumOnBothSurfaces(): void
    {
        $tokens = new ThemeResolver()->lightColors();

        self::assertGreaterThanOrEqual(
            self::MINIMUM_RATIO,
            $this->ratio($tokens['--text-muted'], $tokens['--surface-page']),
            $this->describe('--text-muted', $tokens['--text-muted'], '--surface-page', $tokens['--surface-page']),
        );

        self::assertGreaterThanOrEqual(
            self::MINIMUM_RATIO,
            $this->ratio($tokens['--text-muted'], $tokens['--surface-raised']),
            $this->describe('--text-muted', $tokens['--text-muted'], '--surface-raised', $tokens['--surface-raised']),
        );
    }

    private function describe(
        string $foregroundName,
        string $foreground,
        string $backgroundName,
        string $background,
    ): string {
        return sprintf(
            '%s (%s) against %s (%s) is %.2f:1, below %.1f:1.',
            $foregroundName,
            $foreground,
            $backgroundName,
            $background,
            $this->ratio($foreground, $background),
            self::MINIMUM_RATIO,
        );
    }

    private function luminance(string $colour): float
    {
        self::assertSame(1, preg_match('/^#([0-9a-fA-F]{6})$/', $colour), "Unexpected colour: {$colour}");

        $value    = (string) substr($colour, 1);
        $channels = [
            hexdec(substr($value, 0, 2)) / 255,
            hexdec(substr($value, 2, 2)) / 255,
            hexdec(substr($value, 4, 2)) / 255,
        ];

        $linear = [];
        foreach ($channels as $channel) {
            $linear[] = $channel <= 0.039_28
                ? $channel / 12.92
                : pow(($channel + 0.055) / 1.055, 2.4);
        }

        return (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
    }

    private function ratio(string $foreground, string $background): float
    {
        $first  = $this->luminance($foreground);
        $second = $this->luminance($background);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }
}
