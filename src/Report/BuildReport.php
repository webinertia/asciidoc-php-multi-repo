<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Report;

use Webware\AsciidocPhp\MultiRepo\Cli\Version;

use function strcmp;
use function usort;

/**
 * A complete build report, in the shape `contracts/report.schema.json` describes.
 *
 * @api
 */
final readonly class BuildReport
{
    public const int SCHEMA_VERSION = 1;

    public const string GENERATOR = Version::PACKAGE;

    /**
     * @param list<ProjectResult> $projects
     */
    public function __construct(
        private array $projects,
        private string $version,
        private int $exitCode,
    ) {}

    /**
     * @param list<ProjectResult> $projects
     */
    public static function fromResults(array $projects, int $exitCode): self
    {
        return new self($projects, Version::current(), $exitCode);
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    /**
     * @return list<ProjectResult>
     */
    public function projects(): array
    {
        $projects = $this->projects;

        usort(
            $projects,
            static fn(ProjectResult $left, ProjectResult $right): int => strcmp($left->slug(), $right->slug()),
        );

        return $projects;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $projects = [];

        foreach ($this->projects() as $project) {
            $projects[] = $project->toArray();
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'generatedBy'   => [
                'name'    => self::GENERATOR,
                'version' => $this->version,
            ],
            'projects'      => $projects,
            'totals'        => $this->totals(),
            'exitCode'      => $this->exitCode,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function totals(): array
    {
        $totals = [
            ProjectResult::OK      => 0,
            ProjectResult::PARTIAL => 0,
            ProjectResult::FAILED  => 0,
            ProjectResult::SKIPPED => 0,
            'pages'                => 0,
            'renderedPages'        => 0,
        ];

        foreach ($this->projects as $project) {
            $totals[$project->status()]++;
            $totals['pages']         += $project->pageCount();
            $totals['renderedPages'] += $project->renderedPageCount();
        }

        return $totals;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function withExitCode(int $exitCode): self
    {
        return new self($this->projects, $this->version, $exitCode);
    }
}
