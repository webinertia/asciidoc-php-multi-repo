<?php

declare(strict_types=1);

namespace Webware\AsciidocPhp\MultiRepo\Cli;

use RuntimeException;

use function sprintf;

/**
 * Raised when the command line cannot be parsed. Every message is safe to print on standard error.
 */
final class UsageException extends RuntimeException
{
    public static function invalidValue(string $option, string $value, string $expected): self
    {
        return new self(sprintf('invalid value "%s" for option "%s": expected %s', $value, $option, $expected));
    }

    public static function missingValue(string $option): self
    {
        return new self(sprintf('option "%s" requires a value', $option));
    }

    public static function unexpectedArgument(string $argument): self
    {
        return new self(sprintf('unexpected argument "%s"', $argument));
    }

    public static function unexpectedValue(string $option): self
    {
        return new self(sprintf('option "%s" does not take a value', $option));
    }

    public static function unknownCommand(string $command): self
    {
        return new self(sprintf('unknown command "%s"', $command));
    }

    public static function unknownOption(string $option): self
    {
        return new self(sprintf('unknown option "%s"', $option));
    }
}
