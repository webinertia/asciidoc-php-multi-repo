<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli;

/**
 * Immutable result of parsing the command line. Defaults are applied here so downstream code never
 * has to know which flag was supplied and which one fell back to the contract default.
 */
final readonly class Options
{
    public const int DEFAULT_PORT = 8080;

    public const string DEFAULT_CONFIG = 'site.json';

    /**
     * @param list<string>          $projects Repeatable `--project` values in the order supplied.
     * @param array<string, string> $refs     Repeatable `--ref` values keyed by repository name.
     */
    public function __construct(
        public string $command = '',
        public ?string $config = null,
        public ?string $out = null,
        public ?string $cache = null,
        public ?string $report = null,
        public ?int $concurrency = null,
        public array $projects = [],
        public array $refs = [],
        public ?string $manifest = null,
        public bool $full = false,
        public ?string $basePath = null,
        public int $port = self::DEFAULT_PORT,
        public bool $force = false,
        public bool $quiet = false,
        public bool $verbose = false,
        public bool $timings = false,
        public bool $noColor = false,
        public bool $help = false,
        public bool $version = false,
    ) {}

    public function configPath(): string
    {
        return $this->config ?? self::DEFAULT_CONFIG;
    }

    public function hasProjectFilter(): bool
    {
        return [] !== $this->projects;
    }
}
