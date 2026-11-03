<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\Output;
use WebwareTest\AsciidocPhp\MultiRepo\Support\CapturedOutput;

use function getenv;
use function is_string;
use function putenv;
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
    #[Test]
    #[DataProvider('colourDecisions')]
    public function colourEnabledMatrix(
        bool $noColor,
        ?string $environment,
        bool $interactive,
        bool $expected,
    ): void {
        static::assertSame($expected, Output::colourEnabled($noColor, $environment, $interactive));
    }

    #[Test]
    public function colourIsUsedOnlyWhenEnabled(): void
    {
        $plain = $this->makeOutput();
        $plain->error('missing entry');

        static::assertSame("error: missing entry\n", $this->captured->stderr);

        $this->captured = new CapturedOutput();

        $coloured = $this->makeOutput(color: true);
        $coloured->error('missing entry');

        static::assertSame("\033[31merror: missing entry\n\033[0m", $this->captured->stderr);
    }

    #[Test]
    public function detailRequiresVerbose(): void
    {
        $quiet = $this->makeOutput();
        $quiet->detail('rendering index.adoc');

        static::assertSame('', $this->captured->stderr);

        $verbose = $this->makeOutput(verbose: true);
        $verbose->detail('rendering index.adoc');

        static::assertStringContainsString('rendering index.adoc', $this->captured->stderr);
    }

    #[Test]
    public function forCliCarriesTheFlagState(): void
    {
        $options = new Options(
            quiet  : true,
            verbose: true,
            timings: true,
            noColor: true,
        );
        $output = Output::forCli($options, true, null);

        static::assertTrue($output->isQuiet());
        static::assertTrue($output->isVerbose());
        static::assertFalse($output->usesColour());
    }

    #[Test]
    public function forCliReadsTheEnvironment(): void
    {
        $options = new Options();

        static::assertFalse(Output::forCli($options, false, null)->usesColour());
        static::assertTrue(Output::forCli($options, true, null)->usesColour());
        static::assertFalse(Output::forCli($options, true, '1')->usesColour());
    }

    #[Test]
    public function progressGoesToStandardErrorOnly(): void
    {
        $output = $this->makeOutput();

        $output->progress('fetching message-bus');

        static::assertSame('', $this->captured->stdout);
        static::assertSame("fetching message-bus\n", $this->captured->stderr);
    }

    #[Test]
    public function quietKeepsErrorsAndSummaryButDropsProgress(): void
    {
        $output = $this->makeOutput(quiet: true);

        $output->progress('fetching message-bus');
        $output->warning('skipped archived repository');
        $output->error('missing entry');
        $output->summary('0 ok, 1 failed');

        static::assertSame("0 ok, 1 failed\n", $this->captured->stdout);
        static::assertStringNotContainsString('fetching message-bus', $this->captured->stderr);
        static::assertStringNotContainsString('skipped archived repository', $this->captured->stderr);
        static::assertStringContainsString('error: missing entry', $this->captured->stderr);
    }

    #[Test]
    public function summaryGoesToStandardOutputOnly(): void
    {
        $output = $this->makeOutput();

        $output->summary('1 ok, 0 failed');

        static::assertSame("1 ok, 0 failed\n", $this->captured->stdout);
        static::assertSame('', $this->captured->stderr);
    }

    #[Test]
    public function theDefaultFlagsAreOff(): void
    {
        $output = new Output(
            stdout: $this->captured->stdoutWriter(),
            stderr: $this->captured->stderrWriter(),
        );

        $output->timing('render', 1.0);

        static::assertFalse($output->isQuiet());
        static::assertFalse($output->isVerbose());
        static::assertFalse($output->usesColour());
        static::assertSame('', $this->captured->stderr);
    }

    #[Test]
    public function theEnvironmentFallbackIsUsedOnlyWhenNoneIsSupplied(): void
    {
        $previous = getenv('NO_COLOR');

        putenv('NO_COLOR=1');

        try {
            static::assertSame('1', Output::noColorEnvironment());
            static::assertTrue(Output::forCli(new Options(), true, '')->usesColour());
        } finally {
            putenv(is_string($previous) ? "NO_COLOR={$previous}" : 'NO_COLOR');
        }
    }

    #[Test]
    public function timingRequiresTimings(): void
    {
        $output = $this->makeOutput();
        $output->timing('render', 1.2345);

        static::assertSame('', $this->captured->stderr);

        $timed = $this->makeOutput(timings: true);
        $timed->timing('render', 1.2345);

        static::assertSame("render: 1.234s\n", $this->captured->stderr);
    }

    #[Test]
    public function usageReachesStandardErrorEvenWhenQuiet(): void
    {
        $output = $this->makeOutput(quiet: true);

        $output->usage('usage: build');

        static::assertTrue(str_contains($this->captured->stderr, 'usage: build'));
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
