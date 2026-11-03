<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Config;

use function is_string;
use function ltrim;
use function parse_url;
use function rtrim;

/**
 * The `site` section: identity and hosting for the published site.
 */
final readonly class SiteSection
{
    public const string DEFAULT_VERSION_LABEL = 'latest';

    public function __construct(
        private string $name,
        private string $baseUrl,
        private string $basePath,
        private ?string $hostname,
        private string $defaultVersionLabel,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::requiredString($data, 'name'),
            rtrim(Values::requiredString($data, 'baseUrl'), characters: '/'),
            self::normaliseBasePath(Values::string($data, 'basePath', '/')),
            Values::optionalString($data, 'hostname'),
            Values::string($data, 'defaultVersionLabel', self::DEFAULT_VERSION_LABEL),
        );
    }

    /**
     * Base paths always start and end with a single slash, and "/" is the root.
     */
    public static function normaliseBasePath(string $basePath): string
    {
        $trimmed = ltrim(rtrim($basePath, characters: '/'), characters: '/');

        return '' === $trimmed ? '/' : "/{$trimmed}/";
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function defaultVersionLabel(): string
    {
        return $this->defaultVersionLabel;
    }

    /**
     * The hostname to publish, falling back to the host of baseUrl.
     */
    public function effectiveHostname(): string
    {
        if (null !== $this->hostname && '' !== $this->hostname) {
            return $this->hostname;
        }

        $host = parse_url($this->baseUrl, PHP_URL_HOST);

        return is_string($host) ? $host : '';
    }

    public function hostname(): ?string
    {
        return $this->hostname;
    }

    public function isRootBasePath(): bool
    {
        return '/' === $this->basePath;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function withBasePath(string $basePath): self
    {
        return new self(
            $this->name,
            $this->baseUrl,
            self::normaliseBasePath($basePath),
            $this->hostname,
            $this->defaultVersionLabel,
        );
    }
}
