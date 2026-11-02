<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use Override;
use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Cli\ProjectFilter;
use Webware\AsciidocPhp\MultiRepo\Cli\Serve\StaticServer;
use Webware\AsciidocPhp\MultiRepo\Cli\Serve\StaticSiteHandler;
use Webware\AsciidocPhp\MultiRepo\Cli\Watch\SourceWatcher;
use Webware\AsciidocPhp\MultiRepo\Cli\Watch\WatchChange;
use Webware\AsciidocPhp\MultiRepo\Cli\Watch\WatchedSource;
use Webware\AsciidocPhp\MultiRepo\Cli\Watch\WatchSnapshot;
use Webware\AsciidocPhp\MultiRepo\Config\ConfigException;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryException;
use Webware\AsciidocPhp\MultiRepo\Discovery\DiscoveryFactory;
use Webware\AsciidocPhp\MultiRepo\Discovery\SlugException;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function array_intersect;
use function array_values;
use function count;
use function dirname;
use function getcwd;
use function getenv;
use function implode;
use function in_array;
use function is_dir;
use function is_string;
use function max;
use function microtime;
use function sprintf;
use function usleep;

/**
 * Rebuilds affected projects when their sources change and serves the result.
 *
 * The rebuild is a poll, not a notification: no inotify extension, no `fswatch`, no extra binary to
 * install. A poll costs one directory walk and one hash per file, which is affordable for a
 * documentation tree and behaves identically on every platform the CLI supports.
 *
 * A change is debounced before it is acted on, because an editor saving a file is often several
 * writes in a row and a rebuild per write would render the same page three times.
 *
 * @internal
 */
final class WatchCommand implements CommandInterface
{
    /** How long one loop iteration waits for a connection before the next poll. */
    private const float SERVE_SLICE_SECONDS = 0.25;

    /** The longest a burst of writes may hold a rebuild back before it runs anyway. */
    private const float MAX_SETTLE_SECONDS = 2.0;

    private ?WatchSnapshot $baseline = null;

    private ?SourceWatcher $watcher = null;

    /** The configuration in force, which a reload replaces. */
    private ?SiteConfig $current = null;

    /** @var list<string> Slugs of the projects this run actually watches. */
    private array $watched = [];

    /**
     * @param string $host     Default bind address; `ASCIIDOC_SERVE_HOST` overrides it.
     * @param int    $cycles   Loop iterations before returning, 0 to watch until stopped.
     * @param float  $debounce Quiet period that ends a burst of writes.
     */
    public function __construct(
        private readonly BuildCommand $build = new BuildCommand(),
        private readonly DiscoveryFactory $discovery = new DiscoveryFactory(),
        private readonly string $host = '127.0.0.1',
        private readonly int $cycles = 0,
        private readonly float $debounce = 0.35,
        private readonly SiteConfigLoader $loader = new SiteConfigLoader(),
    ) {}

    /**
     * @throws RuntimeException when a resolved file cannot be read
     */
    #[Override]
    public function execute(Options $options, SiteConfig $config, Output $output): ExitCode
    {
        $status = $this->initialize($options, $config, $output);

        if ($status instanceof ExitCode) {
            return $status;
        }

        $server = new StaticServer(
            new StaticSiteHandler($this->outputDirectory($options, $config), $config->site()->basePath()),
            $this->host(),
            $options->port,
        );

        try {
            $server->start();
        } catch (RuntimeException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::ConfigError;
        }

        $output->progress(sprintf(
            'watching %d source(s) at %s, press Ctrl-C to stop',
            null === $this->watcher ? 0 : count($this->watcher->sources()),
            $server->url(),
        ));

        try {
            $this->run($server, $options, $config, $output);
        } finally {
            $server->stop();
        }

        return ExitCode::Success;
    }

