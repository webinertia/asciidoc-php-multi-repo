<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhpMultiRepo\Support;

use Closure;

use function sprintf;

/**
 * Captures what the CLI writes to its two streams so a test can assert on the split.
 */
final class CapturedOutput
{
    public string $stdout = '';

    public string $stderr = '';

    public function describe(): string
    {
        return sprintf("stdout:\n%s\nstderr:\n%s", $this->stdout, $this->stderr);
    }

    /** @return Closure(string): void */
    public function stderrWriter(): Closure
    {
        return function (string $text): void {
            $this->stderr .= $text;
        };
    }

    /** @return Closure(string): void */
    public function stdoutWriter(): Closure
    {
        return function (string $text): void {
            $this->stdout .= $text;
        };
    }
}
