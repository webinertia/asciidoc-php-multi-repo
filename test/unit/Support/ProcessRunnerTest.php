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

        static::assertSame($argument, $result->stdout());
    }

    #[Test]
    public function argumentWithSpaceAndQuoteReachesChildVerbatim(): void
    {
        $argument = 'a b "c" d\'e';

        $result = $this->runner->run([PHP_BINARY, '-r', 'echo $argv[1];', '--', $argument]);

        static::assertTrue($result->successful());
        static::assertSame($argument, $result->stdout());
    }

    #[Test]
    public function capturesStandardError(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'fwrite(STDERR, "warning");']);

        static::assertTrue($result->successful());
        static::assertSame('warning', $result->stderr());
    }

    #[Test]
    public function capturesStandardOutput(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'echo "hello";']);

        static::assertTrue($result->successful());
        static::assertSame(0, $result->exitCode());
        static::assertSame('hello', $result->stdout());
        static::assertSame('', $result->stderr());
        static::assertFalse($result->timedOut());
    }

    #[Test]
    public function describeCommandListsArguments(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'exit(0);']);

        static::assertStringContainsString(PHP_BINARY, $result->describeCommand());
    }

    #[Test]
    public function mustRunReturnsResultOnSuccess(): void
    {
        $result = $this->runner->mustRun([PHP_BINARY, '-r', 'echo "ok";']);

        static::assertSame('ok', $result->stdout());
    }

    #[Test]
    public function mustRunThrowsWithBothStreamsPreserved(): void
    {
        try {
            $this->runner->mustRun([PHP_BINARY, '-r', 'echo "out"; fwrite(STDERR, "boom"); exit(2);']);
            self::fail('A non-zero exit should have raised an exception.');
        } catch (ProcessFailedException $exception) {
            static::assertSame(2, $exception->result()->exitCode());
            static::assertSame('out', $exception->result()->stdout());
            static::assertSame('boom', $exception->result()->stderr());
            static::assertStringContainsString('boom', $exception->getMessage());
        }
    }

    #[Test]
    public function nonZeroExitCodeIsReported(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'exit(3);']);

        static::assertFalse($result->successful());
        static::assertSame(3, $result->exitCode());
        static::assertStringContainsString('exited with code 3', $result->describeFailure());
    }

    #[Test]
    public function timeoutTerminatesTheProcess(): void
    {
        $result = $this->runner->run([PHP_BINARY, '-r', 'sleep(10);'], null, 0.2);

        static::assertTrue($result->timedOut());
        static::assertFalse($result->successful());
        static::assertStringContainsString('timed out', $result->describeFailure());
    }

    #[Test]
    public function workingDirectoryIsUsed(): void
    {
        $directory = sys_get_temp_dir();

        $result = $this->runner->run([PHP_BINARY, '-r', 'echo getcwd();'], $directory);

        static::assertTrue($result->successful());
        static::assertSame(
            strtolower(str_replace('\\', replace: '/', subject: (string) realpath($directory))),
            strtolower(str_replace('\\', replace: '/', subject: $result->stdout())),
        );
    }

    protected function setUp(): void
    {
        $this->runner = new ProcessRunner();
    }
}