    /**
     * Runs the first build and records the state every later poll is compared against.
     *
     * Returns the exit code that stops watching, or null when watching can continue. A degraded
     * build is not a reason to stop: the page that failed is exactly what the user is about to fix.
     */
    public function initialize(Options $options, SiteConfig $config, Output $output): ?ExitCode
    {
        try {
            $status = $this->build->execute($options, $config, $output);
        } catch (ConfigException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::ConfigError;
        } catch (RuntimeException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::InternalError;
        }

        $this->watcher  = new SourceWatcher($this->sources($options, $config, $output));
        $this->baseline = $this->watcher->snapshot();
        $this->current  = $config;

        if (in_array(
            needle  : $status,
            haystack: [ExitCode::Success, ExitCode::Degraded],
            strict  : true,
        )) {
            return null;
        }

        return $status;
    }

    #[Override]
    public function name(): string
    {
        return 'watch';
    }

    /**
     * Compares the watched sources against the baseline, and rebuilds when something changed.
     *
     * The baseline advances even when the rebuild fails, so one unrenderable page cannot turn every
     * later poll into the same rebuild.
     *
     * @throws RuntimeException when a resolved file cannot be read
     */
    public function poll(Options $options, SiteConfig $config, Output $output): WatchChange
    {
        if (! $this->watcher instanceof SourceWatcher || ! $this->baseline instanceof WatchSnapshot) {
            return WatchChange::none();
        }

        $config   = $this->current ?? $config;
        $snapshot = $this->watcher->snapshot();
        $change   = $this->baseline->changeAgainst($snapshot);

        if ($change->isEmpty()) {
            return $change;
        }

        $this->baseline = $this->settle($snapshot);

        $output->progress($change->describe());

        if (in_array(
            needle  : $config->configPath(),
            haystack: $change->paths(),
            strict  : true,
        )) {
            $reloaded = $this->reconfigure($options, $config, $output);

            if (null === $reloaded) {
                return $change;
            }

            $this->current = $reloaded;
            $config        = $reloaded;
        }

        $projects = $this->projectsToRebuild($change);

        if (null === $projects) {
            $output->detail('nothing watched changed, no rebuild');

            return $change;
        }

        $status = $this->rebuild($options, $config, $output, $projects);

        if (
            null !== $status
            && ! in_array(
                needle  : $status,
                haystack: [ExitCode::Success, ExitCode::Degraded],
                strict  : true,
            )
        ) {
            $output->warning(sprintf('rebuild failed with %s, still watching', $status->label()));
        }

        return $change;
    }

    #[Override]
    public function summary(): string
    {
        return 'Rebuild affected projects on change and serve the site.';
    }

    /**
     * The bind address is configurable through the environment because a container cannot be
     * reached through `127.0.0.1`, while a developer machine should not listen on every interface
     * unless it was asked for.
     */
    private function host(): string
    {
        $environment = getenv('ASCIIDOC_SERVE_HOST');

        return is_string($environment) && '' !== $environment ? $environment : $this->host;
    }

    private function outputDirectory(Options $options, SiteConfig $config): string
    {
        if (null === $options->out) {
            return $config->output()->directory();
        }

        $base = getcwd();

        return Path::resolve(false === $base ? '.' : $base, $options->out);
    }

    /**
     * The projects a change requires, or null when it requires none.
     *
     * A project the user filtered out with `--project` is not rebuilt even when it changed, which is
     * what makes `--project` mean the same thing to `watch` as it does to `build`.
     *
     * @return null|list<string>
     */
    private function projectsToRebuild(WatchChange $change): ?array
    {
        if ($change->everything()) {
            return $this->watched;
        }

        $affected = array_values(array_intersect($change->projects(), $this->watched));

        return [] === $affected ? null : $affected;
    }

    /**
     * Runs the rebuild for a change, reporting a configuration failure without ending the watch.
     *
     * A manifest or a configuration file can be edited into an invalid state while the user is
     * looking at the page they are editing, and that is a reason to say so, not a reason to stop.
     *
     * @param list<string> $projects
     */
    private function rebuild(Options $options, SiteConfig $config, Output $output, array $projects): ?ExitCode
    {
        try {
            return $this->build->execute($options->withProjects($projects), $this->current ?? $config, $output);
        } catch (ConfigException $exception) {
            $output->warning(sprintf('rebuild failed: %s, still watching', $exception->getMessage()));

            return null;
        } catch (RuntimeException $exception) {
            $output->warning(sprintf('rebuild failed: %s, still watching', $exception->getMessage()));

            return null;
        }
    }

