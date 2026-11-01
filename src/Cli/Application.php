<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli;

use Closure;
use Throwable;
use Webware\AsciidocPhpMultiRepo\Cli\Commands\BuildCommand;
use Webware\AsciidocPhpMultiRepo\Cli\Commands\CleanCommand;
use Webware\AsciidocPhpMultiRepo\Cli\Commands\CommandInterface;
use Webware\AsciidocPhpMultiRepo\Cli\Commands\DiscoverCommand;
use Webware\AsciidocPhpMultiRepo\Cli\Commands\FetchCommand;
use Webware\AsciidocPhpMultiRepo\Cli\Commands\ServeCommand;
use Webware\AsciidocPhpMultiRepo\Cli\Commands\ValidateCommand;
use Webware\AsciidocPhpMultiRepo\Cli\Commands\WatchCommand;
use Webware\AsciidocPhpMultiRepo\Config\ConfigException;
use Webware\AsciidocPhpMultiRepo\Config\SiteConfigLoader;

use function array_slice;
use function implode;
use function sprintf;

/**
 * Parses the global options, resolves the subcommand, loads and validates the configuration, and
 * maps every outcome onto the exit codes in contracts/cli.md.
 */
final class Application
{
    public const string NAME = 'asciidoc-php-multi-repo';

    /** Help lines for the global options. Kept next to the parser so the two cannot drift apart. */
    private const array OPTION_LINES = [
        '-c, --config <path>        site.json path. Default: ./site.json',
        '-o, --out <dir>            Override output.directory',
        '    --cache <dir>          Override output.cacheDirectory',
        '    --report <path>        Write the JSON report to this path',
        '    --concurrency <n>      Override render.concurrency',
        '    --project <name>       Repeatable. Limit to these slugs or repository names.',
        '    --ref <name=revision>  Repeatable. Override the ref for one repository.',
        '    --manifest <path>      Read discovery from a manifest instead of the organization.',
        '    --full                 Ignore cached state and rebuild everything.',
        '    --base-path <path>     Override site.basePath.',
        '    --port <n>             Port for watch and serve. Default 8080.',
        '    --force                Allow clean to remove a path outside the config directory.',
        '-q, --quiet                Errors only.',
        '-v, --verbose              Per-page detail.',
        '-t, --timings              Per-phase timings on stderr.',
        '    --no-color             Disable ANSI colour.',
        '-h, --help                 Show this help and exit 0.',
        '    --version              Print the package version and exit 0.',
    ];

    /** @var list<CommandInterface> */
    private readonly array $commands;

    private readonly string $version;

    /**
     * @param null|Closure(string): void $stdout
     * @param null|Closure(string): void $stderr
     * @param null|list<CommandInterface>         $commands
     */
    public function __construct(
        private readonly ?Closure $stdout = null,
        private readonly ?Closure $stderr = null,
        private readonly bool $interactive = false,
        private readonly ?string $noColorEnvironment = null,
        ?string $version = null,
        ?array $commands = null,
        private readonly SiteConfigLoader $configLoader = new SiteConfigLoader(),
    ) {
        $this->commands = $commands ?? self::defaultCommands();
        $this->version  = $version ?? Version::current();
    }

    /** @return list<CommandInterface> */
    public static function defaultCommands(): array
    {
        return [
            new DiscoverCommand(),
            new FetchCommand(),
            new BuildCommand(),
            new WatchCommand(),
            new ServeCommand(),
            new ValidateCommand(),
            new CleanCommand(),
        ];
    }

    /** @return list<CommandInterface> */
    public function commands(): array
    {
        return $this->commands;
    }

    /**
     * @param list<string> $argv The raw argument vector, including the program name at offset 0.
     */
    public function run(array $argv): int
    {
        try {
            $options = new OptionsParser()->parse(array_slice($argv, 1));
        } catch (UsageException $exception) {
            $output = $this->output(new Options());
            $output->error($exception->getMessage());
            $output->usage($this->usage());

            return ExitCode::ConfigError->value;
        }

        $output = $this->output($options);

        if ($options->version) {
            $output->write(sprintf('%s %s', self::NAME, $this->version));

            return ExitCode::Success->value;
        }

        if ($options->help) {
            $output->write($this->usage());

            return ExitCode::Success->value;
        }

        if ('' === $options->command) {
            $output->error('no command given');
            $output->usage($this->usage());

            return ExitCode::ConfigError->value;
        }

        $command = $this->command($options->command);

        if (! $command instanceof CommandInterface) {
            $output->error(sprintf('unknown command "%s"', $options->command));
            $output->usage($this->usage());

            return ExitCode::ConfigError->value;
        }

        try {
            $config = $this->configLoader->load($options->configPath());
        } catch (ConfigException $exception) {
            $output->error($exception->getMessage());

            return ExitCode::ConfigError->value;
        }

        try {
            return $command->execute($options, $config, $output)->value;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());

            return ExitCode::InternalError->value;
        }
    }

    public function usage(): string
    {
        $lines = [
            self::NAME . ' <command> [options]',
            '',
            'Commands:',
        ];

        foreach ($this->commands as $command) {
            $lines[] = sprintf('  %-10s %s', $command->name(), $command->summary());
        }

        $lines[] = '';
        $lines[] = 'Global options:';

        foreach (self::OPTION_LINES as $line) {
            $lines[] = "  {$line}";
        }

        return implode("\n", $lines) . "\n";
    }

    public function version(): string
    {
        return $this->version;
    }

    private function command(string $name): ?CommandInterface
    {
        foreach ($this->commands as $command) {
            if ($command->name() === $name) {
                return $command;
            }
        }

        return null;
    }

    private function output(Options $options): Output
    {
        return new Output(
            quiet  : $options->quiet,
            verbose: $options->verbose,
            timings: $options->timings,
            color  : Output::colourEnabled($options->noColor, $this->noColorEnvironment, $this->interactive),
            stdout : $this->stdout,
            stderr : $this->stderr,
        );
    }
}
