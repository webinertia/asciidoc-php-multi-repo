<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Application;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\CommandInterface;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\StubCommand;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use Webware\AsciidocPhp\MultiRepo\Config\SiteConfigLoader;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\CapturedOutput;

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

    /**
     * The commands that rendering will implement are still stubs. `discover`, `fetch` and `build`
     * are not.
     *
     * @return array<string, array{string}>
     */
    public static function unimplementedCommands(): array
    {
        return [
            'watch' => ['watch'],
            'serve' => ['serve'],
            'clean' => ['clean'],
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

    #[DataProvider('unimplementedCommands')]
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

    #[DataProvider('unimplementedCommands')]
    public function testTheUnimplementedCommandsAreStubs(string $name): void
    {
        self::assertInstanceOf(StubCommand::class, $this->command($name));
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
        return Path::join(dirname(__DIR__, 3), 'test', 'fixtures', 'site.json');
    }

    private function makeOutput(): Output
    {
        return new Output(
            stdout: $this->captured->stdoutWriter(),
            stderr: $this->captured->stderrWriter(),
        );
    }
}