    /**
     * Reloads the configuration after the file itself changed.
     *
     * A configuration edit can move the output directory, change the base path, or add and remove
     * projects, so the watched set is rebuilt around the reloaded file. An edit that cannot be
     * loaded is reported and the previous configuration stays in force, because a half-typed
     * setting is not a reason to stop watching.
     *
     * @throws RuntimeException when a resolved file cannot be read
     */
    private function reconfigure(Options $options, SiteConfig $config, Output $output): ?SiteConfig
    {
        try {
            $reloaded = $this->loader->load($config->configPath());
        } catch (ConfigException $exception) {
            $output->warning(sprintf(
                'configuration reload failed: %s, still watching',
                $exception->getMessage(),
            ));

            return null;
        }

        if (
            $reloaded->output()->directory() !== $config->output()->directory()
            || $reloaded->site()->basePath() !== $config->site()->basePath()
        ) {
            $output->warning('the output directory or base path changed, restart watch to serve the new location');
        }

        $this->watched  = [];
        $this->watcher  = new SourceWatcher($this->sources($options, $reloaded, $output));
        $this->baseline = $this->watcher->snapshot();

        return $reloaded;
    }

    /**
     * @throws RuntimeException when a resolved file cannot be read
     */
    private function run(StaticServer $server, Options $options, SiteConfig $config, Output $output): void
    {
        $cycles = 0;

        while (0 === $this->cycles || $cycles < $this->cycles) {
            $server->serveOnce(self::SERVE_SLICE_SECONDS);
            $this->poll($options, $config, $output);
            $cycles++;
        }
    }

    /**
     * Waits for a burst of writes to finish, and returns the state it settled on.
     *
     * A burst that never goes quiet is cut off at {@see self::MAX_SETTLE_SECONDS}: a rebuild against
     * a half-written file is more useful than no rebuild at all, and the next poll catches the rest.
     */
    private function settle(WatchSnapshot $snapshot): WatchSnapshot
    {
        $deadline = microtime(true) + self::MAX_SETTLE_SECONDS;

        while (microtime(true) < $deadline) {
            usleep(max(0, (int) ($this->debounce * 1_000_000)));

            if (! $this->watcher instanceof SourceWatcher) {
                return $snapshot;
            }

            $next = $this->watcher->snapshot();

            if ($next->signatures() === $snapshot->signatures()) {
                return $next;
            }

            $snapshot = $next;
        }

        return $snapshot;
    }

    /**
     * Every tree whose change requires a rebuild.
     *
     * The configuration file and the package's own `resources/` tree are global, because a setting
     * or a theme asset reaches every page. A repository with a local checkout is watched per
     * project; one that discovery must clone cannot be watched at all, and saying so once is more
     * honest than polling a cache directory the build itself writes to.
     *
     * @return list<WatchedSource>
     */
    private function sources(Options $options, SiteConfig $config, Output $output): array
    {
        $sources = [WatchedSource::global($config->configPath())];

        $resources =
            dirname(
                path  : __DIR__,
                levels: 3,
            ) . '/resources';

        if (is_dir($resources)) {
            $sources[] = WatchedSource::global($resources);
        }

        if (null !== $options->manifest) {
            $base      = getcwd();
            $sources[] = WatchedSource::global(Path::resolve(false === $base ? '.' : $base, $options->manifest));
        }

        try {
            $repositories = ProjectFilter::apply(
                $this->discovery->create($options->manifest)->discover($config),
                $options,
            );
        } catch (ConfigException|SlugException|DiscoveryException $exception) {
            $output->warning(sprintf('unable to watch project sources: %s', $exception->getMessage()));

            return $sources;
        }

        /** @var list<string> $remote */
        $remote = [];

        foreach ($repositories as $repository) {
            $path = $repository->path();

            if (null === $path) {
                $remote[] = $repository->name();

                continue;
            }

            $sources[]       = WatchedSource::forProject($repository->slug(), $path);
            $this->watched[] = $repository->slug();
        }

        if ([] !== $remote) {
            $output->warning(sprintf(
                'not watching %s: discovered remotely, so there is no local checkout to poll',
                implode(', ', $remote),
            ));
        }

        return $sources;
    }
}
