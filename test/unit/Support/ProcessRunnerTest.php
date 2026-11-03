<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessFailedException;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessRunner;

use const PHP_BINARY;

#[CoversClass(ProcessRunner::class)]
final class ProcessRunnerTest extends TestCase
{
    private ProcessRunner $runner;

    #[Test]
    public function argumentWithShellMetacharactersIsNotInterpolated(): void
    {
        $argument = 'x; echo injected && echo injected | echo injected';

        $result = $this->runner->run([PHP_BINARY, '-r', 'echo $argv[1];', '--', $argument]);

        self::assertSame($argument, $result->stdout());
    }

    #[Test]
    public function argumentWithSpaceAndQuoteReachesChildVerbatim(): void
    {
        $argument = 'a b "c" d\'e';

        $result = $this->runner->run([PHP_BINARY, '-r', 'echo $argv[1];', '--', $argument]);

        self::assertTrue($result->successful());
        self::assertSame($argument, $result->stdout());
    }

    #[Test]
    public function capturesStandardError(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'fwrite(STDERR, "warning");']);

        self::assertTrue($result->successful());
        self::assertSame('warning', $result->stderr());
    }

    #[Test]
    public function capturesStandardOutput(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'echo "hello";']);

        self::assertTrue($result->successful());
        self::assertSame(0, $result->exitCode());
        self::assertSame('hello', $result->stdout());
        self::assertSame('', $result->stderr());
        self::assertFalse($result->timedOut());
    }

    #[Test]
    public function describeCommandListsArguments(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'exit(0);']);

        self::assertStringContainsString(PHP_BINARY, $result->describeCommand());
    }

    #[Test]
    public function mustRunReturnsResultOnSuccess(): void
    {
        $result = $this->runner->mustRun([PHP_BINARY, '-r', 'echo "ok";']);

        self::assertSame('ok', $result->stdout());
    }

    #[Test]
    public function mustRunThrowsWithBothStreamsPreserved(): void
    {
        try {
            $this->runner->mustRun([PHP_BINARY, '-r', 'echo "out"; fwrite(STDERR, "boom"); exit(2);']);
            self::fail('A non-zero exit should have raised an exception.');
        } catch (ProcessFailedException $exception) {
            self::assertSame(2, $exception->result()->exitCode());
            self::assertSame('out', $exception->result()->stdout());
            self::assertSame('boom', $exception->result()->stderr());
            self::assertStringContainsString('boom', $exception->getMessage());
        }
    }

    #[Test]
    public function nonZeroExitCodeIsReported(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'exit(3);']);

        self::assertFalse($result->successful());
        self::assertSame(3, $result->exitCode());
        self::assertStringContainsString('exited with code 3', $result->describeFailure());
    }

    #[Test]
    public function timeoutTerminatesTheProcess(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'sleep(10);'], null, 0.2);

        self::assertTrue($result->timedOut());
        self::assertFalse($result->successful());
        self::assertStringContainsString('timed out', $result->describeFailure());
    }

    #[Test]
    public function workingDirectoryIsUsed(): void
    {
        $directory = sys_get_temp_dir();

        $result = $this->runner->run([PHP_BINARY, '-r', 'echo getcwd();'], $directory);

        self::assertTrue($result->successful());
        self::assertSame(
            strtolower(str_replace('\\', '/', (string) realpath($directory))),
            strtolower(str_replace('\\', '/', $result->stdout())),
        );
    }

    protected function setUp(): void
    {
        $this->runner = new ProcessRunner();
    }
}
