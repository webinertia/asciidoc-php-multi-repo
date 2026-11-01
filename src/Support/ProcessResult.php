<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Support;

use function implode;
use function sprintf;

/**
 * The outcome of one external process invocation.
 */
final readonly class ProcessResult
{
    /**
     * @param list<string> $command
     */
    public function __construct(
        private array $command,
        private int $exitCode,
        private string $stdout,
        private string $stderr,
        private bool $timedOut,
    ) {
    }

    /**
     * @return list<string>
     */
    public function command(): array
    {
        return $this->command;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function stdout(): string
    {
        return $this->stdout;
    }

    public function stderr(): string
    {
        return $this->stderr;
    }

    public function timedOut(): bool
    {
        return $this->timedOut;
    }

    public function successful(): bool
    {
        return ! $this->timedOut && $this->exitCode === 0;
    }

    public function describeCommand(): string
    {
        return implode(' ', $this->command);
    }

    public function describeFailure(): string
    {
        if ($this->timedOut) {
            return sprintf('Command "%s" timed out.', $this->describeCommand());
        }

        return sprintf(
            'Command "%s" exited with code %d.',
            $this->describeCommand(),
            $this->exitCode,
        );
    }
}
