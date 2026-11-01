<?php

declare(strict_types=1);

namespace Webware\AsciidocPhpMultiRepo\Test\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhpMultiRepo\Cli\Options;
use Webware\AsciidocPhpMultiRepo\Cli\Output;
use Webware\AsciidocPhpMultiRepo\Test\Support\CapturedOutput;

use function str_contains;

#[CoversClass(Output::class)]
final class OutputTest extends TestCase
{
    private CapturedOutput $captured;

    /**
     * @return array<string, array{bool, null|string, bool, bool}>
     */
    public static function colourDecisions(): array
    {
        return [
            'interactive and clean environment' => [false, null, true, true],
            'not interactive'                   => [false, null, false, false],
            'no color flag'                     => [true, null, true, false],
            'NO_COLOR set'                      => [false, '1', true, false],
            'NO_COLOR set to empty'             => [false, '', true, true],
        ];
    }

    /**
     * @param list<string> $flags
     */
    #[DataProvider('colourDecisions')]
    public function testColourEnabledMatrix(
        bool $noColor,
        ?string $environment,
        bool $interactive,
        bool $expected,
    ): void {
        self::assertSame($expected, Output::colourEnabled($noColor, $environment, $interactive));
    }

    public function testColourIsUsedOnlyWhenEnabled(): void
    {
        $plain = $this->makeOutput();
        $plain->error('missing entry');

        self::assertStringNotContainsString("\033[", $this->captured->stderr);

        $coloured = $this->makeOutput(color: true);
        $coloured->error('missing entry');

        self::assertStringContainsString("\033[31m", $this->captured->stderr);
        self::assertStringContainsString("\033[0m", $this->captured->stderr);
    }

    public function testDetailRequiresVerbose(): void
    {
        $quiet = $this->makeOutput();
        $quiet->detail('rendering index.adoc');

        self::assertSame('', $this->captured->stderr);

        $verbose = $this->makeOutput(verbose: true);
        $verbose->detail('rendering index.adoc');

        self::assertStringContainsString('rendering index.adoc', $this->captured->stderr);
    }

    public function testForCliCarriesTheFlagState(): void
    {
        $options = new Options(
            quiet  : true,
            verbose: true,
            timings: true,
            noColor: true,
        );
        $output = Output::forCli($options, true, null);

        self::assertTrue($output->isQuiet());
        self::assertTrue($output->isVerbose());
        self::assertFalse($output->usesColour());
    }

    public function testForCliReadsTheEnvironment(): void
    {
        $options = new Options();

        self::assertFalse(Output::forCli($options, false, null)->usesColour());
        self::assertTrue(Output::forCli($options, true, null)->usesColour());
        self::assertFalse(Output::forCli($options, true, '1')->usesColour());
    }

    public function testProgressGoesToStandardErrorOnly(): void
    {
        $output = $this->makeOutput();

        $output->progress('fetching message-bus');

        self::assertSame('', $this->captured->stdout);
        self::assertSame("fetching message-bus\n", $this->captured->stderr);
    }

    public function testQuietKeepsErrorsAndSummaryButDropsProgress(): void
    {
        $output = $this->makeOutput(quiet: true);

        $output->progress('fetching message-bus');
        $output->warning('skipped archived repository');
        $output->error('missing entry');
        $output->summary('0 ok, 1 failed');

        self::assertSame("0 ok, 1 failed\n", $this->captured->stdout);
        self::assertStringNotContainsString('fetching message-bus', $this->captured->stderr);
        self::assertStringNotContainsString('skipped archived repository', $this->captured->stderr);
        self::assertStringContainsString('error: missing entry', $this->captured->stderr);
    }

    public function testSummaryGoesToStandardOutputOnly(): void
    {
        $output = $this->makeOutput();

        $output->summary('1 ok, 0 failed');

        self::assertSame("1 ok, 0 failed\n", $this->captured->stdout);
        self::assertSame('', $this->captured->stderr);
    }

    public function testTimingRequiresTimings(): void
    {
        $output = $this->makeOutput();
        $output->timing('render', 1.2345);

        self::assertSame('', $this->captured->stderr);

        $timed = $this->makeOutput(timings: true);
        $timed->timing('render', 1.2345);

        self::assertSame("render: 1.234s\n", $this->captured->stderr);
    }

    public function testUsageReachesStandardErrorEvenWhenQuiet(): void
    {
        $output = $this->makeOutput(quiet: true);

        $output->usage('usage: build');

        self::assertTrue(str_contains($this->captured->stderr, 'usage: build'));
    }

    protected function setUp(): void
    {
        $this->captured = new CapturedOutput();
    }

    private function makeOutput(
        bool $quiet = false,
        bool $verbose = false,
        bool $timings = false,
        bool $color = false,
    ): Output {
        return new Output(
            quiet  : $quiet,
            verbose: $verbose,
            timings: $timings,
            color  : $color,
            stdout : $this->captured->stdoutWriter(),
            stderr : $this->captured->stderrWriter(),
        );
    }
}
