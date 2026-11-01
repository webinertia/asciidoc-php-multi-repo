<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Support;

use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveredRepository;
use Webware\AsciidocPhp\MultiRepo\Source\FetchedRepository;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function dirname;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Builds the configuration and the repository descriptions the discovery and source tests share.
 *
 * The configuration is the checked-in fixture, so a test never invents defaults that the shipped
 * schema would reject.
 */
final class SourceFixture
{
    /**
     * @param array<string, mixed> $overrides shallow-merged into the fixture document
     */
    public static function config(array $overrides = []): SiteConfig
    {
        $document = self::document();

        foreach ($overrides as $key => $value) {
            $document[$key] = $value;
        }

        return SiteConfig::fromArray($document, self::path('site.json'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(): array
    {
        $path = self::path('site.json');
        $json = file_get_contents($path);

        if (! is_string($json)) {
            throw new RuntimeException("Unable to read {$path}");
        }

        $document = json_decode(
            json       : $json,
            associative: true,
            flags      : JSON_THROW_ON_ERROR,
        );

        if (! is_array($document)) {
            throw new RuntimeException("{$path} is not a JSON object");
        }

        return $document;
    }

    /**
     * @param list<string> $files
     */
    public static function fetched(
        string $name,
        array $files,
        string $rootPath = '/repo',
        string $documentationDirectory = 'docs',
        string $revision = 'revision',
    ): FetchedRepository {
        return new FetchedRepository(
            $name,
            $revision,
            $rootPath,
            $documentationDirectory,
            $files,
        );
    }

    public static function fixtureDirectory(string $name): string
    {
        return Path::join(
            dirname(
                path  : __DIR__,
                levels: 2,
            ),
            'fixtures',
            'org',
            $name,
        );
    }

    public static function path(string $file): string
    {
        return Path::join(
            dirname(
                path  : __DIR__,
                levels: 2,
            ),
            'fixtures',
            $file,
        );
    }

    public static function repository(
        string $name = 'versioned',
        string $slug = 'versioned',
        bool $archived = false,
        bool $onboarded = true,
        ?string $path = null,
    ): DiscoveredRepository {
        return new DiscoveredRepository($name, $slug, 'main', $archived, $onboarded, $path);
    }
}
