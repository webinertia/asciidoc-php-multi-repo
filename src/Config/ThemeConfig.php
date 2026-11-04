<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Config;

/**
 * The `theme` section: the built-in token set and the accent used when a project has none.
 */
final readonly class ThemeConfig
{
    public const string DEFAULT_NAME = 'webware';

    public const string DEFAULT_ACCENT = '#7e5ae0';

    /**
     * The converter's own choice of icon font, so a document that sets `icons: font` keeps working.
     */
    public const string DEFAULT_ICON_FONT_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css';

    private function __construct(
        private string $name,
        private string $defaultAccent,
        private ColorMode $colorMode,
        private string $iconFontCdn = self::DEFAULT_ICON_FONT_CDN,
        private string $logo = '',
    ) {}

    public static function defaults(): self
    {
        return new self(self::DEFAULT_NAME, self::DEFAULT_ACCENT, new ColorMode());
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'name', self::DEFAULT_NAME),
            Values::string($data, 'defaultAccent', self::DEFAULT_ACCENT),
            new ColorMode(
                Values::string($data, 'colorMode', ColorMode::SYSTEM),
                Values::string($data, 'defaultColorMode', ColorMode::DARK),
            ),
            Values::string($data, 'iconFontCdn', self::DEFAULT_ICON_FONT_CDN),
            Values::string($data, 'logo', ''),
        );
    }

    public function colorMode(): string
    {
        return $this->colorMode->mode();
    }

    public function defaultAccent(): string
    {
        return $this->defaultAccent;
    }

    public function defaultColorMode(): string
    {
        return $this->colorMode->fallback();
    }

    /**
     * The icon font stylesheet the page shell links, overridable so a build can point at a vendored
     * copy instead of the CDN.
     */
    public function iconFontCdn(): string
    {
        return $this->iconFontCdn;
    }

    /**
     * The only built-in token set in this feature is "webware".
     */
    public function isBuiltIn(): bool
    {
        return self::DEFAULT_NAME === $this->name;
    }

    /**
     * A logo URL that replaces the mark shipped with the package, empty when the shipped mark is used.
     */
    public function logo(): string
    {
        return $this->logo;
    }

    public function name(): string
    {
        return $this->name;
    }
}
