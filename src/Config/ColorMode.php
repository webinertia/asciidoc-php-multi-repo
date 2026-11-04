<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Config;

/**
 * The configured colour mode and the mode painted before the toggle script runs.
 *
 * The two values are always consumed together, and keeping them as a pair stops a caller from
 * passing the fallback where the preference belongs.
 */
final readonly class ColorMode
{
    public const string SYSTEM = 'system';

    public const string DARK = 'dark';

    public function __construct(
        private string $mode = self::SYSTEM,
        private string $fallback = self::DARK,
    ) {}

    /**
     * The mode painted before the toggle script runs.
     */
    public function fallback(): string
    {
        return $this->fallback;
    }

    /**
     * The configured mode: `light`, `dark`, or `system` when the OS preference decides.
     */
    public function mode(): string
    {
        return $this->mode;
    }
}
