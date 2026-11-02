<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Build;

use Webware\AsciidocPhp\MultiRepo\Report\BuildReport;

/**
 * The outcome of one build run: the report, where the site was written, and any determinism
 * violations found in the output.
 *
 * Determinism violations are returned rather than thrown because they are a property of the output
 * the run already produced, and the caller decides how to report them. A run that found any is never
 * considered successful.
 *
 * @api
 */
final readonly class BuildRun
{
    /**
     * @param list<string> $violations
     */
    public function __construct(
        private BuildReport $report,
        private string $outputDirectory,
        private array $violations = [],
    ) {}

    public function outputDirectory(): string
    {
        return $this->outputDirectory;
    }

    public function report(): BuildReport
    {
        return $this->report;
    }

    public function succeeded(): bool
    {
        return [] === $this->violations;
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return $this->violations;
    }
}
