<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;

#[CoversClass(Options::class)]
final class OptionsTest extends TestCase
{
    #[Test]
    public function defaultsMatchTheContract(): void
    {
        $options = new Options();

        static::assertSame('', $options->command);
        static::assertNull($options->config);
        static::assertSame(Options::DEFAULT_CONFIG, $options->configPath());
        static::assertNull($options->out);
        static::assertNull($options->cache);
        static::assertNull($options->report);
        static::assertNull($options->concurrency);
        static::assertSame([], $options->projects);
        static::assertSame([], $options->refs);
        static::assertNull($options->manifest);
        static::assertFalse($options->full);
        static::assertNull($options->basePath);
        static::assertSame(8080, $options->port);
        static::assertFalse($options->force);
        static::assertFalse($options->quiet);
        static::assertFalse($options->verbose);
        static::assertFalse($options->timings);
        static::assertFalse($options->noColor);
        static::assertFalse($options->help);
        static::assertFalse($options->version);
        static::assertFalse($options->hasProjectFilter());
    }

    #[Test]
    public function explicitConfigPathWins(): void
    {
        $options = new Options(config: '/tmp/site.json');

        static::assertSame('/tmp/site.json', $options->configPath());
    }

    #[Test]
    public function projectFilterReportsWhenProjectsAreSelected(): void
    {
        $options = new Options(projects: ['message-bus']);

        static::assertTrue($options->hasProjectFilter());
    }

    #[Test]
    public function withProjectsReplacesOnlyTheProjectList(): void
    {
        $options = new Options(
            command    : 'watch',
            config     : 'custom.json',
            out        : 'build/site',
            cache      : 'build/cache',
            report     : 'build/report.json',
            concurrency: 4,
            projects   : ['message-bus'],
            refs       : ['message-bus' => 'main'],
            manifest   : 'manifest.json',
            full       : true,
            basePath   : '/docs',
            port       : 9000,
            force      : true,
            quiet      : true,
            verbose    : true,
            timings    : true,
            noColor    : true,
            help       : true,
            version    : true,
        );

        $replaced = $options->withProjects(['accounting', 'catalog']);

        static::assertSame(['accounting', 'catalog'], $replaced->projects);
        static::assertSame(
            [
                $options->command,
                $options->config,
                $options->out,
                $options->cache,
                $options->report,
                $options->concurrency,
                $options->refs,
                $options->manifest,
                $options->full,
                $options->basePath,
                $options->port,
                $options->force,
                $options->quiet,
                $options->verbose,
                $options->timings,
                $options->noColor,
                $options->help,
                $options->version,
            ],
            [
                $replaced->command,
                $replaced->config,
                $replaced->out,
                $replaced->cache,
                $replaced->report,
                $replaced->concurrency,
                $replaced->refs,
                $replaced->manifest,
                $replaced->full,
                $replaced->basePath,
                $replaced->port,
                $replaced->force,
                $replaced->quiet,
                $replaced->verbose,
                $replaced->timings,
                $replaced->noColor,
                $replaced->help,
                $replaced->version,
            ],
        );
        static::assertSame(['message-bus'], $options->projects);
        static::assertSame(['message-bus' => 'main'], $replaced->refs);
    }
}
