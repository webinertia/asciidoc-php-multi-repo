<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Support;

use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhp\MultiRepo\Source\DocPage;
use Webware\AsciidocPhp\MultiRepo\Source\DocVersion;
use Webware\AsciidocPhp\MultiRepo\Source\PreparedSource;
use Webware\AsciidocPhp\MultiRepo\Source\SourceFormat;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function dirname;
use function file_get_contents;
use function hash;

/**
 * Reads the checked-in fixture organisation without running discovery or fetching.
 *
 * The render and build tests need a config, a repository, a version, and a prepared source, and
 * none of them need the pipeline that produced those values. Building them here keeps each test
 * pointed at the class it covers.
 */
trait SiteFixtureTrait
{
    protected function fixtureConfig(?string $basePath = null): SiteConfig
    {
        $config = new SiteConfigLoader()->load($this->fixturePath('site.json'));

        if (null === $basePath) {
            return $config;
        }

        return $config->withSite($config->site()->withBasePath($basePath));
    }

    protected function fixturePath(string $relative): string
    {
        return Path::join(dirname(__DIR__, 2), 'fixtures', $relative);
    }

    protected function fixtureRepository(string $name, string $defaultBranch = 'main'): DiscoveredRepository
    {
        return new DiscoveredRepository(
            name         : $name,
            slug         : $name,
            defaultBranch: $defaultBranch,
            path         : $this->fixturePath("org/{$name}"),
        );
    }

    protected function fixtureSource(string $relativePath, string $version = 'v1', ?string $text = null): PreparedSource
    {
        $path = $this->fixturePath($relativePath);
        $body = $text ?? file_get_contents($path);

        return new PreparedSource(
            new DocPage($version, $relativePath, $path),
            SourceFormat::AsciiDoc,
            false === $body ? '' : $body,
            hash('sha256', false === $body ? '' : $body),
        );
    }

    protected function fixtureVersion(string $label = 'v1', string $directory = 'docs/v1', int $number = 1): DocVersion
    {
        return new DocVersion($label, $directory, $number);
    }
}
