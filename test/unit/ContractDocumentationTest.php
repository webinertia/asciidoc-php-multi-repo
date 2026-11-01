<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Application;
use Webware\AsciidocPhp\MultiRepo\Cli\Commands\CommandInterface;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use WebwareTest\AsciidocPhp\MultiRepo\Support\CapturedOutput;

use function array_map;
use function array_slice;
use function dirname;
use function explode;
use function file_get_contents;
use function in_array;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Ties the published CLI contract to the code. Every option, command, and exit code in
 * `contracts/cli.md` must exist, and every exit code in the code must be documented.
 */
#[CoversNothing]
final class ContractDocumentationTest extends TestCase
{
    private CapturedOutput $captured;

    private string $contract;

    public function testEveryDocumentedCommandIsRegistered(): void
    {
        $registered = array_map(
            static fn(CommandInterface $command): string => $command->name(),
            Application::defaultCommands(),
        );

        $documented = $this->documentedCommands();

        self::assertNotSame([], $documented, 'No commands were found in contracts/cli.md.');

        foreach ($documented as $command) {
            self::assertContains(
                $command,
                $registered,
                sprintf('Command "%s" is documented but not registered.', $command),
            );
        }
    }

    public function testEveryDocumentedExitCodeMatchesTheEnum(): void
    {
        $documented = $this->documentedExitCodes();

        self::assertCount(5, $documented, 'The exit code table must document five codes.');

        foreach ($documented as $name => $code) {
            $case = ExitCode::from($code);

            self::assertSame($name, $case->label(), sprintf('Exit code %d is documented as "%s".', $code, $name));
            self::assertSame($code, $case->value);
        }
    }

    public function testEveryDocumentedOptionAppearsInTheHelpText(): void
    {
        $help = $this->application()->usage();

        foreach ($this->documentedOptions() as $option) {
            self::assertStringContainsString(
                $option['flag'],
                $help,
                sprintf('Option "%s" is documented but missing from the help text.', $option['flag']),
            );
        }
    }

    public function testEveryDocumentedOptionIsAccepted(): void
    {
        $options = $this->documentedOptions();

        self::assertNotSame([], $options, 'No options were found in contracts/cli.md.');

        foreach ($options as $option) {
            $arguments = ['asciidoc-php-multi-repo', '--help', $option['flag']];

            if ($option['takesValue']) {
                $arguments[] = $this->sampleValue($option['flag']);
            }

            $exit = $this->application()->run($arguments);

            self::assertSame(
                ExitCode::Success->value,
                $exit,
                sprintf('Option "%s" is documented but was not accepted: %s', $option['flag'], $this->captured->stderr),
            );
            self::assertSame('', $this->captured->stderr, sprintf('Option "%s" produced an error.', $option['flag']));
        }
    }

    public function testEveryEnumExitCodeIsDocumented(): void
    {
        $documented = $this->documentedExitCodes();

        foreach (ExitCode::cases() as $case) {
            self::assertArrayHasKey(
                $case->label(),
                $documented,
                sprintf('Exit code "%s" is not documented in contracts/cli.md.', $case->label()),
            );
            self::assertSame($case->value, $documented[$case->label()]);
        }
    }

    protected function setUp(): void
    {
        $this->captured = new CapturedOutput();
        $contents       = file_get_contents(Path::join(dirname(__DIR__, 2), 'contracts', 'cli.md'));

        self::assertIsString($contents);

        $this->contract = $contents;
    }

    private function application(): Application
    {
        return new Application(
            stdout            : $this->captured->stdoutWriter(),
            stderr            : $this->captured->stderrWriter(),
            interactive       : false,
            noColorEnvironment: null,
            version           : '1.2.3',
        );
    }

    /**
     * @return list<string>
     */
    private function documentedCommands(): array
    {
        $commands = [];

        foreach ($this->tableRows('Commands') as $row) {
            $name = trim($row[0] ?? '', ' `');

            if ('' !== $name) {
                $commands[] = $name;
            }
        }

        return $commands;
    }

    /**
     * @return array<string, int>
     */
    private function documentedExitCodes(): array
    {
        $codes = [];

        foreach ($this->tableRows('Exit codes') as $row) {
            $code = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');

            if ('' === $code || '' === $name || preg_match('/^[0-9]+$/', $code) !== 1) {
                continue;
            }

            $codes[$name] = (int) $code;
        }

        return $codes;
    }

    /**
     * @return list<array{flag: string, takesValue: bool}>
     */
    private function documentedOptions(): array
    {
        $options = [];

        foreach ($this->tableRows('Global options') as $row) {
            $matches = [];
            preg_match_all('/`(-{1,2}[a-zA-Z][a-zA-Z-]*)`/', $row[0] ?? '', $matches);

            $takesValue = trim($row[1] ?? '') !== 'flag';

            foreach ($matches[1] as $flag) {
                $options[] = ['flag' => $flag, 'takesValue' => $takesValue];
            }
        }

        return $options;
    }

    private function sampleValue(string $flag): string
    {
        return match ($flag) {
            '--concurrency' => '2',
            '--port'        => '8080',
            '--ref'         => 'example=main',
            default         => 'value',
        };
    }

    /**
     * @return list<list<string>>
     */
    private function tableRows(string $heading): array
    {
        $rows   = [];
        $inside = false;

        foreach (explode("\n", $this->contract) as $line) {
            if (str_starts_with($line, '## ')) {
                $inside = trim(substr($line, 3)) === $heading;

                continue;
            }

            if (! $inside || ! str_starts_with(trim($line), '|')) {
                continue;
            }

            $cells = array_map('trim', explode('|', trim($line)));
            $cells = array_slice($cells, 1, -1);

            if ([] === $cells || preg_match('/^-+$/', $cells[0]) === 1) {
                continue;
            }

            if (in_array($cells[0], ['Option', 'Command', 'Code'], true)) {
                continue;
            }

            $rows[] = $cells;
        }

        return $rows;
    }
}
