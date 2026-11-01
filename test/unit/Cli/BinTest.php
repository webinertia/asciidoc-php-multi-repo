<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Cli\ExitCode;
use Webware\AsciidocPhpMultiRepo\Support\Path;
use Webware\AsciidocPhpMultiRepo\Support\ProcessRunner;
use WebwareTest\AsciidocPhpMultiRepo\Support\TempDirectoryTrait;

use function dirname;

use const PHP_BINARY;

/**
 * Runs the real entry point in a child process, which is the only way to prove the autoloader
 * lookup, the argument vector handoff, and the process exit code.
 */
#[CoversNothing]
final class BinTest extends TestCase
{
    use TempDirectoryTrait;

    private string $scratch;

    public function testAnAbsoluteConfigResolvesFromAnotherWorkingDirectory(): void
    {
        $result = new ProcessRunner($this->scratch)->run([
            PHP_BINARY,
            $this->binary(),
            'validate',
            '-c',
            $this->fixtureConfig(),
        ]);

        self::assertSame(ExitCode::InternalError->value, $result->exitCode(), $result->describeFailure());
        self::assertStringContainsString('validate: not implemented', $result->stderr());
        self::assertStringNotContainsString('unable to locate', $result->stderr());
    }

    public function testHelpExitsZero(): void
    {
        $result = new ProcessRunner($this->root())->run([PHP_BINARY, $this->binary(), '--help']);

        self::assertSame(ExitCode::Success->value, $result->exitCode(), $result->describeFailure());
        self::assertStringContainsString('Global options:', $result->stdout());
        self::assertStringContainsString('--manifest', $result->stdout());
        self::assertSame('', $result->stderr());
    }

    public function testNoArgumentsPrintsUsageAndExitsTwo(): void
    {
        $result = new ProcessRunner($this->root())->run([PHP_BINARY, $this->binary()]);

        self::assertSame(ExitCode::ConfigError->value, $result->exitCode(), $result->describeFailure());
        self::assertSame('', $result->stdout());
        self::assertStringContainsString('asciidoc-php-multi-repo <command> [options]', $result->stderr());
    }

    protected function setUp(): void
    {
        $this->scratch = $this->createTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory($this->scratch);
    }

    private function binary(): string
    {
        return Path::join($this->root(), 'bin', 'asciidoc-php-multi-repo');
    }

    private function fixtureConfig(): string
    {
        return Path::join($this->root(), 'test', 'fixtures', 'site.json');
    }

    private function root(): string
    {
        return dirname(__DIR__, 3);
    }
}
