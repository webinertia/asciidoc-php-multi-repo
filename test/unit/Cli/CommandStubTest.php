<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    #[DataProvider('contractCommands')]
    public function everyCommandHasANonEmptySummary(string $name): void
    {
        static::assertNotSame('', $this->command($name)->summary());
    }

    #[Test]
    #[DataProvider('contractCommands')]
    public function everyContractCommandIsRegistered(string $name): void
    {
        $names = array_map(
            static fn(CommandInterface $command): string => $command->name(),
            Application::defaultCommands(),
        );

        static::assertContains($name, $names);
    }

    #[Test]
    public function theRegistryHasNoDuplicateNames(): void
    {
        $names = array_map(
            static fn(CommandInterface $command): string => $command->name(),
            Application::defaultCommands(),
        );

        static::assertSame($names, array_values(array_unique($names)));
        static::assertCount(7, $names);
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
