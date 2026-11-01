<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Cli;

use SensitiveParameter;

use function count;
use function in_array;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * Parses the argument vector into an immutable Options instance.
 *
 * Accepted forms are `--name value`, `--name=value`, `-c value`, and clusters of single letter
 * boolean flags such as `-qv`. A bare `--` ends option parsing. Anything after the command that is
 * not an option is an error, because the contract has no positional arguments.
 */
final class OptionsParser
{
    /** Long option name to Options property name for options that take a value. */
    private const array VALUE_OPTIONS = [
        '--config'      => 'config',
        '--out'         => 'out',
        '--output'      => 'out',
        '--cache'       => 'cache',
        '--report'      => 'report',
        '--concurrency' => 'concurrency',
        '--project'     => 'project',
        '--ref'         => 'ref',
        '--manifest'    => 'manifest',
        '--base-path'   => 'basePath',
        '--port'        => 'port',
    ];

    /** Long option name to Options property name for boolean flags. */
    private const array FLAG_OPTIONS = [
        '--full'     => 'full',
        '--force'    => 'force',
        '--quiet'    => 'quiet',
        '--verbose'  => 'verbose',
        '--timings'  => 'timings',
        '--no-color' => 'noColor',
        '--help'     => 'help',
        '--version'  => 'version',
    ];

    /** Single letter option to the Options property it sets and whether it takes a value. */
    private const array SHORT_OPTIONS = [
        '-c' => ['value', 'config'],
        '-o' => ['value', 'out'],
        '-q' => ['flag', 'quiet'],
        '-v' => ['flag', 'verbose'],
        '-t' => ['flag', 'timings'],
        '-h' => ['flag', 'help'],
    ];

    /** Single letter options that may be clustered, for example `-qvt`. */
    private const array SHORT_FLAGS = ['q', 'v', 't', 'h'];

    /**
     * @param list<string> $arguments Arguments with the program name already removed.
     *
     * @throws UsageException
     */
    public function parse(array $arguments): Options
    {
        /** @var array<string, string> $values */
        $values = [];
        /** @var array<string, true> $flags */
        $flags = [];
        /** @var list<string> $projects */
        $projects = [];
        /** @var array<string, string> $refs */
        $refs = [];
        /** @var list<string> $positional */
        $positional = [];

        $index = 0;
        $count = count($arguments);

        while ($index < $count) {
            $token = $arguments[$index];
            $index++;

            if ('--' === $token) {
                while ($index < $count) {
                    $positional[] = $arguments[$index];
                    $index++;
                }

                break;
            }

            if ('-' === $token) {
                throw UsageException::unexpectedArgument($token);
            }

            if (str_starts_with($token, '--')) {
                $this->parseLongOption($token, $arguments, $index, $values, $flags, $projects, $refs);

                continue;
            }

            if (str_starts_with($token, '-')) {
                $this->parseShortOption($token, $arguments, $index, $values, $flags);

                continue;
            }

            $positional[] = $token;
        }

        if (count($positional) > 1) {
            throw UsageException::unexpectedArgument($positional[1]);
        }

        $concurrency = null;
        if (isset($values['concurrency'])) {
            $concurrency = $this->integer('--concurrency', $values['concurrency'], 1);
        }

        $port = Options::DEFAULT_PORT;
        if (isset($values['port'])) {
            $port = $this->integer('--port', $values['port'], 1, 65_535);
        }

        return new Options(
            command    : $positional[0] ?? '',
            config     : $values['config'] ?? null,
            out        : $values['out'] ?? null,
            cache      : $values['cache'] ?? null,
            report     : $values['report'] ?? null,
            concurrency: $concurrency,
            projects   : $projects,
            refs       : $refs,
            manifest   : $values['manifest'] ?? null,
            full       : isset($flags['full']),
            basePath   : $values['basePath'] ?? null,
            port       : $port,
            force      : isset($flags['force']),
            quiet      : isset($flags['quiet']),
            verbose    : isset($flags['verbose']),
            timings    : isset($flags['timings']),
            noColor    : isset($flags['noColor']),
            help       : isset($flags['help']),
            version    : isset($flags['version']),
        );
    }

