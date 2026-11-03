<?php

declare(strict_types=1);

namespace WebwareTest\AsciidocPhp\MultiRepo\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\AsciidocPhp\MultiRepo\Cli\Options;
use Webware\AsciidocPhp\MultiRepo\Cli\OptionsParser;
use Webware\AsciidocPhp\MultiRepo\Cli\UsageException;

#[CoversClass(OptionsParser::class)]
final class OptionsParserTest extends TestCase
{
    private OptionsParser $parser;

    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function invalidArgumentLists(): array
    {
        return [
            'unknown long option'         => [['--nope'], 'unknown option "--nope"'],
            'unknown short option'        => [['-z'], 'unknown option "-z"'],
            'unknown short in cluster'    => [['-qz'], 'unknown option "-qz"'],
            'missing value'               => [['--config'], 'option "--config" requires a value'],
            'empty inline value'          => [['--config='], 'option "--config" requires a value'],
            'missing short value'         => [['-c'], 'option "-c" requires a value'],
            'flag with inline value'      => [['--full=yes'], 'option "--full" does not take a value'],
            'second positional'           => [['build', 'extra'], 'unexpected argument "extra"'],
            'bare dash'                   => [['-'], 'unexpected argument "-"'],
            'positional after terminator' => [['build', '--', 'extra'], 'unexpected argument "extra"'],
            'non numeric concurrency'     => [
                ['--concurrency', 'many'],
                'invalid value "many" for option "--concurrency": expected a whole number',
            ],
            'zero concurrency'            => [
                ['--concurrency', '0'],
                'invalid value "0" for option "--concurrency": expected a whole number of at least 1',
            ],
            'non numeric port'            => [
                ['--port', '-1'],
                'invalid value "-1" for option "--port": expected a whole number',
            ],
            'port above range'            => [
                ['--port', '70000'],
                'invalid value "70000" for option "--port": expected a whole number between 1 and 65535',
            ],
            'ref without revision'        => [
                ['--ref', 'main'],
                'invalid value "main" for option "--ref": expected name=revision',
            ],
            'ref without name'            => [
                ['--ref', '=main'],
                'invalid value "=main" for option "--ref": expected name=revision',
            ],
            'ref without value'           => [
                ['--ref', 'main='],
                'invalid value "main=" for option "--ref": expected name=revision',
            ],
        ];
    }

    #[Test]
    public function booleanFlags(): void
    {
        $options = $this->parser->parse([
            'build',
            '--full',
            '--force',
            '--quiet',
            '--verbose',
            '--timings',
            '--no-color',
            '--help',
            '--version',
        ]);

        self::assertTrue($options->full);
        self::assertTrue($options->force);
        self::assertTrue($options->quiet);
        self::assertTrue($options->verbose);
        self::assertTrue($options->timings);
        self::assertTrue($options->noColor);
        self::assertTrue($options->help);
        self::assertTrue($options->version);
    }

    #[Test]
    public function configLongOptionWithInlineValue(): void
    {
        self::assertSame('docs/site.json', $this->parser->parse(['--config=docs/site.json'])->config);
    }

    #[Test]
    public function configLongOptionWithSeparateValue(): void
    {
        self::assertSame('docs/site.json', $this->parser->parse(['--config', 'docs/site.json'])->config);
    }

    #[Test]
    public function configShortOption(): void
    {
        self::assertSame('docs/site.json', $this->parser->parse(['build', '-c', 'docs/site.json'])->configPath());
    }

    #[Test]
    public function doubleDashEndsOptionParsing(): void
    {
        $options = $this->parser->parse(['--', 'build']);

        self::assertSame('build', $options->command);
    }

    #[Test]
    public function emptyArgumentListProducesDefaults(): void
    {
        $options = $this->parser->parse([]);

        self::assertSame('', $options->command);
        self::assertSame('site.json', $options->configPath());
        self::assertSame(Options::DEFAULT_PORT, $options->port);
    }

    #[Test]
    public function firstPositionalIsTheCommand(): void
    {
        self::assertSame('build', $this->parser->parse(['build'])->command);
    }

    /**
     * @param list<string> $arguments
     */
    #[Test]
    #[DataProvider('invalidArgumentLists')]
    public function invalidArgumentsAreRejected(array $arguments, string $expectedMessage): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->parser->parse($arguments);
    }

    #[Test]
    public function outOptionAcceptsShortLongAndDocumentedAlias(): void
    {
        self::assertSame('build/site', $this->parser->parse(['-o', 'build/site'])->out);
        self::assertSame('build/site', $this->parser->parse(['--out', 'build/site'])->out);
        self::assertSame('build/site', $this->parser->parse(['--output', 'build/site'])->out);
    }

    #[Test]
    public function projectIsRepeatableAndOrdered(): void
    {
        $options = $this->parser->parse(['build', '--project', 'message-bus', '--project=messagebus-event']);

        self::assertSame(['message-bus', 'messagebus-event'], $options->projects);
    }

    #[Test]
    public function refAcceptsARevisionContainingAnEqualsSign(): void
    {
        $options = $this->parser->parse(['--ref', 'message-bus=feature=thing']);

        self::assertSame(['message-bus' => 'feature=thing'], $options->refs);
    }

    #[Test]
    public function refIsRepeatableAndKeyedByRepository(): void
    {
        $options = $this->parser->parse(['build', '--ref', 'message-bus=main', '--ref=webware-tools=v2']);

        self::assertSame(
            ['message-bus' => 'main', 'webware-tools' => 'v2'],
            $options->refs,
        );
    }

    #[Test]
    public function remainingValueOptions(): void
    {
        $options = $this->parser->parse([
            'build',
            '--cache',
            'build/cache',
            '--report',
            'build/report.json',
            '--concurrency',
            '8',
            '--manifest',
            'test/fixtures/manifest.json',
            '--base-path',
            '/docs/',
            '--port',
            '9000',
        ]);

        self::assertSame('build/cache', $options->cache);
        self::assertSame('build/report.json', $options->report);
        self::assertSame(8, $options->concurrency);
        self::assertSame('test/fixtures/manifest.json', $options->manifest);
        self::assertSame('/docs/', $options->basePath);
        self::assertSame(9000, $options->port);
    }

    #[Test]
    public function shortBooleanFlagsMayBeClustered(): void
    {
        $options = $this->parser->parse(['build', '-qvt']);

        self::assertTrue($options->quiet);
        self::assertTrue($options->verbose);
        self::assertTrue($options->timings);
    }

    #[Test]
    public function shortHelpFlag(): void
    {
        self::assertTrue($this->parser->parse(['-h'])->help);
    }

    protected function setUp(): void
    {
        $this->parser = new OptionsParser();
    }
}
