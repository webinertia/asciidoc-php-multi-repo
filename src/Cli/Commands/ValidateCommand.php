<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use InvalidArgumentException;
use Override;
use RuntimeException;
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
use Webware\AsciidocPhp\MultiRepo\Report\ProjectResult;

use function sprintf;

/**
 * Reports, for every onboarded repository, whether it would build.
 *
 * The answer is not a guess. Every project is collected and rendered exactly as `build` does, and
 * only the publishing step is skipped, so a project that would fail on a broken include is reported
 * with the same reason code and corrective action the report would carry. Nothing is written to the
 * output directory, which makes `validate` safe to run against a checkout that already has a
 * published site in it.
 *
 * The exit code follows the build contract: 0 when every project would build or is skipped, 1 when
 * any project would be partial or failed.
 */
final class ValidateCommand implements CommandInterface
{
    public function __construct(
        private readonly DiscoveryFactory $discovery = new DiscoveryFactory(),
        private readonly BuildRunner $runner = new BuildRunner(),
    ) {}

    /**
     * @throws ConfigException          when the manifest cannot be loaded
     * @throws InvalidArgumentException when a project reports a status outside the schema vocabulary
     * @throws RuntimeException         when a page cannot be rendered
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

        $report = $this->runner->validate($config, $repositories, $options, $buildConfig, $output);

        foreach ($report->projects() as $project) {
            $this->report($project, $output);
        }

        $totals = $report->totals();

        $output->summary(sprintf(
            "ok: %d, partial: %d, failed: %d, skipped: %d\nno site output was written",
            $totals['ok'],
            $totals['partial'],
            $totals['failed'],
            $totals['skipped'],
        ));

        return ExitCode::fromBuildReport($report->toArray());
    }

    #[Override]
    public function name(): string
    {
        return 'validate';
    }

    #[Override]
    public function summary(): string
    {
        return 'Report per-repository buildability without writing site output.';
    }

    private function report(ProjectResult $project, Output $output): void
    {
        $output->progress(sprintf('%s: %s', $project->slug(), $project->status()));

        if ([] === $project->errors()) {
            return;
        }

        foreach ($project->errors() as $error) {
            $output->warning(sprintf(
                '%s: %s (%s)%s',
                $error->path() ?? $project->slug(),
                $error->message(),
                $error->reasonCode()->value,
                null === $error->correctiveAction() ? '' : sprintf(' %s', $error->correctiveAction()),
            ));
        }
    }
}
