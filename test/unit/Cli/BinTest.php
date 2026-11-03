<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\ExitCode;
use Webware\AsciidocPhp\MultiRepo\Support\Path;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessRunner;
use WebwareTest\AsciidocPhp\MultiRepo\Support\TempDirectoryTrait;

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

    #[Test]
    public function anAbsoluteConfigResolvesFromAnotherWorkingDirectory(): void
    {
        $result = new ProcessRunner($this->scratch)->run([
            PHP_BINARY,
            $this->binary(),
            'validate',
            '-c',
            $this->fixtureConfig(),
            '--manifest',
            $this->fixtureManifest(),
        ]);

        self::assertSame(ExitCode::Degraded->value, $result->exitCode(), $result->describeFailure());
        self::assertStringContainsString('versioned: ok', $result->stderr());
        self::assertStringContainsString('broken-include: partial', $result->stderr());
        self::assertStringContainsString('markdown-only: failed', $result->stderr());
        self::assertStringContainsString('no site output was written', $result->stdout());
        self::assertStringNotContainsString('unable to locate', $result->stderr());
    }

    #[Test]
    public function helpExitsZero(): void
    {
        $result = new ProcessRunner($this->root())->run([PHP_BINARY, $this->binary(), '--help']);

        self::assertSame(ExitCode::Success->value, $result->exitCode(), $result->describeFailure());
        self::assertStringContainsString('Global options:', $result->stdout());
        self::assertStringContainsString('--manifest', $result->stdout());
        self::assertSame('', $result->stderr());
    }

    #[Test]
    public function noArgumentsPrintsUsageAndExitsTwo(): void
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

    private function fixtureManifest(): string
    {
        return Path::join($this->root(), 'test', 'fixtures', 'manifest.json');
    }

    private function root(): string
    {
        return dirname(__DIR__, 3);
    }
}
