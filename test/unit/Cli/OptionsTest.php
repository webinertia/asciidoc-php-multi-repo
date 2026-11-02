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
    public function testDefaultsMatchTheContract(): void
    {
        $options = new Options();

        self::assertSame('', $options->command);
        self::assertNull($options->config);
        self::assertSame(Options::DEFAULT_CONFIG, $options->configPath());
        self::assertNull($options->out);
        self::assertNull($options->cache);
        self::assertNull($options->report);
        self::assertNull($options->concurrency);
        self::assertSame([], $options->projects);
        self::assertSame([], $options->refs);
        self::assertNull($options->manifest);
        self::assertFalse($options->full);
        self::assertNull($options->basePath);
        self::assertSame(8080, $options->port);
        self::assertFalse($options->force);
        self::assertFalse($options->quiet);
        self::assertFalse($options->verbose);
        self::assertFalse($options->timings);
        self::assertFalse($options->noColor);
        self::assertFalse($options->help);
        self::assertFalse($options->version);
        self::assertFalse($options->hasProjectFilter());
    }

    public function testExplicitConfigPathWins(): void
    {
        $options = new Options(config: '/tmp/site.json');

        self::assertSame('/tmp/site.json', $options->configPath());
    }

    public function testProjectFilterReportsWhenProjectsAreSelected(): void
    {
        $options = new Options(projects: ['message-bus']);

        self::assertTrue($options->hasProjectFilter());
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
