<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use InvalidArgumentException;
use Override;
use RuntimeException;
use Throwable;
use Webware\AsciidocPhp\MultiRepo\Build\BuildRun;
use Webware\AsciidocPhp\MultiRepo\Build\BuildRunner;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Cli\ProjectFilter;
use Webware\AsciidocPhp\MultiRepo\Config\BuildConfig;
use Webware\AsciidocPhp\MultiRepo\Config\ConfigException;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryException;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryFactory;
use Webware\AsciidocPhp\MultiRepo\Discovery\SlugException;
use Webware\AsciidocPhp\MultiRepo\Report\ReportWriter;

use function sprintf;

/**
 * Discovers, fetches, renders, assembles, and writes the whole site.
 *
 * The two streams follow `contracts/cli.md`: progress, warnings, and every error go to standard
 * error, and standard output carries exactly one summary block with the counts by status and the
 * output path. A caller that wants the per-project detail asks for `--verbose`.
 *
 * The report is always written, to `--report` when it is supplied and to the configured
 * `output.reportPath` otherwise, so a downstream publisher never has to guess whether a build left
 * a machine-readable result behind.
 *
 * A page that cannot be rendered is reported with its reason code and its source path, and the run
 * finishes with a degraded exit code rather than an internal error, so the pages that did render are
 * still published and the output says exactly what failed.
 */
final class BuildCommand implements CommandInterface
{
    public function __construct(
        private readonly DiscoveryFactory $discovery = new DiscoveryFactory(),
        private readonly BuildRunner $runner = new BuildRunner(),
        private readonly ReportWriter $reports = new ReportWriter(),
    ) {}

    /**
     * @throws ConfigException          when the manifest cannot be loaded
     * @throws InvalidArgumentException when a project reports a status outside the schema vocabulary
     * @throws RuntimeException         when a shipped asset is missing
     */
    #[Override]
    public function execute(Options $options, SiteConfig $config, Output $output): ExitCode
    {
        $buildConfig = BuildConfig::fromSiteConfig($config)->withOverrides(
            outputDirectory: $options->out,
            cacheDirectory : $options->cache,
            reportPath     : $options->report,
            concurrency    : $options->concurrency,
            basePath       : $options->basePath,
            full           : $options->full,
        );

        if ($buildConfig->basePath() !== $config->site()->basePath()) {
            $config = $config->withSite($config->site()->withBasePath($buildConfig->basePath()));
        }

        try {
            $repositories = ProjectFilter::apply(
                $this->discovery->create($options->manifest)->discover($config),
                $options,
            );
        } catch (SlugException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::ConfigError;
        } catch (DiscoveryException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::DiscoveryError;
        }

        $run = $this->runner->run($config, $repositories, $options, $buildConfig, $output);

        $this->writeReport($buildConfig, $run, $output);

        $output->summary($this->summaryBlock($run, $buildConfig));

        return $run->succeeded() ? ExitCode::fromBuildReport($run->report()->toArray()) : ExitCode::InternalError;
    }

    #[Override]
    public function name(): string
    {
        return 'build';
    }

    #[Override]
    public function summary(): string
    {
        return 'discover, fetch, render, assemble, write, and report.';
    }

    private function summaryBlock(BuildRun $run, BuildConfig $buildConfig): string
    {
        $totals = $run->report()->totals();

        return sprintf(
            "ok: %d, partial: %d, failed: %d, skipped: %d\noutput directory: %s\nreport: %s",
            $totals['ok'],
            $totals['partial'],
            $totals['failed'],
            $totals['skipped'],
            $buildConfig->outputDirectory(),
            $buildConfig->reportPath(),
        );
    }

    private function writeReport(BuildConfig $buildConfig, BuildRun $run, Output $output): void
    {
        try {
            $this->reports->write($buildConfig->reportPath(), $run->report());
        } catch (Throwable $exception) {
            $output->error(sprintf(
                'the build report could not be written to %s: %s',
                $buildConfig->reportPath(),
                $exception->getMessage(),
            ));
        }
    }
}
