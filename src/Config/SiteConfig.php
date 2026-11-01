<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Config;

use Webware\AsciidocPhpMultiRepo\Support\Path;

use function dirname;
use function ksort;

use const SORT_STRING;

/**
 * The root configuration document, loaded from site.json.
 */
final readonly class SiteConfig
{
    public const int SUPPORTED_SCHEMA_VERSION = 1;

    /**
     * @param array<string, ProjectOverride> $projects
     */
    public function __construct(
        private int $schemaVersion,
        private SiteSection $site,
        private OrganizationSection $organization,
        private SourceSection $source,
        private RenderSection $render,
        private ThemeConfig $theme,
        private OutputSection $output,
        private array $projects,
        private string $configPath,
    ) {}

    /**
     * Builds the value object from a validated document. Relative output paths resolve against the
     * directory containing the configuration file.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $configPath): self
    {
        $directory = dirname(Path::normalise($configPath));
        $output    = OutputSection::fromArray(Values::section($data, 'output'));

        $projects = [];
        foreach (Values::sectionMap($data, 'projects') as $name => $override) {
            $projects[$name] = ProjectOverride::fromArray($override);
        }

        ksort($projects, SORT_STRING);

        return new self(
            Values::integer($data, 'schemaVersion', self::SUPPORTED_SCHEMA_VERSION),
            SiteSection::fromArray(Values::section($data, 'site')),
            OrganizationSection::fromArray(Values::section($data, 'organization')),
            SourceSection::fromArray(Values::section($data, 'source')),
            RenderSection::fromArray(Values::section($data, 'render')),
            ThemeConfig::fromArray(Values::section($data, 'theme')),
            $output->withPaths(
                Path::resolve($directory, $output->directory()),
                Path::resolve($directory, $output->cacheDirectory()),
                Path::resolve($directory, $output->reportPath()),
            ),
            $projects,
            Path::normalise($configPath),
        );
    }

    public function configDirectory(): string
    {
        return dirname($this->configPath);
    }

    /**
     * The absolute path of the configuration file this object was loaded from.
     */
    public function configPath(): string
    {
        return $this->configPath;
    }

    public function organization(): OrganizationSection
    {
        return $this->organization;
    }

    public function output(): OutputSection
    {
        return $this->output;
    }

    public function project(string $name): ?ProjectOverride
    {
        return $this->projects[$name] ?? null;
    }

    /**
     * @return array<string, ProjectOverride>
     */
    public function projects(): array
    {
        return $this->projects;
    }

    public function render(): RenderSection
    {
        return $this->render;
    }

    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function site(): SiteSection
    {
        return $this->site;
    }

    public function source(): SourceSection
    {
        return $this->source;
    }

    public function theme(): ThemeConfig
    {
        return $this->theme;
    }

    public function withSite(SiteSection $site): self
    {
        return new self(
            $this->schemaVersion,
            $site,
            $this->organization,
            $this->source,
            $this->render,
            $this->theme,
            $this->output,
            $this->projects,
            $this->configPath,
        );
    }
}
