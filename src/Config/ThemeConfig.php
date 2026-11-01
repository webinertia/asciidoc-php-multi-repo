<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Config;

/**
 * The `theme` section: the built-in token set and the accent used when a project has none.
 */
final readonly class ThemeConfig
{
    public const string DEFAULT_NAME = 'webware';

    public const string DEFAULT_ACCENT = '#7e5ae0';

    public function __construct(
        private string $name,
        private string $defaultAccent,
        private string $colorMode,
        private string $defaultColorMode,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'name', self::DEFAULT_NAME),
            Values::string($data, 'defaultAccent', self::DEFAULT_ACCENT),
            Values::string($data, 'colorMode', 'system'),
            Values::string($data, 'defaultColorMode', 'dark'),
        );
    }

    public static function defaults(): self
    {
        return new self(self::DEFAULT_NAME, self::DEFAULT_ACCENT, 'system', 'dark');
    }

    public function name(): string
    {
        return $this->name;
    }

    public function defaultAccent(): string
    {
        return $this->defaultAccent;
    }

    public function colorMode(): string
    {
        return $this->colorMode;
    }

    public function defaultColorMode(): string
    {
        return $this->defaultColorMode;
    }

    /**
     * The only built-in token set in this feature is "webware".
     */
    public function isBuiltIn(): bool
    {
        return $this->name === self::DEFAULT_NAME;
    }
}
