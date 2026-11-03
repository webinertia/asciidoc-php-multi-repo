<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Application;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\CommandInterface;

use function array_map;
use function array_unique;
use function array_values;
use function sprintf;

#[CoversClass(Application::class)]
final class CommandStubTest extends TestCase
{
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
    public function testEveryCommandHasANonEmptySummary(string $name): void
    {
        self::assertNotSame('', $this->command($name)->summary());
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

    private function command(string $name): CommandInterface
    {
        foreach (Application::defaultCommands() as $command) {
            if ($command->name() === $name) {
                return $command;
            }
        }

        self::fail(sprintf('Command "%s" is not registered.', $name));
    }
}
