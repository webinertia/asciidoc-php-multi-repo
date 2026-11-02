<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli\Commands;

use Override;
use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Cli\Serve\StaticServer;
use Webware\AsciidocPhp\MultiRepo\Cli\Serve\StaticSiteHandler;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Support\Path;

use function getcwd;
use function getenv;
use function is_dir;
use function is_string;
use function sprintf;

/**
 * Serves an already built output directory.
 *
 * The command never builds: it reports the missing output directory and stops, so a preview cannot
 * silently show a stale tree while the user waits for a build that is not going to happen.
 *
 * @internal
 */
final class ServeCommand implements CommandInterface
{
    /** How long one loop iteration waits for a connection before checking the loop condition. */
    private const float POLL_SECONDS = 0.25;

    /**
     * @param string $host         Default bind address; `ASCIIDOC_SERVE_HOST` overrides it.
     * @param int    $requestLimit Requests to answer before returning, 0 to serve until stopped.
     */
    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $requestLimit = 0,
    ) {}

    /**
     * @throws RuntimeException when a resolved file cannot be read
     */
    #[Override]
    public function execute(Options $options, SiteConfig $config, Output $output): ExitCode
    {
        $outputDirectory = $this->outputDirectory($options, $config);

        if (! is_dir($outputDirectory)) {
            $output->error(sprintf(
                'output directory "%s" does not exist. Run "build" first, or pass --out.',
                $outputDirectory,
            ));

            return ExitCode::ConfigError;
        }

        $server = new StaticServer(
            new StaticSiteHandler($outputDirectory, $config->site()->basePath()),
            $this->host(),
            $options->port,
        );

        try {
            $server->start();
        } catch (RuntimeException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::ConfigError;
        }

        $output->progress(sprintf('serving %s at %s', $outputDirectory, $server->url()));

        try {
            $this->loop($server);
        } finally {
            $server->stop();
        }

        return ExitCode::Success;
    }

    #[Override]
    public function name(): string
    {
        return 'serve';
    }

    #[Override]
    public function summary(): string
    {
        return 'Serve an already built output directory.';
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

    /**
     * @throws RuntimeException when a resolved file cannot be read
     */
    private function loop(StaticServer $server): void
    {
        $served = 0;

        while (0 === $this->requestLimit || $served < $this->requestLimit) {
            if (! $server->serveOnce(self::POLL_SECONDS)) {
                continue;
            }

            $served++;
        }
    }

    private function outputDirectory(Options $options, SiteConfig $config): string
    {
        if (null === $options->out) {
            return $config->output()->directory();
        }

        $base = getcwd();

        return Path::resolve(false === $base ? '.' : $base, $options->out);
    }
}
