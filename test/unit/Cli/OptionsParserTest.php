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

        static::assertTrue($options->full);
        static::assertTrue($options->force);
        static::assertTrue($options->quiet);
        static::assertTrue($options->verbose);
        static::assertTrue($options->timings);
        static::assertTrue($options->noColor);
        static::assertTrue($options->help);
        static::assertTrue($options->version);
    }

    #[Test]
    public function configLongOptionWithInlineValue(): void
    {
        static::assertSame('docs/site.json', $this->parser->parse(['--config=docs/site.json'])->config);
    }

    #[Test]
    public function configLongOptionWithSeparateValue(): void
    {
        static::assertSame('docs/site.json', $this->parser->parse(['--config', 'docs/site.json'])->config);
    }

    #[Test]
    public function configShortOption(): void
    {
        static::assertSame('docs/site.json', $this->parser->parse(['build', '-c', 'docs/site.json'])->configPath());
    }

    #[Test]
    public function doubleDashEndsOptionParsing(): void
    {
        $options = $this->parser->parse(['--', 'build']);

        static::assertSame('build', $options->command);
    }

    #[Test]
    public function emptyArgumentListProducesDefaults(): void
    {
        $options = $this->parser->parse([]);

        static::assertSame('', $options->command);
        static::assertSame('site.json', $options->configPath());
        static::assertSame(Options::DEFAULT_PORT, $options->port);
    }

    #[Test]
    public function firstPositionalIsTheCommand(): void
    {
        static::assertSame('build', $this->parser->parse(['build'])->command);
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
        static::assertSame('build/site', $this->parser->parse(['-o', 'build/site'])->out);
        static::assertSame('build/site', $this->parser->parse(['--out', 'build/site'])->out);
        static::assertSame('build/site', $this->parser->parse(['--output', 'build/site'])->out);
    }

    #[Test]
    public function projectIsRepeatableAndOrdered(): void
    {
        $options = $this->parser->parse(['build', '--project', 'message-bus', '--project=messagebus-event']);

        static::assertSame(['message-bus', 'messagebus-event'], $options->projects);
    }

    #[Test]
    public function refAcceptsARevisionContainingAnEqualsSign(): void
    {
        $options = $this->parser->parse(['--ref', 'message-bus=feature=thing']);

        static::assertSame(['message-bus' => 'feature=thing'], $options->refs);
    }

    #[Test]
    public function refIsRepeatableAndKeyedByRepository(): void
    {
        $options = $this->parser->parse(['build', '--ref', 'message-bus=main', '--ref=webware-tools=v2']);

        static::assertSame(
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

        static::assertSame('build/cache', $options->cache);
        static::assertSame('build/report.json', $options->report);
        static::assertSame(8, $options->concurrency);
        static::assertSame('test/fixtures/manifest.json', $options->manifest);
        static::assertSame('/docs/', $options->basePath);
        static::assertSame(9000, $options->port);
    }

    #[Test]
    public function shortBooleanFlagsMayBeClustered(): void
    {
        $options = $this->parser->parse(['build', '-qvt']);

        static::assertTrue($options->quiet);
        static::assertTrue($options->verbose);
        static::assertTrue($options->timings);
    }

    #[Test]
    public function shortHelpFlag(): void
    {
        static::assertTrue($this->parser->parse(['-h'])->help);
    }

    protected function setUp(): void
    {
        $this->parser = new OptionsParser();
    }
}
