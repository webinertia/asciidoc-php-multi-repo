<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Config;

use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function getcwd;

/**
 * The effective build settings for one run: the configuration file values after command line
 * overrides have been applied.
 */
final readonly class BuildConfig
{
    public function __construct(
        private string $outputDirectory,
        private string $cacheDirectory,
        private string $reportPath,
        private int $concurrency,
        private string $basePath,
        private bool $full,
    ) {}

    public static function fromSiteConfig(SiteConfig $config): self
    {
        return new self(
            $config->output()->directory(),
            $config->output()->cacheDirectory(),
            $config->output()->reportPath(),
            $config->render()->concurrency(),
            $config->site()->basePath(),
            false,
        );
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function cacheDirectory(): string
    {
        return $this->cacheDirectory;
    }

    public function concurrency(): int
    {
        return $this->concurrency;
    }

    public function full(): bool
    {
        return $this->full;
    }

    public function outputDirectory(): string
    {
        return $this->outputDirectory;
    }

    public function reportPath(): string
    {
        return $this->reportPath;
    }

    /**
     * Applies command line overrides. Relative override paths resolve against the working directory,
     * which is what a user typing a path expects; configuration file paths resolve against the
     * configuration file instead.
     */
    public function withOverrides(
        ?string $outputDirectory = null,
        ?string $cacheDirectory = null,
        ?string $reportPath = null,
        ?int $concurrency = null,
        ?string $basePath = null,
        ?bool $full = null,
        ?string $workingDirectory = null,
    ): self {
        $base = $workingDirectory ?? (string) getcwd();

        return new self(
            null === $outputDirectory ? $this->outputDirectory : Path::resolve($base, $outputDirectory),
            null === $cacheDirectory ? $this->cacheDirectory : Path::resolve($base, $cacheDirectory),
            null === $reportPath ? $this->reportPath : Path::resolve($base, $reportPath),
            $concurrency ?? $this->concurrency,
            null === $basePath ? $this->basePath : SiteSection::normaliseBasePath($basePath),
            $full ?? $this->full,
        );
    }
}
