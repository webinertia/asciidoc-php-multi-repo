<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli;

use Closure;

use function fclose;
use function fopen;
use function fwrite;
use function getenv;
use function is_resource;
use function is_string;
use function sprintf;
use function str_ends_with;
use function stream_isatty;

/**
 * Owns the two output streams described by contracts/cli.md.
 *
 * Standard output carries the summary only. Standard error carries progress, warnings, detail,
 * timings, and every error. Colour is opt in: it needs an interactive terminal, no `--no-color`, and
 * an unset or empty `NO_COLOR`.
 */
final class Output
{
    private const string RESET = "\033[0m";

    private const string RED = "\033[31m";

    private const string YELLOW = "\033[33m";

    private const string DIM = "\033[2m";

    /** @var array<string, resource> */
    private static array $streams = [];

    /** @var null|Closure(string): void */
    private readonly ?Closure $stdoutWriter;

    /** @var null|Closure(string): void */
    private readonly ?Closure $stderrWriter;

    /**
     * @param null|Closure(string): void $stdout
     * @param null|Closure(string): void $stderr
     */
    public function __construct(
        private readonly bool $quiet = false,
        private readonly bool $verbose = false,
        private readonly bool $timings = false,
        private readonly bool $color = false,
        ?Closure $stdout = null,
        ?Closure $stderr = null,
    ) {
        $this->stdoutWriter = $stdout;
        $this->stderrWriter = $stderr;
    }

    /**
     * Colour is used only when the caller asked for it, the stream is a terminal, and the
     * environment does not opt out.
     */
    public static function colourEnabled(bool $noColor, ?string $environment, bool $interactive): bool
    {
        if ($noColor) {
            return false;
        }

        if (null !== $environment && '' !== $environment) {
            return false;
        }

        return $interactive;
    }

    public static function forCli(Options $options, bool $interactive, ?string $noColorEnvironment = null): self
    {
        $environment = $noColorEnvironment ?? self::noColorEnvironment();

        return new self(
            quiet  : $options->quiet,
            verbose: $options->verbose,
            timings: $options->timings,
            color  : self::colourEnabled($options->noColor, $environment, $interactive),
        );
    }

    public static function noColorEnvironment(): ?string
    {
        $value = getenv('NO_COLOR');

        return is_string($value) ? $value : null;
    }

    public static function stderrIsTty(): bool
    {
        $handle = @fopen('php://stderr', 'wb');

        if (false === $handle) {
            return false;
        }

        $isTty = stream_isatty($handle);
        fclose($handle);

        return $isTty;
    }

    private static function writeStream(string $stream, string $text): void
    {
        $handle = self::$streams[$stream] ?? null;

        if (! is_resource($handle)) {
            $handle = fopen($stream, 'wb');

            if (false === $handle) {
                return;
            }

            self::$streams[$stream] = $handle;
        }

        fwrite($handle, $text);
    }

    /** Standard error. Emitted only under `--verbose`. */
    public function detail(string $text): void
    {
        if ($this->quiet || ! $this->verbose) {
            return;
        }

        $this->toStderr($this->paint($this->line($text), self::DIM));
    }

    /** Standard error. Never suppressed. */
    public function error(string $text): void
    {
        $this->toStderr($this->paint("error: {$text}", self::RED));
    }

    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    /** Standard error. Suppressed by `--quiet`. */
    public function progress(string $text): void
    {
        if ($this->quiet) {
            return;
        }

        $this->toStderr($this->line($text));
    }

    /** Standard output. One block with the counts by status and the output path. */
    public function summary(string $text): void
    {
        $this->write($text);
    }

    /** Standard error. Emitted only under `--timings`. */
    public function timing(string $label, float $seconds): void
    {
        if ($this->quiet || ! $this->timings) {
            return;
        }

        $this->toStderr($this->line(sprintf('%s: %.3fs', $label, $seconds)));
    }

    /** Standard error. Never suppressed, because it accompanies an error. */
    public function usage(string $text): void
    {
        $this->toStderr($this->line($text));
    }

    public function usesColour(): bool
    {
        return $this->color;
    }

    /** Standard error. Suppressed by `--quiet`. */
    public function warning(string $text): void
    {
        if ($this->quiet) {
            return;
        }

        $this->toStderr($this->paint("warning: {$text}", self::YELLOW));
    }

    /** Standard output. The summary, the help text, and the version line. */
    public function write(string $text): void
    {
        $this->toStdout($this->line($text));
    }

    private function line(string $text): string
    {
        if ('' === $text) {
            return "\n";
        }

        return str_ends_with($text, "\n") ? $text : "{$text}\n";
    }

    private function paint(string $text, string $code): string
    {
        $line = $this->line($text);

        if (! $this->color) {
            return $line;
        }

        return $code . $line . self::RESET;
    }

    private function toStderr(string $text): void
    {
        if ($this->stderrWriter instanceof Closure) {
            ($this->stderrWriter)($text);

            return;
        }

        self::writeStream('php://stderr', $text);
    }

    private function toStdout(string $text): void
    {
        if ($this->stdoutWriter instanceof Closure) {
            ($this->stdoutWriter)($text);

            return;
        }

        self::writeStream('php://stdout', $text);
    }
}
