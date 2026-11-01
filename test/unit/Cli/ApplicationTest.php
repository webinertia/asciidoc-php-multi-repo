<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webware\AsciidocPhpMultiRepo\Cli\Application;
use Webware\AsciidocPhpMultiRepo\Cli\Commands\CommandInterface;
use Webware\AsciidocPhpMultiRepo\Cli\ExitCode;
use Webware\AsciidocPhpMultiRepo\Cli\Options;
use Webware\AsciidocPhpMultiRepo\Cli\Output;
use Webware\AsciidocPhpMultiRepo\Config\SiteConfig;
use Webware\AsciidocPhpMultiRepo\Support\Path;
use WebwareTest\AsciidocPhpMultiRepo\Support\CapturedOutput;
use WebwareTest\AsciidocPhpMultiRepo\Support\RecordingCommand;
use WebwareTest\AsciidocPhpMultiRepo\Support\TempDirectoryTrait;

use function dirname;
use function file_put_contents;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    use TempDirectoryTrait;

    private CapturedOutput $captured;

    private string $scratch;

    public function testCommandReceivesParsedOptionsAndTheValidatedConfig(): void
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

        self::assertSame(ExitCode::Degraded->value, $exit);
        self::assertSame(1, $command->calls);
        self::assertInstanceOf(Options::class, $command->options);
        self::assertSame(['message-bus'], $command->options->projects);
        self::assertTrue($command->options->full);
        self::assertSame('Fixture Site', $command->config?->site()->name());
        self::assertInstanceOf(Output::class, $command->output);
    }

    public function testHelpExitsZeroAndListsEveryCommandAndOption(): void
    {
        $exit = $this->application()->run(['asciidoc-php-multi-repo', '--help']);

        self::assertSame(ExitCode::Success->value, $exit);
        self::assertSame('', $this->captured->stderr);

        foreach (['discover', 'fetch', 'build', 'watch', 'serve', 'validate', 'clean'] as $command) {
            self::assertStringContainsString($command, $this->captured->stdout);
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
            self::assertStringContainsString($option, $this->captured->stdout);
        }
    }

    public function testMissingConfigExitsTwo(): void
    {
        $missing = Path::join($this->scratch, 'absent.json');

        $exit = $this->application()->run(['asciidoc-php-multi-repo', 'build', '-c', $missing]);

        self::assertSame(ExitCode::ConfigError->value, $exit);
        self::assertStringContainsString('error:', $this->captured->stderr);
    }

    public function testNoArgumentsExitsTwoWithUsageOnStandardError(): void
    {
        $exit = $this->application()->run(['asciidoc-php-multi-repo']);

        self::assertSame(ExitCode::ConfigError->value, $exit);
        self::assertSame('', $this->captured->stdout);
        self::assertStringContainsString('no command given', $this->captured->stderr);
        self::assertStringContainsString('asciidoc-php-multi-repo <command> [options]', $this->captured->stderr);
    }

    public function testNonInteractiveRunEmitsNoAnsiEscapes(): void
    {
        $this->application()->run(['asciidoc-php-multi-repo', 'build', '--nope']);

        self::assertStringNotContainsString("\033[", $this->captured->stderr);
        self::assertStringNotContainsString("\033[", $this->captured->stdout);
    }

    public function testQuietModeStillReportsConfigurationErrors(): void
    {
        $exit = $this->application()->run([
            'asciidoc-php-multi-repo',
            'build',
            '-c',
            Path::join($this->scratch, 'absent.json'),
            '--quiet',
        ]);

        self::assertSame(ExitCode::ConfigError->value, $exit);
        self::assertStringContainsString('error:', $this->captured->stderr);
    }

    public function testStubCommandExitsFour(): void
    {
        $exit = $this->application()->run([
            'asciidoc-php-multi-repo',
            'build',
            '-c',
            $this->fixtureConfig(),
        ]);

        self::assertSame(ExitCode::InternalError->value, $exit);
        self::assertStringContainsString('build: not implemented', $this->captured->stderr);
        self::assertSame('', $this->captured->stdout);
    }

    public function testUnexpectedExceptionMapsToInternalError(): void
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

        self::assertSame(ExitCode::InternalError->value, $exit);
        self::assertStringContainsString('converter failed', $this->captured->stderr);
    }

    public function testUnknownCommandExitsTwo(): void
    {
        $exit = $this->application()->run(['asciidoc-php-multi-repo', 'publish']);

        self::assertSame(ExitCode::ConfigError->value, $exit);
        self::assertStringContainsString('unknown command "publish"', $this->captured->stderr);
    }

    public function testUnknownOptionExitsTwoWithUsageOnStandardError(): void
    {
        $exit = $this->application()->run(['asciidoc-php-multi-repo', 'build', '--nope']);

        self::assertSame(ExitCode::ConfigError->value, $exit);
        self::assertStringContainsString('unknown option "--nope"', $this->captured->stderr);
        self::assertStringContainsString('Global options:', $this->captured->stderr);
    }

    public function testUnsupportedSchemaVersionExitsTwo(): void
    {
        $path = Path::join($this->scratch, 'site.json');

        /** @var array<string, mixed> $document */
        $document = json_decode(
            (string) file_get_contents($this->fixtureConfig()),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $document['schemaVersion'] = 99;

        file_put_contents($path, json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        $exit = $this->application()->run(['asciidoc-php-multi-repo', 'build', '-c', $path]);

        self::assertSame(ExitCode::ConfigError->value, $exit);
        self::assertStringContainsString('schemaVersion', $this->captured->stderr);
    }

    public function testVersionExitsZeroAndPrintsThePackageName(): void
    {
        $exit = $this->application()->run(['asciidoc-php-multi-repo', '--version']);

        self::assertSame(ExitCode::Success->value, $exit);
        self::assertSame("asciidoc-php-multi-repo 1.2.3\n", $this->captured->stdout);
        self::assertSame('', $this->captured->stderr);
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
        return Path::join(dirname(__DIR__, 3), 'test', 'fixtures', 'site.json');
    }
}
