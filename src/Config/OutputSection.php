<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Config;

/**
 * The `output` section. Every path here is absolute once the loader has resolved it against the
 * directory containing site.json.
 */
final readonly class OutputSection
{
    public function __construct(
        private string $directory,
        private string $cacheDirectory,
        private string $reportPath,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::string($data, 'directory', 'build/site'),
            Values::string($data, 'cacheDirectory', '.cache/multi-repo'),
            Values::string($data, 'reportPath', 'build/report.json'),
        );
    }

    public function cacheDirectory(): string
    {
        return $this->cacheDirectory;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Every path this build writes. A source tree's revision digest must not include them: they
     * are rewritten by the run that computes the digest, so hashing them is both wrong and racy.
     *
     * @return list<string>
     */
    public function generatedPaths(): array
    {
        return [
            $this->directory,
            $this->cacheDirectory,
            $this->reportPath,
        ];
    }

    public function reportPath(): string
    {
        return $this->reportPath;
    }

    public function withPaths(string $directory, string $cacheDirectory, string $reportPath): self
    {
        return new self($directory, $cacheDirectory, $reportPath);
    }
}
