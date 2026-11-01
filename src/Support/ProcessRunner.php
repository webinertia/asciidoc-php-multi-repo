<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Support;

use InvalidArgumentException;
use RuntimeException;

use function count;
use function fclose;
use function implode;
use function is_resource;
use function is_string;
use function microtime;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function sprintf;
use function stream_get_contents;
use function stream_select;
use function stream_set_blocking;
use function usleep;

/**
 * Runs external processes with an argument array, never a shell string.
 *
 * The argument array form of proc_open is used together with the Windows bypass_shell option so
 * that no argument is ever interpreted by a shell. Standard output and standard error are
 * captured independently and a timeout terminates the process.
 */
final class ProcessRunner
{
    private const READ_TIMEOUT_MICROSECONDS = 200_000;

    private const TERMINATION_GRACE_SECONDS = 2.0;

    public function __construct(
        private readonly ?string $workingDirectory = null,
    ) {}

    /**
     * @param list<string>               $command
     * @param array<string, string>|null $environment
     */
    public function mustRun(
        array $command,
        ?string $workingDirectory = null,
        float $timeoutSeconds = 30.0,
        ?array $environment = null,
    ): ProcessResult {
        $result = $this->run($command, $workingDirectory, $timeoutSeconds, $environment);

        if (! $result->successful()) {
            throw new ProcessFailedException($result);
        }

        return $result;
    }

    /**
     * @param list<string>               $command
     * @param array<string, string>|null $environment
     */
    public function run(
        array $command,
        ?string $workingDirectory = null,
        float $timeoutSeconds = 30.0,
        ?array $environment = null,
    ): ProcessResult {
        if ([] === $command) {
            throw new InvalidArgumentException('A process command must contain at least one argument.');
        }

        if ($timeoutSeconds <= 0.0) {
            throw new InvalidArgumentException('The process timeout must be greater than zero.');
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open(
            $command,
            $descriptors,
            $pipes,
            $workingDirectory ?? $this->workingDirectory,
            $environment,
            ['bypass_shell' => true],
        );

        if (! is_resource($process) || count($pipes) < 3) {
            throw new RuntimeException(sprintf(
                'Unable to start command "%s".',
                implode(' ', $command),
            ));
        }

        [$stdin, $stdoutPipe, $stderrPipe] = [$pipes[0], $pipes[1], $pipes[2]];

        fclose($stdin);
        stream_set_blocking($stdoutPipe, false);
        stream_set_blocking($stderrPipe, false);

        $stdout   = '';
        $stderr   = '';
        $timedOut = false;
        $exitCode = -1;
        $deadline = microtime(true) + $timeoutSeconds;

        while (true) {
            $read   = [$stdoutPipe, $stderrPipe];
            $write  = null;
            $except = null;

            if (@stream_select($read, $write, $except, 0, self::READ_TIMEOUT_MICROSECONDS) === false) {
                $read = [];
            }

            foreach ($read as $stream) {
                $chunk = stream_get_contents($stream);
                if (! is_string($chunk) || '' === $chunk) {
                    continue;
                }

                if ($stream === $stdoutPipe) {
                    $stdout .= $chunk;
                    continue;
                }

                $stderr .= $chunk;
            }

            $status = proc_get_status($process);

            if (! $status['running']) {
                $exitCode = (int) $status['exitcode'];
                break;
            }

            if (microtime(true) < $deadline) {
                continue;
            }

            $timedOut = true;
            proc_terminate($process);
            $exitCode = $this->awaitTermination($process);
            break;
        }

        $remaining = stream_get_contents($stdoutPipe);
        if (is_string($remaining)) {
            $stdout .= $remaining;
        }

        $remaining = stream_get_contents($stderrPipe);
        if (is_string($remaining)) {
            $stderr .= $remaining;
        }

        fclose($stdoutPipe);
        fclose($stderrPipe);

        $closeCode = proc_close($process);
        if ($exitCode === -1 && $closeCode >= 0) {
            $exitCode = $closeCode;
        }

        return new ProcessResult($command, $exitCode, $stdout, $stderr, $timedOut);
    }

    /**
     * @param resource $process
     */
    private function awaitTermination($process): int
    {
        $deadline = microtime(true) + self::TERMINATION_GRACE_SECONDS;

        while (microtime(true) < $deadline) {
            $status = proc_get_status($process);

            if (! $status['running']) {
                return (int) $status['exitcode'];
            }

            usleep(10_000);
        }

        return -1;
    }
}
