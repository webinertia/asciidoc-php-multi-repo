<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli;

use function in_array;
use function is_array;
use function is_string;

/**
 * The process exit codes defined by contracts/cli.md.
 */
enum ExitCode: int
{
    case Success        = 0;
    case Degraded       = 1;
    case ConfigError    = 2;
    case DiscoveryError = 3;
    case InternalError  = 4;

    /**
     * Maps a build report to an exit code: 0 when every project is ok or skipped, 1 when at
     * least one project is partial or failed.
     *
     * @param array<string, mixed> $report
     */
    public static function fromBuildReport(array $report): self
    {
        $statuses = [];
        $projects = $report['projects'] ?? [];

        if (! is_array($projects)) {
            $projects = [];
        }

        foreach ($projects as $project) {
            if (! is_array($project)) {
                continue;
            }

            $status = $project['status'] ?? null;

            if (is_string($status)) {
                $statuses[] = $status;
            }
        }

        return self::fromProjectStatuses(...$statuses);
    }

    /**
     * Maps project statuses to an exit code. Any status other than ok or skipped degrades the run.
     */
    public static function fromProjectStatuses(string ...$statuses): self
    {
        foreach ($statuses as $status) {
            if (! in_array($status, ['ok', 'skipped'], true)) {
                return self::Degraded;
            }
        }

        return self::Success;
    }

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Success',
            self::Degraded => 'Degraded',
            self::ConfigError => 'ConfigError',
            self::DiscoveryError => 'DiscoveryError',
            self::InternalError => 'InternalError',
        };
    }
}
