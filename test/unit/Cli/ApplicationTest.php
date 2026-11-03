<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webware\AsciidocPhp\MultiRepo\Cli\Application;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\CommandInterface;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfig;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\CapturedOutput;
use WebwareTest\AsciidocPhp\MultiRepo\Support\RecordingCommand;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

use function dirname;
use function file_put_contents;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    use TempDirectoryTrait;

    private CapturedOutput $captured;

    private string $scratch;

    #[Test]
    public function commandReceivesParsedOptionsAndTheValidatedConfig(): void
    {
        $command = new RecordingCommand('build', ExitCode::Degraded);

        $exit = $this->application($command)->run([
            'asciidoc-php-multi-repo',
            'build',
            '-c',
            $this->fixtureConfig(),
            '--project',
            'message-bus',
            '--full',
        ]);

        static::assertSame(ExitCode::Degraded->value, $exit);
        static::assertSame(1, $command->calls);
        static::assertInstanceOf(Options::class, $command->options);
        static::assertSame(['message-bus'], $command->options->projects);
        static::assertTrue($command->options->full);
        static::assertSame('Fixture Site', $command->config?->site()->name());
        static::assertInstanceOf(Output::class, $command->output);
    }

    #[Test]
    public function helpExitsZeroAndListsEveryCommandAndOption(): void
    {
        $exit = $this->application()->run(['asciidoc-php-multi-repo', '--help']);

        static::assertSame(ExitCode::Success->value, $exit);
        static::assertSame('', $this->captured->stderr);

        foreach (['discover', 'fetch', 'build', 'watch', 'serve', 'validate', 'clean'] as $command) {
            static::assertStringContainsString($command, $this->captured->stdout);
        }

        foreach ([
            '--config',
            '--out',
            '--cache',
            '--report',
            '--concurrency',
            '--project',
            '--ref',
            '--manifest',
            '--full',
            '--base-path',
            '--port',
            '--force',
            '--quiet',
            '--verbose',
            '--timings',
            '--no-color',
            '--help',
            '--version',
        ] as $option) {
            static::assertStringContainsString($option, $this->captured->stdout);
        }
    }

    #[Test]
    public function missingConfigExitsTwo(): void
    {
        $missing = Path::join($this->scratch, 'absent.json');

        $exit = $this->application()->run(['asciidoc-php-multi-repo', 'build', '-c', $missing]);

        static::assertSame(ExitCode::ConfigError->value, $exit);
        static::assertStringContainsString('error:', $this->captured->stderr);
    }

    #[Test]
    public function noArgumentsExitsTwoWithUsageOnStandardError(): void
    {
        $exit = $this->application()->run(['asciidoc-php-multi-repo']);

        static::assertSame(ExitCode::ConfigError->value, $exit);
        static::assertSame('', $this->captured->stdout);
        static::assertStringContainsString('no command given', $this->captured->stderr);
        static::assertStringContainsString('asciidoc-php-multi-repo <command> [options]', $this->captured->stderr);
    }

    #[Test]
    public function nonInteractiveRunEmitsNoAnsiEscapes(): void
    {
        $this->application()->run(['asciidoc-php-multi-repo', 'build', '--nope']);

        static::assertStringNotContainsString("\033[", $this->captured->stderr);
        static::assertStringNotContainsString("\033[", $this->captured->stdout);
    }

    #[Test]
    public function quietModeStillReportsConfigurationErrors(): void
    {
        $exit = $this->application()->run([
            'asciidoc-php-multi-repo',
            'build',
            '-c',
            Path::join($this->scratch, 'absent.json'),
            '--quiet',
        ]);

        static::assertSame(ExitCode::ConfigError->value, $exit);
        static::assertStringContainsString('error:', $this->captured->stderr);
    }

    #[Test]
    public function unexpectedExceptionMapsToInternalError(): void
    {
        $command = new class implements CommandInterface {
            public function execute(Options $options, SiteConfig $config, Output $output): ExitCode
            {
                throw new RuntimeException('converter failed');
            }

            public function name(): string
            {
                return 'build';
            }

            public function summary(): string
            {
                return 'Explode.';
            }
        };

        $exit = $this->application($command)->run([
            'asciidoc-php-multi-repo',
            'build',
            '-c',
            $this->fixtureConfig(),
        ]);

        static::assertSame(ExitCode::InternalError->value, $exit);
        static::assertStringContainsString('converter failed', $this->captured->stderr);
    }

    #[Test]
    public function unknownCommandExitsTwo(): void
    {
        $exit = $this->application()->run(['asciidoc-php-multi-repo', 'publish']);

        static::assertSame(ExitCode::ConfigError->value, $exit);
        static::assertStringContainsString('unknown command "publish"', $this->captured->stderr);
    }

    #[Test]
    public function unknownOptionExitsTwoWithUsageOnStandardError(): void
    {
        $exit = $this->application()->run(['asciidoc-php-multi-repo', 'build', '--nope']);

        static::assertSame(ExitCode::ConfigError->value, $exit);
        static::assertStringContainsString('unknown option "--nope"', $this->captured->stderr);
        static::assertStringContainsString('Global options:', $this->captured->stderr);
    }

    #[Test]
    public function unsupportedSchemaVersionExitsTwo(): void
    {
        $path = Path::join($this->scratch, 'site.json');

        /** @var array<string, mixed> $document */
        $document = json_decode(
            (string) file_get_contents($this->fixtureConfig()),
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
        );
        $document['schemaVersion'] = 99;

        file_put_contents($path, json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        $exit = $this->application()->run(['asciidoc-php-multi-repo', 'build', '-c', $path]);

        static::assertSame(ExitCode::ConfigError->value, $exit);
        static::assertStringContainsString('schemaVersion', $this->captured->stderr);
    }

    #[Test]
    public function versionExitsZeroAndPrintsThePackageName(): void
    {
        $exit = $this->application()->run(['asciidoc-php-multi-repo', '--version']);

        static::assertSame(ExitCode::Success->value, $exit);
        static::assertSame("asciidoc-php-multi-repo 1.2.3\n", $this->captured->stdout);
        static::assertSame('', $this->captured->stderr);
    }

    protected function setUp(): void
    {
        $this->captured = new CapturedOutput();
        $this->scratch  = $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory($this->scratch);
    }

    private function application(?CommandInterface $command = null): Application
    {
        return new Application(
            stdout            : $this->captured->stdoutWriter(),
            stderr            : $this->captured->stderrWriter(),
            interactive       : false,
            noColorEnvironment: null,
            version           : '1.2.3',
            commands          : $command instanceof CommandInterface ? [$command] : null,
        );
    }

    private function fixtureConfig(): string
    {
        return Path::join(dirname(__DIR__, levels: 3), 'test', 'fixtures', 'site.json');
    }
}
