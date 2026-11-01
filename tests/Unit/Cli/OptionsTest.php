<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Cli\Options;

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
}
