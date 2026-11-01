<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessFailedException;
use Webware\AsciidocPhp\MultiRepo\Support\ProcessRunner;

use const PHP_BINARY;

#[CoversClass(ProcessRunner::class)]
final class ProcessRunnerTest extends TestCase
{
    private ProcessRunner $runner;

    public function testArgumentWithShellMetacharactersIsNotInterpolated(): void
    {
        $argument = 'x; echo injected && echo injected | echo injected';

        $result = $this->runner->run([PHP_BINARY, '-r', 'echo $argv[1];', '--', $argument]);

        self::assertSame($argument, $result->stdout());
    }

    public function testArgumentWithSpaceAndQuoteReachesChildVerbatim(): void
    {
        $argument = 'a b "c" d\'e';

        $result = $this->runner->run([PHP_BINARY, '-r', 'echo $argv[1];', '--', $argument]);

        self::assertTrue($result->successful());
        self::assertSame($argument, $result->stdout());
    }

    public function testCapturesStandardError(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'fwrite(STDERR, "warning");']);

        self::assertTrue($result->successful());
        self::assertSame('warning', $result->stderr());
    }

    public function testCapturesStandardOutput(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'echo "hello";']);

        self::assertTrue($result->successful());
        self::assertSame(0, $result->exitCode());
        self::assertSame('hello', $result->stdout());
        self::assertSame('', $result->stderr());
        self::assertFalse($result->timedOut());
    }

    public function testDescribeCommandListsArguments(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'exit(0);']);

        self::assertStringContainsString(PHP_BINARY, $result->describeCommand());
    }

    public function testMustRunReturnsResultOnSuccess(): void
    {
        $result = $this->runner->mustRun([PHP_BINARY, '-r', 'echo "ok";']);

        self::assertSame('ok', $result->stdout());
    }

    public function testMustRunThrowsWithBothStreamsPreserved(): void
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

    public function testNonZeroExitCodeIsReported(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'exit(3);']);

        self::assertFalse($result->successful());
        self::assertSame(3, $result->exitCode());
        self::assertStringContainsString('exited with code 3', $result->describeFailure());
    }

    public function testTimeoutTerminatesTheProcess(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'sleep(10);'], null, 0.2);

        self::assertTrue($result->timedOut());
        self::assertFalse($result->successful());
        self::assertStringContainsString('timed out', $result->describeFailure());
    }

    public function testWorkingDirectoryIsUsed(): void
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