    /**
     * @param array<string, string> $refs
     *
     * @throws UsageException
     */
    private function addRef(string $option, string $assignment, array &$refs): void
    {
        $equals = strpos($assignment, '=');

        if (false === $equals || 0 === $equals || $equals === (strlen($assignment) - 1)) {
            throw UsageException::invalidValue($option, $assignment, 'name=revision');
        }

        $refs[substr($assignment, 0, $equals)] = substr($assignment, $equals + 1);
    }

    /**
     * @param list<string>          $arguments
     * @param array<string, string> $values
     * @param array<string, true>   $flags
     *
     * @throws UsageException
     */
    private function applyShortOption(
        #[SensitiveParameter]
        string $token,
        array $arguments,
        int &$index,
        array &$values,
        array &$flags,
    ): void {
        if (! isset(self::SHORT_OPTIONS[$token])) {
            throw UsageException::unknownOption($token);
        }

        [$kind, $property] = self::SHORT_OPTIONS[$token];

        if ('flag' === $kind) {
            $flags[$property] = true;

            return;
        }

        $values[$property] = $this->value($token, null, $arguments, $index);
    }

    /**
     * @throws UsageException
     */
    private function integer(string $option, string $value, int $minimum, ?int $maximum = null): int
    {
        if (preg_match('/^[0-9]+$/', $value) !== 1) {
            throw UsageException::invalidValue($option, $value, 'a whole number');
        }

        $number = (int) $value;

        if ($number < $minimum || (null !== $maximum && $number > $maximum)) {
            $expected = null === $maximum
                ? sprintf('a whole number of at least %d', $minimum)
                : sprintf('a whole number between %d and %d', $minimum, $maximum);

            throw UsageException::invalidValue($option, $value, $expected);
        }

        return $number;
    }

    /**
     * @param list<string>          $arguments
     * @param array<string, string> $values
     * @param array<string, true>   $flags
     * @param list<string>          $projects
     * @param array<string, string> $refs
     *
     * @throws UsageException
     */
    private function parseLongOption(
        #[SensitiveParameter]
        string $token,
        array $arguments,
        int &$index,
        array &$values,
        array &$flags,
        array &$projects,
        array &$refs,
    ): void {
        $name   = $token;
        $inline = null;
        $equals = strpos($token, '=');

        if (false !== $equals) {
            $name   = substr($token, 0, $equals);
            $inline = substr($token, $equals + 1);
        }

        if (isset(self::FLAG_OPTIONS[$name])) {
            if (null !== $inline) {
                throw UsageException::unexpectedValue($name);
            }

            $flags[self::FLAG_OPTIONS[$name]] = true;

            return;
        }

        if (! isset(self::VALUE_OPTIONS[$name])) {
            throw UsageException::unknownOption($name);
        }

        $value    = $this->value($name, $inline, $arguments, $index);
        $property = self::VALUE_OPTIONS[$name];

        if ('project' === $property) {
            $projects[] = $value;

            return;
        }

        if ('ref' === $property) {
            $this->addRef($name, $value, $refs);

            return;
        }

        $values[$property] = $value;
    }

    /**
     * @param list<string>          $arguments
     * @param array<string, string> $values
     * @param array<string, true>   $flags
     *
     * @throws UsageException
     */
    private function parseShortOption(
        #[SensitiveParameter]
        string $token,
        array $arguments,
        int &$index,
        array &$values,
        array &$flags,
    ): void {
        if (strlen($token) > 2) {
            $cluster = substr($token, 1);
            $letters = [];

            for ($offset = 0; $offset < strlen($cluster); $offset++) {
                $letters[] = $cluster[$offset];
            }

            foreach ($letters as $letter) {
                if (! in_array($letter, self::SHORT_FLAGS, true)) {
                    throw UsageException::unknownOption($token);
                }
            }

            foreach ($letters as $letter) {
                $this->applyShortOption("-{$letter}", $arguments, $index, $values, $flags);
            }

            return;
        }

        $this->applyShortOption($token, $arguments, $index, $values, $flags);
    }

    /**
     * @param list<string> $arguments
     *
     * @throws UsageException
     */
    private function value(string $option, ?string $inline, array $arguments, int &$index): string
    {
        if (null !== $inline) {
            if ('' === $inline) {
                throw UsageException::missingValue($option);
            }

            return $inline;
        }

        if (! isset($arguments[$index])) {
            throw UsageException::missingValue($option);
        }

        $value = $arguments[$index];
        $index++;

        if ('' === $value) {
            throw UsageException::missingValue($option);
        }

        return $value;
    }
}
