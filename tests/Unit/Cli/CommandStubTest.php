<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Cli\Application;
use Webware\AsciidocPhpMultiRepo\Cli\Commands\CommandInterface;
use Webware\AsciidocPhpMultiRepo\Cli\Commands\StubCommand;
use Webware\AsciidocPhpMultiRepo\Cli\ExitCode;
use Webware\AsciidocPhpMultiRepo\Cli\Options;
use Webware\AsciidocPhpMultiRepo\Cli\Output;
use Webware\AsciidocPhpMultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhpMultiRepo\Support\Path;
use Webware\AsciidocPhpMultiRepo\Test\Support\CapturedOutput;

use function array_map;
use function array_unique;
use function array_values;
use function dirname;
use function sprintf;

#[CoversClass(StubCommand::class)]
final class CommandStubTest extends TestCase
{
    private CapturedOutput $captured;

    /**
     * @return array<string, array{string}>
     */
    public static function contractCommands(): array
    {
        return [
            'discover' => ['discover'],
            'fetch'    => ['fetch'],
            'build'    => ['build'],
            'watch'    => ['watch'],
            'serve'    => ['serve'],
            'validate' => ['validate'],
            'clean'    => ['clean'],
        ];
    }

    #[DataProvider('contractCommands')]
    public function testEveryContractCommandIsRegistered(string $name): void
    {
        $names = array_map(
            static fn(CommandInterface $command): string => $command->name(),
            Application::defaultCommands(),
        );

        self::assertContains($name, $names);
    }

    #[DataProvider('contractCommands')]
    public function testEveryStubHasANonEmptySummary(string $name): void
    {
        self::assertNotSame('', $this->command($name)->summary());
    }

    #[DataProvider('contractCommands')]
    public function testStubReturnsInternalErrorAndSaysItIsNotImplemented(string $name): void
    {
        $command = $this->command($name);
        $config  = new SiteConfigLoader()->load($this->fixtureConfig());

        $exit = $command->execute(new Options(command: $name), $config, $this->makeOutput());

        self::assertSame(ExitCode::InternalError, $exit);
        self::assertSame(sprintf("error: %s: not implemented\n", $name), $this->captured->stderr);
        self::assertSame('', $this->captured->stdout);
    }

    public function testTheRegistryHasNoDuplicateNames(): void
    {
        $names = array_map(
            static fn(CommandInterface $command): string => $command->name(),
            Application::defaultCommands(),
        );

        self::assertSame($names, array_values(array_unique($names)));
        self::assertCount(7, $names);
    }

    protected function setUp(): void
    {
        $this->captured = new CapturedOutput();
    }

    private function command(string $name): CommandInterface
    {
        foreach (Application::defaultCommands() as $command) {
            if ($command->name() === $name) {
                return $command;
            }
        }

        self::fail(sprintf('Command "%s" is not registered.', $name));
    }

    private function fixtureConfig(): string
    {
        return Path::join(dirname(__DIR__, 3), 'tests', 'fixtures', 'site.json');
    }

    private function makeOutput(): Output
    {
        return new Output(
            stdout: $this->captured->stdoutWriter(),
            stderr: $this->captured->stderrWriter(),
        );
    }
}
